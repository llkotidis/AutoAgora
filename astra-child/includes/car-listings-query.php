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
        'transmission' => 'transmission',
        'number_of_doors' => 'number_of_doors',
        'number_of_seats' => 'number_of_seats',
        'availability' => 'availability',
        'isantique' => 'isantique' // For true/false, ACF stores 1 or 0. Comparison should be '='.
    );

    foreach ($filter_params as $filter_key => $meta_key) {
        if (isset($filter_source[$filter_key]) && $filter_source[$filter_key] !== '') { // Check for not empty string
            $value = sanitize_text_field($filter_source[$filter_key]);
            $compare_operator = '=';

            if ($meta_key === 'isantique') {
                 // Ensure value is 1 or 0 for boolean ACF fields
                $value = ($value == '1' || strtolower($value) === 'true') ? '1' : '0';
            }

            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => $value,
                'compare' => $compare_operator
            );
        }
    }

    // --- Range Filters (min/max) ---
    // JS is expected to send parameters like year_min, year_max, price_min, price_max etc.
    $range_filters = array(
        'year' => 'year', // meta_key 'year'
        'price' => 'price', // meta_key 'price'
        'mileage' => 'mileage', // meta_key 'mileage'
        'engine_capacity' => 'engine_capacity', // meta_key 'engine_capacity'
        'hp' => 'hp', // meta_key 'hp'
        'numowners' => 'numowners', // meta_key 'numowners'
    );

    foreach ($range_filters as $filter_key => $meta_key) {
        $min_val = isset($filter_source[$filter_key . '_min']) ? $filter_source[$filter_key . '_min'] : null;
        $max_val = isset($filter_source[$filter_key . '_max']) ? $filter_source[$filter_key . '_max'] : null;

        if ($min_val !== null && $min_val !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => sanitize_text_field($min_val),
                'type'    => 'NUMERIC',
                'compare' => '>='
            );
        }
        if ($max_val !== null && $max_val !== '') {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => sanitize_text_field($max_val),
                'type'    => 'NUMERIC',
                'compare' => '<='
            );
        }
    }

    // --- Checkbox / Multi-Select Filters ---
    // JS is expected to send parameters like fuel_type[]=Petrol&fuel_type[]=Diesel
    $checkbox_filters = array(
        'fuel_type' => 'fuel_type',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'body_type' => 'body_type',
        'drive_type' => 'drive_type',
        'extras' => 'extras',
        'vehiclehistory' => 'vehiclehistory'
    );

    foreach ($checkbox_filters as $filter_key => $meta_key) {
        $actual_key = $filter_key; // Simpler if JS sends plain keys e.g. fuel_type: ['Petrol', 'Diesel']
        // Check if JS sent it as fuel_type[] which PHP converts to an array under the key 'fuel_type'
        if (isset($filter_source[$filter_key]) && is_array($filter_source[$filter_key])) {
            $values_raw = $filter_source[$filter_key];
        } 
        // Legacy check if JS sent it as filter_key[] which gets converted to filter_key by some JS methods or manual construction
        // else if (isset($filter_source[$filter_key . '[]']) && is_array($filter_source[$filter_key . '[]'])) { 
        //    $values_raw = $filter_source[$filter_key . '[]'];
        // }
        else {
            $values_raw = null;
        }
        
        if ($values_raw && !empty($values_raw)) {
            $values = array_filter(array_map('sanitize_text_field', $values_raw)); 
            if (!empty($values)) {
                // For ACF checkboxes/multi-select, values are often stored serialized (e.g., a:1:{i:0;s:6:"Petrol";})
                // or as a simple array. If it is a serialized array of choices, a LIKE query for each is needed.
                $group_relation = ($meta_key === 'extras' || $meta_key === 'vehiclehistory') ? 'AND' : 'OR'; // For extras, all selected must match. For others, any match.
                $individual_meta_queries = array('relation' => $group_relation);

                foreach ($values as $single_value) {
                    $individual_meta_queries[] = array(
                        'key' => $meta_key, 
                        'value' => '"' . $single_value . '"', // searching for "value" within serialized string
                        'compare' => 'LIKE'
                    );
                }
                if (count($individual_meta_queries) > 1) { // only add if there are actual conditions
                    $args['meta_query'][] = $individual_meta_queries;
                }
            }
        }
    }
    
    // If after all filters, post__in is set to [0] (no location match) and other meta queries exist,
    // the query will correctly return no results. If post__in is not set, other meta queries apply normally.

    return $args;
}