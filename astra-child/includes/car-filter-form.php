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

    // --- Generate Static Ranges ---
    $current_year = date('Y');
    $years = range($current_year, 1990); // Example range
    $engine_capacities = array(); // Example range for engine size (e.g., 1.0 to 6.0)
    for ($i = 1.0; $i <= 6.0; $i += 0.1) { $engine_capacities[] = number_format($i, 1); }
    $mileages = range(0, 300000, 10000); // Example range

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
    $ajax_nonce = wp_create_nonce('car_filter_variant_nonce');
    // --- End Nonce Generation ---
    
    // --- Prepare Data for JS (for potential future dynamic updates) ---
    $js_data = [
        'context' => $context,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => $ajax_nonce,
        'makeModelVariantStructure' => $make_model_variant_data,
        'initialCounts' => [
            'location' => $published_location_counts,
            'make' => $make_counts,
            'modelByMake' => $model_counts_by_make,
            'fuelType' => $fuel_type_counts,
            'transmission' => $transmission_counts,
            'exteriorColor' => $ext_color_counts,
            'interiorColor' => $int_color_counts,
            'bodyType' => $body_type_counts,
            'driveType' => $drive_type_counts,
            // Variant counts are fetched via AJAX
        ],
        'choices' => [
             'location' => $all_possible_locations,
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
        <form id="car-filter-form-<?php echo esc_attr($context); ?>" class="car-filter-form" method="get" action=""> 
            
            <h2>Find Your Car</h2> 

            <?php // Helper function for generating select options 
            function render_select_options($choices, $counts, $selected_value = '') {
                foreach ($choices as $value => $label) {
                    $count = isset($counts[$value]) ? $counts[$value] : 0;
                    $disabled_attr = ($count == 0) ? ' disabled="disabled"' : '';
                    $display_text = esc_html($label);
                    $display_text .= ' (' . $count . ')';
                    $selected_attr = selected($selected_value, $value, false);
                    echo "<option value=\"" . esc_attr($value) . "\"{$disabled_attr}{$selected_attr}>{$display_text}</option>";
                }
            }
            function render_range_options($range, $selected_value = '', $suffix = '') {
                foreach ($range as $value) {
                    $selected_attr = selected($selected_value, $value, false);
                    echo "<option value=\"" . esc_attr($value) . "\"{$selected_attr}>" . esc_html($value . $suffix) . "</option>";
                }
            }
            ?>

            <!-- Location Selector (existing) -->
            <div class="filter-form-group filter-group-location">
                <label for="filter-location-<?php echo esc_attr($context); ?>">Location</label>
                <select id="filter-location-<?php echo esc_attr($context); ?>" name="filter_location">
                    <option value="">All Locations</option>
                    <?php render_select_options($all_possible_locations, $published_location_counts); ?>
                </select>
            </div>

            <!-- Make Selector (existing) -->
            <div class="filter-form-group filter-group-make">
                <label for="filter-make-<?php echo esc_attr($context); ?>">Make</label>
                <select id="filter-make-<?php echo esc_attr($context); ?>" name="filter_make">
                    <option value="">All Makes</option>
                     <?php 
                    if (!empty($all_makes_from_files)):
                        foreach ( $all_makes_from_files as $make_name ) : 
                            $count = isset($make_counts[$make_name]) ? $make_counts[$make_name] : 0;
                            $disabled_attr = ( $count == 0 ) ? ' disabled="disabled"' : '';
                            $display_text = esc_html( $make_name ); 
                            $display_text .= ' (' . $count . ')'; // Always show count
                        ?>
                            <option value="<?php echo esc_attr( $make_name ); ?>"<?php echo $disabled_attr; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php endforeach; 
                    endif; 
                    ?>
                </select>
            </div>

            <!-- Model Selector (existing) -->
            <div class="filter-form-group filter-group-model">
                <label for="filter-model-<?php echo esc_attr($context); ?>">Model</label>
                <select id="filter-model-<?php echo esc_attr($context); ?>" name="filter_model" disabled>
                    <option value="">Select Make First</option>
                </select>
            </div>

            <!-- Variant Selector (existing) -->
            <div class="filter-form-group filter-group-variant">
                <label for="filter-variant-<?php echo esc_attr($context); ?>">Variant</label>
                <select id="filter-variant-<?php echo esc_attr($context); ?>" name="filter_variant" disabled>
                    <option value="">Select Model First</option>
               </select>
            </div>

            <!-- Year Range -->
            <div class="filter-form-group filter-group-year">
                <label>Year</label>
                <div class="filter-range-fields">
                    <select id="filter-year-min-<?php echo esc_attr($context); ?>" name="filter_year_min">
                        <option value="">Min Year</option>
                        <?php render_range_options($years); ?>
                    </select>
                    <span class="range-separator">-</span>
                    <select id="filter-year-max-<?php echo esc_attr($context); ?>" name="filter_year_max">
                        <option value="">Max Year</option>
                         <?php render_range_options($years); ?>
                   </select>
                </div>
            </div>

             <!-- Engine Capacity Range -->
            <div class="filter-form-group filter-group-engine">
                <label>Engine (L)</label>
                 <div class="filter-range-fields">
                    <select id="filter-engine-min-<?php echo esc_attr($context); ?>" name="filter_engine_min">
                        <option value="">Min Size</option>
                         <?php render_range_options($engine_capacities, '', 'L'); ?>
                    </select>
                     <span class="range-separator">-</span>
                    <select id="filter-engine-max-<?php echo esc_attr($context); ?>" name="filter_engine_max">
                        <option value="">Max Size</option>
                        <?php render_range_options($engine_capacities, '', 'L'); ?>
                    </select>
                </div>
            </div>

            <!-- Mileage Range -->
            <div class="filter-form-group filter-group-mileage">
                <label>Mileage (km)</label>
                 <div class="filter-range-fields">
                    <select id="filter-mileage-min-<?php echo esc_attr($context); ?>" name="filter_mileage_min">
                        <option value="">Min KM</option>
                         <?php render_range_options($mileages, '', ' km'); ?>
                    </select>
                     <span class="range-separator">-</span>
                    <select id="filter-mileage-max-<?php echo esc_attr($context); ?>" name="filter_mileage_max">
                        <option value="">Max KM</option>
                        <?php render_range_options($mileages, '', ' km'); ?>
                    </select>
                </div>
            </div>

             <!-- Fuel Type Selector -->
            <div class="filter-form-group filter-group-fuel">
                <label for="filter-fuel_type-<?php echo esc_attr($context); ?>">Fuel Type</label>
                <select id="filter-fuel_type-<?php echo esc_attr($context); ?>" name="filter_fuel_type">
                    <option value="">All Fuel Types</option>
                     <?php render_select_options($fuel_type_choices, $fuel_type_counts); ?>
                </select>
            </div>

            <!-- Transmission Selector -->
            <div class="filter-form-group filter-group-transmission">
                <label for="filter-transmission-<?php echo esc_attr($context); ?>">Transmission</label>
                <select id="filter-transmission-<?php echo esc_attr($context); ?>" name="filter_transmission">
                    <option value="">All Transmissions</option>
                    <?php render_select_options($transmission_choices, $transmission_counts); ?>
                </select>
            </div>

            <!-- Body Type Selector -->
             <div class="filter-form-group filter-group-bodytype">
                <label for="filter-body_type-<?php echo esc_attr($context); ?>">Body Type</label>
                <select id="filter-body_type-<?php echo esc_attr($context); ?>" name="filter_body_type">
                    <option value="">All Body Types</option>
                    <?php render_select_options($body_type_choices, $body_type_counts); ?>
                </select>
            </div>

            <!-- Drive Type Selector -->
             <div class="filter-form-group filter-group-drivetype">
                <label for="filter-drive_type-<?php echo esc_attr($context); ?>">Drive Type</label>
                <select id="filter-drive_type-<?php echo esc_attr($context); ?>" name="filter_drive_type">
                    <option value="">All Drive Types</option>
                    <?php render_select_options($drive_type_choices, $drive_type_counts); ?>
                </select>
            </div>

            <!-- Exterior Color Selector -->
            <div class="filter-form-group filter-group-extcolor">
                <label for="filter-exterior_color-<?php echo esc_attr($context); ?>">Exterior Color</label>
                <select id="filter-exterior_color-<?php echo esc_attr($context); ?>" name="filter_exterior_color">
                    <option value="">Any Exterior Color</option>
                    <?php render_select_options($ext_color_choices, $ext_color_counts); ?>
                </select>
            </div>

            <!-- Interior Color Selector -->
             <div class="filter-form-group filter-group-intcolor">
                <label for="filter-interior_color-<?php echo esc_attr($context); ?>">Interior Color</label>
                <select id="filter-interior_color-<?php echo esc_attr($context); ?>" name="filter_interior_color">
                    <option value="">Any Interior Color</option>
                    <?php render_select_options($int_color_choices, $int_color_counts); ?>
                </select>
            </div>


            <div class="filter-form-actions">
                 <button type="submit" class="filter-submit-button">Search</button>
            </div>

        </form>
    </div>

    <?php // Inline JS - Consider moving/refining later ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const filterData = <?php echo json_encode($js_data); ?>;
            const context = filterData.context;
            const makeModelVariantData = filterData.makeModelVariantStructure;
            const modelCountsByMake = filterData.initialCounts.modelByMake;
            const ajaxNonce = filterData.nonce;
            const ajaxUrl = filterData.ajaxUrl;

            const makeSelect = document.getElementById('filter-make-' + context);
            const modelSelect = document.getElementById('filter-model-' + context);
            const variantSelect = document.getElementById('filter-variant-' + context);

            // Add refs for other selects if needed for future dynamic updates

            if (!makeSelect || !modelSelect || !variantSelect) {
                console.error('Core filter dropdowns not found for context:', context);
                return;
            }

            function resetSelect(selectElement, defaultText) {
                selectElement.innerHTML = '';
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = defaultText;
                selectElement.appendChild(defaultOption);
                selectElement.disabled = true;
            }

            // --- Make Selection Handler (Updates Models) ---
            makeSelect.addEventListener('change', function() {
                const selectedMake = this.value;
                resetSelect(variantSelect, 'Select Model First');
                resetSelect(modelSelect, 'Select Make First');

                if (selectedMake && makeModelVariantData[selectedMake]) {
                    modelSelect.disabled = false;
                    modelSelect.options[0].textContent = 'All Models';
                    const models = makeModelVariantData[selectedMake];
                    const modelCounts = modelCountsByMake[selectedMake] || {};

                    Object.keys(models).sort().forEach(model => {
                        const count = modelCounts[model] || 0;
                        const option = document.createElement('option');
                        option.value = model;
                        option.textContent = model + ' (' + count + ')';
                        if (count === 0) {
                            option.disabled = true;
                        }
                        modelSelect.appendChild(option);
                    });
                }
            });

            // --- Model Selection Handler (Updates Variants via AJAX) ---
            modelSelect.addEventListener('change', function() {
                const selectedMake = makeSelect.value;
                const selectedModel = this.value;
                resetSelect(variantSelect, 'Select Model First');

                if (selectedMake && selectedModel &&
                    makeModelVariantData[selectedMake] &&
                    makeModelVariantData[selectedMake][selectedModel]) {
                    
                    variantSelect.options[0].textContent = 'Loading Variants...';
                    variantSelect.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'get_variant_counts');
                    formData.append('make', selectedMake);
                    formData.append('model', selectedModel);
                    formData.append('nonce', ajaxNonce);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(result => {
                        resetSelect(variantSelect, 'All Variants');
                        variantSelect.disabled = false;

                        if (result.success && result.data) {
                            const variantCounts = result.data;
                            const variants = makeModelVariantData[selectedMake][selectedModel];
                            
                            variants.sort().forEach(variant => {
                                const count = variantCounts[variant] || 0;
                                const option = document.createElement('option');
                                option.value = variant;
                                option.textContent = variant + ' (' + count + ')';
                                if (count === 0) {
                                    option.disabled = true;
                                }
                                variantSelect.appendChild(option);
                            });
                        } else {
                            console.error('AJAX error fetching variants:', result.data || 'Unknown error');
                             variantSelect.options[0].textContent = 'Error loading variants';
                             variantSelect.disabled = true;
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        resetSelect(variantSelect, 'Error loading variants');
                        variantSelect.disabled = true;
                    });
                }
            });

            // Add event listeners for other fields HERE if full dynamic updates are needed later

        });
    </script>
    <?php
    return ob_get_clean();
}

// Example of how you might call this on a page template:
// require_once get_stylesheet_directory() . '/includes/car-filter-form.php';
// echo display_car_filter_form('homepage'); 