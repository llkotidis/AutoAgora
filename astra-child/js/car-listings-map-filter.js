jQuery(document).ready(function($) {
    const mapConfig = carListingsMapFilterData.mapboxConfig;
    const ajaxurl = carListingsMapFilterData.ajaxurl;
    const nonce = carListingsMapFilterData.nonce;
    let initialFilter = carListingsMapFilterData.initialFilter;

    let map = null;
    let marker = null;
    let geocoder = null;
    let selectedCoords = null; // LngLat array [lng, lat]
    let selectedLocationName = initialFilter.text || 'All of Cyprus'; // For display
    let currentRadiusKm = initialFilter.radius || 10; // Default radius in km
    const circleSourceId = 'radius-circle-source';
    const circleLayerId = 'radius-circle-layer';
    let moveTimeout; // For debouncing map moveend
    let currentListingsRequest = null; // Variable to hold the current AJAX request

    const modal = $('#location-filter-modal');
    const changeLocationBtn = $('#change-location-filter-btn');
    const closeBtn = $('#close-location-filter-modal');
    const applyBtn = $('#apply-location-filter-btn');
    const mapContainer = $('#filter-map-container');
    const geocoderContainer = $('#filter-geocoder');
    const radiusSlider = $('#radius-slider');
    const radiusValueDisplay = $('#radius-value');
    const currentLocationText = $('#current-location-filter-text');

    const specFiltersPopup = $('#spec-filters-popup');
    const openSpecFiltersBtn = $('#open-spec-filters-popup-btn');
    const closeSpecFiltersBtn = $('#close-spec-filters-popup-btn');
    const applySpecFiltersBtn = $('#apply-spec-filters-btn'); // From car-filter-form.php
    const resetSpecFiltersBtn = $('#reset-spec-filters-btn'); // From car-filter-form.php
    const specFiltersContainer = $('#car-spec-filters-container'); // Container of all spec filters

    // Initialize UI elements
    radiusSlider.val(currentRadiusKm);
    radiusValueDisplay.text(currentRadiusKm);
    currentLocationText.text(selectedLocationName);

    // --- Initialize from URL parameters if present ---
    const urlParams = new URLSearchParams(window.location.search);
    const urlLat = urlParams.get('lat');
    const urlLng = urlParams.get('lng');
    const urlRadius = urlParams.get('radius');
    const urlLocationName = urlParams.get('location_name');

    let loadedFromStorage = false;

    if (urlLat && urlLng && urlRadius) {
        initialFilter = {
            lat: parseFloat(urlLat),
            lng: parseFloat(urlLng),
            radius: parseInt(urlRadius, 10),
            text: urlLocationName || 'Selected on map'
        };
        selectedCoords = [initialFilter.lng, initialFilter.lat];
        currentRadiusKm = initialFilter.radius;
        selectedLocationName = initialFilter.text;
        console.log('[Init] Loaded filter from URL:', initialFilter);
    } else {
        // --- Try to load from localStorage if not in URL ---
        const savedLocation = localStorage.getItem('autoAgoraUserLocation');
        if (savedLocation) {
            try {
                const parsedLocation = JSON.parse(savedLocation);
                if (parsedLocation.lat && parsedLocation.lng && parsedLocation.radius) {
                    initialFilter = {
                        lat: parseFloat(parsedLocation.lat),
                        lng: parseFloat(parsedLocation.lng),
                        radius: parseInt(parsedLocation.radius, 10),
                        text: parsedLocation.name || 'Saved location'
                    };
                    selectedCoords = [initialFilter.lng, initialFilter.lat];
                    currentRadiusKm = initialFilter.radius;
                    selectedLocationName = initialFilter.text;
                    loadedFromStorage = true;
                    console.log('[Init] Loaded filter from localStorage:', initialFilter);
                }
            } catch (e) {
                console.error('[Init] Error parsing location from localStorage:', e);
                localStorage.removeItem('autoAgoraUserLocation'); // Clear corrupted data
            }
        }
    }

    // Update UI elements (either from URL, localStorage, or defaults via wp_localize_script)
    radiusSlider.val(currentRadiusKm);
    radiusValueDisplay.text(currentRadiusKm);
    currentLocationText.text(selectedLocationName);
    if (loadedFromStorage) {
        console.log('[Init] UI updated with localStorage data.');
    } else if (urlLat && urlLng && urlRadius) {
        console.log('[Init] UI updated with URL data.');
    } else {
        console.log('[Init] No location filter in URL or localStorage, UI reflects defaults.');
    }

    // Update URL if filter was loaded from localStorage and not from URL parameters
    if (loadedFromStorage && !(urlLat && urlLng && urlRadius)) {
        const currentUrl = new URL(window.location.href);
        if (initialFilter && initialFilter.lat && initialFilter.lng && initialFilter.radius && initialFilter.text) {
            currentUrl.searchParams.set('lat', initialFilter.lat.toFixed(7));
            currentUrl.searchParams.set('lng', initialFilter.lng.toFixed(7));
            currentUrl.searchParams.set('radius', initialFilter.radius.toString());
            currentUrl.searchParams.set('location_name', initialFilter.text);
            
            // Manage 'paged' parameter: if not in original URL, ensure it implies page 1 for the new state
            if (!urlParams.has('paged')) {
                currentUrl.searchParams.delete('paged'); // Or set to 1: currentUrl.searchParams.set('paged', '1');
            }
            history.pushState({ path: currentUrl.href }, '', currentUrl.href);
            console.log('[Init] URL updated from localStorage data:', currentUrl.href);
        } else {
            console.warn('[Init] Tried to update URL from localStorage, but initialFilter data was incomplete.');
        }
    }
    // --- End Initialize from URL parameters / localStorage ---

    changeLocationBtn.on('click', function() {
        modal.show();
        if (!map) {
            initializeMap();
        } else {
            map.resize(); 
            if (selectedCoords) {
                map.setCenter(selectedCoords);
                updateMarkerPosition(selectedCoords);
                updateRadiusCircle(selectedCoords, currentRadiusKm);
            } else {
                // If no coords, center on default and place marker there
                map.setCenter(mapConfig.cyprusCenter);
                const centerArray = map.getCenter().toArray();
                updateMarkerPosition(centerArray);
                updateRadiusCircle(centerArray, currentRadiusKm);
            }
        }
    });

    closeBtn.on('click', function() {
        modal.hide();
    });

    modal.on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.hide();
        }
    });

    // --- Locate Me Control for Mapbox ---
    class LocateMeControl {
        onAdd(mapInstance) {
            this._map = mapInstance;
            this._container = document.createElement('div');
            this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group';

            const button = document.createElement('button');
            button.className = 'mapboxgl-ctrl-text mapboxgl-ctrl-locate-me';
            button.type = 'button';
            button.title = 'Find my current location';
            button.setAttribute('aria-label', 'Find my current location');
            button.textContent = 'Find my current location';

            button.onclick = () => {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser.');
                    return;
                }

                // Add a loading/locating indicator to the button if desired
                button.classList.add('mapboxgl-ctrl-geolocate-active'); // Optional: for styling during location fetch

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const newCoords = [position.coords.longitude, position.coords.latitude];
                        selectedCoords = newCoords; // Update the shared selectedCoords

                        this._map.flyTo({
                            center: newCoords,
                            zoom: 14 // Zoom in closer for better context
                        });
                        // The map's 'moveend' event will handle marker update, reverse geocode, and radius update.
                        button.classList.remove('mapboxgl-ctrl-geolocate-active'); // Remove loading state
                    },
                    (error) => {
                        alert(`Error getting location: ${error.message}`);
                        console.error('Geolocation error:', error);
                        button.classList.remove('mapboxgl-ctrl-geolocate-active'); // Remove loading state
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 8000, // Increased timeout
                        maximumAge: 0
                    }
                );
            };

            this._container.appendChild(button);
            return this._container;
        }

        onRemove() {
            if (this._container && this._container.parentNode) {
                this._container.parentNode.removeChild(this._container);
            }
            this._map = undefined;
        }
    }
    // --- End Locate Me Control ---

    function initializeMap() {
        if (!mapConfig.accessToken) {
            console.error('Mapbox Access Token is not configured.');
            mapContainer.html('<p style="color:red;text-align:center;margin-top:20px;">Map configuration error.</p>');
            return;
        }
        mapboxgl.accessToken = mapConfig.accessToken;
        
        let initialMapCenter = mapConfig.cyprusCenter;
        if (initialFilter.lat && initialFilter.lng) {
            initialMapCenter = [initialFilter.lng, initialFilter.lat];
            selectedCoords = initialMapCenter; 
        }

        map = new mapboxgl.Map({
            container: 'filter-map-container',
            style: mapConfig.style,
            center: initialMapCenter, 
            zoom: mapConfig.defaultZoom,
            scrollZoom: {around: 'center'},
            transformRequest: (url, resourceType) => {
                if (url.includes('events.mapbox.com')) return { url: '' };
                return { url };
            }
        });

        map.on('load', function() {
            map.addControl(new mapboxgl.NavigationControl());
            map.addControl(new LocateMeControl(), 'bottom-right'); // Changed from top-right to bottom-right

            const currentCenterArray = map.getCenter().toArray();
            updateMarkerPosition(currentCenterArray);
            if (selectedCoords) { 
                 updateRadiusCircle(selectedCoords, currentRadiusKm);
            } else { 
                 updateRadiusCircle(currentCenterArray, currentRadiusKm);
                 selectedCoords = currentCenterArray; 
            }

            geocoder = new MapboxGeocoder({
                accessToken: mapboxgl.accessToken,
                mapboxgl: mapboxgl,
                marker: false, 
                placeholder: 'Search for a location in Cyprus...',
                countries: 'CY',
                language: 'en'
            });

            geocoderContainer.empty().append(geocoder.onAdd(map));

            geocoder.on('result', function(ev) {
                const resultCoords = ev.result.center; 
                selectedCoords = resultCoords;
                selectedLocationName = ev.result.place_name;
                map.setCenter(resultCoords); // This will trigger move and moveend
                // Marker and circle will update via map move/moveend events
            });

            geocoder.on('clear', function() {
                console.log('Geocoder cleared');
                // Optional: Implement full reset logic here if desired
                // e.g., reset selectedCoords, selectedLocationName, fly to default, update marker/circle
            });
        });

        map.on('move', () => {
            const centerArray = map.getCenter().toArray();
            if (marker) {
                marker.setLngLat(centerArray);
            }
            // Update circle visually during move for smoothness
            updateRadiusCircle(centerArray, currentRadiusKm);
        });

        map.on('moveend', () => {
            if (moveTimeout) {
                clearTimeout(moveTimeout);
            }
            moveTimeout = setTimeout(() => {
                const centerArray = map.getCenter().toArray();
                selectedCoords = centerArray;
                // Marker is already at center due to 'move' event
                // Circle is also updated visually due to 'move' event
                // Final update of circle data source is good practice here too
                updateRadiusCircle(centerArray, currentRadiusKm); 

                reverseGeocode(centerArray, (name) => {
                    selectedLocationName = name || 'Area around selected point';
                    
                    // Directly set the input field's value and then blur it
                    if (geocoder && geocoder._inputEl) { 
                        console.log('[Move End] Directly setting geocoder input value to:', selectedLocationName);
                        geocoder._inputEl.value = selectedLocationName; // Directly set the value

                        if (typeof geocoder._inputEl.blur === 'function') {
                            console.log('[Move End] Blurring geocoder input after direct value set.');
                            geocoder._inputEl.blur();
                        }
                    } else {
                        console.warn('[Move End] Geocoder or its input element (_inputEl) not available for updating input.');
                    }
                });
            }, 250); 
        });

        map.on('click', (e) => {
            const clickedCoords = [e.lngLat.lng, e.lngLat.lat];
            selectedCoords = clickedCoords; // Set selectedCoords immediately on click
            map.flyTo({ center: clickedCoords }); // flyTo will trigger move and moveend
        });
    }

    function updateMarkerPosition(lngLatArray) {
        if (!map) return;
        if (!marker) {
            marker = new mapboxgl.Marker({ draggable: false, color: '#FF0000' }) 
                .setLngLat(lngLatArray)
                .addTo(map);
        } else {
            marker.setLngLat(lngLatArray);
        }
    }

    function updateRadiusCircle(centerLngLatArray, radiusKm) {
        if (!map || !turf || !centerLngLatArray || centerLngLatArray.length !== 2) return;

        const center = turf.point(centerLngLatArray);
        const circlePolygon = turf.circle(center, radiusKm, { steps: 64, units: 'kilometers' });

        let source = map.getSource(circleSourceId);
        if (source) {
            source.setData(circlePolygon);
        } else {
            map.addSource(circleSourceId, {
                type: 'geojson',
                data: circlePolygon
            });
            map.addLayer({
                id: circleLayerId,
                type: 'fill',
                source: circleSourceId,
                paint: {
                    'fill-color': '#007cbf',
                    'fill-opacity': 0.3
                }
            });
        }
    }

    function reverseGeocode(coordsLngLatArray, callback) {
        if (!mapConfig.accessToken || !coordsLngLatArray || coordsLngLatArray.length !== 2) {
            callback(null); // Ensure callback is called even on invalid input
            return;
        }
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${coordsLngLatArray[0]},${coordsLngLatArray[1]}.json?access_token=${mapConfig.accessToken}&types=place,locality,neighborhood,address&limit=1&country=CY`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.features && data.features.length > 0) {
                    callback(data.features[0].place_name);
                } else {
                    callback(null);
                }
            })
            .catch(error => {
                console.error('Error reverse geocoding:', error);
                callback(null);
            });
    }

    // Event listener for the radius slider
    radiusSlider.on('input', function() {
        currentRadiusKm = parseFloat($(this).val());
        radiusValueDisplay.text(currentRadiusKm);
        console.log('[Radius Slider] New Radius (km):', currentRadiusKm);

        const centerForCircle = selectedCoords || (map ? map.getCenter().toArray() : mapConfig.cyprusCenter);
        console.log('[Radius Slider] Center for circle:', centerForCircle);
        
        updateRadiusCircle(centerForCircle, currentRadiusKm);

        console.log('[Radius Slider] Map available?', !!map);
        console.log('[Radius Slider] Turf available?', typeof turf !== 'undefined' ? !!turf : 'Turf IS UNDEFINED');

        if (map && typeof turf !== 'undefined' && turf && centerForCircle && centerForCircle.length === 2) {
            console.log('[Radius Slider] Proceeding with fitBounds logic.');
            try {
                const turfPoint = turf.point(centerForCircle);
                console.log('[Radius Slider] Turf point:', turfPoint);

                const circlePolygon = turf.circle(turfPoint, currentRadiusKm, { steps: 64, units: 'kilometers' });
                console.log('[Radius Slider] Circle Polygon:', circlePolygon);

                if (circlePolygon && circlePolygon.geometry && circlePolygon.geometry.coordinates && circlePolygon.geometry.coordinates.length > 0) {
                    const circleBbox = turf.bbox(circlePolygon);
                    console.log('[Radius Slider] Circle BBox:', circleBbox);
                    
                    console.log('[Radius Slider] Calling map.fitBounds with BBox:', circleBbox);
                    map.fitBounds(circleBbox, { 
                        padding: 40, 
                        duration: 500 
                    });
                } else {
                    console.warn("[Radius Slider] Could not generate valid circle polygon for fitBounds. Polygon:", circlePolygon);
                }
            } catch (e) {
                console.error("[Radius Slider] Error calculating or fitting bounds for radius circle:", e);
            }
        } else {
            console.warn('[Radius Slider] Skipped fitBounds logic. Conditions not met.', 
                { map: !!map, turf: typeof turf !== 'undefined' ? !!turf : 'Turf IS UNDEFINED', centerForCircle, centerLength: centerForCircle ? centerForCircle.length : 'N/A' });
        }
    });

    applyBtn.on('click', function() {
        if (selectedCoords && selectedCoords.length === 2) {
            const lat = selectedCoords[1];
            const lng = selectedCoords[0];
            const radius = currentRadiusKm;
            const locationName = selectedLocationName || 'Selected on map';

            // Update UI
            currentLocationText.text(locationName);
            modal.hide();

            // Clear any selected make before fetching new listings
            $('#filter-make').val('').addClass('loading-filter');
            
            // Fetch listings with new location
            fetchFilteredListings(1, lat, lng, radius);

            // Update URL
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('lat', lat.toFixed(7));
            currentUrl.searchParams.set('lng', lng.toFixed(7));
            currentUrl.searchParams.set('radius', radius.toString());
            currentUrl.searchParams.set('location_name', locationName);
            currentUrl.searchParams.delete('paged');
            currentUrl.searchParams.delete('make'); // Remove make filter from URL
            history.pushState({ path: currentUrl.href }, '', currentUrl.href);
            console.log('[ApplyFilter] URL updated to:', currentUrl.href);

            // Save to localStorage
            const preferredLocation = {
                lat: lat,
                lng: lng,
                radius: radius,
                name: locationName
            };
            localStorage.setItem('autoAgoraUserLocation', JSON.stringify(preferredLocation));
            console.log('[ApplyFilter] Location saved to localStorage:', preferredLocation);

        } else {
            // If no specific coords (e.g., user clears map and applies "All of Cyprus")
            currentLocationText.text('All of Cyprus');
            modal.hide();
            
            // Clear any selected make before fetching new listings
            $('#filter-make').val('').addClass('loading-filter');
            
            // Fetch all listings
            fetchFilteredListings(1, null, null, null);

            // Update URL to remove location parameters
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('lat');
            currentUrl.searchParams.delete('lng');
            currentUrl.searchParams.delete('radius');
            currentUrl.searchParams.delete('location_name');
            currentUrl.searchParams.delete('paged');
            currentUrl.searchParams.delete('make'); // Remove make filter from URL
            history.pushState({ path: currentUrl.href }, '', currentUrl.href);
            console.log('[ApplyFilter] URL updated for "All of Cyprus":', currentUrl.href);

            // Clear from localStorage
            localStorage.removeItem('autoAgoraUserLocation');
            console.log('[ApplyFilter] Location cleared from localStorage.');
        }
    });

    function updateUrlWithFilters(page, lat, lng, radius) {
        const currentUrl = new URL(window.location.href);
        
        // Update or remove location parameters
        if (lat && lng && radius) {
            currentUrl.searchParams.set('lat', lat.toFixed(7));
            currentUrl.searchParams.set('lng', lng.toFixed(7));
            currentUrl.searchParams.set('radius', radius.toString());
        } else {
            currentUrl.searchParams.delete('lat');
            currentUrl.searchParams.delete('lng');
            currentUrl.searchParams.delete('radius');
        }

        // Update page number
        if (page && page > 1) {
            currentUrl.searchParams.set('paged', page.toString());
        } else {
            currentUrl.searchParams.delete('paged');
        }

        // Get all current filter values
        const filters = {
            make: $('#filter-make').val(),
            model: $('#filter-model').val(),
            variant: $('#filter-variant').val(),
            year_min: $('#filter-year-min').val(),
            year_max: $('#filter-year-max').val(),
            price_min: $('#filter-price-min').val(),
            price_max: $('#filter-price-max').val(),
            mileage_min: $('#filter-mileage-min').val(),
            mileage_max: $('#filter-mileage-max').val()
        };

        // Update URL with filter values
        Object.entries(filters).forEach(([key, value]) => {
            if (value && value !== '') {
                currentUrl.searchParams.set(key, value);
            } else {
                currentUrl.searchParams.delete(key);
            }
        });

        // Update URL without reloading the page
        history.pushState({ path: currentUrl.href }, '', currentUrl.href);
        console.log('[UpdateURL] Updated URL with filters:', currentUrl.href);
    }

    function fetchFilteredListings(page = 1, lat = null, lng = null, radius = null) {
        // Show loading states
        $('#filter-make').addClass('loading-filter');
        $('.car-listings-grid').html('<div class="loading-spinner">Loading listings...</div>');

        // Abort any existing request
        if (currentListingsRequest && currentListingsRequest.readyState !== 4) {
            currentListingsRequest.abort();
        }

        const data = {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: page,
            filter_lat: lat,
            filter_lng: lng,
            filter_radius: radius,
            per_page: carListingsMapFilterData.perPage || 12,
            get_filter_counts: true,
            get_all_makes: true // Add this to explicitly request all makes
        };

        // Add any selected filters
        const selectedMake = $('#filter-make').val();
        if (selectedMake) {
            data.make = selectedMake;
        }

        currentListingsRequest = $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                $('#filter-make').removeClass('loading-filter');
                
                if (response.success) {
                    // Update listings
                    $('.car-listings-grid').html(response.data.listings_html);
                    $('.car-listings-pagination').html(response.data.pagination_html);

                    // Update make filter options if provided
                    if (response.data.all_makes) {
                        updateMakeFilter(response.data.all_makes, selectedMake);
                    }

                    // Update other filter counts
                    if (response.data.filter_counts) {
                        updateFilterCounts(response.data.filter_counts);
                    }

                    // Calculate distances if location is set
                    if (lat !== null && lng !== null && radius !== null) {
                        calculateAndDisplayDistances(lat, lng);
                    }

                    // Reinitialize features
                    if (typeof reinitializeCarousels === "function") {
                        reinitializeCarousels();
                    }
                    if (typeof reinitializeFavoriteButtons === "function") {
                        reinitializeFavoriteButtons();
                    }
                    if (typeof updateResultsCounter === "function") {
                        const totalResults = response.data.query_vars?.found_posts;
                        updateResultsCounter(totalResults);
                    }

                    // Update URL
                    updateUrlWithFilters(page, lat, lng, radius);
                } else {
                    $('.car-listings-grid').html('<p>Error loading listings. ' + (response.data?.message || '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (textStatus !== 'abort') {
                    $('.car-listings-grid').html('<p>Error loading listings. Please try again.</p>');
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
                $('#filter-make').removeClass('loading-filter');
            }
        });
    }

    function updateMakeFilter(makes, selectedMake = '') {
        const $makeSelect = $('#filter-make');
        let options = '<option value="">All Makes</option>';
        
        // If makes is undefined, null, or not an object, treat as empty
        if (typeof makes !== 'object' || makes === null) {
            makes = {};
        }

        // Sort makes alphabetically
        const sortedMakes = Object.entries(makes).sort((a, b) => a[0].localeCompare(b[0]));
        
        sortedMakes.forEach(([make, count]) => {
            const isSelected = make === selectedMake ? 'selected' : '';
            const isDisabled = count === 0 && make !== selectedMake ? 'disabled' : '';
            options += `<option value="${make}" ${isSelected} ${isDisabled}>${make} (${count})</option>`;
        });
        
        $makeSelect.html(options);
    }

    // Add event listener for make filter changes
    $('#filter-make').on('change', function() {
        const selectedMake = $(this).val();
        console.log('[MakeFilter] Selected make:', selectedMake);
        
        // Get current location parameters
        const urlParams = new URLSearchParams(window.location.search);
        const lat = urlParams.get('lat');
        const lng = urlParams.get('lng');
        const radius = urlParams.get('radius');
        
        // Fetch filtered listings with the new make selection
        fetchFilteredListings(1, lat, lng, radius);
    });

    function calculateAndDisplayDistances(centerLat, centerLng) {
        const pinLocation = turf.point([centerLng, centerLat]);

        $('.car-listings-grid .car-listing-card').each(function() {
            const $card = $(this);
            const cardLat = $card.data('latitude');
            const cardLng = $card.data('longitude');
            const $locationEl = $card.find('.car-location');
            const $locationTextSpan = $locationEl.find('span.location-text');

            if (cardLat !== undefined && cardLng !== undefined && $locationTextSpan.length) {
                const carLocationPoint = turf.point([parseFloat(cardLng), parseFloat(cardLat)]);
                const distance = turf.distance(pinLocation, carLocationPoint, { units: 'kilometers' });
                const distanceText = ` (${distance.toFixed(1)} km away)`;
                
                let currentText = $locationTextSpan.text().replace(/\s*\([\d\.]+\s*km away\)/, '');
                $locationTextSpan.text(currentText + distanceText);
            }
        });
    }

    function updateFilterCounts(filterCounts) {
        if (!filterCounts) return;

        // Update make dropdown with counts
        if (filterCounts.make) {
            const $makeSelect = $('#filter-make');
            $makeSelect.find('option').each(function() {
                const $option = $(this);
                const makeValue = $option.val();
                
                if (makeValue !== '') {
                    const count = filterCounts.make[makeValue] || 0;
                    const baseText = $option.text().replace(/\s*\(\d+\)$/, '');
                    
                    $option.text(`${baseText} (${count})`);
                    
                    // Disable options with zero count unless currently selected
                    if (count === 0 && !$option.prop('selected')) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        }

        // Update model dropdown (if make is selected)
        const selectedMake = $('#filter-make').val();
        if (selectedMake && filterCounts.model_by_make && filterCounts.model_by_make[selectedMake]) {
            const $modelSelect = $('#filter-model');
            $modelSelect.find('option').each(function() {
                const $option = $(this);
                const modelValue = $option.val();
                if (modelValue !== '') { // Skip the "Select Make First" option
                    const count = filterCounts.model_by_make[selectedMake][modelValue] || 0;
                    const optionText = $option.text().replace(/\s*\(\d+\)$/, ''); // Remove existing count
                    $option.text(`${optionText} (${count})`);
                    
                    // Disable options with zero count unless currently selected
                    if (count === 0 && $option.prop('selected') === false) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        }

        // Update fuel type dropdown
        if (filterCounts.fuel_type) {
            updateMultiselectCounts('fuel_type', filterCounts.fuel_type);
        }

        // Update transmission dropdown
        if (filterCounts.transmission) {
            updateMultiselectCounts('transmission', filterCounts.transmission);
        }

        // Update body type dropdown
        if (filterCounts.body_type) {
            updateMultiselectCounts('body_type', filterCounts.body_type);
        }

        // Update drive type dropdown
        if (filterCounts.drive_type) {
            updateMultiselectCounts('drive_type', filterCounts.drive_type);
        }

        // Update exterior color dropdown
        if (filterCounts.exterior_color) {
            updateMultiselectCounts('exterior_color', filterCounts.exterior_color);
        }

        // Update interior color dropdown
        if (filterCounts.interior_color) {
            updateMultiselectCounts('interior_color', filterCounts.interior_color);
        }

        // Update year range selects
        if (filterCounts.year) {
            updateRangeSelectCounts('year', filterCounts.year);
        }

        // Update engine capacity range selects
        if (filterCounts.engine_capacity) {
            updateRangeSelectCounts('engine', filterCounts.engine_capacity);
        }

        // Update mileage range selects
        if (filterCounts.mileage) {
            updateRangeSelectCounts('mileage', filterCounts.mileage);
        }
    }

    // Helper function to update multiselect filter counts
    function updateMultiselectCounts(filterKey, counts) {
        const $container = $(`.multi-select-filter[data-filter-key="${filterKey}"]`);
        if ($container.length) {
            $container.find('li input[type="checkbox"]').each(function() {
                const $checkbox = $(this);
                const value = $checkbox.val();
                const count = counts[value] || 0;
                const $countSpan = $checkbox.closest('label').find('.option-count');
                
                if ($countSpan.length) {
                    $countSpan.text(count);
                }
                
                // Disable options with zero count unless currently checked
                if (count === 0 && !$checkbox.prop('checked')) {
                    $checkbox.prop('disabled', true);
                    $checkbox.closest('li').addClass('disabled-option');
                } else {
                    $checkbox.prop('disabled', false);
                    $checkbox.closest('li').removeClass('disabled-option');
                }
            });
        }
    }

    // Helper function to update range select filter counts
    function updateRangeSelectCounts(rangeType, counts) {
        // Handle min select
        const $minSelect = $(`select[name="${rangeType}_min"]`);
        if ($minSelect.length) {
            $minSelect.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                if (value !== '') { // Skip the "Min" option
                    let displayValue = value;
                    const count = counts[value] || 0;
                    
                    // Extract existing display text without the count
                    let baseText = $option.text().replace(/\s*\(\d+\)$/, '');
                    
                    // Make sure we keep any suffix (km, L)
                    $option.text(`${baseText} (${count})`);
                    
                    // Disable options with zero count unless currently selected
                    if (count === 0 && $option.prop('selected') === false) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        }
        
        // Handle max select
        const $maxSelect = $(`select[name="${rangeType}_max"]`);
        if ($maxSelect.length) {
            $maxSelect.find('option').each(function() {
                const $option = $(this);
                const value = $option.val();
                if (value !== '') { // Skip the "Max" option
                    let displayValue = value;
                    const count = counts[value] || 0;
                    
                    // Extract existing display text without the count
                    let baseText = $option.text().replace(/\s*\(\d+\)$/, '');
                    
                    // Make sure we keep any suffix (km, L)
                    $option.text(`${baseText} (${count})`);
                    
                    // Disable options with zero count unless currently selected
                    if (count === 0 && $option.prop('selected') === false) {
                        $option.prop('disabled', true);
                    } else {
                        $option.prop('disabled', false);
                    }
                }
            });
        }
    }

    // --- Popup Handling for Spec Filters ---
    openSpecFiltersBtn.on('click', function() {
        specFiltersPopup.show();
    });

    closeSpecFiltersBtn.on('click', function() {
        specFiltersPopup.hide();
    });

    specFiltersPopup.on('click', function(e) {
        if ($(e.target).is(specFiltersPopup)) {
            specFiltersPopup.hide();
        }
    });

    // --- Apply Spec Filters Button ---
    applySpecFiltersBtn.on('click', function() {
        console.log('[ApplySpecFilters] Apply button clicked.');
        // Collect current location from URL (or stored state if preferred)
        const urlParams = new URLSearchParams(window.location.search);
        const lat = urlParams.get('lat') || (initialFilter.lat || null);
        const lng = urlParams.get('lng') || (initialFilter.lng || null);
        const radius = urlParams.get('radius') || (initialFilter.radius || null);
        
        fetchFilteredListings(1, lat, lng, radius); // Page 1, with current location and newly applied spec filters
        specFiltersPopup.hide();
        // updateActiveFiltersDisplay(); // Call function to update the display of active filters
    });

    // --- Reset Spec Filters Button ---
    resetSpecFiltersBtn.on('click', function() {
        console.log('[ResetSpecFilters] Reset button clicked.');
        // Clear all filter inputs within the specFiltersContainer
        specFiltersContainer.find('select').val('');
        specFiltersContainer.find('input[type="text"]').val('');
        specFiltersContainer.find('input[type="checkbox"]').prop('checked', false);

        // Special handling for model dropdown (disable and reset)
        $('#filter-model').prop('disabled', true).html('<option value="">Select Make First</option>');

        // After resetting, fetch listings. Pass current location filters.
        const urlParams = new URLSearchParams(window.location.search);
        const lat = urlParams.get('lat') || (initialFilter.lat || null);
        const lng = urlParams.get('lng') || (initialFilter.lng || null);
        const radius = urlParams.get('radius') || (initialFilter.radius || null);

        fetchFilteredListings(1, lat, lng, radius);
        specFiltersPopup.hide(); // Optionally hide popup after reset
        // updateActiveFiltersDisplay(); // Update display of active filters (should be empty now)
    });

    // Initial fetch on page load, respecting URL and localStorage
    const pageToFetch = urlParams.get('paged') || 1;
    let initialLoadLat = null;
    let initialLoadLng = null;
    let initialLoadRadius = null;

    // Initialize makes filter
    $('#filter-make').addClass('loading-filter');

    if (initialFilter && typeof initialFilter === 'object') {
        if (initialFilter.lat !== null && initialFilter.lng !== null && initialFilter.radius !== null) {
            console.log('[PageLoad] Using location from initialFilter (URL or localStorage).', initialFilter);
            initialLoadLat = initialFilter.lat;
            initialLoadLng = initialFilter.lng;
            initialLoadRadius = initialFilter.radius;
            currentLocationText.text(initialFilter.text || 'Selected location');
        } else {
            console.log('[PageLoad] initialFilter present but location data is null/incomplete.');
        }
    } else {
        console.log('[PageLoad] initialFilter is not an object or is null.');
    }

    // Fetch initial makes data
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: 1,
            per_page: 1,
            get_all_makes: true
        },
        success: function(response) {
            if (response.success && response.data.all_makes) {
                updateMakeFilter(response.data.all_makes, '');
            }
            $('#filter-make').removeClass('loading-filter');
        },
        error: function() {
            $('#filter-make').removeClass('loading-filter');
        }
    });

    // Fetch listings with location (if any) and any spec filters from URL
    console.log('[PageLoad] Triggering initial fetchFilteredListings.');
    fetchFilteredListings(pageToFetch, initialLoadLat, initialLoadLng, initialLoadRadius);

    $('body').on('click', '.car-listings-pagination a.page-numbers', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const urlParams = new URLSearchParams(href.split('?')[1]);
        const page = parseInt(urlParams.get('paged')) || 1;
        fetchFilteredListings(page);
    });
}); 