<?php
/**
 * Mapbox Assets
 *
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Mapbox assets
 */
function autoagora_enqueue_mapbox_assets() {
    // Only load on single car pages and add listing page
    if (!is_singular('car') && !is_page('add-listing')) {
        return;
    }

    // Debug: Check if Mapbox token is defined and log its first few characters
    if (defined('MAPBOX_ACCESS_TOKEN')) {
        $token = MAPBOX_ACCESS_TOKEN;
        $token_preview = substr($token, 0, 4) . '...' . substr($token, -4);
        error_log('Mapbox token found: ' . $token_preview);
    } else {
        error_log('Mapbox token NOT found in wp-config.php');
        return;
    }

    // Enqueue Mapbox CSS
    wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css');

    // Enqueue Mapbox JS
    wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), null, true);

    // Only load location picker on add-listing page
    if (is_page('add-listing')) {
        // Enqueue location picker styles
        wp_enqueue_style('location-picker', get_stylesheet_directory_uri() . '/assets/css/location-picker.css');

        // Enqueue location picker script
        wp_enqueue_script('location-picker', get_stylesheet_directory_uri() . '/assets/js/location-picker.js', array('mapbox-gl'), null, true);

        // Pass configuration to JavaScript
        wp_localize_script('location-picker', 'mapboxConfig', array(
            'accessToken' => MAPBOX_ACCESS_TOKEN,
            'style' => 'mapbox://styles/mapbox/streets-v12',
            'defaultZoom' => 10,
            'center' => [35.1856, 33.3823] // Default to Nicosia
        ));

        // Pass cities JSON URL to JavaScript
        wp_localize_script('location-picker', 'locationPickerData', array(
            'citiesJsonUrl' => get_stylesheet_directory_uri() . '/simple_jsons/cities.json'
        ));
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_mapbox_assets'); 