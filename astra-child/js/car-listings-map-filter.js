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
                updateMarkerPosition(map.getCenter().toArray());
                updateRadiusCircle(map.getCenter().toArray(), currentRadiusKm);
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
            selectedCoords = initialMapCenter; // Keep track of initial filter coords
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

            // Initialize marker at the initial center of the map
            updateMarkerPosition(map.getCenter().toArray());
            if (selectedCoords) { // if initial filter was set, draw circle
                 updateRadiusCircle(selectedCoords, currentRadiusKm);
            } else { // otherwise, use map center for initial circle display
                 updateRadiusCircle(map.getCenter().toArray(), currentRadiusKm);
                 selectedCoords = map.getCenter().toArray(); // Set selectedCoords to map center if not initially filtered
            }

            geocoder = new MapboxGeocoder({
                accessToken: mapboxgl.accessToken,
                mapboxgl: mapboxgl,
                marker: false, 
                placeholder: 'Search for a location in Cyprus...',
                countries: 'CY',
                language: 'en',
                mapboxgl: mapboxgl, // Pass mapboxgl again, as per some examples
            });

            geocoderContainer.empty().append(geocoder.onAdd(map)); // Ensure container is empty before appending

            geocoder.on('result', function(ev) {
                const resultCoords = ev.result.center; 
                selectedCoords = resultCoords;
                selectedLocationName = ev.result.place_name;
                updateMarkerPosition(resultCoords);
                updateRadiusCircle(resultCoords, currentRadiusKm);
                // No map.flyTo here, marker will be at center due to move events
            });

            geocoder.on('clear', function() {
                // Option: Reset to Cyprus default or keep last selected?
                // For now, let's keep the marker at the last valid selectedCoords or map center
                // but clear the selectedLocationName if we want to indicate "All of Cyprus"
                // For a full reset:
                // selectedCoords = mapConfig.cyprusCenter;
                // selectedLocationName = 'All of Cyprus';
                // map.flyTo({ center: selectedCoords, zoom: mapConfig.defaultZoom });
                // updateMarkerPosition(selectedCoords);
                // updateRadiusCircle(selectedCoords, currentRadiusKm);
                console.log('Geocoder cleared');
            });
        });

        map.on('move', () => {
            if (marker) {
                marker.setLngLat(map.getCenter());
            }
        });

        map.on('moveend', () => {
            if (moveTimeout) {
                clearTimeout(moveTimeout);
            }
            moveTimeout = setTimeout(() => {
                const center = map.getCenter().toArray();
                selectedCoords = center;
                if (marker) { // Ensure marker is updated to final resting place
                    marker.setLngLat(center);
                }
                updateRadiusCircle(center, currentRadiusKm);
                // Perform reverse geocode to update location name for the center
                reverseGeocode(center, (name) => {
                    selectedLocationName = name || 'Area around selected point';
                    // Update geocoder input if desired, but be careful not to trigger 'result' event
                    // const geocoderInput = geocoderContainer.find('.mapboxgl-ctrl-geocoder--input');
                    // if (geocoderInput.length > 0 && document.activeElement !== geocoderInput[0]) {
                    //    geocoderInput.val(selectedLocationName); 
                    // }
                });
            }, 250); // Debounce for 250ms
        });

        // Clicking on map directly sets the point (alternative to drag)
        map.on('click', (e) => {
            const clickedCoords = [e.lngLat.lng, e.lngLat.lat];
            selectedCoords = clickedCoords;
            map.flyTo({ center: clickedCoords }); // This will trigger move and moveend
        });
    }

    function updateMarkerPosition(lngLatArray) {
        if (!map) return;
        if (!marker) {
            marker = new mapboxgl.Marker({ draggable: false, color: '#FF0000' }) // Draggable false, as it follows map center
                .setLngLat(lngLatArray)
                .addTo(map);
        } else {
            marker.setLngLat(lngLatArray);
        }
    }

    function updateRadiusCircle(centerLngLatArray, radiusKm) {
        if (!map || !turf || !centerLngLatArray) return;

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
        if (!mapConfig.accessToken || !coordsLngLatArray) return;
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
             // Ensure selectedLocationName is up-to-date from the last reverse geocode or search result
            initialFilter = {
                lat: selectedCoords[1],
                lng: selectedCoords[0],
                radius: currentRadiusKm,
                text: `Within ${currentRadiusKm}km of ${selectedLocationName}` // Use the updated name
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