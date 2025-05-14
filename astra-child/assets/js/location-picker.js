document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let map;
    let marker;
    let cities;
    let selectedCity = '';
    let selectedDistrict = '';
    let selectedCoordinates = null;

    // Load cities data
    fetch('/wp-content/themes/astra-child/simple_jsons/cities.json')
        .then(response => response.json())
        .then(data => {
            cities = data;
            populateCityDropdown();
        })
        .catch(error => console.error('Error loading cities:', error));

    // Create and show the modal
    function showLocationPicker() {
        const modal = document.createElement('div');
        modal.className = 'location-picker-modal';
        modal.innerHTML = `
            <div class="location-picker-content">
                <div class="location-picker-header">
                    <h2>Choose Location</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="location-selectors">
                        <select id="city-select" class="form-control">
                            <option value="">Select City</option>
                        </select>
                        <select id="district-select" class="form-control" disabled>
                            <option value="">Select District</option>
                        </select>
                    </div>
                    <div id="map" class="location-map"></div>
                </div>
                <div class="location-picker-footer">
                    <button id="continue-btn" class="btn btn-primary" disabled>Continue</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Initialize map after modal is added to DOM
        initializeMap();

        // Add event listeners
        document.getElementById('city-select').addEventListener('change', handleCityChange);
        document.getElementById('district-select').addEventListener('change', handleDistrictChange);
        document.getElementById('continue-btn').addEventListener('click', handleContinue);
        modal.querySelector('.close-modal').addEventListener('click', () => {
            modal.remove();
            if (map) {
                map.remove();
                map = null;
            }
        });
    }

    // Initialize Mapbox map
    function initializeMap() {
        mapboxgl.accessToken = mapboxConfig.accessToken;
        map = new mapboxgl.Map({
            container: 'map',
            style: mapboxConfig.styleUrl,
            center: mapboxConfig.defaultCenter,
            zoom: mapboxConfig.defaultZoom
        });

        map.addControl(new mapboxgl.NavigationControl());

        // Add click event to map
        map.on('click', function(e) {
            if (selectedCity && selectedDistrict) {
                updateMarker(e.lngLat);
            }
        });
    }

    // Populate city dropdown
    function populateCityDropdown() {
        const citySelect = document.getElementById('city-select');
        Object.keys(cities).forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
        });
    }

    // Handle city selection
    function handleCityChange(e) {
        const citySelect = e.target;
        const districtSelect = document.getElementById('district-select');
        
        // Clear district dropdown
        districtSelect.innerHTML = '<option value="">Select District</option>';
        
        if (citySelect.value) {
            selectedCity = citySelect.value;
            const districts = cities[selectedCity].districts;
            
            // Populate district dropdown
            districts.forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                districtSelect.appendChild(option);
            });
            
            districtSelect.disabled = false;
            
            // Center map on selected city
            const coordinates = cities[selectedCity].center;
            map.flyTo({
                center: coordinates,
                zoom: 12
            });
        } else {
            selectedCity = '';
            districtSelect.disabled = true;
        }
        
        updateContinueButton();
    }

    // Handle district selection
    function handleDistrictChange(e) {
        selectedDistrict = e.target.value;
        updateContinueButton();
    }

    // Update marker position
    function updateMarker(lngLat) {
        if (marker) {
            marker.setLngLat(lngLat);
        } else {
            marker = new mapboxgl.Marker()
                .setLngLat(lngLat)
                .addTo(map);
        }
        selectedCoordinates = lngLat;
        updateContinueButton();
    }

    // Update continue button state
    function updateContinueButton() {
        const continueBtn = document.getElementById('continue-btn');
        continueBtn.disabled = !(selectedCity && selectedDistrict && selectedCoordinates);
    }

    // Handle continue button click
    function handleContinue() {
        const locationInput = document.getElementById('location');
        locationInput.value = `${selectedDistrict}, ${selectedCity}`;
        
        // Add hidden inputs for coordinates
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
        
        latInput.value = selectedCoordinates.lat;
        lngInput.value = selectedCoordinates.lng;
        
        // Close modal
        document.querySelector('.location-picker-modal').remove();
        if (map) {
            map.remove();
            map = null;
        }
    }

    // Add click handler to location input
    const locationInput = document.getElementById('location');
    if (locationInput) {
        const chooseLocationBtn = document.createElement('button');
        chooseLocationBtn.type = 'button';
        chooseLocationBtn.className = 'btn btn-secondary choose-location-btn';
        chooseLocationBtn.textContent = 'Choose Location >';
        chooseLocationBtn.addEventListener('click', showLocationPicker);
        
        locationInput.parentNode.appendChild(chooseLocationBtn);
    }
}); 