<?php
// [car-images-slider] shortcode for car image carousel
if (!function_exists('car_images_slider_shortcode')) {
    function car_images_slider_shortcode($atts) {
        return 'hello';
    }
    add_shortcode('car-images-slider', 'car_images_slider_shortcode');
}

// Enqueue carousel JS and CSS only when needed
if (!function_exists('car_images_slider_enqueue_assets')) {
    function car_images_slider_enqueue_assets() {
        // Only enqueue once per request
        static $enqueued = false;
        if ($enqueued) return;
        $enqueued = true;
        $theme_dir = get_stylesheet_directory_uri();
        // Use the original car-listings carousel assets
        wp_enqueue_style('car-listings-style', $theme_dir . '/includes/car-listings/car-listings.css', array(), filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings.css'));
        wp_enqueue_script('car-listings-js', $theme_dir . '/includes/car-listings/car-listings.js', array('jquery'), filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings.js'), true);
    }
} 