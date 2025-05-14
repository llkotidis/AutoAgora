<?php
/**
 * Car Submission Functionality
 * 
 * Handles form submissions for car listings
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Process form submission for adding a new car listing
 */
function handle_add_car_listing() {
    // Initialize variables for error tracking
    $errors = array();
    $required_fields = array(
        'make' => 'Make',
        'model' => 'Model',
        'variant' => 'Variant',
        'year' => 'Year',
        'mileage' => 'Mileage',
        'price' => 'Price',
        'location' => 'Location',
        'engine_capacity' => 'Engine Capacity',
        'fuel_type' => 'Fuel Type',
        'transmission' => 'Transmission',
        'body_type' => 'Body Type',
        'drive_type' => 'Drive Type',
        'exterior_color' => 'Exterior Color',
        'interior_color' => 'Interior Color',
        'description' => 'Description',
        'number_of_doors' => 'Number of Doors',
        'number_of_seats' => 'Number of Seats'
    );
    
    // Verify nonce
    if (!isset($_POST['add_car_listing_nonce']) || !wp_verify_nonce($_POST['add_car_listing_nonce'], 'add_car_listing_nonce')) {
        wp_redirect(add_query_arg('listing_error', 'nonce_failed', wp_get_referer()));
        exit;
    }
    
    // Check for required fields
    $missing_fields = array();
    foreach ($required_fields as $field_key => $field_label) {
        if (!isset($_POST[$field_key]) || empty(trim($_POST[$field_key]))) {
            $missing_fields[] = $field_key;
        }
    }
    
    if (!empty($missing_fields)) {
        // Redirect back with error message
        $redirect_url = add_query_arg(
            array(
                'error' => 'validation',
                'fields' => implode(',', $missing_fields)
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
    
    // Check for images - at least one image is required
    if (!isset($_FILES['car_images']) || empty($_FILES['car_images']['name'][0])) {
        wp_redirect(add_query_arg('error', 'images', wp_get_referer()));
        exit;
    }
    
    // Sanitize form data
    $make = sanitize_text_field($_POST['make']);
    $model = sanitize_text_field($_POST['model']);
    $variant = sanitize_text_field($_POST['variant']);
    $year = intval($_POST['year']);
    $mileage = intval($_POST['mileage']);
    $price = intval($_POST['price']);
    $location = sanitize_text_field($_POST['location']);
    
    // Process location fields
    $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    $district = isset($_POST['district']) ? sanitize_text_field($_POST['district']) : '';
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
    $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
    
    $engine_capacity = sanitize_text_field($_POST['engine_capacity']);
    $fuel_type = sanitize_text_field($_POST['fuel_type']);
    $transmission = sanitize_text_field($_POST['transmission']);
    $body_type = sanitize_text_field($_POST['body_type']);
    $drive_type = sanitize_text_field($_POST['drive_type']);
    $exterior_color = sanitize_text_field($_POST['exterior_color']);
    $interior_color = sanitize_text_field($_POST['interior_color']);
    $description = wp_kses_post($_POST['description']);
    $number_of_doors = intval($_POST['number_of_doors']);
    $number_of_seats = intval($_POST['number_of_seats']);
    
    // Process MOT status (optional)
    $motuntil = isset($_POST['motuntil']) ? sanitize_text_field($_POST['motuntil']) : '';
    
    // Process extras (checkboxes)
    $extras = isset($_POST['extras']) ? array_map('sanitize_text_field', $_POST['extras']) : array();
    
    // Process ACF fields
    $hp = isset($_POST['hp']) ? intval($_POST['hp']) : '';
    $numowners = isset($_POST['numowners']) ? intval($_POST['numowners']) : '';
    $isantique = isset($_POST['isantique']) ? 1 : 0;
    
    // Process vehicle history as an array of selected options
    $vehiclehistory = array();
    if (isset($_POST['vehiclehistory']) && is_array($_POST['vehiclehistory'])) {
        foreach ($_POST['vehiclehistory'] as $history_item) {
            $vehiclehistory[] = sanitize_text_field($history_item);
        }
    }
    
    // Prepare post data
    $post_title = $year . ' ' . $make . ' ' . $model . ' ' . $variant;
    
    // Create the post
    $post_data = array(
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'pending', // Set as pending for admin review
        'post_type' => 'car',
        'post_author' => get_current_user_id(),
    );
    
    // Insert the post
    $post_id = wp_insert_post($post_data);
    
    // Check if post creation was successful
    if (is_wp_error($post_id)) {
        error_log('Error creating car listing: ' . $post_id->get_error_message());
        wp_redirect(add_query_arg('error', 'post_creation', wp_get_referer()));
        exit;
    }
    
    // Add post meta for all the car details
    update_post_meta($post_id, 'make', $make);
    update_post_meta($post_id, 'model', $model);
    update_post_meta($post_id, 'variant', $variant);
    update_post_meta($post_id, 'year', $year);
    update_post_meta($post_id, 'mileage', $mileage);
    update_post_meta($post_id, 'price', $price);
    update_post_meta($post_id, 'location', $location);
    update_post_meta($post_id, 'city', $city);
    update_post_meta($post_id, 'district', $district);
    update_post_meta($post_id, 'latitude', $latitude);
    update_post_meta($post_id, 'longitude', $longitude);
    update_post_meta($post_id, 'address', $address);
    update_post_meta($post_id, 'engine_capacity', $engine_capacity);
    update_post_meta($post_id, 'fuel_type', $fuel_type);
    update_post_meta($post_id, 'transmission', $transmission);
    update_post_meta($post_id, 'body_type', $body_type);
    update_post_meta($post_id, 'drive_type', $drive_type);
    update_post_meta($post_id, 'exterior_color', $exterior_color);
    update_post_meta($post_id, 'interior_color', $interior_color);
    update_post_meta($post_id, 'description', $description);
    update_post_meta($post_id, 'number_of_doors', $number_of_doors);
    update_post_meta($post_id, 'number_of_seats', $number_of_seats);
    update_post_meta($post_id, 'motuntil', $motuntil);
    update_post_meta($post_id, 'extras', $extras);
    
    // Update ACF fields
    update_field('hp', $hp, $post_id);
    update_field('numowners', $numowners, $post_id);
    update_field('isantique', $isantique, $post_id);
    update_field('vehiclehistory', $vehiclehistory, $post_id);
    
    // Process image uploads
    $image_ids = handle_car_image_uploads($post_id);
    
    // If image processing failed
    if (is_wp_error($image_ids)) {
        error_log('Error processing car images: ' . $image_ids->get_error_message());
        // Delete the post since images are required
        wp_delete_post($post_id, true);
        wp_redirect(add_query_arg('error', 'image_upload', wp_get_referer()));
        exit;
    }
    
    // Redirect to success page
    wp_redirect(add_query_arg('listing_submitted', 'success', wp_get_referer()));
    exit;
}

// Add hooks for handling form submissions
add_action('admin_post_add_new_car_listing', 'handle_add_car_listing');
add_action('admin_post_nopriv_add_new_car_listing', 'handle_add_car_listing');

/**
 * Process image uploads for car listings
 *
 * @param int $post_id The ID of the car listing post
 * @return array|WP_Error Array of attachment IDs or WP_Error on failure
 */
function handle_car_image_uploads($post_id) {
    // Check if files were uploaded
    if (!isset($_FILES['car_images']) || empty($_FILES['car_images']['name'][0])) {
        return new WP_Error('no_image', __('No images were uploaded', 'astra-child'));
    }
    
    // Log the structure of $_FILES for debugging
    car_submission_log('FILES structure: ' . print_r($_FILES, true));
    
    // Allowed file types
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    
    // Setup return array for attachment IDs
    $attachment_ids = array();
    
    // Get the file count
    $file_count = count($_FILES['car_images']['name']);
    car_submission_log('Number of files to process: ' . $file_count);
    
    // Ensure WordPress media handling is loaded
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    // Loop through each file
    for ($i = 0; $i < $file_count; $i++) {
        // If the file is empty, skip it
        if (empty($_FILES['car_images']['name'][$i])) {
            car_submission_log('Empty file at index ' . $i . ', skipping');
            continue;
        }
        
        car_submission_log('Processing file: ' . $_FILES['car_images']['name'][$i]);
        
        // Check file type
        $file_type = wp_check_filetype($_FILES['car_images']['name'][$i]);
        $mime_type = $_FILES['car_images']['type'][$i];
        
        car_submission_log('File MIME type: ' . $mime_type);
        
        if (!in_array($mime_type, $allowed_types)) {
            car_submission_log('Invalid file type: ' . $mime_type);
            continue; // Skip this file
        }
        
        // Create a new file array with only the current file
        $file = array(
            'name'     => $_FILES['car_images']['name'][$i],
            'type'     => $_FILES['car_images']['type'][$i],
            'tmp_name' => $_FILES['car_images']['tmp_name'][$i],
            'error'    => $_FILES['car_images']['error'][$i],
            'size'     => $_FILES['car_images']['size'][$i]
        );
        
        // Set up $_FILES array for this single file
        $_FILES['car_image'] = $file;
        
        // Use WordPress built-in attachment handling
        $attachment_id = media_handle_upload('car_image', $post_id);
        
        if (is_wp_error($attachment_id)) {
            car_submission_log('Error creating attachment: ' . $attachment_id->get_error_message());
            continue;
        }
        
        car_submission_log('Successfully created attachment with ID: ' . $attachment_id);
        
        // Add to our array of attachment IDs
        $attachment_ids[] = $attachment_id;
    }
    
    // If we have no attachments, return an error
    if (empty($attachment_ids)) {
        car_submission_log('No valid images were uploaded');
        return new WP_Error('no_valid_images', __('No valid images were uploaded', 'astra-child'));
    }
    
    car_submission_log('Total attachments processed: ' . count($attachment_ids));
    
    // Save attachment IDs to the ACF gallery field instead of setting featured image
    update_field('car_images', $attachment_ids, $post_id);
    car_submission_log('Saved all attachment IDs to ACF car_images gallery field');
    
    return $attachment_ids;
}

/**
 * Log errors to WordPress debug log
 * 
 * @param string $message The error message to log
 */
function car_submission_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/**
 * Display car gallery images on single car listing pages
 * 
 * @param int $post_id The post ID to display gallery for
 * @return string HTML output of the gallery
 */
function display_car_gallery($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // Get gallery image IDs from ACF field
    $gallery_images = get_field('car_images', $post_id);
    
    if (empty($gallery_images) || !is_array($gallery_images)) {
        return ''; // No gallery images
    }
    
    $output = '<div class="car-gallery">';
    
    // Display first image as main
    if (!empty($gallery_images[0])) {
        $main_image_id = $gallery_images[0];
        $full_image_url = wp_get_attachment_image_url($main_image_id, 'large');
        $output .= '<div class="car-gallery-main">';
        $output .= '<img src="' . esc_url($full_image_url) . '" alt="' . esc_attr(get_the_title($post_id)) . '" class="main-gallery-image">';
        $output .= '</div>';
    }
    
    // Thumbnail gallery
    if (count($gallery_images) > 1) {
        $output .= '<div class="car-gallery-thumbnails">';
        foreach ($gallery_images as $attachment_id) {
            $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $full_url = wp_get_attachment_image_url($attachment_id, 'large');
            if ($thumb_url) {
                $output .= '<div class="gallery-thumbnail" data-full-image="' . esc_url($full_url) . '">';
                $output .= '<img src="' . esc_url($thumb_url) . '" alt="' . esc_attr(get_the_title($post_id)) . '">';
                $output .= '</div>';
            }
        }
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Shortcode to display car gallery
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function car_gallery_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => null,
    ), $atts);
    
    return display_car_gallery($atts['id']);
}
add_shortcode('car_gallery', 'car_gallery_shortcode');

/**
 * Add car gallery to single car listing content
 * 
 * @param string $content The post content
 * @return string Modified content with gallery
 */
function add_car_gallery_to_content($content) {
    if (is_singular('car')) {
        $gallery = display_car_gallery();
        return $gallery . $content;
    }
    return $content;
}
add_filter('the_content', 'add_car_gallery_to_content', 20);

/**
 * Store the publication date when a car listing is published
 * 
 * @param string $new_status The new post status
 * @param string $old_status The old post status
 * @param WP_Post $post The post object
 */
function store_car_publication_date($new_status, $old_status, $post) {
    // Only proceed if this is a car post type
    if ($post->post_type !== 'car') {
        return;
    }

    // Check if the post is being published
    if ($new_status === 'publish' && $old_status !== 'publish') {
        // Store the current time as the publication date
        $publication_date = current_time('mysql');
        update_post_meta($post->ID, 'publication_date', $publication_date);
        
        // Debug log
        if (WP_DEBUG === true) {
            error_log('Car publication date stored: ' . $publication_date . ' for post ID: ' . $post->ID);
        }
    }
}
add_action('transition_post_status', 'store_car_publication_date', 10, 3);