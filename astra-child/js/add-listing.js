// Handle form submission
$('#add-car-listing-form').on('submit', function(e) {
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
    const imageCount = fileInput[0].files.length;
    if (imageCount < 5) {
        e.preventDefault();
        alert('Please upload at least 5 images for your car listing.');
        return false;
    }

    if (imageCount > 25) {
        e.preventDefault();
        alert('You can upload a maximum of 25 images for your car listing.');
        return false;
    }
}); 