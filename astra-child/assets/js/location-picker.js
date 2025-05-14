document.addEventListener('DOMContentLoaded', function() {
    let map = null;
    let marker = null;
    let selectedCoordinates = null;
    let geocoder = null;

    // Debug: Check if mapboxConfig is available
    console.log('Mapbox Config:', mapboxConfig);

    // Function to show location picker
    function showLocationPicker() {
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'location-picker-modal';
        modal.innerHTML = `
            <div class="location-picker-content">
                <div class="location-picker-header">
                    <h2>Choose Location</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="location-selection-container">
                        <div class="search-container">
                            <div id="geocoder" class="geocoder"></div>
                        </div>
                    </div>
                    <div class="location-map"></div>
                </div>
                <div class="location-picker-footer">
                    <button class="choose-location-btn" disabled>Continue</button>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.appendChild(modal);

        // Initialize map
        const mapContainer = modal.querySelector('.location-map');
        mapContainer.classList.add('visible');

        try {
            map = new mapboxgl.Map({
                container: mapContainer,
                style: mapboxConfig.style,
                center: [33.3823, 35.1856], // Default to Cyprus center
                zoom: 8,
                accessToken: mapboxConfig.accessToken
            });

            // Add navigation controls
            map.addControl(new mapboxgl.NavigationControl());

            // Initialize geocoder
            geocoder = new MapboxGeocoder({
                accessToken: mapboxConfig.accessToken,
                mapboxgl: mapboxgl,
                map: map,
                marker: false, // We'll handle the marker ourselves
                placeholder: 'Search for a location in Cyprus...',
                countries: 'cy', // Restrict to Cyprus
                types: 'place,neighborhood,address',
                language: 'en'
            });

            // Add geocoder to the map
            document.getElementById('geocoder').appendChild(geocoder.onAdd(map));

            // Handle geocoder results
            geocoder.on('result', (event) => {
                const result = event.result;
                selectedCoordinates = result.center;
                
                // Update marker
                if (marker) {
                    marker.remove();
                }
                marker = new mapboxgl.Marker({
                    draggable: true
                })
                    .setLngLat(selectedCoordinates)
                    .addTo(map);

                // Enable continue button
                document.querySelector('.choose-location-btn').disabled = false;

                // Handle marker drag end
                marker.on('dragend', () => {
                    const lngLat = marker.getLngLat();
                    selectedCoordinates = [lngLat.lng, lngLat.lat];
                    
                    // Reverse geocode the new position
                    reverseGeocode(lngLat);
                });
            });

            // Handle geocoder clear
            geocoder.on('clear', () => {
                if (marker) {
                    marker.remove();
                    marker = null;
                }
                selectedCoordinates = null;
                document.querySelector('.choose-location-btn').disabled = true;
            });

            // Handle map click
            map.on('click', (e) => {
                if (marker) {
                    marker.remove();
                }
                marker = new mapboxgl.Marker({
                    draggable: true
                })
                    .setLngLat(e.lngLat)
                    .addTo(map);

                selectedCoordinates = [e.lngLat.lng, e.lngLat.lat];
                
                // Reverse geocode the clicked position
                reverseGeocode(e.lngLat);
                
                // Enable continue button
                document.querySelector('.choose-location-btn').disabled = false;
            });

        } catch (error) {
            console.error('Error initializing map:', error);
        }

        // Close button functionality
        const closeBtn = modal.querySelector('.close-modal');
        closeBtn.addEventListener('click', () => {
            if (map) {
                map.remove();
                map = null;
            }
            modal.remove();
        });

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                if (map) {
                    map.remove();
                    map = null;
                }
                modal.remove();
            }
        });

        // Continue button functionality
        const continueBtn = modal.querySelector('.choose-location-btn');
        continueBtn.addEventListener('click', () => handleContinue(modal));
    }

    // Function to reverse geocode coordinates
    function reverseGeocode(lngLat) {
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lngLat.lng},${lngLat.lat}.json?access_token=${mapboxConfig.accessToken}&types=place,neighborhood,address&country=cy`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.features && data.features.length > 0) {
                    // Update the geocoder input with the new address
                    const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
                    if (geocoderInput) {
                        geocoderInput.value = data.features[0].place_name;
                    }
                }
            })
            .catch(error => {
                console.error('Error reverse geocoding:', error);
            });
    }

    // Add click handler to the button
    const chooseLocationBtn = document.querySelector('.choose-location-btn');
    if (chooseLocationBtn) {
        chooseLocationBtn.addEventListener('click', showLocationPicker);
    }

    function handleContinue(modal) {
        if (selectedCoordinates) {
            const locationInput = document.getElementById('location');
            const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
            
            // Use the current geocoder input value
            locationInput.value = geocoderInput ? geocoderInput.value : '';
            
            // Add hidden inputs for coordinates if they don't exist
            let latInput = document.getElementById('latitude');
            let lngInput = document.getElementById('longitude');
            
            if (!latInput) {
                latInput = document.createElement('input');
                latInput.type = 'hidden';
                latInput.id = 'latitude';
                latInput.name = 'latitude';
                locationInput.parentNode.appendChild(latInput);
            }
            
            if (!lngInput) {
                lngInput = document.createElement('input');
                lngInput.type = 'hidden';
                lngInput.id = 'longitude';
                lngInput.name = 'longitude';
                locationInput.parentNode.appendChild(lngInput);
            }
            
            // Store the coordinates
            latInput.value = selectedCoordinates[1]; // Latitude
            lngInput.value = selectedCoordinates[0]; // Longitude
            
            if (map) {
                map.remove();
                map = null;
            }
            modal.remove();
        }
    }
}); 