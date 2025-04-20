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

/**
 * Builds the WP_Query arguments array for car listings based on shortcode attributes and GET parameters.
 * 
 * @param array $atts Shortcode attributes.
 * @param int   $paged Current page number.
 * @param array $filters (Optional) An array of filters passed directly (e.g., from AJAX). Defaults to null.
 * @return array The WP_Query arguments array.
 */
function build_car_listings_query_args($atts, $paged, $filters = null) {
    // Determine the source of filters: direct parameter or $_GET
    $filter_source = is_array($filters) ? $filters : $_GET;

    // Base query arguments
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => $atts['per_page'],
        'paged' => $paged,
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND', // Ensure all meta queries must be met
        )
    );

    // Add filter arguments from the determined source
    $filter_params = array(
        'make' => 'make',
        'model' => 'model',
        'variant' => 'variant',
        'location' => 'location'
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

    // Add range filters (Min/Max)
    $range_filters = array(
        'price' => 'price',
        'year' => 'year',
        'km' => 'mileage',
        'engine' => 'engine_capacity'
    );

    foreach ($range_filters as $filter_prefix => $meta_key) {
        $min_key = $filter_prefix . '_min';
        $max_key = $filter_prefix . '_max';

        if (isset($filter_source[$min_key]) && !empty($filter_source[$min_key])) {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => ($meta_key === 'engine_capacity') ? floatval($filter_source[$min_key]) : intval($filter_source[$min_key]),
                'type' => 'NUMERIC',
                'compare' => '>='
            );
        }
        if (isset($filter_source[$max_key]) && !empty($filter_source[$max_key])) {
            $args['meta_query'][] = array(
                'key' => $meta_key,
                'value' => ($meta_key === 'engine_capacity') ? floatval($filter_source[$max_key]) : intval($filter_source[$max_key]),
                'type' => 'NUMERIC',
                'compare' => '<='
            );
        }
    }

    // Add checkbox filters (Multiple values possible - IN comparison)
    $checkbox_filters = array(
        'fuel_type' => 'fuel_type',
        'body_type' => 'body_type',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'drive_type' => 'drive_type'
    );

    foreach ($checkbox_filters as $filter_key => $meta_key) {
        // Check for keys like 'fuel_type[]' or just 'fuel_type'
        $actual_key = isset($filter_source[$filter_key . '[]']) ? $filter_key . '[]' : $filter_key; 

        if (isset($filter_source[$actual_key]) && !empty($filter_source[$actual_key])) {
            // Ensure values are always treated as an array
            $values = array_map('sanitize_text_field', (array)$filter_source[$actual_key]); 
            if (!empty($values)) {
                 $args['meta_query'][] = array(
                     'key' => $meta_key,
                     'value' => $values,
                     'compare' => 'IN'
                 );
            }
        }
    }
    
    // Ensure meta_query array exists even if no filters were added, if not set to empty array.
    if (empty($args['meta_query'])) {
        unset($args['meta_query']); // Remove meta_query if it's empty
    }

    return $args;
}