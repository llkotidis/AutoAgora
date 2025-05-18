jQuery(document).ready(function($) {
    console.log('[Add Listing] jQuery ready');
    
    // Store the makes data from localized script
    const makesData = addListingData.makesData;
    console.log('[Add Listing] Makes data:', makesData);
    
    // Handle make selection change
    $('#make').on('change', function() {
        const selectedMake = $(this).val();
        console.log('[Add Listing] Selected make:', selectedMake);
        console.log('[Add Listing] Makes data for selected make:', makesData[selectedMake]);
        
        const modelSelect = $('#model');
        const variantSelect = $('#variant');
        
        // Clear existing options
        modelSelect.empty().append('<option value="">Select Model</option>');
        variantSelect.empty().append('<option value="">Select Variant</option>');
        
        if (selectedMake && makesData && makesData[selectedMake]) {
            // Add model options
            Object.keys(makesData[selectedMake]).forEach(model => {
                modelSelect.append(`<option value="${model}">${model}</option>`);
            });
        } else {
            console.error('[Add Listing] No data found for make:', selectedMake);
        }
    });
    
    // Handle model selection change
    $('#model').on('change', function() {
        const selectedMake = $('#make').val();
        const selectedModel = $(this).val();
        console.log('[Add Listing] Selected model:', selectedModel);
        console.log('[Add Listing] Makes data for selected model:', makesData[selectedMake]?.[selectedModel]);
        
        const variantSelect = $('#variant');
        
        // Clear existing options
        variantSelect.empty().append('<option value="">Select Variant</option>');
        
        if (selectedMake && selectedModel && makesData && makesData[selectedMake] && makesData[selectedMake][selectedModel]) {
            // Add variant options
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) { // Only add non-empty variants
                    variantSelect.append(`<option value="${variant}">${variant}</option>`);
                }
            });
        } else {
            console.error('[Add Listing] No data found for model:', selectedModel);
        }
    });
    
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreview = $('#image-preview');
    let accumulatedFilesList = []; // Source of truth for selected files
    
    // Add mileage formatting
    const mileageInput = $('#mileage');
    const priceInput = $('#price');
    
    // Format number with commas
    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Remove commas and convert to number
    function unformatNumber(formattedNumber) {
        return parseInt(formattedNumber.replace(/[^0-9]/g, '')) || 0;
    }
    
    // Format mileage with commas
    mileageInput.on('input', function(e) {
        let value = this.value.replace(/[km,]/g, '');
        value = value.replace(/[^\d]/g, '');
        if (value) {
            const formattedValue = formatNumber(value);
            this.value = formattedValue;
            $(this).data('raw-value', value);
        } else {
            this.value = '';
            $(this).data('raw-value', 0);
        }
    });

    // Handle price input event for real-time formatting
    priceInput.on('input', function(e) {
        // Get the raw value without commas and euro sign
        let value = this.value.replace(/[€,]/g, '');
        
        // Only allow numbers
        value = value.replace(/[^\d]/g, '');
        
        // Format with commas and euro sign
        if (value) {
            const formattedValue = '€' + formatNumber(value);
            
            // Update the display value
            this.value = formattedValue;
            
            // Store the raw value in a data attribute
            $(this).data('raw-value', unformatNumber(value));
        } else {
            this.value = '';
            $(this).data('raw-value', 0);
        }
    });
    
    // Format HP input with thousand separators
    $('#hp').on('input', function() {
        // Remove any non-digit characters
        let value = $(this).val().replace(/[^\d]/g, '');
        
        // Add thousand separators
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        
        // Update the input value
        $(this).val(value);
        // Store the raw value
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });

    // Handle form submission
    $('#add-car-listing-form').on('submit', function(e) {
        // Validate image count using accumulatedFilesList
        if (accumulatedFilesList.length < 5) {
            e.preventDefault(); // Prevent default form submission
            alert('Please upload at least 5 images before submitting the form');
            return;
        }
        if (accumulatedFilesList.length > 25) {
            e.preventDefault(); // Prevent default form submission
            alert('Maximum 25 images allowed');
            return;
        }
        
        // Get the raw values from data attributes
        const rawMileage = mileageInput.data('raw-value') || unformatNumber(mileageInput.val());
        const rawPrice = priceInput.data('raw-value') || unformatNumber(priceInput.val().replace('€', ''));
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
        
        // Remove the original inputs from submission
        mileageInput.prop('disabled', true);
        priceInput.prop('disabled', true);
        $('#hp').prop('disabled', true);
        
        // Ensure fileInput has the correct files from accumulatedFilesList
        updateActualFileInput(); 
        // Form will submit with fileInput populated by accumulatedFilesList
    });
    
    console.log('[Add Listing] Elements found:', {
        fileInput: fileInput.length,
        fileUploadArea: fileUploadArea.length,
        imagePreview: imagePreview.length
    });
    
    function updateImagePreviewClass() {
        if (accumulatedFilesList.length > 0) {
            imagePreview.addClass('has-images');
            console.log('[Add Listing] Added .has-images class to #image-preview');
        } else {
            imagePreview.removeClass('has-images');
            console.log('[Add Listing] Removed .has-images class from #image-preview');
        }
    }
    
    // Handle click on upload area
    fileUploadArea.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('[Add Listing] Upload area clicked');
        fileInput.trigger('click');
    });
    
    // Handle when files are selected through the file dialog
    fileInput.on('change', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const newlySelectedThroughDialog = Array.from(this.files);
        console.log('[Add Listing] Files selected through file dialog:', newlySelectedThroughDialog.length);
        if (newlySelectedThroughDialog.length > 0) {
            processNewFiles(newlySelectedThroughDialog);
        }
        // Clear the file input's displayed value to allow re-selecting the same file(s)
        // and ensure 'change' event fires consistently.
        $(this).val('');
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
        console.log('[Add Listing] Files dropped:', droppedFiles.length);
        processNewFiles(droppedFiles);
    });
    
    function processNewFiles(candidateFiles) {
        console.log('[Add Listing] Processing', candidateFiles.length, 'new candidate files.');
        const maxFiles = 25;
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        let filesAddedThisBatchCount = 0;

        candidateFiles.forEach(file => {
            // Check if adding this file would exceed the maximum, considering those already processed in this batch
            if (accumulatedFilesList.length + filesAddedThisBatchCount >= maxFiles) {
                alert('Maximum ' + maxFiles + ' files allowed. Some files were not added.');
                return false; // Effectively breaks forEach for this iteration's candidateFiles
            }

            const isDuplicate = accumulatedFilesList.some(
                existingFile => existingFile.name === file.name && existingFile.size === file.size && existingFile.type === file.type
            );

            if (isDuplicate) {
                console.log('[Add Listing] Skipping duplicate file:', file.name);
                return; // to next candidate in forEach
            }

            if (!allowedTypes.includes(file.type)) {
                alert(`File type not allowed for ${file.name}. Only JPG, PNG, GIF, and WebP are permitted.`);
                return; // to next candidate in forEach
            }

            if (file.size > maxFileSize) {
                alert(`File ${file.name} is too large (max 5MB).`);
                return; // to next candidate in forEach
            }

            // If all checks pass, add to accumulated list and create preview
            accumulatedFilesList.push(file);
            createAndDisplayPreview(file); // Create preview for the newly added file
            filesAddedThisBatchCount++;
        });

        if (filesAddedThisBatchCount > 0) {
            updateActualFileInput(); // Update the hidden file input with the new state of accumulatedFilesList
        }
        updateImagePreviewClass(); // Update the class for #image-preview
        console.log('[Add Listing] Processed batch. Accumulated files count:', accumulatedFilesList.length);
    }
    
    function createAndDisplayPreview(file) {
        console.log('[Add Listing] Creating preview for:', file.name);
        const reader = new FileReader();
        reader.onload = function(e) {
            console.log('[Add Listing] File read complete for preview, creating DOM element for:', file.name);
            const previewItem = $('<div>').addClass('image-preview-item');
            const img = $('<img>').attr({ 'src': e.target.result, 'alt': file.name });
            const removeBtn = $('<div>').addClass('remove-image')
                .html('<i class="fas fa-times"></i>')
                .on('click', function() {
                    console.log('[Add Listing] Remove button clicked for:', file.name);
                    removeFileFromSelection(file.name); // Pass file name to identify for removal
                    previewItem.remove(); // Remove the preview DOM element
                });
            previewItem.append(img).append(removeBtn);
            imagePreview.append(previewItem); // Append to the main preview container
            console.log('[Add Listing] Preview added to DOM for:', file.name);
        };
        reader.onerror = function() {
            console.error('[Add Listing] Error reading file for preview:', file.name);
        };
        reader.readAsDataURL(file);
    }

    function removeFileFromSelection(fileNameToRemove) {
        console.log('[Add Listing] Attempting to remove file from selection:', fileNameToRemove);
        accumulatedFilesList = accumulatedFilesList.filter(
            file => file.name !== fileNameToRemove
        );
        updateActualFileInput(); // Refresh the actual file input
        updateImagePreviewClass(); // Update the class for #image-preview
        console.log('[Add Listing] File removed. Accumulated files count:', accumulatedFilesList.length);
    }
    
    function updateActualFileInput() {
        const dataTransfer = new DataTransfer();
        accumulatedFilesList.forEach(file => {
            try {
                dataTransfer.items.add(file);
            } catch (error) {
                console.error('[Add Listing] Error adding file to DataTransfer:', file.name, error);
            }
        });
        try {
            fileInput[0].files = dataTransfer.files;
        } catch (error) {
            console.error('[Add Listing] Error setting files on input element:', error);
        }
        console.log('[Add Listing] Actual file input updated. Count:', fileInput[0].files.length);
    }

    // Initial check for image preview class
    updateImagePreviewClass();
}); 