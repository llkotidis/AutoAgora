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

    radiusSlider.on('input', function() {
        currentRadiusKm = parseInt($(this).val());
        radiusValueDisplay.text(currentRadiusKm);
        if (selectedCoords) {
            updateRadiusCircle(selectedCoords, currentRadiusKm);
        }
    });

    applyBtn.on('click', function() {
        if (!selectedCoords) {
            initialFilter = { lat: null, lng: null, radius: null, text: 'All of Cyprus' };
            selectedLocationName = 'All of Cyprus';
        } else {
            initialFilter = {
                lat: selectedCoords[1],
                lng: selectedCoords[0],
                radius: currentRadiusKm,
                text: `Within ${currentRadiusKm}km of ${selectedLocationName || 'selected area'}` 
            };
        }
        currentLocationText.text(initialFilter.text);
        modal.hide();
        fetchFilteredListings(1); 
    });

    function fetchFilteredListings(page = 1) {
        $('.car-listings-grid').html('<p>Loading listings...</p>'); 
        $('.car-listings-pagination').empty();

        const ajaxData = {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: page,
            filter_lat: initialFilter.lat,
            filter_lng: initialFilter.lng,
            filter_radius: initialFilter.radius,
            per_page: 12 
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    $('.car-listings-grid').html(response.data.listings_html);
                    $('.car-listings-pagination').html(response.data.pagination_html);

                    // Calculate and display distances if location filter is active
                    if (initialFilter.lat !== null && initialFilter.lng !== null && initialFilter.radius !== null) {
                        const pinLocation = turf.point([initialFilter.lng, initialFilter.lat]);

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
                $('.car-listings-grid').html('<p>AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
            }
        });
    }

    $('body').on('click', '.car-listings-pagination a.page-numbers', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const urlParams = new URLSearchParams(href.split('?')[1]);
        const page = parseInt(urlParams.get('paged')) || 1;
        fetchFilteredListings(page);
    });
}); 