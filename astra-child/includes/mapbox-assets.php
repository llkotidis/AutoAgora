<?php
/**
 * Mapbox Assets
 *
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue Mapbox assets
 */
function autoagora_enqueue_mapbox_assets() {
    // Only enqueue on single car pages
    if (!is_singular('car')) {
        return;
    }

    // Check if Mapbox token is defined
    if (!defined('MAPBOX_ACCESS_TOKEN')) {
        error_log('Mapbox access token is not defined in wp-config.php');
        return;
    }

    // Enqueue Mapbox CSS
    wp_enqueue_style(
        'mapbox-gl-css',
        'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
        array(),
        '2.15.0'
    );

    // Enqueue Mapbox JS
    wp_enqueue_script(
        'mapbox-gl-js',
        'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
        array(),
        '2.15.0',
        true
    );

    // Enqueue our custom map script
    wp_enqueue_script(
        'autoagora-map',
        get_stylesheet_directory_uri() . '/assets/js/map.js',
        array('mapbox-gl-js'),
        filemtime(get_stylesheet_directory() . '/assets/js/map.js'),
        true
    );

    // Pass Mapbox configuration to JavaScript
    wp_localize_script('autoagora-map', 'mapboxConfig', array(
        'accessToken' => MAPBOX_ACCESS_TOKEN,
        'styleUrl' => 'mapbox://styles/mapbox/streets-v12',
        'defaultZoom' => 12,
        'defaultCenter' => array(
            'lat' => 35.8617,
            'lng' => 104.1954
        )
    ));
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_mapbox_assets'); 