<?php

/**
 * Mapbox functionality for car listings
 */

// Get Mapbox token from environment variable
function get_mapbox_token() {
    return getenv('MAPBOX_TOKEN');
}

// Enqueue Mapbox scripts and styles
function enqueue_mapbox_assets() {
    // Only enqueue on pages that need maps
    if (is_singular('car') || is_page('add-listing') || is_page('car-listings')) {
        // Mapbox GL JS
        wp_enqueue_script(
            'mapbox-gl-js',
            'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
            array(),
            '2.15.0',
            true
        );

        // Mapbox GL CSS
        wp_enqueue_style(
            'mapbox-gl-css',
            'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
            array(),
            '2.15.0'
        );

        // Our custom map script
        wp_enqueue_script(
            'car-map-js',
            get_stylesheet_directory_uri() . '/js/car-map.js',
            array('mapbox-gl-js'),
            ASTRA_CHILD_THEME_VERSION,
            true
        );

        // Pass Mapbox token to JavaScript
        wp_localize_script(
            'car-map-js',
            'mapboxData',
            array(
                'token' => get_mapbox_token(),
                'defaultCenter' => array(
                    'lat' => 34.7071, // Cyprus center
                    'lng' => 33.0226
                ),
                'defaultZoom' => 9
            )
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_mapbox_assets');

// Get districts for a city
function get_districts_for_city($city) {
    $districts_file = get_stylesheet_directory() . '/simple_jsons/districts.json';
    
    if (file_exists($districts_file)) {
        $districts = json_decode(file_get_contents($districts_file), true);
        return isset($districts[$city]) ? $districts[$city] : array();
    }
    
    return array();
}

// AJAX handler for getting districts
function get_districts_ajax() {
    $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    
    if (empty($city)) {
        wp_send_json_error('City is required');
    }
    
    $districts = get_districts_for_city($city);
    wp_send_json_success($districts);
}
add_action('wp_ajax_get_districts', 'get_districts_ajax');
add_action('wp_ajax_nopriv_get_districts', 'get_districts_ajax'); 