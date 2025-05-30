<?php
/**
 * Edit Listing Processing - Form Processing Logic
 * Separated from car-submission.php for better organization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process edit listing form submission
 * 
 * @param array $data Form data to process
 * @param int $car_id Car ID being edited
 * @return bool True on success, false on failure
 */
function process_edit_listing_form($data, $car_id) {
    $editable_fields = array(
        'mileage' => 'Mileage',
        'price' => 'Price',
        'description' => 'Description'
    );
    
    // Update only editable fields
    foreach ($editable_fields as $field_key => $field_label) {
        update_field($field_key, sanitize_text_field($data[$field_key]), $car_id);
    }
    
    // Update location-related fields
    if (isset($data['car_city'])) {
        update_field('car_city', sanitize_text_field($data['car_city']), $car_id);
    }
    if (isset($data['car_district'])) {
        update_field('car_district', sanitize_text_field($data['car_district']), $car_id);
    }
    if (isset($data['car_latitude'])) {
        update_field('car_latitude', floatval($data['car_latitude']), $car_id);
    }
    if (isset($data['car_longitude'])) {
        update_field('car_longitude', floatval($data['car_longitude']), $car_id);
    }
    if (isset($data['car_address'])) {
        update_field('car_address', sanitize_text_field($data['car_address']), $car_id);
    }
    
    // Update optional fields
    if (isset($data['hp'])) {
        update_field('hp', sanitize_text_field($data['hp']), $car_id);
    }
    
    // Update number of owners
    if (isset($data['numowners'])) {
        update_field('numowners', intval($data['numowners']), $car_id);
    }
    
    // Process vehicle history
    $vehiclehistory = array();
    if (isset($data['vehiclehistory']) && is_array($data['vehiclehistory'])) {
        foreach ($data['vehiclehistory'] as $history_item) {
            $vehiclehistory[] = sanitize_text_field($history_item);
        }
    }
    update_field('vehiclehistory', $vehiclehistory, $car_id);
    
    // Process extras
    $extras = isset($data['extras']) ? array_map('sanitize_text_field', $data['extras']) : array();
    update_field('extras', $extras, $car_id);
    
    return true;
}

/**
 * Process edit listing images - OPTIMIZED VERSION
 *
 * @param int $car_id Post ID
 * @param array $files Uploaded files
 * @param array $removed_images Image IDs to remove
 * @return bool Success status
 */
function process_edit_listing_images($car_id, $files, $removed_images) {
    // Get existing images
    $existing_images = get_field('car_images', $car_id);
    if (!is_array($existing_images)) {
        $existing_images = array();
    }
    
    // Remove selected images
    if (!empty($removed_images)) {
        foreach ($removed_images as $removed_id) {
            $key = array_search($removed_id, $existing_images);
            if ($key !== false) {
                unset($existing_images[$key]);
                // Optionally delete the attachment file
                wp_delete_attachment($removed_id, true);
            }
        }
        // Reindex array
        $existing_images = array_values($existing_images);
    }
    
    // Process new image uploads if any - OPTIMIZED VERSION
    if (!empty($files['car_images']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $image_ids = array();
        $processed_files = array();
        
        // OPTIMIZATION: Disable intermediate image generation during batch upload
        add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
        
        // Upload new images with optimized processing
        foreach ($files['car_images']['name'] as $key => $value) {
            if ($files['car_images']['error'][$key] === 0) {
                $file = array(
                    'name'     => $files['car_images']['name'][$key],
                    'type'     => $files['car_images']['type'][$key],
                    'tmp_name' => $files['car_images']['tmp_name'][$key],
                    'error'    => $files['car_images']['error'][$key],
                    'size'     => $files['car_images']['size'][$key]
                );
            
                $_FILES['car_image'] = $file;
                
                // Use optimized upload function
                $attachment_id = optimized_media_handle_upload('car_image', $car_id);
                
                if (!is_wp_error($attachment_id)) {
                    $image_ids[] = $attachment_id;
                    $processed_files[] = $attachment_id;
                }
            }
        }
        
        // OPTIMIZATION: Re-enable image generation and generate only needed sizes
        remove_filter('intermediate_image_sizes_advanced', '__return_empty_array');
        
        // Generate optimized image sizes in batch
        if (!empty($processed_files)) {
            generate_optimized_car_image_sizes($processed_files);
        }
        
        // Combine existing and new images
        $all_images = array_merge($existing_images, $image_ids);
    } else {
        // If no new images uploaded, just use the existing images after removal
        $all_images = $existing_images;
    }
    
    // Update the car_images field
    update_field('car_images', $all_images, $car_id);
    
    // Set the first image as featured image
    if (!empty($all_images)) {
        set_post_thumbnail($car_id, $all_images[0]);
    }
    
    return true;
}

/**
 * Handle redirect with success or error messages
 * 
 * @param string $type Type of redirect ('success' or 'error')
 * @param string $message_key Key for the message
 * @param string $fallback_url Fallback URL if referer is not available
 */
function handle_edit_listing_redirect($type, $message_key, $fallback_url = '') {
    if ($type === 'success') {
        // Redirect to my listings page with success message
        wp_redirect(add_query_arg('listing_updated', '1', home_url('/my-listings/')));
    } else {
        // Error redirect
        $redirect_url = wp_get_referer();
        if (!$redirect_url) {
            $redirect_url = $fallback_url ?: home_url('/my-listings/');
        }
        wp_redirect(add_query_arg('listing_error', $message_key, $redirect_url));
    }
    exit;
} 