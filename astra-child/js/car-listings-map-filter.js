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
            // Location is selected
            const lat = selectedCoords[1];
            const lng = selectedCoords[0];
            const radius = currentRadiusKm;
            const locationName = selectedLocationName || 'Selected on map';

            currentLocationText.text(locationName);
            modal.hide();

            // Save to localStorage BEFORE calling handleFilterChange, 
            // as getActiveFilters might use selectedLocationName which should be fresh.
            const preferredLocation = {
                lat: lat,
                lng: lng,
                radius: radius,
                name: locationName
            };
            localStorage.setItem('autoAgoraUserLocation', JSON.stringify(preferredLocation));
            console.log('[ApplyFilter] Location saved to localStorage:', preferredLocation);

            // Call handleFilterChange. It will pick up selectedCoords, currentRadiusKm, 
            // selectedLocationName and update URL & fetch.
            handleFilterChange(false);

        } else {
            // No specific coords - means "All of Cyprus" or location cleared
            selectedCoords = null;
            currentRadiusKm = initialFilter.radius || 10; // Reset to a default or initial if cleared
            selectedLocationName = 'All of Cyprus'; // Explicitly set for getActiveFilters

            currentLocationText.text(selectedLocationName);
            modal.hide();
            
            localStorage.removeItem('autoAgoraUserLocation');
            console.log('[ApplyFilter] Location cleared from localStorage.');

            // Call handleFilterChange. It will see no selectedCoords and act accordingly.
            handleFilterChange(false);
        }
    });

    function fetchFilteredListings(page = 1, lat = null, lng = null, radius = null) {
        console.log(`[FetchListings] Init. Page: ${page}, Explicit Lat: ${lat}, Lng: ${lng}, Radius: ${radius}`);
        
        if (currentListingsRequest && currentListingsRequest.readyState !== 4) {
            console.log('[FetchListings] Aborting previous listings request.');
            currentListingsRequest.abort();
        }

        // Get all other active filters from the form
        const otherActiveFilters = getActiveFilters();
        // console.log('[FetchListings] Other active filters from getActiveFilters():', JSON.parse(JSON.stringify(otherActiveFilters)));

        const data = {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: page,
            per_page: carListingsMapFilterData.perPage || 12
        };

        // 1. Add explicit location parameters if provided to the function call
        // These take precedence and are typically from a direct map interaction.
        if (lat !== null && lng !== null && radius !== null) {
            data.filter_lat = lat;
            data.filter_lng = lng;
            data.filter_radius = radius;
            // console.log('[FetchListings] Using explicit location params for AJAX data.');
        } else if (otherActiveFilters.lat && otherActiveFilters.lng && otherActiveFilters.radius) {
            // 2. If not explicit, use location from getActiveFilters (which reads from selectedCoords or URL)
            data.filter_lat = otherActiveFilters.lat;
            data.filter_lng = otherActiveFilters.lng;
            data.filter_radius = otherActiveFilters.radius;
            // console.log('[FetchListings] Using location from getActiveFilters() for AJAX data.');
        } else {
            // console.log('[FetchListings] No location filter active for AJAX data.');
        }

        // 3. Add all other non-location filters from getActiveFilters()
        for (const key in otherActiveFilters) {
            if (Object.prototype.hasOwnProperty.call(otherActiveFilters, key)) {
                if (key !== 'lat' && key !== 'lng' && key !== 'radius' && key !== 'location_name') {
                    // For array values (multi-select), pass them as is. jQuery/PHP will handle foo[]=bar&foo[]=baz
                    data[key] = otherActiveFilters[key];
                }
            }
        }
        
        // console.log('[FetchListings] Final AJAX data being sent:', JSON.parse(JSON.stringify(data)));

        $('.car-listings-grid').html('<div class="loading-spinner">Loading listings...</div>');
        $('.car-listings-pagination').empty(); // Clear pagination during load

        currentListingsRequest = $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('.car-listings-grid').html(response.data.listings_html);
                    $('.car-listings-pagination').html(response.data.pagination_html);

                    // Re-initialize features for the new content
                    if (typeof reinitializeCarousels === "function") {
                        console.log('[MapFilter AJAX] Calling reinitializeCarousels');
                        reinitializeCarousels();
                    }
                    if (typeof reinitializeFavoriteButtons === "function") {
                        console.log('[MapFilter AJAX] Calling reinitializeFavoriteButtons');
                        reinitializeFavoriteButtons();
                    }
                    if (typeof updateResultsCounter === "function") {
                         console.log('[MapFilter AJAX] Calling updateResultsCounter');
                        // Ensure data.query_vars.found_posts is available and a number
                        const totalResults = (response.data && typeof response.data.query_vars && typeof response.data.query_vars.found_posts !== 'undefined') 
                                             ? parseInt(response.data.query_vars.found_posts, 10) 
                                             : null;
                        updateResultsCounter(isNaN(totalResults) ? null : totalResults);
                    }

                    // Update filter counts - always do this on a successful AJAX response
                    if (response.data.filter_counts) {
                        console.log('[MapFilter AJAX] Updating filter counts with new data');
                        updateFilterCounts(response.data.filter_counts);
                    } else {
                        console.warn('[MapFilter AJAX] No filter_counts in AJAX response');
                    }

                    // Calculate and display distances if location filter is active
                    if (data.filter_lat !== null && data.filter_lng !== null && data.filter_radius !== null) {
                        const pinLocation = turf.point([data.filter_lng, data.filter_lat]);

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
                                
                                // Get current text, remove old distance if any, then append new one
                                let currentText = $locationTextSpan.text();
                                currentText = currentText.replace(/\s*\([\d\.]+\s*km away\)/, ''); // Remove old distance
                                $locationTextSpan.text(currentText + distanceText); // Set new text with distance
                            }
                        });
                    } else {
                        // No active location filter, ensure distances are cleared from any cards
                        $('.car-listings-grid .car-listing-card .car-location span.location-text').each(function() {
                            const $span = $(this);
                            let currentText = $span.text();
                            currentText = currentText.replace(/\s*\([\d\.]+\s*km away\)/, '');
                            $span.text(currentText);
                        });
                    }
                } else {
                    $('.car-listings-grid').html('<p>Error loading listings. ' + (response.data && response.data.message ? response.data.message : '') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Only show error if this request was not manually aborted
                if (textStatus !== 'abort') {
                    $('.car-listings-grid').html('<p>AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                }
            }
        });
    }

    // Main function to update all filter counts based on AJAX response
    function updateFilterCounts(filterCounts) {
        if (!filterCounts) {
            // console.warn('[updateFilterCounts] filterCounts is undefined or null. Skipping update.');
            return;
        }
        // console.log('[updateFilterCounts] Received new counts:', JSON.parse(JSON.stringify(filterCounts)));

        // --- 1. Update Make Filter First ---
        const $makeSelect = $('#make-filter');
        if ($makeSelect.length) {
            const makeCounts = filterCounts.make || {};
            // console.log('[updateFilterCounts] Updating MAKE with counts:', makeCounts);
            updateSelectOptionsWithCounts($makeSelect, makeCounts, 'Any Make', false);
        } else {
            // console.warn('[updateFilterCounts] Make select (#make-filter) not found.');
        }

        // --- 2. Update Model Filter (dependent on Make) ---
        const $modelSelect = $('#model-filter');
        if ($modelSelect.length) {
            const selectedMake = $makeSelect.val(); // Get current make value AFTER it might have been updated by updateSelectOptionsWithCounts
            let modelCountsForSelectedMake = {};
            let modelPlaceholder = 'Select Make First';
            let disableModelPlaceholder = true;

            if (selectedMake && filterCounts.model_by_make && filterCounts.model_by_make[selectedMake]) {
                modelCountsForSelectedMake = filterCounts.model_by_make[selectedMake];
                modelPlaceholder = 'Any Model';
                disableModelPlaceholder = false;
                if (Object.keys(modelCountsForSelectedMake).length === 0) {
                    modelPlaceholder = 'No models for ' + selectedMake;
                    disableModelPlaceholder = true; // No models, so disable placeholder
                }
            } else if (selectedMake) {
                // Make is selected, but no specific model counts for it (e.g. model_by_make didn't include it or it's empty)
                modelPlaceholder = 'No models for ' + selectedMake;
                disableModelPlaceholder = true;
            }
            // If no make is selected, placeholder remains 'Select Make First' and is disabled.
            
            // console.log(`[updateFilterCounts] Updating MODEL for make "${selectedMake || 'N/A'}" with counts:`, modelCountsForSelectedMake, `Placeholder: "${modelPlaceholder}"`);
            updateSelectOptionsWithCounts($modelSelect, modelCountsForSelectedMake, modelPlaceholder, disableModelPlaceholder);
        } else {
            // console.warn('[updateFilterCounts] Model select (#model-filter) not found.');
        }

        // --- 3. Update Multi-select Checkbox Filters ---
        ['fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type'].forEach(filterKey => {
            const countsForKey = filterCounts[filterKey] || {};
            // console.log(`[updateFilterCounts] Updating MULTISELECT "${filterKey}" with counts:`, countsForKey);
            updateMultiselectCounts(filterKey, countsForKey);
        });

        // --- 4. Update Range Filters (Year, Price, Mileage, Engine Capacity) ---
        ['year', 'price', 'mileage', 'engine_capacity'].forEach(rangeType => {
            const countsForRange = filterCounts[rangeType] || {};
            // console.log(`[updateFilterCounts] Updating RANGE "${rangeType}" with counts:`, countsForRange);
            updateRangeSelectCounts(rangeType, countsForRange); 
        });
        
        // console.log('[updateFilterCounts] All filter counts dynamically processed.');
    }

    // Helper to populate a select dropdown with options and their counts
    function updateSelectOptionsWithCounts($select, optionsWithCounts, placeholder, disabledPlaceholder = false) {
        if (!$select || $select.length === 0) {
            // console.warn('[updateSelectOptionsWithCounts] Select element not found or invalid.');
            return;
        }
        const currentVal = $select.val();
        $select.empty();

        // Add placeholder option
        const $placeholderOption = $('<option></option>').val('').text(placeholder);
        if (disabledPlaceholder) {
            $placeholderOption.prop('disabled', true);
        }
        $select.append($placeholderOption);
        
        let hasSelectedValueInNewOptions = false;
        let hasAnyOptions = false; // To track if any actual options are added besides placeholder

        if (optionsWithCounts && Object.keys(optionsWithCounts).length > 0) {
            hasAnyOptions = true;
            // Sort options by key (e.g., make name, model name) for consistent order
            const sortedKeys = Object.keys(optionsWithCounts).sort((a, b) => {
                // If keys are numeric (like years from range filters), sort numerically, otherwise alphabetically
                if (!isNaN(parseFloat(a)) && !isNaN(parseFloat(b))) {
                    return parseFloat(a) - parseFloat(b);
                }
                return String(a).localeCompare(String(b)); // Ensure string comparison for makes, models etc.
            });

            sortedKeys.forEach(key => {
                const count = parseInt(optionsWithCounts[key], 10);
                const $option = $('<option></option>')
                    .val(key)
                    .text(`${key} (${count})`);
                
                // Disable if count is 0, UNLESS it's the currently selected value
                if (count === 0 && String(key) !== String(currentVal)) {
                    $option.prop('disabled', true).addClass('disabled-option zero-count');
                }
                
                if (String(key) === String(currentVal)) {
                    $option.prop('selected', true);
                    hasSelectedValueInNewOptions = true;
                    // If the selected option now has 0 count, visually mark it but don't disable (user might want to unselect)
                    if (count === 0) {
                         $option.addClass('disabled-option zero-count'); 
                         // $option.prop('disabled', true); // Keep it selectable to allow unselecting
                    }
                }
                $select.append($option);
            });
        }

        // If the previously selected value is no longer in the options (e.g., count became 0 or filter changed)
        // Add it back as a selected, disabled option to show what was selected, but make it clear it's no longer valid/available.
        if (currentVal && currentVal !== '' && !hasSelectedValueInNewOptions) {
            // console.log(`[updateSelectOptionsWithCounts] Persisting old selection for ${$select.attr('id')}: ${currentVal}`);
            const $previousSelectedOption = $('<option></option>')
                .val(currentVal)
                .text(`${currentVal} (0)`) // Show 0 count
                .prop('selected', true)
                .prop('disabled', true)
                .addClass('disabled-option zero-count persisted-selection');
            $select.append($previousSelectedOption);
        } else if ((!currentVal || currentVal === '') && !disabledPlaceholder) {
             // If no current value (placeholder was selected) and the placeholder is not a "disabled" one (like 'Select Make First')
             // ensure the (default) placeholder is re-selected.
             $select.val(''); 
        } else if (hasSelectedValueInNewOptions) {
            // If the current value is still valid, ensure it's re-selected (it should be already by the loop above)
            $select.val(currentVal); 
        }
        
        // If no options were actually added (other than placeholder) and placeholder is not disabled, ensure placeholder selected.
        if (!hasAnyOptions && !disabledPlaceholder) {
            $select.val('');
        }
    }

    // Helper function to update multiselect filter counts and disabled state
    function updateMultiselectCounts(filterKey, counts) {
        const $container = $(`.multi-select-filter[data-filter-key="${filterKey}"]`);
        if ($container.length) {
            $container.find('li input[type="checkbox"]').each(function() {
                const $checkbox = $(this);
                const value = $checkbox.val();
                // Ensure counts is treated as an object; default to 0 if value not in counts or counts is null
                const count = (counts && typeof counts === 'object' && counts[value] !== undefined) ? parseInt(counts[value], 10) : 0;
                const $label = $checkbox.closest('label');
                let $countSpan = $label.find('.option-count');

                // Add count span if it doesn't exist
                if ($countSpan.length === 0) {
                    $label.append(' <span class="option-count"></span>');
                    $countSpan = $label.find('.option-count');
                }
                
                $countSpan.text(`(${count})`);

                // Visually de-emphasize and disable if count is 0 AND not currently checked
                // Re-enable if count > 0 or if it is checked (to allow unchecking)
                if (count === 0 && !$checkbox.prop('checked')) {
                    $checkbox.prop('disabled', true);
                    $label.addClass('disabled-option zero-count');
                } else {
                    $checkbox.prop('disabled', false);
                    $label.removeClass('disabled-option zero-count');
                }
            });
        } else {
            // console.warn(`[updateMultiselectCounts] Container not found for filterKey: ${filterKey}`);
        }
    }

    // Main new function to handle updating both min and max selects for a given range type
    function updateRangeSelectCounts(rangeType, counts) {
        // console.log(`[updateRangeSelectCounts] For "${rangeType}" with counts:`, JSON.parse(JSON.stringify(counts)));
        const $minSelect = $(`#${rangeType}_min`);
        const $maxSelect = $(`#${rangeType}_max`);

        if (!$minSelect.length || !$maxSelect.length) {
            // console.warn(`[updateRangeSelectCounts] Min/max select elements not found for rangeType: ${rangeType}`);
            return;
        }

        const currentMin = $minSelect.val();
        const currentMax = $maxSelect.val();

        let suffix = '';
        if (rangeType === 'mileage') suffix = ' km';
        else if (rangeType === 'engine_capacity') suffix = ' L';
        // Price might need formatting if using decimals, but values are keys for now.

        const placeholderMin = `Min ${rangeType.charAt(0).toUpperCase() + rangeType.slice(1).replace('_',' ')}`;
        const placeholderMax = `Max ${rangeType.charAt(0).toUpperCase() + rangeType.slice(1).replace('_',' ')}`;
        
        // Update Min Dropdown
        // Opposite value for min is currentMax. isMinSelect is true.
        updateSingleRangeDropdown($minSelect, counts, placeholderMin, currentMax, true, currentMin, suffix);
        
        // Update Max Dropdown
        // Opposite value for max is currentMin. isMinSelect is false.
        updateSingleRangeDropdown($maxSelect, counts, placeholderMax, currentMin, false, currentMax, suffix);
    }

    // Helper to update a single range dropdown (either min or max)
    function updateSingleRangeDropdown($select, valuesWithCounts, placeholder, oppositeValue, isMinSelect, currentValue, suffix = '') {
        if (!$select || $select.length === 0) {
            // console.warn('[updateSingleRangeDropdown] Select element not found.');
            return;
        }
        $select.empty();
        $select.append($('<option></option>').val('').text(placeholder));

        let hasSelectedValueInNewOptions = false;
        let hasAnyOptions = false;

        if (valuesWithCounts && Object.keys(valuesWithCounts).length > 0) {
            hasAnyOptions = true;
            // Sort values numerically. Keys are strings, so parse them.
            const sortedValues = Object.keys(valuesWithCounts).sort((a, b) => parseFloat(a) - parseFloat(b));

            sortedValues.forEach(value => {
                const count = parseInt(valuesWithCounts[value], 10);
                const displayValue = value + suffix; // Add suffix for display like km or L
                const $option = $('<option></option>')
                    .val(value) // Store raw value
                    .text(`${displayValue} (${count})`); 

                let disableOption = false;
                // Disable if count is 0, unless it's the current value
                if (count === 0 && String(value) !== String(currentValue)) {
                    disableOption = true;
                }

                // Disable based on opposite value if opposite is selected
                if (oppositeValue && oppositeValue !== '') {
                    const numValue = parseFloat(value);
                    const numOppositeValue = parseFloat(oppositeValue);
                    if (isMinSelect && numValue > numOppositeValue) { // Min option value > selected Max value
                        disableOption = true;
                    } else if (!isMinSelect && numValue < numOppositeValue) { // Max option value < selected Min value
                        disableOption = true;
                    }
                }
                
                if (disableOption && String(value) !== String(currentValue)) {
                    $option.prop('disabled', true).addClass('disabled-option');
                    if (count === 0) {
                        $option.addClass('zero-count');
                    }
                }

                if (String(value) === String(currentValue)) {
                    $option.prop('selected', true);
                    hasSelectedValueInNewOptions = true;
                    if (count === 0) { // If selected value now has 0 count, mark it but keep selectable
                        $option.addClass('disabled-option zero-count');
                    }
                }
                $select.append($option);
            });
        }
        
        if (currentValue && currentValue !== '' && !hasSelectedValueInNewOptions) {
            const $previousSelectedOption = $('<option></option>')
                .val(currentValue)
                .text(`${currentValue}${suffix} (0)`) 
                .prop('selected', true)
                .prop('disabled', true)
                .addClass('disabled-option zero-count persisted-selection');
            $select.append($previousSelectedOption);
        } else if (!currentValue || currentValue === '') {
            $select.val(''); 
        } else if (hasSelectedValueInNewOptions) {
            $select.val(currentValue);
        }

        if (!hasAnyOptions) {
            $select.val('');
        }
    }

    // Initial fetch on page load, respecting URL and localStorage
    const pageToFetch = urlParams.get('paged') || 1;
    if (initialFilter && typeof initialFilter === 'object' && 
        initialFilter.hasOwnProperty('lat') && initialFilter.hasOwnProperty('lng') && initialFilter.hasOwnProperty('radius') &&
        initialFilter.lat !== null && initialFilter.lng !== null && initialFilter.radius !== null) {
        console.log('[PageLoad] Fetching initial listings based on active filter (URL or localStorage).', initialFilter);
        currentLocationText.text(initialFilter.text || 'Selected location');
        // Initial fetch using handleFilterChange to ensure URL and everything is consistent from the start
        handleFilterChange(pageToFetch > 1); // Pass true if paged > 1, so it keeps 'paged'
    } else {
        console.log('[PageLoad] No specific location active or initialFilter is incomplete/invalid, fetching default listings.', initialFilter);
        // Initial fetch using handleFilterChange
        handleFilterChange(pageToFetch > 1);
    }

    // --- Centralized Event Handlers for Filter Changes ---
    const filterContainer = $('.filters-popup-content'); // Assuming this is the main container for spec filters

    // Select dropdowns for make, model, ranges, and sort
    filterContainer.on('change', 
        '#make-filter, #model-filter, #year_min, #year_max, #price_min, #price_max, #mileage_min, #mileage_max, #engine_capacity_min, #engine_capacity_max, #sort-by-select',
        function() {
            console.log(`[FilterChange] Event on: ${this.id}`);
            handleFilterChange(false);
        }
    );

    // Checkboxes within multi-select filters
    filterContainer.on('change', '.multi-select-filter input[type="checkbox"]', function() {
        const filterKey = $(this).closest('.multi-select-filter').data('filter-key');
        console.log(`[FilterChange] Checkbox change in: ${filterKey}`);
        handleFilterChange(false);
    });

    // Pagination Clicks
    $('body').on('click', '.car-listings-pagination a.page-numbers', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const PagedUrlParams = new URLSearchParams(href.split('?')[1]); // Renamed to avoid conflict
        const page = parseInt(PagedUrlParams.get('paged')) || 1;
        console.log(`[PaginationClick] Navigating to page: ${page}`);
        
        // Update URL's paged parameter directly before calling handleFilterChange with isPagination = true
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('paged', page.toString());
        history.replaceState({ path: currentUrl.href }, '', currentUrl.href); // Use replaceState to avoid double history entry
        
        handleFilterChange(true); // True indicates it's a pagination change
    });

    // --- NEW HELPER: Get all active filter values from the form ---
    function getActiveFilters() {
        const filters = {};

        // Location (if selected)
        if (selectedCoords && selectedCoords.length === 2 && currentRadiusKm) {
            filters.lat = selectedCoords[1].toFixed(7);
            filters.lng = selectedCoords[0].toFixed(7);
            filters.radius = currentRadiusKm.toString();
            if (selectedLocationName && selectedLocationName !== 'All of Cyprus') {
                filters.location_name = selectedLocationName;
            }
        }

        // Single select filters
        ['make', 'model'].forEach(key => {
            const val = $(`#${key}-filter`).val();
            if (val) filters[key] = val;
        });

        // Range filters (min/max for year, price, mileage, engine_capacity)
        ['year', 'price', 'mileage', 'engine_capacity'].forEach(rangeType => {
            const minVal = $(`#${rangeType}_min`).val();
            const maxVal = $(`#${rangeType}_max`).val();
            if (minVal) filters[`${rangeType}_min`] = minVal;
            if (maxVal) filters[`${rangeType}_max`] = maxVal;
        });

        // Multi-select checkbox filters
        ['fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type'].forEach(filterKey => {
            const checkedValues = [];
            $(`.multi-select-filter[data-filter-key="${filterKey}"] input[type="checkbox"]:checked`).each(function() {
                checkedValues.push($(this).val());
            });
            if (checkedValues.length > 0) {
                filters[filterKey] = checkedValues; // Store as an array
            }
        });
        
        // Sort order
        const sortBy = $('#sort-by-select').val(); // Assuming a sort select with this ID
        if (sortBy) {
             const parts = sortBy.split('_');
             if (parts.length === 2) {
                 filters.orderby = parts[0];
                 filters.order = parts[1].toUpperCase();
             } else if (sortBy === 'default') { // Default might be relevance or date desc
                 filters.orderby = 'date'; // Or whatever your default is
                 filters.order = 'DESC';
             }
        }

        // console.log('[getActiveFilters] Collected:', JSON.parse(JSON.stringify(filters)));
        return filters;
    }

    // --- NEW ORCHESTRATOR: Handle any filter change ---
    function handleFilterChange(isPagination = false) {
        console.log('[handleFilterChange] Triggered.');
        const activeFilters = getActiveFilters();
        const currentUrl = new URL(window.location.href);

        // Clear existing filter params from URL, except for 'paged' if it's not a pagination call
        const paramsToKeep = isPagination ? ['paged'] : [];
        const allKnownFilterKeys = [
            'lat', 'lng', 'radius', 'location_name',
            'make', 'model', 'year_min', 'year_max', 'price_min', 'price_max', 
            'mileage_min', 'mileage_max', 'engine_capacity_min', 'engine_capacity_max',
            'fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type',
            'orderby', 'order'
        ];

        allKnownFilterKeys.forEach(key => {
            if (!paramsToKeep.includes(key)) {
                currentUrl.searchParams.delete(key);
            }
        });
        
        // Add active filters to URL
        for (const key in activeFilters) {
            if (Object.prototype.hasOwnProperty.call(activeFilters, key)) {
                const value = activeFilters[key];
                if (Array.isArray(value)) { // For multi-select checkboxes
                    value.forEach(v => currentUrl.searchParams.append(`${key}[]`, v)); // Use PHP array format for query params
                } else {
                    currentUrl.searchParams.set(key, value);
                }
            }
        }

        // Reset to page 1 if it's not a pagination triggered change
        if (!isPagination) {
            currentUrl.searchParams.delete('paged');
        }
        
        history.pushState({ path: currentUrl.href }, '', currentUrl.href);
        console.log('[handleFilterChange] URL updated to:', currentUrl.href);

        // Fetch listings. If location filters are active, they are in activeFilters.
        // fetchFilteredListings expects lat, lng, radius as separate args for its primary logic
        // but will also use them if they are just in the URL (which they now are)
        fetchFilteredListings(
            isPagination ? parseInt(urlParams.get('paged'),10) || 1 : 1, 
            activeFilters.lat || null, 
            activeFilters.lng || null, 
            activeFilters.radius || null
        );
    }

    // --- Filter Reset Functionality ---
    function resetAllFilters() {
        console.log('[ResetAllFilters] Resetting all filters.');

        // 1. Reset spec filter inputs
        // Selects (make, model, ranges, sort)
        $('#make-filter, #model-filter, #year_min, #year_max, #price_min, #price_max, #mileage_min, #mileage_max, #engine_capacity_min, #engine_capacity_max, #sort-by-select').val('');

        // Checkboxes
        $('.multi-select-filter input[type="checkbox"]').prop('checked', false);

        // Trigger change on one of them to refresh dependent UI if any (though updateFilterCounts will do most of this)
        // $('#make-filter').trigger('change'); // Not strictly necessary as handleFilterChange will call fetch which updates counts

        // 2. Reset location filter variables and UI
        selectedCoords = null;
        currentRadiusKm = carListingsMapFilterData.initialFilter.radius || 10; // Reset to initial default
        selectedLocationName = 'All of Cyprus';
        
        currentLocationText.text(selectedLocationName);
        radiusSlider.val(currentRadiusKm);
        radiusValueDisplay.text(currentRadiusKm);
        localStorage.removeItem('autoAgoraUserLocation');

        // Reset map view and geocoder if map is initialized
        if (map) {
            map.flyTo({ center: mapConfig.cyprusCenter, zoom: mapConfig.defaultZoom });
            if (marker) {
                marker.setLngLat(mapConfig.cyprusCenter); // Or remove marker
            }
            // Remove circle or reset it
            if (map.getLayer(circleLayerId)) map.removeLayer(circleLayerId);
            if (map.getSource(circleSourceId)) map.removeSource(circleSourceId);
            // updateRadiusCircle(mapConfig.cyprusCenter, currentRadiusKm); // Re-add default circle if desired
            
            if (geocoder && geocoder._inputEl) {
                geocoder._inputEl.value = ''; // Clear geocoder input
                geocoder.clear(); // Clear geocoder results
            }
        }
        
        // 3. Call handleFilterChange to update URL and fetch results
        handleFilterChange(false);
    }

    // Event listener for a "Reset All" button (assuming ID #reset-all-filters-btn)
    // The button itself should be added to the HTML structure where desired.
    $('body').on('click', '#reset-all-filters-btn', function(e) {
        e.preventDefault();
        resetAllFilters();
    });
}); 