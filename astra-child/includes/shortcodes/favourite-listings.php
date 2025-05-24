<?php
/**
 * Favorite Listings Shortcode - Controller
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Enqueue separated CSS and JS files
function enqueue_favourite_listings_assets() {
    if (is_page() || (is_user_logged_in() && (strpos($_SERVER['REQUEST_URI'], 'my-account') !== false || isset($_GET['shortcode']) && $_GET['shortcode'] === 'favourite_listings'))) {
        // Enqueue Favourite Listings CSS
        wp_enqueue_style(
            'favourite-listings-css',
            get_stylesheet_directory_uri() . '/includes/shortcodes/favourite-listings/favourite-listings.css',
            array(),
            '1.0.0'
        );

        // Enqueue Favourite Listings JS
        wp_enqueue_script(
            'favourite-listings-js',
            get_stylesheet_directory_uri() . '/includes/shortcodes/favourite-listings/favourite-listings.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script for AJAX
        wp_localize_script('favourite-listings-js', 'carListingsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('toggle_favorite_car')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_favourite_listings_assets');

// Register the shortcode
add_shortcode('favourite_listings', 'display_favourite_listings');

function display_favourite_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Include the separated display file
    ob_start();
    include get_stylesheet_directory() . '/includes/shortcodes/favourite-listings/favourite-listings-display.php';
    return ob_get_clean();
}

// Include AJAX handlers
include get_stylesheet_directory() . '/includes/shortcodes/favourite-listings/favourite-listings-ajax.php';
?>