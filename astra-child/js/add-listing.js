// Handle form submission
jQuery(document).ready(function($) {
    console.log('[Add Listing] jQuery ready');
    
    // Define fileInput at the top level of the ready function
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreview = $('#image-preview');
    
    // Store the makes data - this will be populated by PHP in the template
    const makesData = window.makesData || {};
    
    // Add a counter for images
    let imageCounter = 0;
    
    // Function to update and display count
    function updateImageCount() {
        console.log('📸 Current Image Count:', imageCounter);
        // You can also add a visual counter on the page if needed
        $('#image-count-display').text(`Images: ${imageCounter}/25`);
    }
    
    // Handle form submission
    $('#add-car-listing-form').on('submit', function(e) {
        e.preventDefault(); // Always prevent default submission first
        
        console.log('=== FORM SUBMISSION VALIDATION ===');
        updateImageCount();
        console.log('Checking if count is between 5 and 25...');
        
        // Get the raw values from data attributes
        const rawMileage = $('#mileage').data('raw-value') || unformatNumber($('#mileage').val());
        const rawPrice = $('#price').data('raw-value') || unformatNumber($('#price').val());
        const rawHp = $('#hp').data('raw-value') || unformatNumber($('#hp').val());
        
        // Simple validation using our counter
        if (imageCounter < 5) {
            console.log('❌ Validation failed: Image count (' + imageCounter + ') is less than 5');
            alert('Please upload at least 5 images for your car listing.');
            return false;
        }

        if (imageCounter > 25) {
            console.log('❌ Validation failed: Image count (' + imageCounter + ') is more than 25');
            alert('You can upload a maximum of 25 images for your car listing.');
            return false;
        }

        console.log('✅ Validation passed: Image count (' + imageCounter + ') is between 5 and 25');

        // If validation passes, create hidden inputs and submit
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

        // Submit the form
        this.submit();
    });

    // Handle click on upload area
    fileUploadArea.on('click', function(e) {
        console.log('[Add Listing] Upload area clicked');
        fileInput.trigger('click');
    });
    
    // Handle when files are selected through the file dialog
    fileInput.on('change', function(e) {
        console.log('=== FILES SELECTED ===');
        console.log('Files selected through file dialog:', this.files.length);
        if (this.files.length > 0) {
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
        console.log('=== FILES DROPPED ===');
        console.log('Files dropped:', e.originalEvent.dataTransfer.files.length);
        handleFiles(e.originalEvent.dataTransfer.files, false);
    });

    // Process the files - common function for both methods
    function handleFiles(files, isFileDialog) {
        console.log('=== ADDING FILES ===');
        updateImageCount();
        console.log('Attempting to add', files.length, 'files');
        
        const maxFiles = 25;
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Check if adding these files would exceed the maximum
        if (imageCounter + files.length > maxFiles) {
            console.log('❌ Cannot add files: Would exceed maximum of', maxFiles, 'files');
            alert('Maximum ' + maxFiles + ' files allowed');
            return;
        }
        
        // Create a DataTransfer object to manage files
        const dataTransfer = new DataTransfer();
        
        // Add existing files first (only for drag and drop)
        if (!isFileDialog) {
            Array.from(fileInput[0].files).forEach(file => {
                dataTransfer.items.add(file);
            });
        }
        
        let successfullyAdded = 0;
        
        // Process each new file
        Array.from(files).forEach(file => {
            // Check if duplicate (only for drag and drop)
            if (!isFileDialog) {
                const isDuplicate = Array.from(fileInput[0].files).some(
                    existingFile => existingFile.name === file.name && existingFile.size === file.size
                );
                
                if (isDuplicate) {
                    console.log('⚠️ Skipping duplicate file:', file.name);
                    return; // Skip this file
                }
            }
            
            // Validate file type
            if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
                console.log('❌ Invalid file type:', file.name);
                alert('Only JPG, PNG, GIF, and WebP files are allowed');
                return; // Skip this file
            }
            
            // Validate file size
            if (file.size > maxFileSize) {
                console.log('❌ File too large:', file.name);
                alert('File size must be less than 5MB');
                return; // Skip this file
            }
            
            // Add valid file to our collection
            dataTransfer.items.add(file);
            
            // Increment our counter
            imageCounter++;
            successfullyAdded++;
            
            // Create preview for this file
            createPreviewForFile(file);
        });
        
        // Update the file input with all files
        fileInput[0].files = dataTransfer.files;
        console.log('✅ Successfully added', successfullyAdded, 'files');
        updateImageCount();
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
        console.log('=== REMOVING FILE ===');
        updateImageCount();
        console.log('Removing file:', fileName);
        
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
        
        // Decrement our counter
        imageCounter--;
        
        console.log('✅ File removed successfully');
        updateImageCount();
        
        // Check if we're below minimum after removal
        if (imageCounter < 5) {
            console.log('⚠️ Warning: Image count (' + imageCounter + ') is now below minimum of 5');
            alert('Please upload at least 5 images for your car listing.');
        }
    }

    // Format number with commas
    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Remove commas and convert to number
    function unformatNumber(formattedNumber) {
        return parseInt(formattedNumber.replace(/[^0-9]/g, '')) || 0;
    }

    // Add mileage formatting
    const mileageInput = $('#mileage');
    const priceInput = $('#price');
    
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
}); 