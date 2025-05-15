// Handle form submission
$('#add-car-listing-form').on('submit', function(e) {
    e.preventDefault(); // Always prevent default submission first
    
    // Get the raw values from data attributes
    const rawMileage = $('#mileage').data('raw-value') || unformatNumber($('#mileage').val());
    const rawPrice = $('#price').data('raw-value') || unformatNumber($('#price').val());
    const rawHp = $('#hp').data('raw-value') || unformatNumber($('#hp').val());
    
    // Validate image count
    const imageCount = fileInput[0].files.length;
    if (imageCount < 5) {
        alert('Please upload at least 5 images for your car listing.');
        return false;
    }

    if (imageCount > 25) {
        alert('You can upload a maximum of 25 images for your car listing.');
        return false;
    }

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

// Add image count validation to file handling
function handleFiles(files, isFileDialog) {
    console.log('[Add Listing] Processing', files.length, 'files, isFileDialog:', isFileDialog);
    
    const maxFiles = 25;
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
}

// Update remove file function to validate count
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
    
    // Check if we're below minimum after removal
    if (fileInput[0].files.length < 5) {
        alert('Please upload at least 5 images for your car listing.');
    }
} 