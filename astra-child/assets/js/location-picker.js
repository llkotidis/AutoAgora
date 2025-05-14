document.addEventListener('DOMContentLoaded', function() {
    let map = null;
    let marker = null;
    let cities = null;
    let selectedCity = null;
    let selectedDistrict = null;
    let selectedCoordinates = null;

    // Load cities data
    fetch('/wp-content/themes/astra-child/simple_jsons/cities.json')
        .then(response => response.json())
        .then(data => {
            cities = data;
        })
        .catch(error => {
            console.error('Error loading cities data:', error);
        });

    // Show location picker modal
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
                    <div class="location-selection-container">
                        <div class="cities-list"></div>
                        <div class="districts-list"></div>
                    </div>
                    <div class="location-map"></div>
                </div>
                <div class="location-picker-footer">
                    <button class="btn btn-primary continue-btn" disabled>Continue</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Populate cities list
        const citiesList = modal.querySelector('.cities-list');
        Object.keys(cities).forEach(city => {
            const cityItem = document.createElement('div');
            cityItem.className = 'city-item';
            cityItem.textContent = city;
            cityItem.addEventListener('click', () => handleCitySelect(city, cityItem));
            citiesList.appendChild(cityItem);
        });

        // Close modal handler
        modal.querySelector('.close-modal').addEventListener('click', () => {
            document.body.removeChild(modal);
        });

        // Continue button handler
        modal.querySelector('.continue-btn').addEventListener('click', () => {
            handleContinue(modal);
        });
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
        document.querySelector('.continue-btn').disabled = true;
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
        document.querySelector('.continue-btn').disabled = false;
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
            
            document.body.removeChild(modal);
        }
    }

    // Add click handler to the choose location button
    document.querySelector('.choose-location-btn').addEventListener('click', showLocationPicker);
}); 