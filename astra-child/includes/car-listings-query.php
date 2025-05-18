<?php
/**
 * Car Listings Query Argument Builder
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/geo-utils.php'; // Include geo utility functions

// Ensure the distance calculation function is available
// MOVED - now included above
/*
if (!function_exists('autoagora_calculate_distance')) {
    // If it's in car-listings.php, and this file can't always assume car-listings.php has run,
    // it might be better to move autoagora_calculate_distance to a common file or duplicate it here carefully.
    // For now, assuming it might be available or we might need to include it from car-listings.php if that's where it lives.
    // Ideally, autoagora_calculate_distance should be in a general utilities file included by both.
    // Let's assume it might be in car-listings.php for now, or should be moved to a shared include.
    // If car-listings.php includes this file, then it might be okay.
    // Temporary: if it's not found, it will cause an error later.
}
*/

/**
 * Builds the WP_Query arguments array for car listings based on shortcode attributes and GET parameters.
 * 
 * @param array $atts Shortcode attributes.
 * @param int   $paged Current page number.
 * @param array $filters (Optional) An array of filters passed directly (e.g., from AJAX). Defaults to null.
 * @return array The WP_Query arguments array.
 */
function build_car_listings_query_args($atts, $paged, $filters = null) {
    global $wpdb; // Needed for direct DB query for location
    // Determine the source of filters: direct parameter or $_GET if $filters is null
    // If $filters is provided (e.g. from AJAX), it should contain all necessary filter keys including lat, lng, radius
    $filter_source = is_array($filters) ? $filters : $_GET;

    // Base query arguments
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => isset($atts['per_page']) ? intval($atts['per_page']) : 12,
        'paged' => intval($paged),
        'orderby' => isset($atts['orderby']) ? $atts['orderby'] : 'date',
        'order' => isset($atts['order']) ? $atts['order'] : 'DESC',
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND', 
            array(
                'relation' => 'OR',
                array(
                    'key' => 'is_sold',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'is_sold',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        )
    );

    // --- Location Filter (lat, lng, radius) ---
    $filter_lat = isset($filter_source['lat']) && $filter_source['lat'] !== 'null' && $filter_source['lat'] !== '' ? floatval($filter_source['lat']) : null;
    $filter_lng = isset($filter_source['lng']) && $filter_source['lng'] !== 'null' && $filter_source['lng'] !== '' ? floatval($filter_source['lng']) : null;
    $filter_radius = isset($filter_source['radius']) && $filter_source['radius'] !== 'null' && $filter_source['radius'] !== '' ? floatval($filter_source['radius']) : null;

    if ($filter_lat !== null && $filter_lng !== null && $filter_radius !== null) {
        // Query all car IDs first (this is the part we might optimize further with direct SQL Haversine)
        $all_car_ids_args = array(
            'post_type' => 'car',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                 array(
                    'relation' => 'OR',
                    array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
                    array('key' => 'is_sold', 'value' => '1', 'compare' => '!=')
                )
            )
            // We don't apply other spec filters here yet, to get a broad list for location filtering.
            // This could be refined if spec filters should pre-filter before location distance calc.
        );
        $all_car_ids_query = new WP_Query($all_car_ids_args);
        $matching_location_car_ids = array();

        if ($all_car_ids_query->have_posts()) {
            if (function_exists('autoagora_calculate_distance')) {
                foreach ($all_car_ids_query->posts as $car_id) {
                    $car_latitude = get_field('car_latitude', $car_id);
                    $car_longitude = get_field('car_longitude', $car_id);
                    if ($car_latitude && $car_longitude) {
                        $distance = autoagora_calculate_distance($filter_lat, $filter_lng, $car_latitude, $car_longitude);
                        if ($distance <= $filter_radius) {
                            $matching_location_car_ids[] = $car_id;
                        }
                    }
                }
            } else {
                // Log error or handle missing distance function
                error_log('autoagora_calculate_distance function not found in build_car_listings_query_args.');
            }
        }

        if (empty($matching_location_car_ids)) {
            $args['post__in'] = [0]; // No cars match location, so query for no posts
        } else {
            $args['post__in'] = $matching_location_car_ids;
        }
    } // End Location Filter

    // Add other spec filter arguments from the determined source
    $filter_params = array(
        'make' => 'make',
        'model' => 'model',
        'variant' => 'variant',
        'number_of_doors' => 'number_of_doors',
        'number_of_seats' => 'number_of_seats',
        // 'location' => 'location' // This was for text-based location, map handled above
    );

    foreach ($filter_params as $filter_key => $meta_key) {
        if (isset($filter_source[$filter_key]) && !empty($filter_source[$filter_key])) {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => sanitize_text_field($filter_source[$filter_key]),
                'compare' => '='
            );
        }
    }

    $range_filters = array(
        'price' => 'price',
        'year' => 'year',
        'km' => 'mileage', // Assuming URL/filter key is 'km_min', 'km_max'
        'engine' => 'engine_capacity' // Assuming URL/filter key is 'engine_min', 'engine_max'
    );

    foreach ($range_filters as $filter_prefix => $meta_key) {
        $min_key = $filter_prefix . '_min';
        $max_key = $filter_prefix . '_max';

        if (isset($filter_source[$min_key]) && $filter_source[$min_key] !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => ($meta_key === 'engine_capacity') ? floatval($filter_source[$min_key]) : intval($filter_source[$min_key]),
                'type' => 'NUMERIC',
                'compare' => '>='
            );
        }
        if (isset($filter_source[$max_key]) && $filter_source[$max_key] !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => ($meta_key === 'engine_capacity') ? floatval($filter_source[$max_key]) : intval($filter_source[$max_key]),
                'type' => 'NUMERIC',
                'compare' => '<='
            );
        }
    }

    $checkbox_filters = array(
        'fuel_type' => 'fuel_type',
        'body_type' => 'body_type',
        'transmission' => 'transmission', // Added transmission
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'drive_type' => 'drive_type'
    );

    foreach ($checkbox_filters as $filter_key => $meta_key) {
        $actual_key = $filter_key; // Simpler if JS sends plain keys
        if (isset($filter_source[$filter_key . '[]'])) { // Check for PHP array style keys
            $actual_key = $filter_key . '[]';
        }
        
        if (isset($filter_source[$actual_key]) && !empty($filter_source[$actual_key])) {
            $values_raw = (array)$filter_source[$actual_key];
            $values = array_filter(array_map('sanitize_text_field', $values_raw)); 
            if (!empty($values)) {
                 $args['meta_query'][] = array(
                     'key' => $meta_key,
                     'value' => $values,
                     'compare' => 'IN'
                 );
            }
        }
    }
    
    // If after all filters, post__in is set to [0] (no location match) and other meta queries exist,
    // the query will correctly return no results. If post__in is not set, other meta queries apply normally.

    return $args;
}