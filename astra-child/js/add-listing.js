// Handle form submission
$('#add-car-listing-form').on('submit', function(e) {
    e.preventDefault(); // Always prevent default submission first
    
    // Validate image count first, before any other processing
    const imageCount = fileInput[0].files.length;
    if (imageCount < 5) {
        alert('Please upload at least 5 images for your car listing.');
        return false;
    }

    if (imageCount > 25) {
        alert('You can upload a maximum of 25 images for your car listing.');
        return false;
    }
    
    // Only proceed with form processing if validation passes
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

    // If we get here, validation passed - submit the form
    this.submit();
});

// Process the files - common function for both methods
function handleFiles(files, isFileDialog) {
    console.log('[Add Listing] Processing', files.length, 'files, isFileDialog:', isFileDialog);
    
    const maxFiles = 25;
    const minFiles = 5;
    const maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // Get current files from input
    const currentFiles = isFileDialog ? [] : Array.from(fileInput[0].files);
    console.log('[Add Listing] Current files:', currentFiles.length);
    
    // Check if too many files
    if (currentFiles.length + files.length > maxFiles) {
        alert('Maximum ' + maxFiles + ' files allowed');
        return;
    }
    
    // Create a DataTransfer object to manage files
    const dataTransfer = new DataTransfer();
    
    // Add existing files first (only for drag and drop)
    if (!isFileDialog) {
        currentFiles.forEach(file => {
            dataTransfer.items.add(file);
        });
    }
    
    // Process each new file
    Array.from(files).forEach(file => {
        // Check if duplicate (only for drag and drop)
        if (!isFileDialog) {
            const isDuplicate = currentFiles.some(
                existingFile => existingFile.name === file.name && existingFile.size === file.size
            );
            
            if (isDuplicate) {
                console.log('[Add Listing] Skipping duplicate file:', file.name);
                return; // Skip this file
            }
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
    
    // Update submit button state based on file count
    const totalFiles = fileInput[0].files.length;
    const submitButton = $('.submit-button');
    
    if (totalFiles < minFiles) {
        submitButton.prop('disabled', true).attr('title', 'Please upload at least 5 images');
    } else if (totalFiles > maxFiles) {
        submitButton.prop('disabled', true).attr('title', 'Maximum 25 images allowed');
    } else {
        submitButton.prop('disabled', false).attr('title', '');
    }
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
    
    // Update submit button state based on new file count
    const totalFiles = fileInput[0].files.length;
    const submitButton = $('.submit-button');
    
    if (totalFiles < 5) {
        submitButton.prop('disabled', true).attr('title', 'Please upload at least 5 images');
    } else if (totalFiles > 25) {
        submitButton.prop('disabled', true).attr('title', 'Maximum 25 images allowed');
    } else {
        submitButton.prop('disabled', false).attr('title', '');
    }
}

// Remove the separate submit button handler since we already handle it in the form submit 