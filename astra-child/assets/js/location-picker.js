document.addEventListener('DOMContentLoaded', function() {
    let map = null;
    let marker = null;
    let cities = null;
    let selectedCity = null;
    let selectedDistrict = null;
    let selectedCoordinates = null;
    let isDataLoaded = false;

    // Load cities data using the localized URL from WordPress
    const citiesJsonPath = locationPickerData.citiesJsonUrl;
    console.log('Attempting to load cities from:', citiesJsonPath);

    // Function to show location picker
    function showLocationPicker() {
        if (!isDataLoaded) {
            alert('Location data is still loading. Please try again in a moment.');
            return;
        }

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
                        <div class="cities-list">
                            <h3>Cities</h3>
                            <div class="list-container"></div>
                        </div>
                        <div class="districts-list">
                            <h3>Districts</h3>
                            <div class="list-container"></div>
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

        // Populate cities list
        const citiesList = modal.querySelector('.cities-list .list-container');
        Object.keys(cities).forEach(cityName => {
            const cityItem = document.createElement('div');
            cityItem.className = 'city-item';
            cityItem.textContent = cityName;
            cityItem.addEventListener('click', () => handleCitySelection(cityName, modal));
            citiesList.appendChild(cityItem);
        });

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

    // Load cities data
    fetch(citiesJsonPath)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Cities data loaded successfully:', data);
            cities = data;
            isDataLoaded = true;
        })
        .catch(error => {
            console.error('Error loading cities data:', error);
            console.error('Full error details:', {
                message: error.message,
                stack: error.stack,
                path: citiesJsonPath
            });
            alert('Error loading location data. Please try again later.');
        });

    // Add click handler to the button
    const chooseLocationBtn = document.querySelector('.choose-location-btn');
    if (chooseLocationBtn) {
        chooseLocationBtn.addEventListener('click', showLocationPicker);
    }

    function handleCitySelect(city, cityElement) {
        selectedCity = city;
        selectedDistrict = null;
        selectedCoordinates = null;

        // Update selected city UI
        document.querySelectorAll('.city-item').forEach(item => {
            item.classList.remove('selected');
        });
        cityElement.classList.add('selected');

        // Populate districts list
        const districtsList = document.querySelector('.districts-list');
        districtsList.innerHTML = '';
        cities[city].districts.forEach(district => {
            const districtItem = document.createElement('div');
            districtItem.className = 'district-item';
            districtItem.textContent = district;
            districtItem.addEventListener('click', () => handleDistrictSelect(city, district, districtItem));
            districtsList.appendChild(districtItem);
        });

        // Hide map initially
        const mapContainer = document.querySelector('.location-map');
        mapContainer.classList.remove('visible');
        mapContainer.innerHTML = '';

        // Disable continue button until district is selected
        document.querySelector('.choose-location-btn').disabled = true;
    }

    function handleDistrictSelect(city, district, districtElement) {
        selectedDistrict = district;
        selectedCoordinates = cities[city].center;

        // Update selected district UI
        document.querySelectorAll('.district-item').forEach(item => {
            item.classList.remove('selected');
        });
        districtElement.classList.add('selected');

        // Show and initialize map
        const mapContainer = document.querySelector('.location-map');
        mapContainer.classList.add('visible');
        
        if (!map) {
            map = new mapboxgl.Map({
                container: mapContainer,
                style: mapboxConfig.style,
                center: selectedCoordinates,
                zoom: mapboxConfig.defaultZoom
            });

            map.addControl(new mapboxgl.NavigationControl());

            map.on('load', () => {
                if (marker) {
                    marker.remove();
                }
                marker = new mapboxgl.Marker()
                    .setLngLat(selectedCoordinates)
                    .addTo(map);
            });
        } else {
            map.flyTo({
                center: selectedCoordinates,
                zoom: mapboxConfig.defaultZoom
            });
            if (marker) {
                marker.remove();
            }
            marker = new mapboxgl.Marker()
                .setLngLat(selectedCoordinates)
                .addTo(map);
        }

        // Enable continue button
        document.querySelector('.choose-location-btn').disabled = false;
    }

    function handleContinue(modal) {
        if (selectedCity && selectedDistrict && selectedCoordinates) {
            const locationInput = document.getElementById('location');
            locationInput.value = `${selectedDistrict}, ${selectedCity}`;
            
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
            
            latInput.value = selectedCoordinates[0];
            lngInput.value = selectedCoordinates[1];
            
            if (map) {
                map.remove();
                map = null;
            }
            document.body.removeChild(modal);
        }
    }
}); 