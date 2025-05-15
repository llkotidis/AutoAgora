jQuery(document).ready(function($) {
    // Store the makes data
    const makesData = editListingData.makesData;
    
    // Set initial make value
    const selectedMake = editListingData.selectedMake;
    if (selectedMake) {
        $('#make').val(selectedMake);
    }
    
    // Set initial model and variant options based on the selected make
    if (selectedMake && makesData[selectedMake]) {
        const modelSelect = $('#model');
        Object.keys(makesData[selectedMake]).forEach(model => {
            const option = $('<option>', {
                value: model,
                text: model
            });
            if (model === editListingData.selectedModel) {
                option.prop('selected', true);
            }
            modelSelect.append(option);
        });
        
        // Set initial variant options
        const selectedModel = editListingData.selectedModel;
        if (selectedModel && makesData[selectedMake][selectedModel]) {
            const variantSelect = $('#variant');
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    const option = $('<option>', {
                        value: variant,
                        text: variant
                    });
                    if (variant === editListingData.selectedVariant) {
                        option.prop('selected', true);
                    }
                    variantSelect.append(option);
                }
            });
        }
    }
    
    // Handle make selection change
    $('#make').on('change', function() {
        const selectedMake = $(this).val();
        const modelSelect = $('#model');
        const variantSelect = $('#variant');
        
        // Clear existing options
        modelSelect.html('<option value="">Select Model</option>');
        variantSelect.html('<option value="">Select Variant</option>');
        
        if (selectedMake && makesData[selectedMake]) {
            // Add model options
            Object.keys(makesData[selectedMake]).forEach(model => {
                modelSelect.append($('<option>', {
                    value: model,
                    text: model
                }));
            });
        }
    });
    
    // Handle model selection change
    $('#model').on('change', function() {
        const selectedMake = $('#make').val();
        const selectedModel = $(this).val();
        const variantSelect = $('#variant');
        
        // Clear existing options
        variantSelect.html('<option value="">Select Variant</option>');
        
        if (selectedMake && selectedModel && makesData[selectedMake] && makesData[selectedMake][selectedModel]) {
            // Add variant options
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    variantSelect.append($('<option>', {
                        value: variant,
                        text: variant
                    }));
                }
            });
        }
    });

    // Handle image upload and preview
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreview = $('#image-preview');
    
    // Handle click on upload area
    fileUploadArea.on('click', function() {
        fileInput.click();
    });
    
    // Handle file selection
    fileInput.on('change', function() {
        handleFiles(this.files);
    });
    
    // Handle drag and drop
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function() {
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        handleFiles(e.originalEvent.dataTransfer.files);
    });
    
    // Handle remove image
    imagePreview.on('click', '.remove-image', function(e) {
        e.preventDefault();
        const imageId = $(this).data('image-id');
        if (imageId) {
            // Add hidden input to track removed images
            $('<input>').attr({
                type: 'hidden',
                name: 'removed_images[]',
                value: imageId
            }).appendTo('#edit-car-listing-form');
        }
        $(this).parent().remove();
        
        // Reset the file input to allow selecting the same files again
        fileInput.val('');
    });
    
    function handleFiles(files) {
        if (!files.length) return;
        
        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = $('<div>').addClass('image-preview-item');
                const img = $('<img>').attr('src', e.target.result);
                const removeBtn = $('<button>').addClass('remove-image').text('×');
                
                previewItem.append(img).append(removeBtn);
                imagePreview.append(previewItem);
            };
            reader.readAsDataURL(file);
        });
    }
    
    // Format numbers with commas
    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Remove commas from number
    function unformatNumber(number) {
        return number.toString().replace(/,/g, '');
    }
    
    // Format mileage input
    $('#mileage').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format price input
    $('#price').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format HP input
    $('#hp').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Handle form submission
    $('#edit-car-listing-form').on('submit', function(e) {
        // Get the raw values from data attributes
        const rawMileage = $('#mileage').data('raw-value') || unformatNumber($('#mileage').val());
        const rawPrice = $('#price').data('raw-value') || unformatNumber($('#price').val());
        const rawHp = $('#hp').data('raw-value') || unformatNumber($('#hp').val());
        
        // Create hidden inputs with the raw values
        $('<input>').attr({
            type: 'hidden',
            name: 'mileage',
            value: rawMileage
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'price',
            value: rawPrice
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'hp',
            value: rawHp
        }).appendTo(this);
        
        // Disable the original inputs
        $('#mileage, #price, #hp').prop('disabled', true);

        // Validate image count
        const existingImages = $('.image-preview-item').length;
        const newImages = fileInput[0].files.length;
        const totalImages = existingImages + newImages;

        if (totalImages < 5) {
            e.preventDefault();
            alert('Please upload at least 5 images for your car listing.');
            return false;
        }

        if (totalImages > 25) {
            e.preventDefault();
            alert('You can upload a maximum of 25 images for your car listing.');
            return false;
        }
    });

    // Initialize location picker
    $('.choose-location-btn').on('click', function() {
        // Open the location picker modal
        $('#location-picker-modal').show();
        
        // Initialize the map if not already initialized
        if (!window.locationMap) {
            initializeLocationMap();
        }
    });

    // Function to initialize the location map
    function initializeLocationMap() {
        // Create map instance
        window.locationMap = L.map('location-map').setView([51.505, -0.09], 13);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(window.locationMap);

        // Add marker
        window.locationMarker = L.marker([51.505, -0.09], {
            draggable: true
        }).addTo(window.locationMap);

        // Handle marker drag end
        window.locationMarker.on('dragend', function(e) {
            updateLocationFields(e.target.getLatLng());
        });

        // Handle map click
        window.locationMap.on('click', function(e) {
            window.locationMarker.setLatLng(e.latlng);
            updateLocationFields(e.latlng);
        });
    }

    // Function to update location fields
    function updateLocationFields(latlng) {
        // Reverse geocode the coordinates
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)
            .then(response => response.json())
            .then(data => {
                // Update the location fields
                $('#location').val(data.display_name);
                $('#car_latitude').val(latlng.lat);
                $('#car_longitude').val(latlng.lng);
                $('#car_address').val(data.display_name);
                
                // Extract city and district from address components
                const address = data.address;
                $('#car_city').val(address.city || address.town || address.village || '');
                $('#car_district').val(address.county || address.state || '');
            })
            .catch(error => console.error('Error:', error));
    }

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#location-picker-modal')) {
            $('#location-picker-modal').hide();
        }
    });
}); 