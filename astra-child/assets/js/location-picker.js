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
        // Get stored location if it exists
        const storedLocation = JSON.parse(localStorage.getItem('lastSelectedLocation')) || null;
        
        // Reset global variables
        map = null;
        marker = null;
        selectedCoordinates = storedLocation ? [storedLocation.longitude, storedLocation.latitude] : null;
        geocoder = null;
        selectedLocation = storedLocation || {
            city: '',
            district: '',
            address: '',
            latitude: null,
            longitude: null
        };

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

        try {
            // Initialize map
            map = new mapboxgl.Map({
                container: mapContainer,
                style: mapboxConfig.style,
                center: selectedCoordinates || mapboxConfig.cyprusCenter,
                zoom: selectedCoordinates ? 13 : mapboxConfig.defaultZoom,
                accessToken: mapboxConfig.accessToken,
                trackUserLocation: false,
                attributionControl: true,
                preserveDrawingBuffer: true,
                antialias: true,
                fadeDuration: 0,
                collectResourceTiming: false,
                renderWorldCopies: true,
                maxZoom: 18,
                minZoom: 0,
                pitch: 0,
                bearing: 0,
                interactive: true,
                failIfMajorPerformanceCaveat: false,
                preserveDrawingBuffer: false,
                refreshExpiredTiles: true,
                scrollZoom: { around: 'center' },
                transformRequest: (url, resourceType) => {
                    // Disable analytics requests
                    if (url.includes('events.mapbox.com')) {
                        return { url: '' };
                    }
                    return { url };
                }
            });

            // Add navigation controls
            map.addControl(new mapboxgl.NavigationControl());
            map.addControl(new LocateMeControl(), 'bottom-right');

            // Initialize geocoder
            geocoder = new MapboxGeocoder({
                accessToken: mapboxConfig.accessToken,
                mapboxgl: mapboxgl,
                map: map,
                marker: false,
                placeholder: 'Search for a location in Cyprus...',
                countries: 'cy',
                language: 'en',
                types: 'place,neighborhood,address',
                enableEventLogging: false,
                localGeocoder: null,
                clearOnBlur: true,
                clearAndBlurOnEsc: true,
                trackProximity: false,
                minLength: 2,
                limit: 5,
                flyTo: {
                    animate: true,
                    duration: 2000,
                    zoom: 15
                }
            });

            // Add geocoder to the map
            const geocoderContainer = document.getElementById('geocoder');
            if (geocoderContainer) {
                geocoderContainer.appendChild(geocoder.onAdd(map));
                console.log('Geocoder added to container');
            } else {
                console.error('Geocoder container not found');
            }

            // Add marker if we have stored coordinates
            if (selectedCoordinates) {
                marker = new mapboxgl.Marker()
                    .setLngLat(selectedCoordinates)
                    .addTo(map);
                
                // Enable continue button since we have coordinates
                const continueBtn = modal.querySelector('.choose-location-btn');
                continueBtn.disabled = false;
            }

            map.on('load', () => {
                console.log('Map loaded, initializing geocoder...');
                
                // Handle geocoder results
                geocoder.on('result', (event) => {
                    console.log('Geocoder result:', event.result);
                    const result = event.result;
                    selectedCoordinates = result.center;
                    selectedLocation = parseLocationDetails(result);
                    
                    // Fly to the result, which will trigger move and moveend events
                    map.flyTo({ center: result.center, zoom: 15 });
                    
                    // Enable continue button since we have valid coordinates
                    const continueBtn = modal.querySelector('.choose-location-btn');
                    if (continueBtn) {
                        continueBtn.disabled = false;
                        console.log('Continue button enabled after search');
                    }
                });

                // Handle geocoder clear
                geocoder.on('clear', () => {
                    console.log('Geocoder cleared');
                    // Keep the current center as selected coordinates
                    const currentCenter = map.getCenter();
                    selectedCoordinates = [currentCenter.lng, currentCenter.lat];
                    
                    // Preserve the selectedLocation data, but update coordinates if needed
                    if (selectedLocation.latitude && selectedLocation.longitude) {
                        console.log('Preserving selected location data:', selectedLocation);
                    } else {
                        selectedLocation = {
                            city: '',
                            district: '',
                            address: '',
                            latitude: currentCenter.lat,
                            longitude: currentCenter.lng
                        };
                    }
                    // Don't disable the continue button since we still have valid coordinates
                    console.log('Geocoder cleared but keeping continue button enabled');
                });
            });

            // Handle map click
            map.on('click', (e) => {
                console.log('Map clicked at:', e.lngLat);
                const clickedCoords = [e.lngLat.lng, e.lngLat.lat];
                selectedCoordinates = clickedCoords;
                
                // Fly to the clicked position, which will trigger move and moveend events
                map.flyTo({ center: clickedCoords });
            });

            // Handle map movement
            let moveTimeout;
            map.on('move', () => {
                // Keep marker centered during movement
                if (marker) {
                    marker.setLngLat(map.getCenter());
                }
            });

            // Handle map movement end
            map.on('moveend', () => {
                // Clear any existing timeout
                if (moveTimeout) {
                    clearTimeout(moveTimeout);
                }

                // Update coordinates and reverse geocode after movement stops
                moveTimeout = setTimeout(() => {
                    const center = map.getCenter();
                    selectedCoordinates = [center.lng, center.lat];
                    
                    // Enable continue button since we have valid coordinates
                    const continueBtn = modal.querySelector('.choose-location-btn');
                    if (continueBtn) {
                        continueBtn.disabled = false;
                        console.log('Continue button enabled after map movement');
                    }
                    
                    // Reverse geocode the new center position
                    reverseGeocode(center);
                }, 150); // Wait 150ms after movement stops
            });

        } catch (error) {
            console.error('Error initializing map:', error);
        }

        // Close button functionality
        const closeBtn = modal.querySelector('.close-modal');
        closeBtn.addEventListener('click', () => {
            // Restore original location value
            if (locationField) {
                locationField.value = originalLocationValue;
            }
            cleanupMap();
            modal.remove();
        });

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                // Restore original location value
                if (locationField) {
                    locationField.value = originalLocationValue;
                }
                cleanupMap();
                modal.remove();
            }
        });

        // Handle continue button click
        const continueBtn = modal.querySelector('.choose-location-btn');
        continueBtn.addEventListener('click', () => {
            console.log('Continue button clicked');
            console.log('Selected location:', selectedLocation);
            // Store the selected location before handling continue
            localStorage.setItem('lastSelectedLocation', JSON.stringify(selectedLocation));
            handleContinue(modal);
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

    function handleContinue(modal) {
        console.log('Handling continue...');
        if (selectedLocation.latitude && selectedLocation.longitude) {
            console.log('Location selected:', selectedLocation);
            
            // Get the current search input value
            const geocoderInput = document.querySelector('.mapboxgl-ctrl-geocoder input');
            const searchValue = geocoderInput ? geocoderInput.value : selectedLocation.address;
            
            // Update the location field with the full address
            const locationField = document.getElementById('location');
            if (locationField) {
                // Use the search value if available, otherwise use the selected location address
                const finalAddress = searchValue || selectedLocation.address;
                locationField.value = finalAddress;
                console.log('Updated location field with:', finalAddress);
                
                // Add hidden fields for location components
                const form = locationField.closest('form');
                if (form) {
                    // Remove any existing hidden fields first
                    ['car_city', 'car_district', 'car_latitude', 'car_longitude', 'car_address'].forEach(field => {
                        const existingField = form.querySelector(`input[name="${field}"]`);
                        if (existingField) existingField.remove();
                    });
                    
                    // Add new hidden fields
                    const fields = {
                        'car_city': selectedLocation.city,
                        'car_district': selectedLocation.district || selectedLocation.city, // Fallback to city if no district
                        'car_latitude': selectedLocation.latitude,
                        'car_longitude': selectedLocation.longitude,
                        'car_address': finalAddress
                    };
                    
                    console.log('Adding hidden fields with values:', fields);
                    
                    Object.entries(fields).forEach(([name, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                        console.log(`Added hidden field ${name} with value:`, value);
                    });
                    
                    console.log('Added hidden fields for location components');
                }
            } else {
                console.warn('Location field not found');
            }
            
            cleanupMap();
            modal.remove();
        } else {
            console.log('No location selected');
        }
    }
}); 