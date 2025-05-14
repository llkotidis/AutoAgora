// Initialize Mapbox
mapboxgl.accessToken = mapboxData.token;

// Function to initialize map
function initMap(containerId, options = {}) {
    const defaultOptions = {
        center: [mapboxData.defaultCenter.lng, mapboxData.defaultCenter.lat],
        zoom: mapboxData.defaultZoom,
        style: 'mapbox://styles/mapbox/streets-v12'
    };

    const map = new mapboxgl.Map({
        container: containerId,
        ...defaultOptions,
        ...options
    });

    // Add navigation controls
    map.addControl(new mapboxgl.NavigationControl());

    return map;
}

// Function to add a marker to the map
function addMarker(map, coordinates, options = {}) {
    const defaultOptions = {
        color: '#FF0000',
        draggable: false
    };

    const marker = new mapboxgl.Marker({
        color: options.color || defaultOptions.color,
        draggable: options.draggable || defaultOptions.draggable
    })
        .setLngLat(coordinates)
        .addTo(map);

    if (options.draggable) {
        marker.on('dragend', () => {
            const lngLat = marker.getLngLat();
            // Update hidden input fields with new coordinates
            document.getElementById('car_latitude').value = lngLat.lat;
            document.getElementById('car_longitude').value = lngLat.lng;
        });
    }

    // Add popup if popup options are provided
    if (options.popup) {
        const popupContent = document.createElement('div');
        popupContent.className = 'map-popup';
        
        // Create popup HTML
        let popupHTML = `
            <div class="popup-title">${options.popup.title}</div>
            <div class="popup-price">${options.popup.price}</div>
        `;
        
        // Add link if URL is provided
        if (options.popup.url) {
            popupHTML = `<a href="${options.popup.url}" class="popup-link">${popupHTML}</a>`;
        }
        
        popupContent.innerHTML = popupHTML;
        
        // Create and add popup
        const popup = new mapboxgl.Popup({ offset: 25 })
            .setDOMContent(popupContent);
        
        marker.setPopup(popup);
    }

    return marker;
}

// Function to handle city/district selection
function handleCityChange(citySelect, districtSelect) {
    const city = citySelect.value;
    
    if (!city) {
        districtSelect.innerHTML = '<option value="">Select District</option>';
        return;
    }

    // Fetch districts for selected city
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'get_districts',
            city: city
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const districts = data.data;
            districtSelect.innerHTML = '<option value="">Select District</option>';
            
            districts.forEach(district => {
                const option = document.createElement('option');
                option.value = district.value;
                option.textContent = district.label;
                districtSelect.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching districts:', error));
}

// Initialize map on add listing page
document.addEventListener('DOMContentLoaded', () => {
    const mapContainer = document.getElementById('car-map');
    if (mapContainer) {
        const map = initMap('car-map');
        
        // Add marker if coordinates exist
        const lat = document.getElementById('car_latitude').value;
        const lng = document.getElementById('car_longitude').value;
        
        if (lat && lng) {
            addMarker(map, [lng, lat], { draggable: true });
        }

        // Add click handler to add/update marker
        map.on('click', (e) => {
            const marker = addMarker(map, e.lngLat, { draggable: true });
            document.getElementById('car_latitude').value = e.lngLat.lat;
            document.getElementById('car_longitude').value = e.lngLat.lng;
        });
    }

    // Initialize city/district selection
    const citySelect = document.getElementById('car_city');
    const districtSelect = document.getElementById('car_district');
    
    if (citySelect && districtSelect) {
        citySelect.addEventListener('change', () => handleCityChange(citySelect, districtSelect));
    }
}); 