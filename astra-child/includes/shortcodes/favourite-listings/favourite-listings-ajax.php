<?php
/**
 * Favorite Listings AJAX Handlers - Separated for better organization
 * Handles server-side AJAX requests for favorite functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// AJAX handler for toggling favorite cars
function toggle_favorite_car() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'toggle_favorite_car')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    // Get car ID and favorite status
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $is_favorite = isset($_POST['is_favorite']) ? (bool)$_POST['is_favorite'] : false;
    
    if ($car_id <= 0) {
        wp_send_json_error('Invalid car ID');
        return;
    }
    
    // Get current user's favorite cars
    $user_id = get_current_user_id();
    $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
    
    // Initialize as empty array if not set
    if (!is_array($favorite_cars)) {
        $favorite_cars = array();
    }
    
    // Add or remove car from favorites
    if ($is_favorite) {
        // Add to favorites if not already there
        if (!in_array($car_id, $favorite_cars)) {
            $favorite_cars[] = $car_id;
        }
    } else {
        // Remove from favorites
        $favorite_cars = array_diff($favorite_cars, array($car_id));
    }
    
    // Update user meta
    update_user_meta($user_id, 'favorite_cars', $favorite_cars);
    
    wp_send_json_success(array(
        'favorite_cars' => $favorite_cars,
        'is_favorite' => $is_favorite
    ));
}
add_action('wp_ajax_toggle_favorite_car', 'toggle_favorite_car');
add_action('wp_ajax_nopriv_toggle_favorite_car', 'toggle_favorite_car');
?> 