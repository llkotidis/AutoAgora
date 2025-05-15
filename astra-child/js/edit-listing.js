jQuery(document).ready(function($) {
    // Store the makes data
    const makesData = editListingData.makesData;
    let accumulatedFilesList = []; // For newly added files
    
    // Define these early for use in initial count and event handlers
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreviewContainer = $('#image-preview');
    
    // Initial count of existing images on page load
    const initialExistingImageCount = imagePreviewContainer.find('.image-preview-item img[data-image-id]').length;
    console.log('[Edit Listing] Initial existing images on page load:', initialExistingImageCount);

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
    const imagePreviewContainer = $('#image-preview'); // Renamed for clarity
    
    // Handle click on upload area
    fileUploadArea.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    // Handle file selection through dialog
    fileInput.on('change', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const newlySelectedThroughDialog = Array.from(this.files);
        if (newlySelectedThroughDialog.length > 0) {
            processNewFiles(newlySelectedThroughDialog);
        }
        $(this).val(''); // Clear the input to allow re-selecting the same file
    });
    
    // Handle drag and drop
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        const droppedFiles = Array.from(e.originalEvent.dataTransfer.files);
        processNewFiles(droppedFiles);
    });
    
    // Handle removing EXISTING images (those loaded with the page)
    imagePreviewContainer.on('click', '.remove-image[data-image-id]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const imageId = $(this).data('image-id');
        if (imageId) {
            // Add hidden input to track removed images for the backend
            $('<input>').attr({
                type: 'hidden',
                name: 'removed_images[]',
                value: imageId
            }).appendTo('#edit-car-listing-form');
            console.log('[Edit Listing] Marked existing image for removal, ID:', imageId);
        }
        $(this).closest('.image-preview-item').remove(); // Use closest to ensure the correct item is removed
    });
    
    function processNewFiles(candidateFiles) {
        console.log('[Edit Listing] Processing', candidateFiles.length, 'new candidate files.');
        const maxTotalFiles = 25; // Max total images (existing + new)
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        let filesActuallyAddedInThisBatch = 0;

        // Get the count of images that are already part of the listing and not marked for removal
        const currentPersistedExistingImageCount = imagePreviewContainer.find('.image-preview-item:not(:has(input[name="removed_images[]"])) img[data-image-id]').length;
        // A more direct way if removed items are immediately detached from DOM:
        // const currentPersistedExistingImageCount = imagePreviewContainer.find('.image-preview-item img[data-image-id]').length;
        // Assuming previews of removed existing images are detached from DOM, the above simpler one is fine.
        // Sticking to the simpler one as per current remove logic for existing images.
        const currentExistingImageDOMCount = imagePreviewContainer.find('.image-preview-item img[data-image-id]').length;

        console.log('[Edit Listing] Current existing images in DOM:', currentExistingImageDOMCount);
        console.log('[Edit Listing] Currently accumulated new files:', accumulatedFilesList.length);

        candidateFiles.forEach(file => {
            // Check if adding THIS one new file would exceed the total limit
            if (currentExistingImageDOMCount + accumulatedFilesList.length >= maxTotalFiles) {
                alert('Maximum ' + maxTotalFiles + ' total images allowed. Cannot add "' + file.name + '".');
                return; // Skips this file, continues to the next in candidateFiles if any
            }

            const isDuplicateInNew = accumulatedFilesList.some(
                existingFile => existingFile.name === file.name && existingFile.size === file.size && existingFile.type === file.type
            );
            if (isDuplicateInNew) {
                console.log('[Edit Listing] Skipping duplicate new file (already in this edit session):', file.name);
                return;
            }
            if (!allowedTypes.includes(file.type)) {
                alert(`File type not allowed for ${file.name}. Only JPG, PNG, GIF, and WebP are permitted.`);
                return;
            }
            if (file.size > maxFileSize) {
                alert(`File ${file.name} is too large (max 5MB).`);
                return;
            }

            accumulatedFilesList.push(file);
            createAndDisplayPreviewForNewFile(file);
            filesActuallyAddedInThisBatch++;
        });

        if (filesActuallyAddedInThisBatch > 0) {
            updateActualFileInput();
        }
        console.log('[Edit Listing] Processed batch. Total new files added in session:', accumulatedFilesList.length);
    }

    function createAndDisplayPreviewForNewFile(file) {
        console.log('[Edit Listing] Creating preview for new file:', file.name);
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewItem = $('<div>').addClass('image-preview-item new-image'); // Add 'new-image' class for styling/selection if needed
            const img = $('<img>').attr({ 'src': e.target.result, 'alt': file.name });
            const removeBtn = $('<div>').addClass('remove-image remove-new-image') // Specific class for new image removal
                .html('<i class="fas fa-times"></i>')
                .on('click', function(event) {
                    event.stopPropagation(); // Prevent potential parent clicks
                    console.log('[Edit Listing] Remove button clicked for new file:', file.name);
                    removeNewFileFromSelection(file.name);
                    previewItem.remove();
                });
            previewItem.append(img).append(removeBtn);
            imagePreviewContainer.append(previewItem);
        };
        reader.onerror = function() {
            console.error('[Edit Listing] Error reading new file for preview:', file.name);
        };
        reader.readAsDataURL(file);
    }

    function removeNewFileFromSelection(fileNameToRemove) {
        console.log('[Edit Listing] Attempting to remove new file from selection:', fileNameToRemove);
        accumulatedFilesList = accumulatedFilesList.filter(
            file => file.name !== fileNameToRemove
        );
        updateActualFileInput();
        console.log('[Edit Listing] New file removed. Accumulated new files count:', accumulatedFilesList.length);
    }
    
    function updateActualFileInput() {
        const dataTransfer = new DataTransfer();
        accumulatedFilesList.forEach(file => {
            try {
                dataTransfer.items.add(file);
            } catch (error) {
                console.error('[Edit Listing] Error adding new file to DataTransfer:', file.name, error);
            }
        });
        try {
            fileInput[0].files = dataTransfer.files;
        } catch (error) {
            console.error('[Edit Listing] Error setting new files on input element:', error);
        }
        console.log('[Edit Listing] Actual file input updated with new files. Count:', fileInput[0].files.length);
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
        const existingImagesCount = imagePreviewContainer.find('.image-preview-item img[data-image-id]').length;
        const newImagesCount = accumulatedFilesList.length;
        const totalImages = existingImagesCount + newImagesCount;

        if (totalImages < 5) {
            e.preventDefault();
            alert('Please ensure there are at least 5 images for your car listing (including existing and newly added).');
            return false;
        }

        if (totalImages > 25) {
            e.preventDefault();
            alert('You can have a maximum of 25 images for your car listing (including existing and newly added).');
            return false;
        }
        // Ensure the file input is up-to-date with new files before submission
        updateActualFileInput();
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