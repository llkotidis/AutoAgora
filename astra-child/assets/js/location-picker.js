document.addEventListener('DOMContentLoaded', function() {
    let map = null;
    let marker = null;
    let selectedCoordinates = null;
    let geocoder = null;
    let selectedLocation = {
        city: '',
        district: '',
        address: '',
        latitude: null,
        longitude: null
    };

    // Debug: Check if mapboxConfig is available
    console.log('Mapbox Config:', mapboxConfig);

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

                // Add a loading/locating indicator to the button
                button.classList.add('mapboxgl-ctrl-geolocate-active');

                // Force fresh location by using watchPosition briefly then clearing it
                // This bypasses browser location caching more effectively
                let watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        // Immediately clear the watch to stop continuous tracking
                        navigator.geolocation.clearWatch(watchId);
                        
                        const newCoords = [
                            position.coords.longitude,
                            position.coords.latitude,
                        ];
                        selectedCoordinates = newCoords;

                        // Check accuracy and zoom level accordingly
                        const accuracy = position.coords.accuracy;
                        const timestamp = new Date(position.timestamp).toLocaleTimeString();
                        let zoomLevel = 18; // Very close zoom for high accuracy
                        
                        if (accuracy > 100) {
                            zoomLevel = 15; // Moderate zoom for lower accuracy
                        } else if (accuracy > 50) {
                            zoomLevel = 16; // Close zoom for medium accuracy
                        } else if (accuracy > 20) {
                            zoomLevel = 17; // Closer zoom for good accuracy
                        }

                        this._map.flyTo({
                            center: newCoords,
                            zoom: zoomLevel,
                        });
                        
                        // Log accuracy and timestamp for debugging
                        console.log(`Fresh location found at ${timestamp} with accuracy: ${accuracy.toFixed(1)} meters`);
                        
                        // The map's 'moveend' event will handle marker update and reverse geocode
                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    (error) => {
                        navigator.geolocation.clearWatch(watchId);
                        alert(`Error getting location: ${error.message}`);
                        console.error('Geolocation error:', error);
                        button.classList.remove('mapboxgl-ctrl-geolocate-active');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000, // Increased timeout for better GPS fix
                        maximumAge: 0, // Always get fresh location
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

    // Function to parse location details from Mapbox result
    function parseLocationDetails(result) {
        const context = result.context || [];
        const placeName = result.place_name || '';
        const address = result.text || '';
        
        // Initialize location object
        const location = {
            city: '',
            district: '',
            address: placeName,
            latitude: result.center[1],
            longitude: result.center[0]
        };

        // Parse context to get city and district
        context.forEach(item => {
            if (item.id.startsWith('place')) {
                location.city = item.text;
            } else if (item.id.startsWith('neighborhood') || item.id.startsWith('locality')) {
                location.district = item.text;
            }
        });

        // If no district found in context, try to extract from address
        if (!location.district && placeName) {
            const parts = placeName.split(',');
            if (parts.length > 1) {
                // Try to get district from the first part of the address
                const firstPart = parts[0].trim();
                // Only use it if it's not the same as the city
                if (firstPart !== location.city) {
                    location.district = firstPart;
                }
            }
        }

        // If still no district, use the city as district
        if (!location.district && location.city) {
            location.district = location.city;
        }

        console.log('Parsed location details:', location);
        console.log('District value:', location.district);
        return location;
    }

    // Function to update marker position
    function updateMarkerPosition(lngLat) {
        if (!marker) {
            // Create marker only if it doesn't exist
            marker = new mapboxgl.Marker({
                draggable: false,
                color: '#FF0000' // Make it more visible
            })
                .setLngLat(lngLat)
                .addTo(map);
        } else {
            // Just update position if marker exists
            marker.setLngLat(lngLat);
        }

        // Enable continue button
        const continueBtn = document.querySelector('.location-picker-modal .choose-location-btn');
        if (continueBtn) {
            continueBtn.disabled = false;
            console.log('Continue button enabled');
        } else {
            console.warn('Continue button not found');
        }
    }

    // Function to show location picker
    function showLocationPicker() {
        // Get the last chosen location from localStorage
        const lastLocation = localStorage.getItem('lastChosenLocation');
        let initialLocation = null;
        
        if (lastLocation) {
            try {
                initialLocation = JSON.parse(lastLocation);
            } catch (e) {
                console.error('Error parsing last location:', e);
            }
        }

        // Store the original location field value
        const locationField = document.getElementById('location');
        const originalLocationValue = locationField ? locationField.value : '';

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

        // Initialize map with last location if available
        map = new mapboxgl.Map({
            container: mapContainer,
            style: 'mapbox://styles/mapbox/streets-v11',
            center: initialLocation ? [initialLocation.longitude, initialLocation.latitude] : mapboxConfig.cyprusCenter,
            zoom: initialLocation ? 14 : mapboxConfig.initialZoom
        });

        // Add navigation controls
        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

        // Initialize geocoder
        geocoder = new MapboxGeocoder({
            accessToken: mapboxConfig.accessToken,
            mapboxgl: mapboxgl,
            map: map,
            placeholder: 'Search for a location...',
            countries: 'cy',
            language: 'en',
            types: 'place,neighborhood,address'
        });

        // Add geocoder to the map
        const geocoderContainer = modal.querySelector('#geocoder');
        geocoderContainer.appendChild(geocoder.onAdd(map));

        // Add marker if we have a last location
        if (initialLocation) {
            selectedCoordinates = [initialLocation.longitude, initialLocation.latitude];
            marker = new mapboxgl.Marker()
                .setLngLat(selectedCoordinates)
                .addTo(map);
            
            // Update selected location
            selectedLocation = {
                city: initialLocation.city || '',
                district: initialLocation.district || '',
                address: initialLocation.address || '',
                latitude: initialLocation.latitude,
                longitude: initialLocation.longitude
            };

            // Enable continue button
            const continueBtn = modal.querySelector('.choose-location-btn');
            continueBtn.disabled = false;
        }

        // Handle map clicks
        map.on('click', (e) => {
            const lngLat = e.lngLat;
            selectedCoordinates = [lngLat.lng, lngLat.lat];
            
            // Remove existing marker if any
            if (marker) {
                marker.remove();
            }
            
            // Add new marker
            marker = new mapboxgl.Marker()
                .setLngLat(selectedCoordinates)
                .addTo(map);
            
            // Reverse geocode the coordinates
            reverseGeocode(lngLat);
            
            // Enable continue button
            const continueBtn = modal.querySelector('.choose-location-btn');
            continueBtn.disabled = false;
        });

        // Handle geocoder result
        geocoder.on('result', (e) => {
            const result = e.result;
            selectedCoordinates = result.center;
            
            // Remove existing marker if any
            if (marker) {
                marker.remove();
            }
            
            // Add new marker
            marker = new mapboxgl.Marker()
                .setLngLat(selectedCoordinates)
                .addTo(map);
            
            // Update selected location
            selectedLocation = parseLocationDetails(result);
            
            // Enable continue button
            const continueBtn = modal.querySelector('.choose-location-btn');
            continueBtn.disabled = false;
        });

        // Handle modal close
        const closeBtn = modal.querySelector('.close-modal');
        closeBtn.addEventListener('click', () => {
            cleanupMap();
            modal.remove();
        });

        // Handle clicking outside modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cleanupMap();
                modal.remove();
            }
        });

        // Handle continue button
        const continueBtn = modal.querySelector('.choose-location-btn');
        continueBtn.addEventListener('click', () => {
            if (selectedLocation && selectedLocation.latitude && selectedLocation.longitude) {
                // Save the selected location to localStorage
                localStorage.setItem('lastChosenLocation', JSON.stringify(selectedLocation));
                
                // Update the location field
                if (locationField) {
                    locationField.value = selectedLocation.address;
                }
                
                // Update hidden fields
                const cityField = document.getElementById('car_city');
                const districtField = document.getElementById('car_district');
                const latitudeField = document.getElementById('car_latitude');
                const longitudeField = document.getElementById('car_longitude');
                const addressField = document.getElementById('car_address');
                
                if (cityField) cityField.value = selectedLocation.city;
                if (districtField) districtField.value = selectedLocation.district;
                if (latitudeField) latitudeField.value = selectedLocation.latitude;
                if (longitudeField) longitudeField.value = selectedLocation.longitude;
                if (addressField) addressField.value = selectedLocation.address;
            }
            
            cleanupMap();
            modal.remove();
        });
    }

    // Function to clean up map resources
    function cleanupMap() {
        if (marker) {
            marker.remove();
            marker = null;
        }
        if (map) {
            map.remove();
            map = null;
        }
        if (geocoder) {
            geocoder = null;
        }
        selectedCoordinates = null;
        selectedLocation = {
            city: '',
            district: '',
            address: '',
            latitude: null,
            longitude: null
        };
    }

    // Function to reverse geocode coordinates
    function reverseGeocode(lngLat) {
        console.log('Reverse geocoding:', lngLat);
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lngLat.lng},${lngLat.lat}.json?access_token=${mapboxConfig.accessToken}&types=place,neighborhood,address&country=cy&language=en`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                console.log('Reverse geocode result:', data);
                if (data.features && data.features.length > 0) {
                    const result = data.features[0];
                    selectedLocation = parseLocationDetails(result);
                    
                    // Update the geocoder input with the new address
                    const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
                    if (geocoderInput) {
                        geocoderInput.value = result.place_name;
                        console.log('Updated geocoder input with:', result.place_name);
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
}); 