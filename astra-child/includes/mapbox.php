<?php
/**
 * Mapbox integration functions
 *
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue Mapbox scripts and styles
 */
function enqueue_mapbox_scripts() {
    // Enqueue Mapbox GL JS
    wp_enqueue_script(
        'mapbox-gl-js',
        'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
        array(),
        '2.15.0',
        true
    );

    // Enqueue Mapbox GL CSS
    wp_enqueue_style(
        'mapbox-gl-css',
        'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
        array(),
        '2.15.0'
    );

    // Add your token to JavaScript securely
    wp_add_inline_script('mapbox-gl-js', 'mapboxgl.accessToken = "' . esc_js(MAPBOX_ACCESS_TOKEN) . '";', 'before');

    // Enqueue custom Mapbox initialization script
    wp_enqueue_script(
        'mapbox-init',
        get_stylesheet_directory_uri() . '/js/mapbox-init.js',
        array('mapbox-gl-js'),
        ASTRA_CHILD_THEME_VERSION,
        true
    );

    // Localize the script with necessary data
    wp_localize_script('mapbox-init', 'mapboxData', array(
        'defaultCenter' => array(
            'lat' => 34.7071, // Default to Cyprus center
            'lng' => 33.0226
        ),
        'defaultZoom' => 9,
        'markerIcon' => get_stylesheet_directory_uri() . '/assets/images/map-marker.png'
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_mapbox_scripts');

/**
 * Get location data from post meta
 *
 * @param int $post_id The post ID
 * @return array Location data
 */
function get_car_location_data($post_id) {
    return array(
        'lat' => get_post_meta($post_id, 'location_lat', true),
        'lng' => get_post_meta($post_id, 'location_lng', true),
        'address' => get_post_meta($post_id, 'location_address', true),
        'city' => get_post_meta($post_id, 'location_city', true),
        'district' => get_post_meta($post_id, 'location_district', true)
    );
}

/**
 * Save location data to post meta
 *
 * @param int $post_id The post ID
 * @param array $location_data Location data
 */
function save_car_location_data($post_id, $location_data) {
    update_post_meta($post_id, 'location_lat', $location_data['lat']);
    update_post_meta($post_id, 'location_lng', $location_data['lng']);
    update_post_meta($post_id, 'location_address', $location_data['address']);
    update_post_meta($post_id, 'location_city', $location_data['city']);
    update_post_meta($post_id, 'location_district', $location_data['district']);
}

/**
 * Get all unique cities from the database
 *
 * @return array List of cities
 */
function get_car_cities() {
    global $wpdb;
    $cities = $wpdb->get_col(
        "SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'location_city' 
        AND meta_value != '' 
        ORDER BY meta_value ASC"
    );
    return $cities;
}

/**
 * Get districts for a specific city
 *
 * @param string $city The city name
 * @return array List of districts
 */
function get_car_districts($city) {
    global $wpdb;
    $districts = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'location_district' 
            AND post_id IN (
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'location_city' 
                AND meta_value = %s
            )
            AND meta_value != '' 
            ORDER BY meta_value ASC",
            $city
        )
    );
    return $districts;
} 