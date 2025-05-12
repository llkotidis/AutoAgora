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
 * Helper function to format JSON filename to Make name.
 */
function format_make_name_from_filename($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace('_', ' ', $name);
    $name = ucwords($name);
    $name = str_replace('Bmw', 'BMW', $name);
    // Add more specific replacements here if necessary
    return $name;
}

/**
 * Helper function to get ACF choices, handling potential errors.
 */
function get_acf_choices_safe($field_key, $post_id = null) {
    $field = $post_id ? get_field_object($field_key, $post_id) : get_field_object($field_key);
    if ($field && isset($field['choices']) && is_array($field['choices'])) {
        // Ensure consistent sorting if needed (e.g., alphabetically by label)
        // asort($field['choices']); 
        return $field['choices'];
    }
    // error_log("ACF field '{$field_key}' not found or has no choices.");
    return array();
}

/**
 * Helper function to get counts for a specific meta key.
 */
function get_counts_for_meta_key($meta_key) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT pm.meta_value, COUNT(p.ID) as count
         FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s
         AND p.post_type = 'car'
         AND p.post_status = 'publish'
         AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
         GROUP BY pm.meta_value",
        $meta_key
    );
    $results = $wpdb->get_results($sql, OBJECT_K);
    
    $counts = array();
    if ($results) {
        foreach ($results as $value => $data) {
            $counts[$value] = (int)$data->count;
        }
    }
    return $counts;
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

    // --- Get Sample Post ID for ACF Context ---
    $sample_car_post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT 1", 'car', 'publish'
        )
    );

    // --- Field Keys (Verify these match your ACF setup) ---
    $location_field_key = 'location';
    $make_field_key = 'make'; // Assuming 'make' is also stored in post meta
    $model_field_key = 'model'; // Assuming 'model' is stored
    $variant_field_key = 'variant'; // Assuming 'variant' is stored
    $fuel_type_field_key = 'fuel_type';
    $transmission_field_key = 'transmission';
    $ext_color_field_key = 'exterior_color';
    $int_color_field_key = 'interior_color';
    $body_type_field_key = 'body_type';
    $drive_type_field_key = 'drive_type';
    $year_field_key = 'year'; // For range, not choices
    $engine_cap_field_key = 'engine_capacity'; // For range
    $mileage_field_key = 'mileage'; // For range

    // --- Get Choices for Select Fields ---
    $all_possible_locations = get_acf_choices_safe($location_field_key, $sample_car_post_id);
    $fuel_type_choices = get_acf_choices_safe($fuel_type_field_key, $sample_car_post_id);
    $transmission_choices = get_acf_choices_safe($transmission_field_key, $sample_car_post_id);
    $ext_color_choices = get_acf_choices_safe($ext_color_field_key, $sample_car_post_id);
    $int_color_choices = get_acf_choices_safe($int_color_field_key, $sample_car_post_id);
    $body_type_choices = get_acf_choices_safe($body_type_field_key, $sample_car_post_id);
    $drive_type_choices = get_acf_choices_safe($drive_type_field_key, $sample_car_post_id);
    // Note: Make/Model/Variant choices come from JSONs below

    // --- Get Initial Counts for Select Fields ---
    $published_location_counts = get_counts_for_meta_key($location_field_key);
    $fuel_type_counts = get_counts_for_meta_key($fuel_type_field_key);
    $transmission_counts = get_counts_for_meta_key($transmission_field_key);
    $ext_color_counts = get_counts_for_meta_key($ext_color_field_key);
    $int_color_counts = get_counts_for_meta_key($int_color_field_key);
    $body_type_counts = get_counts_for_meta_key($body_type_field_key);
    $drive_type_counts = get_counts_for_meta_key($drive_type_field_key);
    // Note: Make/Model counts handled separately below, Variant via AJAX

    // --- Get Initial Engine Counts --- 
    $initial_engine_counts_raw = get_counts_for_meta_key($engine_cap_field_key);
    $initial_engine_counts = [];
    foreach($initial_engine_counts_raw as $value => $count) {
         // Ensure keys are formatted consistently (e.g., '1.0', '2.0') for matching option values
         $formatted_key = number_format(floatval($value), 1); 
         $initial_engine_counts[$formatted_key] = $count;
    }

    // --- Generate Static Ranges ---
    $current_year = date('Y');
    $years = range($current_year, 1990); // Example range
    // Use the specific list provided by the user
    $engine_capacities = [0.0, 0.5, 0.7, 1.0, 1.2, 1.4, 1.6, 1.8, 1.9, 2.0, 2.2, 2.4, 2.6, 3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0, 6.5, 7.0]; 
    // Generate stepped mileage options
    $mileages = [];
    for ($i = 0; $i <= 50000; $i += 5000) { $mileages[] = $i; }
    for ($i = 60000; $i <= 150000; $i += 10000) { $mileages[] = $i; }
    for ($i = 200000; $i <= 300000; $i += 50000) { $mileages[] = $i; }

    // --- Get Initial Mileage Counts ---
    $initial_mileage_counts_raw = get_counts_for_meta_key($mileage_field_key);
    $initial_mileage_counts = [];
    foreach($initial_mileage_counts_raw as $value => $count) {
         // Ensure keys are integers for matching option values
         $formatted_key = intval($value); 
         $initial_mileage_counts[$formatted_key] = $count;
    }

    // --- Get Make/Model/Variant Data from JSONs ---
    // (Existing code to read JSONs and build $make_model_variant_data)
    $make_model_variant_data = array();
    $all_makes_from_files = array();
    $json_dir = get_stylesheet_directory() . '/simple_jsons/';
    if (is_dir($json_dir)) {
        $json_files = glob($json_dir . '*.json');
        sort($json_files);
        foreach ($json_files as $file) {
            $make_name_formatted = format_make_name_from_filename(basename($file));
            $all_makes_from_files[] = $make_name_formatted;
            $json_content = file_get_contents($file);
            $data = json_decode($json_content, true);
            if ($data && is_array($data)) {
                $make_key_in_json = key($data);
                if (isset($data[$make_key_in_json])) {
                     $make_model_variant_data[$make_name_formatted] = $data[$make_key_in_json];
                }
            }
        }
    }
    // --- End Make/Model/Variant Data ---

    // --- Get Counts for Makes ---
    $make_counts = get_counts_for_meta_key($make_field_key); // Use helper
    // --- End Make Counts ---

    // --- Get Counts for Models grouped by Make ---
    $model_counts_by_make = array();
    if (!empty($all_makes_from_files)) {
        // (Existing code to query and structure model counts)
        $make_placeholders = implode(', ', array_fill(0, count($all_makes_from_files), '%s'));
        $sql = $wpdb->prepare(
            "SELECT pm_make.meta_value as make, pm_model.meta_value as model, COUNT(p.ID) as count
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_make ON p.ID = pm_make.post_id AND pm_make.meta_key = %s
             JOIN {$wpdb->postmeta} pm_model ON p.ID = pm_model.post_id AND pm_model.meta_key = %s
             WHERE p.post_type = 'car'
             AND p.post_status = 'publish'
             AND pm_make.meta_value IN ({$make_placeholders})
             AND pm_model.meta_value IS NOT NULL AND pm_model.meta_value != ''
             GROUP BY pm_make.meta_value, pm_model.meta_value",
            array_merge([$make_field_key, $model_field_key, 'car', 'publish'], $all_makes_from_files)
        );
        $model_counts_results = $wpdb->get_results($sql);
        foreach ($model_counts_results as $row) {
            if (!isset($model_counts_by_make[$row->make])) {
                $model_counts_by_make[$row->make] = array();
            }
            $model_counts_by_make[$row->make][$row->model] = (int)$row->count;
        }
    }
    // --- End Model Counts ---

    // --- Generate Nonce for AJAX ---
    $ajax_update_nonce = wp_create_nonce('car_filter_update_nonce'); // New nonce for updating counts
    // --- End Nonce Generation ---
    
    // --- Prepare Data for JS (for potential future dynamic updates) ---
    $js_data = [
        'context' => $context,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'updateNonce' => $ajax_update_nonce, // Pass the new nonce
        'updateAction' => 'update_filter_counts', // Pass the new action name
        'makeModelVariantStructure' => $make_model_variant_data,
        'initialCounts' => [
            'location' => $published_location_counts,
            'make' => $make_counts,
            'modelByMake' => $model_counts_by_make, // Still useful for initial model population
            'fuelType' => $fuel_type_counts,
            'transmission' => $transmission_counts,
            'exteriorColor' => $ext_color_counts,
            'interiorColor' => $int_color_counts,
            'bodyType' => $body_type_counts,
            'driveType' => $drive_type_counts,
            // Variant counts are now fetched via the main update AJAX
        ],
        'initialYearCounts' => get_counts_for_meta_key($year_field_key),
        'choices' => [
             'location' => $all_possible_locations,
             // Make choices are derived from $all_makes_from_files in the PHP
             // Model/Variant choices are derived from makeModelVariantStructure dynamically
             'fuelType' => $fuel_type_choices,
             'transmission' => $transmission_choices,
             'exteriorColor' => $ext_color_choices,
             'interiorColor' => $int_color_choices,
             'bodyType' => $body_type_choices,
             'driveType' => $drive_type_choices,
        ],
        'ranges' => [
            'year' => $years,
            'engineCapacity' => $engine_capacities,
            'mileage' => $mileages,
        ]
    ];

    // --- Start Form Output ---
    ob_start();
    ?>
    <div class="car-filter-form-container context-<?php echo esc_attr($context); ?>">
        <div class="filter-layout-container"> <!-- New overall layout wrapper -->
            <form id="car-filter-form-<?php echo esc_attr($context); ?>" class="car-filter-form" method="get" action="/car_listings/"> 
                
                <?php if ($context !== 'listings_page'): ?>
                    <h2>Find Your Car</h2> 
                <?php endif; ?>

                <?php // Helper function for generating select options 
                function render_select_options($choices, $counts, $selected_value = '', $show_count = true) {
                    foreach ($choices as $value => $label) {
                        $count = isset($counts[$value]) ? $counts[$value] : 0;
                        // Initial render - disable based on initial counts
                        $disabled_attr = ($count == 0 && $selected_value !== $value) ? ' disabled="disabled"' : '';
                        $display_text = esc_html($label);
                        // Conditionally add count
                        if ($show_count) {
                            $display_text .= ' (' . $count . ')';
                        }
                        $selected_attr = selected($selected_value, $value, false);
                        echo "<option value=\"" . esc_attr($value) . "\"{$disabled_attr}{$selected_attr}>{$display_text}</option>";
                    }
                }
                function render_range_options($range, $selected_value = '', $suffix = '', $initial_counts = [], $reverse_order = false) {
                    // Reverse the range if requested
                    if ($reverse_order) {
                        $range = array_reverse($range, false); // false preserves keys if they were associative, not needed here but good practice
                    }

                    $is_engine = ($suffix === 'L'); // Check if it's the engine field

                    foreach ($range as $value) {
                        // Ensure value is treated as integer for lookup and attribute
                        // $numeric_value = intval($value); // Old logic - incorrect for engine
                        
                        // Determine value type based on suffix
                        if ($is_engine) {
                             $numeric_value = floatval($value);
                             $value_attr = number_format($numeric_value, 1); // Format value="4.0"
                             $count_key = $value_attr; // Use the formatted string "4.0" as key
                             $display_value_num = $value_attr; // Display 4.0, 4.5 etc.
                        } else {
                             $numeric_value = intval($value); 
                             $value_attr = $numeric_value; // Format value="10000"
                             $count_key = $numeric_value; // Use integer as key
                             $display_value_num = number_format($numeric_value); // Display 10,000
                        }
                        
                        $selected_attr = selected($selected_value, $value_attr, false); // Compare against formatted value_attr
                        // $value_attr = $numeric_value; // Use integer for value attribute

                        // Format display number with commas
                        // $display_value_num = number_format($numeric_value); 
               
                        // Look up initial count using the appropriate key
                        // Use intval for key matching if counts keys might be strings from DB
                        // $count_key = intval($value_attr);
                        $count = isset($initial_counts[$count_key]) ? $initial_counts[$count_key] : 0; 
         
                        // Add suffix WITHOUT a preceding space if suffix exists
                        $display_text = $display_value_num . ($suffix ? trim($suffix) : ''); 
                        $display_text .= ' (' . $count . ')'; // Append count

                        // Disable if count is 0 (initially)
                        $disabled_attr = ($count == 0 && $selected_value !== $value_attr) ? ' disabled="disabled"' : '';

                        echo "<option value=\"" . esc_attr($value_attr) . "\"{$selected_attr}{$disabled_attr}>" . esc_html($display_text) . "</option>";
                    }
                }
                ?>

                <!-- Location Selector -->
                <div class="filter-form-group filter-group-location">
                    <label for="filter-location-<?php echo esc_attr($context); ?>">Location</label>
                    <select id="filter-location-<?php echo esc_attr($context); ?>" name="location" data-filter-key="location">
                        <option value="">All Locations</option>
                        <?php render_select_options($all_possible_locations, $published_location_counts, '', false); ?>
                    </select>
                </div>

                <!-- Make and Model Selectors Side-by-Side -->
                <div class="filter-form-group filter-group-make-model">
                    <div class="filter-side-by-side-fields"> <!-- Using a new class for clarity, can be styled like filter-range-fields -->
                        <div class="sub-group make-sub-group">
                            <label for="filter-make-<?php echo esc_attr($context); ?>">Make</label>
                            <select id="filter-make-<?php echo esc_attr($context); ?>" name="make" data-filter-key="make">
                                <option value="">All Makes</option>
                                <?php 
                                if (!empty($all_makes_from_files)):
                                    $make_choices_assoc = array_combine($all_makes_from_files, $all_makes_from_files);
                                    render_select_options($make_choices_assoc, $make_counts);
                                endif; 
                                ?>
                            </select>
                        </div>
                        <div class="sub-group model-sub-group">
                            <label for="filter-model-<?php echo esc_attr($context); ?>">Model</label>
                            <select id="filter-model-<?php echo esc_attr($context); ?>" name="model" data-filter-key="model" disabled>
                                <option value="">Select Make First</option>
                                <?php
                                    // JS will handle dynamic population
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Variant Selector -->
                <div class="filter-form-group filter-group-variant">
                    <label for="filter-variant-<?php echo esc_attr($context); ?>">Variant</label>
                    <select id="filter-variant-<?php echo esc_attr($context); ?>" name="variant" data-filter-key="variant" disabled>
                        <option value="">Select Model First</option>
                         <?php
                            // Initially empty, JS will handle dynamic population based on Model selection and AJAX counts
                        ?>
                   </select>
                </div>

                <!-- Year Range -->
                <div class="filter-form-group filter-group-year">
                    <label>Year</label>
                    <div class="filter-range-fields">
                        <select id="filter-year-min-<?php echo esc_attr($context); ?>" name="year_min" data-filter-key="year_min">
                            <option value="">Min Year</option>
                            <?php render_range_options($years, '', '', $js_data['initialYearCounts'], true); ?>
                        </select>
                        <select id="filter-year-max-<?php echo esc_attr($context); ?>" name="year_max" data-filter-key="year_max">
                            <option value="">Max Year</option>
                             <?php render_range_options($years, '', '', $js_data['initialYearCounts'], false); ?>
                       </select>
                    </div>
                </div>

                <!-- Actions and More Options Link moved here, inside the form, but styled later -->
                <div class="filter-form-actions">
                     <button type="submit" class="filter-submit-button">Search</button>
                     <div class="filter-actions-row">
                         <button type="button" class="filter-reset-button">Reset Filters</button>
                         <?php if ($context !== 'listings_page'): ?>
                         <a href="#" id="toggle-more-options" class="more-options-link">
                             <span>More Options</span>
                         </a>
                         <?php endif; ?>
                     </div>
                </div>

            </form> <!-- End of car-filter-form -->

            <div id="more-options" class="<?php if ($context === 'listings_page') echo 'show permanently-open'; ?>"> 
                <?php if ($context !== 'listings_page'): ?>
                <h2>More Options</h2> 
                <?php endif; ?>
             <!-- Engine Capacity Range -->
            <div class="filter-form-group filter-group-engine">
                <label>Engine (L)</label>
                 <div class="filter-range-fields">
                    <select id="filter-engine-min-<?php echo esc_attr($context); ?>" name="engine_min" data-filter-key="engine_min">
                        <option value="">Min Size</option>
                         <?php render_range_options($engine_capacities, '', 'L', $initial_engine_counts); ?>
                    </select>
                     <select id="filter-engine-max-<?php echo esc_attr($context); ?>" name="engine_max" data-filter-key="engine_max">
                        <option value="">Max Size</option>
                        <?php render_range_options($engine_capacities, '', 'L', $initial_engine_counts); ?>
                    </select>
                </div>
            </div>

            <!-- Mileage Range -->
            <div class="filter-form-group filter-group-mileage">
                <label>Mileage (km)</label>
                 <div class="filter-range-fields">
                    <select id="filter-mileage-min-<?php echo esc_attr($context); ?>" name="mileage_min" data-filter-key="mileage_min">
                        <option value="">Min KM</option>
                         <?php render_range_options($mileages, '', ' km', $initial_mileage_counts); ?>
                    </select>
                     <select id="filter-mileage-max-<?php echo esc_attr($context); ?>" name="mileage_max" data-filter-key="mileage_max">
                        <option value="">Max KM</option>
                        <?php render_range_options($mileages, '', ' km', $initial_mileage_counts); ?>
                    </select>
                </div>
            </div>

            <!-- Fuel Type & Transmission Side-by-Side -->
            <div class="filter-form-group filter-group-fuel-transmission">
                <div class="filter-side-by-side-fields">
                    <div class="sub-group fuel-type-sub-group">
                        <?php $field_label = "Fuel Type"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="fuel_type">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($fuel_type_choices as $value => $label) {
                                        $count = isset($fuel_type_counts[$value]) ? $fuel_type_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="fuel_type" class="multi-select-value">
                        </div>
                    </div>
                    <div class="sub-group transmission-sub-group">
                        <?php $field_label = "Transmission"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="transmission">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($transmission_choices as $value => $label) {
                                        $count = isset($transmission_counts[$value]) ? $transmission_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="transmission" class="multi-select-value">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body Type & Drive Type Side-by-Side -->
            <div class="filter-form-group filter-group-body-drive">
                <div class="filter-side-by-side-fields">
                    <div class="sub-group body-type-sub-group">
                        <?php $field_label = "Body Type"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="body_type">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($body_type_choices as $value => $label) {
                                        $count = isset($body_type_counts[$value]) ? $body_type_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="body_type" class="multi-select-value">
                        </div>
                    </div>
                    <div class="sub-group drive-type-sub-group">
                        <?php $field_label = "Drive Type"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="drive_type">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($drive_type_choices as $value => $label) {
                                        $count = isset($drive_type_counts[$value]) ? $drive_type_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="drive_type" class="multi-select-value">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exterior & Interior Color Side-by-Side -->
            <div class="filter-form-group filter-group-colors">
                <div class="filter-side-by-side-fields">
                    <div class="sub-group exterior-color-sub-group">
                        <?php $field_label = "Exterior Color"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="exterior_color">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($ext_color_choices as $value => $label) {
                                        $count = isset($ext_color_counts[$value]) ? $ext_color_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="exterior_color" class="multi-select-value">
                        </div>
                    </div>
                    <div class="sub-group interior-color-sub-group">
                        <?php $field_label = "Interior Color"; ?>
                        <label><?php echo esc_html($field_label); ?></label>
                        <div class="multi-select-filter" data-filter-key="interior_color">
                            <div class="multi-select-display" data-default-text="Select <?php echo esc_attr($field_label); ?>">
                                <span>Select <?php echo esc_html($field_label); ?></span>
                            </div>
                            <div class="multi-select-popup">
                                <ul>
                                    <?php
                                    foreach ($int_color_choices as $value => $label) {
                                        $count = isset($int_color_counts[$value]) ? $int_color_counts[$value] : 0;
                                        $disabled_attr = ($count === 0) ? ' disabled="disabled"' : '';
                                        $li_class = ($count === 0) ? ' class="disabled-option"' : '';
                                        echo '<li'. $li_class .'><label>';
                                        echo '<input type="checkbox" value="' . esc_attr($value) . '" data-label="'.esc_attr($label).'"' . $disabled_attr . '>';
                                        echo esc_html($label) . ' (<span class="option-count">' . $count . '</span>)';
                                        echo '</label></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <input type="hidden" name="interior_color" class="multi-select-value">
                        </div>
                    </div>
                </div>
            </div>

            </div> <!-- End of #more-options -->
        </div> <!-- End of filter-layout-container -->
    </div> <!-- End of car-filter-form-container -->

    <?php
    // --- Enqueue and Localize Script --- 
    // Create a unique handle for the script
    $script_handle = 'car-filter-script-' . $context; // Unique handle per context
    
    // Enqueue the script
    wp_enqueue_script(
        $script_handle, // Unique handle
        get_stylesheet_directory_uri() . '/js/car-filter.js', // Path to the JS file
        array('jquery'), // Dependencies (optional, add if needed)
        filemtime(get_stylesheet_directory() . '/js/car-filter.js'), // Versioning based on file modification time
        true // Load in footer
    );
    
    // Localize the script: Pass PHP data to JS
    wp_localize_script(
        $script_handle, // The same unique handle used in wp_enqueue_script
        'carFilterData', // The JavaScript object name to access the data (e.g., carFilterData.ajaxUrl)
        $js_data // The PHP array containing the data
    );
    // --- End Enqueue and Localize Script ---
    
    return ob_get_clean();
}