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

    // --- Get Predefined Choices from ACF Select Field ---
    $location_field_key = 'location'; // MAKE SURE THIS IS THE CORRECT FIELD NAME/KEY
    
    // Try to get a sample post ID from the 'car' post type for context
    $sample_car_post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT 1",
            'car',
            'publish'
        )
    );

    $field = false;
    if ($sample_car_post_id) {
        $field = get_field_object( $location_field_key, $sample_car_post_id );
    } else {
        // Fallback if no published cars exist yet, try without post ID
        $field = get_field_object( $location_field_key ); 
    }

    $all_possible_locations = array();



    if ( $field && isset($field['choices']) && is_array($field['choices']) ) {
        // Assuming choices are stored as value => label
        $all_possible_locations = $field['choices'];
        // Sort alphabetically by label (city name) for display consistency
        asort($all_possible_locations);



    } else {
  
    }

    // --- Get Counts of PUBLISHED 'car' posts for each location ---
    $published_counts_query = array(); // Initialize as empty array
    if (!empty($all_possible_locations)) {
        // Only run query if we have locations to check
        $published_counts_query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value, COUNT(p.ID) as count
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_value IN (" . implode(', ', array_fill(0, count($all_possible_locations), '%s')) . ")
                GROUP BY pm.meta_value",
                array_merge([$location_field_key, 'car', 'publish'], array_keys($all_possible_locations)) // Pass field key, post type, status, and all possible locations
            ),
            OBJECT_K // Index the results by meta_value (location name)
        );
    }

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
                    <?php 
                    if (!empty($all_possible_locations)):
                        foreach ( $all_possible_locations as $value => $label ) : 
                            // $value is the location name stored in meta, $label is the display name
                            $count = isset($published_counts_query[$value]) ? $published_counts_query[$value]->count : 0;
                            $disabled_attr = ( $count == 0 ) ? ' disabled="disabled"' : '';
                            $display_text = esc_html( $label ); // Use the label from ACF choices for display
                            if ( $count > 0 ) {
                                $display_text .= ' (' . $count . ')';
                            } else {
                                $display_text .= ' (0)'; // Explicitly show (0) for disabled options
                            }
                        ?>
                            <option value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled_attr; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php endforeach; 
                    endif; // end check for !empty($all_possible_locations)
                    ?>
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