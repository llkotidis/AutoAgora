<?php
/**
 * Car Filter Form Functionality
 * 
 * Generates and handles the car filtering form.
 * Can adapt based on the context (page) it's displayed on.
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays the car filter form.
 *
 * @param string $context Optional context identifier (e.g., 'homepage', 'listings_page') 
 *                        to potentially alter form behavior. Defaults to 'default'.
 * @return string The HTML output for the filter form.
 */
function display_car_filter_form( $context = 'default' ) {
    global $wpdb;

    // --- Get Unique Locations from ACF field ---
    // Query unique 'location' meta values from published 'car' posts
    $location_query = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s 
            AND p.post_type = %s 
            AND p.post_status = %s
            ORDER BY pm.meta_value ASC",
            'location', // The ACF field key
            'car',      // The custom post type
            'publish'   // Only published posts
        )
    );

    $locations = array_filter( $location_query ); // Remove any empty values just in case

    // --- Start Form Output ---
    ob_start();
    ?>
    <div class="car-filter-form-container context-<?php echo esc_attr($context); ?>">
        <form id="car-filter-form-<?php echo esc_attr($context); ?>" class="car-filter-form" method="get" action=""> 
            
            <h2>Find Your Car</h2> 

            <!-- Location Selector -->
            <div class="filter-form-group">
                <label for="filter-location-<?php echo esc_attr($context); ?>">Location</label>
                <select id="filter-location-<?php echo esc_attr($context); ?>" name="filter_location">
                    <option value="">All Locations</option>
                    <?php foreach ( $locations as $location ) : ?>
                        <option value="<?php echo esc_attr( $location ); ?>">
                            <?php echo esc_html( $location ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- More filters will be added here -->

            <div class="filter-form-actions">
                 <button type="submit" class="filter-submit-button">Search</button>
                 <!-- Reset button might be added later -->
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Example of how you might call this on a page template:
// require_once get_stylesheet_directory() . '/includes/car-filter-form.php';
// echo display_car_filter_form('homepage'); 