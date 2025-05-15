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
        e.preventDefault(); // Prevent default form submission
        
        // Validate image count
        const fileCount = fileInput[0].files.length;
        if (fileCount < 5) {
            alert('Please upload at least 5 images before submitting the form');
            return;
        }
        if (fileCount > 25) {
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
        
        // Submit the form
        this.submit();
    });
    
    console.log('[Add Listing] Elements found:', {
        fileInput: fileInput.length,
        fileUploadArea: fileUploadArea.length,
        imagePreview: imagePreview.length
    });
    
    // Handle click on upload area
    fileUploadArea.on('click', function(e) {
        console.log('[Add Listing] Upload area clicked');
        fileInput.trigger('click');
    });
    
    // Handle when files are selected through the file dialog
    fileInput.on('change', function(e) {
        console.log('[Add Listing] Files selected through file dialog:', this.files.length);
        if (this.files.length > 0) {
            // For file dialog selection, we want to replace the current files
            handleFiles(this.files, true);
        }
    });
    
    // Handle drag and drop
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        console.log('[Add Listing] Files dropped:', e.originalEvent.dataTransfer.files.length);
        // For drag and drop, we want to append to existing files
        handleFiles(e.originalEvent.dataTransfer.files, false);
    });
    
    // Process the files - common function for both methods
    function handleFiles(files, isFileDialog) {
        console.log('[Add Listing] Processing', files.length, 'files, isFileDialog:', isFileDialog);
        
        const maxFiles = 25;
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Get current files from input
        const currentFiles = Array.from(fileInput[0].files || []);
        console.log('[Add Listing] Current files:', currentFiles.length);
        
        // Check if too many files
        if (currentFiles.length + files.length > maxFiles) {
            alert('Maximum ' + maxFiles + ' files allowed');
            return;
        }
        
        // Create a DataTransfer object to manage files
        const dataTransfer = new DataTransfer();
        
        // Add existing files first
        currentFiles.forEach(file => {
            dataTransfer.items.add(file);
        });
        
        // Process each new file
        Array.from(files).forEach(file => {
            // Check if duplicate
            const isDuplicate = currentFiles.some(
                existingFile => existingFile.name === file.name && existingFile.size === file.size
            );
            
            if (isDuplicate) {
                console.log('[Add Listing] Skipping duplicate file:', file.name);
                return; // Skip this file
            }
            
            // Validate file type
            if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
                alert('Only JPG, PNG, GIF, and WebP files are allowed');
                return; // Skip this file
            }
            
            // Validate file size
            if (file.size > maxFileSize) {
                alert('File size must be less than 5MB');
                return; // Skip this file
            }
            
            // Add valid file to our collection
            dataTransfer.items.add(file);
            
            // Create preview for this file
            createPreviewForFile(file);
        });
        
        // Update the file input with all files
        fileInput[0].files = dataTransfer.files;
        console.log('[Add Listing] Updated file input, now has', fileInput[0].files.length, 'files');
    }
    
    // Create preview for a single file
    function createPreviewForFile(file) {
        console.log('[Add Listing] Creating preview for:', file.name);
        
        // Create a FileReader to read the image
        const reader = new FileReader();
        
        reader.onload = function(e) {
            console.log('[Add Listing] File read complete, creating preview');
            
            // Create preview container
            const previewItem = $('<div>').addClass('image-preview-item');
            
            // Create image element
            const img = $('<img>').attr({
                'src': e.target.result,
                'alt': file.name
            });
            
            // Create remove button
            const removeBtn = $('<div>').addClass('remove-image')
                .html('<i class="fas fa-times"></i>')
                .on('click', function() {
                    removeFile(file.name);
                    previewItem.remove();
                });
            
            // Add image and button to preview item
            previewItem.append(img).append(removeBtn);
            
            // Add to preview container
            imagePreview.append(previewItem);
            console.log('[Add Listing] Preview added for:', file.name);
        };
        
        reader.onerror = function() {
            console.error('[Add Listing] Error reading file:', file.name);
        };
        
        // Start reading the file
        reader.readAsDataURL(file);
    }
    
    // Remove a file by name
    function removeFile(fileName) {
        console.log('[Add Listing] Removing file:', fileName);
        
        const dataTransfer = new DataTransfer();
        const currentFiles = Array.from(fileInput[0].files);
        
        // Add all files except the one to remove
        currentFiles.forEach(file => {
            if (file.name !== fileName) {
                dataTransfer.items.add(file);
            }
        });
        
        // Update the file input
        fileInput[0].files = dataTransfer.files;
        console.log('[Add Listing] After removal, file input has', fileInput[0].files.length, 'files');
    }
}); 