/**
 * Mapbox initialization and utility functions
 */

// Initialize map when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map if map container exists
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        initializeMap(mapContainer);
    }
});

/**
 * Initialize Mapbox map
 * @param {HTMLElement} container - The map container element
 */
function initializeMap(container) {
    // Create map instance
    const map = new mapboxgl.Map({
        container: container,
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [mapboxData.defaultCenter.lng, mapboxData.defaultCenter.lat],
        zoom: mapboxData.defaultZoom
    });

    // Add navigation controls
    map.addControl(new mapboxgl.NavigationControl(), 'top-right');

    // Add geolocate control
    map.addControl(
        new mapboxgl.GeolocateControl({
            positionOptions: {
                enableHighAccuracy: true
            },
            trackUserLocation: true
        }),
        'top-right'
    );

    // Add marker if coordinates are provided
    const markerElement = document.createElement('div');
    markerElement.className = 'map-marker';
    markerElement.style.backgroundImage = `url(${mapboxData.markerIcon})`;
    markerElement.style.width = '32px';
    markerElement.style.height = '32px';
    markerElement.style.backgroundSize = 'contain';
    markerElement.style.backgroundRepeat = 'no-repeat';

    // Add click event to map
    map.on('click', function(e) {
        // Remove existing marker
        const existingMarker = document.querySelector('.map-marker');
        if (existingMarker) {
            existingMarker.remove();
        }

        // Add new marker
        new mapboxgl.Marker(markerElement)
            .setLngLat(e.lngLat)
            .addTo(map);

        // Update hidden input fields if they exist
        const latInput = document.getElementById('location_lat');
        const lngInput = document.getElementById('location_lng');
        if (latInput && lngInput) {
            latInput.value = e.lngLat.lat;
            lngInput.value = e.lngLat.lng;
        }

        // Reverse geocode to get address
        reverseGeocode(e.lngLat.lng, e.lngLat.lat);
    });
}

/**
 * Reverse geocode coordinates to get address
 * @param {number} lng - Longitude
 * @param {number} lat - Latitude
 */
function reverseGeocode(lng, lat) {
    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${mapboxgl.accessToken}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.features && data.features.length > 0) {
                const address = data.features[0].place_name;
                const addressInput = document.getElementById('location_address');
                if (addressInput) {
                    addressInput.value = address;
                }

                // Extract city and district from context
                const context = data.features[0].context || [];
                let city = '';
                let district = '';

                context.forEach(item => {
                    if (item.id.startsWith('place')) {
                        city = item.text;
                    } else if (item.id.startsWith('neighborhood')) {
                        district = item.text;
                    }
                });

                // Update city and district inputs if they exist
                const cityInput = document.getElementById('location_city');
                const districtInput = document.getElementById('location_district');
                
                if (cityInput) {
                    cityInput.value = city;
                }
                if (districtInput) {
                    districtInput.value = district;
                }
            }
        })
        .catch(error => console.error('Error reverse geocoding:', error));
}

/**
 * Add markers for multiple locations
 * @param {Object} map - Mapbox map instance
 * @param {Array} locations - Array of location objects with lat, lng, and title
 */
function addLocationMarkers(map, locations) {
    locations.forEach(location => {
        const markerElement = document.createElement('div');
        markerElement.className = 'map-marker';
        markerElement.style.backgroundImage = `url(${mapboxData.markerIcon})`;
        markerElement.style.width = '32px';
        markerElement.style.height = '32px';
        markerElement.style.backgroundSize = 'contain';
        markerElement.style.backgroundRepeat = 'no-repeat';

        const popup = new mapboxgl.Popup({ offset: 25 })
            .setHTML(`<h3>${location.title}</h3>`);

        new mapboxgl.Marker(markerElement)
            .setLngLat([location.lng, location.lat])
            .setPopup(popup)
            .addTo(map);
    });
} 