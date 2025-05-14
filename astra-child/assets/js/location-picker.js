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
            // Initialize map
            map = new mapboxgl.Map({
                container: mapContainer,
                style: mapboxConfig.style,
                center: mapboxConfig.center,
                zoom: mapboxConfig.defaultZoom,
                accessToken: mapboxConfig.accessToken
            });

            // Add navigation controls
            map.addControl(new mapboxgl.NavigationControl());

            // Wait for map to load before adding geocoder
            map.on('load', () => {
                console.log('Map loaded, initializing geocoder...');
                
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
                    console.log('Geocoder result:', event.result);
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
                    const continueBtn = modal.querySelector('.choose-location-btn');
                    continueBtn.disabled = false;
                    console.log('Continue button enabled');

                    // Handle marker drag end
                    marker.on('dragend', () => {
                        const lngLat = marker.getLngLat();
                        selectedCoordinates = [lngLat.lng, lngLat.lat];
                        console.log('Marker dragged to:', selectedCoordinates);
                        
                        // Reverse geocode the new position
                        reverseGeocode(lngLat);
                    });
                });

                // Handle geocoder clear
                geocoder.on('clear', () => {
                    console.log('Geocoder cleared');
                    if (marker) {
                        marker.remove();
                        marker = null;
                    }
                    selectedCoordinates = null;
                    const continueBtn = modal.querySelector('.choose-location-btn');
                    continueBtn.disabled = true;
                    console.log('Continue button disabled');
                });
            });

            // Handle map click
            map.on('click', (e) => {
                console.log('Map clicked at:', e.lngLat);
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
                const continueBtn = modal.querySelector('.choose-location-btn');
                continueBtn.disabled = false;
                console.log('Continue button enabled after map click');
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
        continueBtn.addEventListener('click', () => {
            console.log('Continue button clicked');
            console.log('Selected coordinates:', selectedCoordinates);
            handleContinue(modal);
        });
    }

    // Function to reverse geocode coordinates
    function reverseGeocode(lngLat) {
        console.log('Reverse geocoding:', lngLat);
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lngLat.lng},${lngLat.lat}.json?access_token=${mapboxConfig.accessToken}&types=place,neighborhood,address&country=cy`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                console.log('Reverse geocode result:', data);
                if (data.features && data.features.length > 0) {
                    // Update the geocoder input with the new address
                    const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
                    if (geocoderInput) {
                        geocoderInput.value = data.features[0].place_name;
                        console.log('Updated geocoder input with:', data.features[0].place_name);
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
        console.log('Handling continue...');
        if (selectedCoordinates) {
            console.log('Coordinates selected:', selectedCoordinates);
            const locationInput = document.getElementById('location');
            const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
            
            // Use the current geocoder input value
            const locationValue = geocoderInput ? geocoderInput.value : '';
            console.log('Setting location value:', locationValue);
            locationInput.value = locationValue;
            
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
            console.log('Stored coordinates:', { lat: latInput.value, lng: lngInput.value });
            
            if (map) {
                map.remove();
                map = null;
            }
            modal.remove();
        } else {
            console.log('No coordinates selected');
        }
    }
}); 