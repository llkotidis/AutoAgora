jQuery(document).ready(function($) {
    const mapConfig = carListingsMapFilterData.mapboxConfig;
    const ajaxurl = carListingsMapFilterData.ajaxurl;
    const nonce = carListingsMapFilterData.nonce;
    let initialFilter = carListingsMapFilterData.initialFilter;

    let map = null;
    let marker = null;
    let geocoder = null;
    let selectedCoords = null; // LngLat array
    let currentRadiusKm = initialFilter.radius || 10; // Default radius in km
    const circleSourceId = 'radius-circle-source';
    const circleLayerId = 'radius-circle-layer';

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
    currentLocationText.text(initialFilter.text || 'All of Cyprus');

    changeLocationBtn.on('click', function() {
        modal.show();
        if (!map) {
            initializeMap();
        } else {
            map.resize(); // Ensure map is correctly sized if previously hidden
            // If there was a previous selection, re-center and show marker/circle
            if (selectedCoords) {
                map.setCenter(selectedCoords);
                updateMarkerPosition(selectedCoords);
                updateRadiusCircle(selectedCoords, currentRadiusKm);
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
            mapContainer.html('<p style="color:red;text-align:center;margin-top:20px;">Map configuration error. Please contact support.</p>');
            return;
        }
        mapboxgl.accessToken = mapConfig.accessToken;
        map = new mapboxgl.Map({
            container: 'filter-map-container',
            style: mapConfig.style,
            center: selectedCoords || mapConfig.cyprusCenter, // Use selected or default
            zoom: mapConfig.defaultZoom,
            // Disable analytics as in location-picker.js
            transformRequest: (url, resourceType) => {
                if (url.includes('events.mapbox.com')) {
                    return { url: '' }; 
                }
                return { url };
            }
        });

        map.on('load', function() {
            map.addControl(new mapboxgl.NavigationControl());

            geocoder = new MapboxGeocoder({
                accessToken: mapboxgl.accessToken,
                mapboxgl: mapboxgl,
                marker: false, // We handle our own marker
                placeholder: 'Search for a location in Cyprus...',
                countries: 'CY', // Restrict to Cyprus
                language: 'en'
            });

            geocoderContainer.append(geocoder.onAdd(map));

            geocoder.on('result', function(e) {
                selectedCoords = e.result.center; // LngLat array
                updateMarkerPosition(selectedCoords);
                updateRadiusCircle(selectedCoords, currentRadiusKm);
                map.flyTo({ center: selectedCoords, zoom: 12 });
            });
            
            // If there's an initial location from a previous filter, set it up
            if (initialFilter.lat && initialFilter.lng) {
                selectedCoords = [initialFilter.lng, initialFilter.lat];
                map.setCenter(selectedCoords);
                updateMarkerPosition(selectedCoords);
                updateRadiusCircle(selectedCoords, currentRadiusKm);
            }
        });

        map.on('click', function(e) {
            selectedCoords = [e.lngLat.lng, e.lngLat.lat];
            updateMarkerPosition(selectedCoords);
            updateRadiusCircle(selectedCoords, currentRadiusKm);
        });
    }

    function updateMarkerPosition(lngLat) {
        if (!map) return;
        if (!marker) {
            marker = new mapboxgl.Marker({ draggable: true, color: '#FF0000' })
                .setLngLat(lngLat)
                .addTo(map);

            marker.on('dragend', () => {
                selectedCoords = marker.getLngLat().toArray();
                updateRadiusCircle(selectedCoords, currentRadiusKm);
            });
        } else {
            marker.setLngLat(lngLat);
        }
    }

    function updateRadiusCircle(centerLngLat, radiusKm) {
        if (!map || !turf) return;

        const center = turf.point(centerLngLat);
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

    radiusSlider.on('input', function() {
        currentRadiusKm = parseInt($(this).val());
        radiusValueDisplay.text(currentRadiusKm);
        if (selectedCoords) {
            updateRadiusCircle(selectedCoords, currentRadiusKm);
        }
    });

    applyBtn.on('click', function() {
        if (!selectedCoords) {
            // If no specific point is selected, maybe clear filter or use "All of Cyprus"
            initialFilter = { lat: null, lng: null, radius: null, text: 'All of Cyprus' };
            currentLocationText.text('All of Cyprus');
        } else {
            // For simplicity, we'll use the geocoder's last result or a generic text if not available
            // A reverse geocode call would be better here for a precise name.
            let locationName = `Around selected point`;
            const geocoderInput = geocoderContainer.find('.mapboxgl-ctrl-geocoder--input');
            if (geocoderInput.val()) {
                locationName = geocoderInput.val();
            }
            initialFilter = {
                lat: selectedCoords[1],
                lng: selectedCoords[0],
                radius: currentRadiusKm,
                text: `Within ${currentRadiusKm}km of ${locationName}`
            };
            currentLocationText.text(initialFilter.text);
        }
        modal.hide();
        fetchFilteredListings(1); // Fetch first page of results
    });

    function fetchFilteredListings(page = 1) {
        $('.car-listings-grid').html('<p>Loading listings...</p>'); // Show loading indicator
        $('.car-listings-pagination').empty();

        const ajaxData = {
            action: 'filter_listings_by_location',
            nonce: nonce,
            paged: page,
            filter_lat: initialFilter.lat,
            filter_lng: initialFilter.lng,
            filter_radius: initialFilter.radius,
            per_page: 12 // or get from shortcode atts if possible/needed
            // You would also add other active filters here from car-filter-form.php if integrating
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    $('.car-listings-grid').html(response.data.listings_html);
                    $('.car-listings-pagination').html(response.data.pagination_html);
                    // Re-attach event handlers for new favorite buttons if needed, or use event delegation
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

    // Handle pagination clicks (delegated event)
    $('body').on('click', '.car-listings-pagination a.page-numbers', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const urlParams = new URLSearchParams(href.split('?')[1]);
        const page = parseInt(urlParams.get('paged')) || 1;
        fetchFilteredListings(page);
    });

    // Initial load if filter is pre-set (e.g. from query params or session - not implemented here)
    // If initialFilter.lat, .lng are set, you might want to call fetchFilteredListings() on page load.
    // For now, it loads all listings initially as per original shortcode logic until a filter is applied.

}); 