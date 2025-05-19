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

            currentLocationText.text(locationName);
            modal.hide();
            
            // Clear the make filter when location changes
            $('#filter-make').val('');
            
            // Fetch listings with new location
            fetchFilteredListings(1, lat, lng, radius);

            // Update URL and localStorage as before
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('lat', lat.toFixed(7));
            currentUrl.searchParams.set('lng', lng.toFixed(7));
            currentUrl.searchParams.set('radius', radius.toString());
            currentUrl.searchParams.set('location_name', locationName);
            currentUrl.searchParams.delete('paged');
            currentUrl.searchParams.delete('make'); // Remove make filter from URL
            history.pushState({ path: currentUrl.href }, '', currentUrl.href);

            const preferredLocation = {
                lat: lat,
                lng: lng,
                radius: radius,
                name: locationName
            };
            localStorage.setItem('autoAgoraUserLocation', JSON.stringify(preferredLocation));
        } else {
            currentLocationText.text('All of Cyprus');
            modal.hide();
            fetchFilteredListings(1, null, null, null);
            
            // Clear location parameters from URL
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('lat');
            currentUrl.searchParams.delete('lng');
            currentUrl.searchParams.delete('radius');
            currentUrl.searchParams.delete('location_name');
            currentUrl.searchParams.delete('paged');
            currentUrl.searchParams.delete('make'); // Remove make filter from URL
            history.pushState({ path: currentUrl.href }, '', currentUrl.href);
            
            localStorage.removeItem('autoAgoraUserLocation');
        }
    });

    function fetchFilteredListings(page = 1, lat = null, lng = null, radius = null) {
        // First check explicit parameters, then URL parameters if not provided
        const urlParams = new URLSearchParams(window.location.search);
        
        // Use explicit parameters first, fall back to URL parameters for location
        let filterLat = lat !== null ? lat : urlParams.get('lat') || null;
        let filterLng = lng !== null ? lng : urlParams.get('lng') || null;
        let filterRadius = radius !== null ? radius : urlParams.get('radius') || null;
        
        // Collect Specification Filters
        const specFilters = {};
        specFilters.make = $('#filter-make').val() || urlParams.get('make') || '';
        specFilters.model = $('#filter-model').val() || urlParams.get('model') || '';
        specFilters.variant = $('#filter-variant').val() || urlParams.get('variant') || '';
        specFilters.year_min = $('#filter-year-min').val() || urlParams.get('year_min') || '';
        specFilters.year_max = $('#filter-year-max').val() || urlParams.get('year_max') || '';
        specFilters.price_min = $('#filter-price-min').val() || urlParams.get('price_min') || '';
        specFilters.price_max = $('#filter-price-max').val() || urlParams.get('price_max') || '';
        specFilters.mileage_min = $('#filter-mileage-min').val() || urlParams.get('mileage_min') || '';
        specFilters.mileage_max = $('#filter-mileage-max').val() || urlParams.get('mileage_max') || '';
        specFilters.engine_capacity_min = $('#filter-engine-capacity-min').val() || urlParams.get('engine_capacity_min') || '';
        specFilters.engine_capacity_max = $('#filter-engine-capacity-max').val() || urlParams.get('engine_capacity_max') || '';
        specFilters.hp_min = $('#filter-hp-min').val() || urlParams.get('hp_min') || '';
        specFilters.hp_max = $('#filter-hp-max').val() || urlParams.get('hp_max') || '';
        specFilters.transmission = $('#filter-transmission').val() || urlParams.get('transmission') || '';
        specFilters.number_of_doors = $('#filter-number-of-doors').val() || urlParams.get('number_of_doors') || '';
        specFilters.number_of_seats = $('#filter-number-of-seats').val() || urlParams.get('number_of_seats') || '';
        specFilters.availability = $('#filter-availability').val() || urlParams.get('availability') || '';
        specFilters.numowners_min = $('#filter-numowners-min').val() || urlParams.get('numowners_min') || '';
        specFilters.numowners_max = $('#filter-numowners-max').val() || urlParams.get('numowners_max') || '';
        specFilters.isantique = $('#filter-isantique').val() || urlParams.get('isantique') || '';

        // Collect checkbox (multi-select) filters
        $('.multi-select-filter').each(function() {
            const filterKey = $(this).data('filter-key');
            const checkedValues = $(this).find('input[type="checkbox"]:checked').map(function() {
                return $(this).val();
            }).get();
            if (checkedValues.length > 0) {
                specFilters[filterKey] = checkedValues;
            } else {
                // If nothing is checked, check URL params for this filter key
                const urlValues = urlParams.getAll(filterKey + '[]'); // Check for array format like fuel_type[]
                if (urlValues.length > 0) {
                    specFilters[filterKey] = urlValues;
                } else {
                    const singleUrlValue = urlParams.get(filterKey);
                    if (singleUrlValue) specFilters[filterKey] = [singleUrlValue]; // Handle single value from URL if present
                }
            }
        });

        console.log(`[FetchListings] Fetching page ${page}. Lat: ${filterLat}, Lng: ${filterLng}, Radius: ${filterRadius}`);
        console.log('[FetchListings] Spec Filters:', specFilters);
        
        // Abort any existing listings request if it's still running
        if (currentListingsRequest && currentListingsRequest.readyState !== 4) {
            console.log('[FetchListings] Aborting previous listings request.');
            currentListingsRequest.abort();
        }

        // Preserve other existing URL parameters when fetching
        const currentUrlParams = new URLSearchParams(window.location.search);
        const data = {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: page,
            filter_lat: filterLat,
            filter_lng: filterLng,
            filter_radius: filterRadius,
            per_page: carListingsMapFilterData.perPage || 12
        };

        // Add collected specification filters to the data object
        for (const key in specFilters) {
            if (specFilters.hasOwnProperty(key) && specFilters[key] !== '' && specFilters[key] !== null) {
                if (Array.isArray(specFilters[key]) && specFilters[key].length === 0) {
                    // Skip empty arrays for checkbox filters if nothing selected and not in URL
                    continue; 
                }
                data[key] = specFilters[key];
            }
        }

        // Add other existing URL parameters to the AJAX request if they are not related to location/pagination/known spec filters
        // This helps if other filters are also managed via URL and should persist
        const knownFilterKeys = ['lat', 'lng', 'radius', 'location_name', 'paged', 'action', 'nonce', 'per_page'].concat(Object.keys(specFilters));
        currentUrlParams.forEach((value, key) => {
            if (!knownFilterKeys.includes(key) && !(key.endsWith('_min') || key.endsWith('_max'))) {
                 // Check for array format like fuel_type[] from URL that might not be in specFilters if empty
                if (key.includes('[]')) { 
                    const plainKey = key.replace('[]', '');
                    if (!knownFilterKeys.includes(plainKey)) {
                        data[key] = value; // Or handle as array if needed: data[key] = currentUrlParams.getAll(key);
                    }
                } else {
                    data[key] = value;
                }
            }
        });

        $('.car-listings-grid').html('<div class="loading-spinner">Loading listings...</div>'); // Show loading indicator

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
                    if (filterLat !== null && filterLng !== null && filterRadius !== null) {
                        const pinLocation = turf.point([filterLng, filterLat]);

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

                    updateCarListings(response.listings_html);
                    updatePagination(response.pagination_html);
                    updateUrlWithFilters(page, currentFilter.lat, currentFilter.lng, currentFilter.radius);

                    // Removed: Update filter dropdowns with new counts as spec filters are removed from listings page
                    /*
                    if (response.filter_counts) {
                        console.log('[FetchListings] Received filter counts:', response.filter_counts);
                        // Assuming you have a function to update your filter dropdowns
                        // This is a placeholder for where you'd call it
                        // updateFilterDropdowns(response.filter_counts);
                    } else {
                        console.log('[FetchListings] No filter counts received in response.');
                    }
                    */

                    console.log("[FetchListings] Completed. Map should reflect new listings.");
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

    // New helper function to update filter counts
    function updateFilterCounts(filterCounts) {
        // Update make dropdown
        if (filterCounts.make) {
            const $makeSelect = $('#filter-make');
            const currentValue = $makeSelect.val(); // Store current selection
            
            // Clear existing options except the first one (usually "All Makes")
            $makeSelect.find('option:not(:first)').remove();
            
            // Sort makes alphabetically
            const sortedMakes = Object.keys(filterCounts.make).sort();
            
            // Add options with counts
            sortedMakes.forEach(make => {
                const count = filterCounts.make[make];
                const $option = $('<option>', {
                    value: make,
                    text: `${make} (${count})`,
                    disabled: count === 0 && make !== currentValue
                });
                
                // If this was the previously selected value, mark it as selected
                if (make === currentValue) {
                    $option.prop('selected', true);
                }
                
                $makeSelect.append($option);
            });
        }

        // Update model dropdown (if make is selected)
        const selectedMake = $('select[name="make"]').val();
        if (selectedMake && filterCounts.model_by_make && filterCounts.model_by_make[selectedMake]) {
            const $modelSelect = $('select[name="model"]');
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
    
    // Fetch listings with location (if any) and any spec filters from URL
    // The collection of spec filters inside fetchFilteredListings will pick them up from URL if present.
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