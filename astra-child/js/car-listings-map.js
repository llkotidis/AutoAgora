/**
 * Car Listings Map Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [33.0226, 34.7071], // Default to Cyprus center
        zoom: 9
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

    // View toggle functionality
    const viewToggleBtns = document.querySelectorAll('.view-toggle-btn');
    const mapContainer = document.getElementById('map');
    const listingsGrid = document.querySelector('.car-listings-grid');

    viewToggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            
            // Update active button
            viewToggleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Toggle view
            if (view === 'map') {
                mapContainer.style.display = 'block';
                listingsGrid.style.display = 'none';
                addMarkersToMap();
            } else {
                mapContainer.style.display = 'none';
                listingsGrid.style.display = 'grid';
            }
        });
    });

    // Add markers to map
    function addMarkersToMap() {
        // Clear existing markers
        const existingMarkers = document.querySelectorAll('.mapboxgl-marker');
        existingMarkers.forEach(marker => marker.remove());

        // Get all car listings
        const carListings = document.querySelectorAll('.car-listing-card');
        
        carListings.forEach(listing => {
            const lat = parseFloat(listing.dataset.lat);
            const lng = parseFloat(listing.dataset.lng);
            const title = listing.dataset.title;
            const price = listing.dataset.price;
            const url = listing.dataset.url;

            if (!isNaN(lat) && !isNaN(lng)) {
                // Create marker element
                const markerElement = document.createElement('div');
                markerElement.className = 'map-marker';
                markerElement.style.backgroundImage = `url(${mapboxData.markerIcon})`;
                markerElement.style.width = '32px';
                markerElement.style.height = '32px';
                markerElement.style.backgroundSize = 'contain';
                markerElement.style.backgroundRepeat = 'no-repeat';

                // Create popup content
                const popupContent = `
                    <div class="map-popup">
                        <h3>${title}</h3>
                        <p class="price">€${Number(price).toLocaleString()}</p>
                        <a href="${url}" class="view-listing">View Listing</a>
                    </div>
                `;

                // Create popup
                const popup = new mapboxgl.Popup({ offset: 25 })
                    .setHTML(popupContent);

                // Add marker to map
                new mapboxgl.Marker(markerElement)
                    .setLngLat([lng, lat])
                    .setPopup(popup)
                    .addTo(map);
            }
        });

        // Fit map bounds to show all markers
        const bounds = new mapboxgl.LngLatBounds();
        carListings.forEach(listing => {
            const lat = parseFloat(listing.dataset.lat);
            const lng = parseFloat(listing.dataset.lng);
            if (!isNaN(lat) && !isNaN(lng)) {
                bounds.extend([lng, lat]);
            }
        });

        if (!bounds.isEmpty()) {
            map.fitBounds(bounds, {
                padding: 50,
                maxZoom: 15
            });
        }
    }

    // Add click event to map markers
    map.on('click', 'map-marker', function(e) {
        const features = map.queryRenderedFeatures(e.point, {
            layers: ['map-marker']
        });

        if (features.length > 0) {
            const feature = features[0];
            const popup = new mapboxgl.Popup()
                .setLngLat(feature.geometry.coordinates)
                .setHTML(feature.properties.popupContent)
                .addTo(map);
        }
    });

    // Change cursor on hover
    map.on('mouseenter', 'map-marker', function() {
        map.getCanvas().style.cursor = 'pointer';
    });

    map.on('mouseleave', 'map-marker', function() {
        map.getCanvas().style.cursor = '';
    });
}); 