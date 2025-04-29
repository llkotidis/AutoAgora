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
        <form id="car-filter-form-<?php echo esc_attr($context); ?>" class="car-filter-form" method="get" action="">

            <h2>Find Your Car</h2>

            <?php // Helper function for generating select options
            function render_select_options($choices, $counts, $selected_value = '') {
                foreach ($choices as $value => $label) {
                    $count = isset($counts[$value]) ? $counts[$value] : 0;
                    // Initial render - disable based on initial counts
                    $disabled_attr = ($count == 0 && $selected_value !== $value) ? ' disabled="disabled"' : '';
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

            <!-- Location Selector -->
            <div class="filter-form-group filter-group-location">
                <label for="filter-location-<?php echo esc_attr($context); ?>">Location</label>
                <select id="filter-location-<?php echo esc_attr($context); ?>" name="filter_location" data-filter-key="location">
                    <option value="">All Locations</option>
                    <?php render_select_options($all_possible_locations, $published_location_counts); ?>
                </select>
            </div>

            <!-- Make Selector -->
            <div class="filter-form-group filter-group-make">
                <label for="filter-make-<?php echo esc_attr($context); ?>">Make</label>
                <select id="filter-make-<?php echo esc_attr($context); ?>" name="filter_make" data-filter-key="make">
                    <option value="">All Makes</option>
                     <?php
                    if (!empty($all_makes_from_files)):
                        // Create a choices array for render_select_options
                        $make_choices_assoc = array_combine($all_makes_from_files, $all_makes_from_files);
                        render_select_options($make_choices_assoc, $make_counts);
                    endif;
                    ?>
                </select>
            </div>

            <!-- Model Selector -->
            <div class="filter-form-group filter-group-model">
                <label for="filter-model-<?php echo esc_attr($context); ?>">Model</label>
                <select id="filter-model-<?php echo esc_attr($context); ?>" name="filter_model" data-filter-key="model" disabled>
                    <option value="">Select Make First</option>
                    <?php
                        // Initially empty or potentially pre-populated if make is pre-selected
                        // JS will handle dynamic population based on Make selection and AJAX counts
                    ?>
                </select>
            </div>

            <!-- Variant Selector -->
            <div class="filter-form-group filter-group-variant">
                <label for="filter-variant-<?php echo esc_attr($context); ?>">Variant</label>
                <select id="filter-variant-<?php echo esc_attr($context); ?>" name="filter_variant" data-filter-key="variant" disabled>
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
                    <select id="filter-year-min-<?php echo esc_attr($context); ?>" name="filter_year_min" data-filter-key="year_min">
                        <option value="">Min Year</option>
                        <?php render_range_options($years); ?>
                    </select>
                    <span class="range-separator">-</span>
                    <select id="filter-year-max-<?php echo esc_attr($context); ?>" name="filter_year_max" data-filter-key="year_max">
                        <option value="">Max Year</option>
                         <?php render_range_options($years); ?>
                   </select>
                </div>
            </div>

             <!-- Engine Capacity Range -->
            <div class="filter-form-group filter-group-engine">
                <label>Engine (L)</label>
                 <div class="filter-range-fields">
                    <select id="filter-engine-min-<?php echo esc_attr($context); ?>" name="filter_engine_min" data-filter-key="engine_min">
                        <option value="">Min Size</option>
                         <?php render_range_options($engine_capacities, '', 'L'); ?>
                    </select>
                     <span class="range-separator">-</span>
                    <select id="filter-engine-max-<?php echo esc_attr($context); ?>" name="filter_engine_max" data-filter-key="engine_max">
                        <option value="">Max Size</option>
                        <?php render_range_options($engine_capacities, '', 'L'); ?>
                    </select>
                </div>
            </div>

            <!-- Mileage Range -->
            <div class="filter-form-group filter-group-mileage">
                <label>Mileage (km)</label>
                 <div class="filter-range-fields">
                    <select id="filter-mileage-min-<?php echo esc_attr($context); ?>" name="filter_mileage_min" data-filter-key="mileage_min">
                        <option value="">Min KM</option>
                         <?php render_range_options($mileages, '', ' km'); ?>
                    </select>
                     <span class="range-separator">-</span>
                    <select id="filter-mileage-max-<?php echo esc_attr($context); ?>" name="filter_mileage_max" data-filter-key="mileage_max">
                        <option value="">Max KM</option>
                        <?php render_range_options($mileages, '', ' km'); ?>
                    </select>
                </div>
            </div>

             <!-- Fuel Type Selector -->
            <div class="filter-form-group filter-group-fuel">
                <label for="filter-fuel_type-<?php echo esc_attr($context); ?>">Fuel Type</label>
                <select id="filter-fuel_type-<?php echo esc_attr($context); ?>" name="filter_fuel_type" data-filter-key="fuel_type">
                    <option value="">All Fuel Types</option>
                     <?php render_select_options($fuel_type_choices, $fuel_type_counts); ?>
                </select>
            </div>

            <!-- Transmission Selector -->
            <div class="filter-form-group filter-group-transmission">
                <label for="filter-transmission-<?php echo esc_attr($context); ?>">Transmission</label>
                <select id="filter-transmission-<?php echo esc_attr($context); ?>" name="filter_transmission" data-filter-key="transmission">
                    <option value="">All Transmissions</option>
                    <?php render_select_options($transmission_choices, $transmission_counts); ?>
                </select>
            </div>

            <!-- Body Type Selector -->
             <div class="filter-form-group filter-group-bodytype">
                <label for="filter-body_type-<?php echo esc_attr($context); ?>">Body Type</label>
                <select id="filter-body_type-<?php echo esc_attr($context); ?>" name="filter_body_type" data-filter-key="body_type">
                    <option value="">All Body Types</option>
                    <?php render_select_options($body_type_choices, $body_type_counts); ?>
                </select>
            </div>

            <!-- Drive Type Selector -->
             <div class="filter-form-group filter-group-drivetype">
                <label for="filter-drive_type-<?php echo esc_attr($context); ?>">Drive Type</label>
                <select id="filter-drive_type-<?php echo esc_attr($context); ?>" name="filter_drive_type" data-filter-key="drive_type">
                    <option value="">All Drive Types</option>
                    <?php render_select_options($drive_type_choices, $drive_type_counts); ?>
                </select>
            </div>

            <!-- Exterior Color Selector -->
            <div class="filter-form-group filter-group-extcolor">
                <label for="filter-exterior_color-<?php echo esc_attr($context); ?>">Exterior Color</label>
                <select id="filter-exterior_color-<?php echo esc_attr($context); ?>" name="filter_exterior_color" data-filter-key="exterior_color">
                    <option value="">Any Exterior Color</option>
                    <?php render_select_options($ext_color_choices, $ext_color_counts); ?>
                </select>
            </div>

            <!-- Interior Color Selector -->
             <div class="filter-form-group filter-group-intcolor">
                <label for="filter-interior_color-<?php echo esc_attr($context); ?>">Interior Color</label>
                <select id="filter-interior_color-<?php echo esc_attr($context); ?>" name="filter_interior_color" data-filter-key="interior_color">
                    <option value="">Any Interior Color</option>
                    <?php render_select_options($int_color_choices, $int_color_counts); ?>
                </select>
            </div>


            <div class="filter-form-actions">
                 <button type="submit" class="filter-submit-button">Search</button>
                 <button type="button" class="filter-reset-button">Reset</button> <!-- Optional: Add a reset button -->
            </div>

        </form>
    </div>

    <?php // Inline JS - Handles interdependent filtering ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const filterData = <?php echo json_encode($js_data); ?>;
            const context = filterData.context;
            const ajaxUrl = filterData.ajaxUrl;
            const updateNonce = filterData.updateNonce;
            const updateAction = filterData.updateAction;
            const makeModelVariantStructure = filterData.makeModelVariantStructure;
            const allChoices = filterData.choices; // All possible static choices (ACF)

            const form = document.getElementById('car-filter-form-' + context);
            const container = form.closest('.car-filter-form-container');
            const filterSelects = form.querySelectorAll('select[data-filter-key]');
            const resetButton = form.querySelector('.filter-reset-button');

            // --- Helper: Get all current filter values --- 
            function getCurrentFilters() {
                const filters = {};
                filterSelects.forEach(select => {
                    const key = select.getAttribute('data-filter-key');
                    if (key && select.value) {
                        filters[key] = select.value;
                    }
                });
                return filters;
            }

            // --- Helper: Update Options in a Select Dropdown --- 
            function updateSelectOptions(selectElement, choices, counts, defaultOptionText, keepExistingValue = true) {
                const currentVal = selectElement.value;
                const filterKey = selectElement.getAttribute('data-filter-key');
                
                selectElement.innerHTML = ''; // Clear existing options

                // Add the default "All/Any..." option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = defaultOptionText;
                selectElement.appendChild(defaultOption);

                // Add the actual filter options
                Object.entries(choices).sort(([,a],[,b]) => a.localeCompare(b)).forEach(([value, label]) => {
                    const count = counts[value] || 0;
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label + ' (' + count + ')';
                    option.disabled = (count === 0);
                    selectElement.appendChild(option);
                });

                // Restore previous selection if possible and desired
                if (keepExistingValue && currentVal && selectElement.querySelector(`option[value="${currentVal}"]:not([disabled])`)) {
                    selectElement.value = currentVal;
                } else {
                     selectElement.value = ''; // Reset if previous value is no longer valid/available
                }

                // Special handling for model/variant enablement
                if (filterKey === 'model') {
                    const makeSelect = form.querySelector('#filter-make-' + context);
                    selectElement.disabled = !makeSelect.value; 
                }
                if (filterKey === 'variant') {
                     const modelSelect = form.querySelector('#filter-model-' + context);
                     selectElement.disabled = !modelSelect.value; 
                }
            }

            // --- Main Filter Update Function (AJAX Call) --- 
            function handleFilterChange() {
                const currentFilters = getCurrentFilters();

                const formData = new FormData();
                formData.append('action', updateAction);
                formData.append('nonce', updateNonce);
                // Send filters as an object string (PHP will parse it)
                // Using JSON.stringify is more robust for complex values if needed later
                for (const key in currentFilters) {
                     formData.append(`filters[${key}]`, currentFilters[key]);
                }
                
                fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(response => {
                         if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                         }
                         return response.json();
                     })
                    .then(result => {
                        if (result.success && result.data) {
                            const updatedCounts = result.data;

                            // Update each select based on the new counts
                            filterSelects.forEach(select => {
                                const filterKey = select.getAttribute('data-filter-key');
                                
                                // Skip range inputs, they don't get counts back
                                if (filterKey.endsWith('_min') || filterKey.endsWith('_max')) {
                                    return;
                                }
                                
                                let choicesForThisSelect = {};
                                let defaultText = 'All'; // Generic default

                                switch (filterKey) {
                                    case 'location':
                                        choicesForThisSelect = allChoices.location || {};
                                        defaultText = 'All Locations';
                                        break;
                                    case 'make':
                                        // Choices come from initial PHP render (all makes from files)
                                        // We just need to update counts
                                        const makeOptions = select.querySelectorAll('option');
                                        makeOptions.forEach(opt => {
                                             if (opt.value) { // skip default option
                                                const count = updatedCounts.make[opt.value] || 0;
                                                opt.textContent = opt.value + ' (' + count + ')'; // Assuming value and label are the same for makes
                                                opt.disabled = (count === 0);
                                             }
                                        });
                                        // Re-select current value manually for make since we didn't use updateSelectOptions
                                        const currentMake = select.value;
                                        if (currentMake && select.querySelector(`option[value="${currentMake}"]:not([disabled])`)) {
                                            select.value = currentMake;
                                        } else {
                                            select.value = '';
                                        }
                                        break; // Skip generic update for make
                                    case 'model':
                                        const selectedMake = form.querySelector('#filter-make-' + context).value;
                                        if (selectedMake && makeModelVariantStructure[selectedMake]) {
                                            // Models depend on the selected Make
                                            choicesForThisSelect = Object.keys(makeModelVariantStructure[selectedMake])
                                                                        .reduce((obj, key) => { obj[key] = key; return obj; }, {});
                                             defaultText = 'All Models';
                                        } else {
                                             choicesForThisSelect = {}; // No make selected, no models to show
                                             defaultText = 'Select Make First';
                                        }
                                        break;
                                    case 'variant':
                                         const selMake = form.querySelector('#filter-make-' + context).value;
                                         const selModel = form.querySelector('#filter-model-' + context).value;
                                        if (selMake && selModel && makeModelVariantStructure[selMake] && makeModelVariantStructure[selMake][selModel]) {
                                            // Variants depend on selected Make AND Model
                                             choicesForThisSelect = makeModelVariantStructure[selMake][selModel]
                                                                         .reduce((obj, key) => { obj[key] = key; return obj; }, {});
                                            defaultText = 'All Variants';
                                        } else {
                                             choicesForThisSelect = {}; // No model selected, no variants
                                             defaultText = 'Select Model First';
                                        }
                                        break;
                                    case 'fuel_type':
                                        choicesForThisSelect = allChoices.fuelType || {};
                                        defaultText = 'All Fuel Types';
                                        break;
                                     case 'transmission':
                                        choicesForThisSelect = allChoices.transmission || {};
                                        defaultText = 'All Transmissions';
                                        break;
                                    case 'body_type':
                                        choicesForThisSelect = allChoices.bodyType || {};
                                        defaultText = 'All Body Types';
                                        break;
                                    case 'drive_type':
                                         choicesForThisSelect = allChoices.driveType || {};
                                         defaultText = 'All Drive Types';
                                         break;
                                     case 'exterior_color':
                                        choicesForThisSelect = allChoices.exteriorColor || {};
                                         defaultText = 'Any Exterior Color';
                                         break;
                                     case 'interior_color':
                                         choicesForThisSelect = allChoices.interiorColor || {};
                                         defaultText = 'Any Interior Color';
                                         break;
                                    // Add other simple filters here if needed
                                }
                                
                                // Use the helper to update options for most fields
                                if (filterKey !== 'make') { // Make was handled specially
                                    const countsForThisSelect = updatedCounts[filterKey] || {};
                                     updateSelectOptions(select, choicesForThisSelect, countsForThisSelect, defaultText);
                                }

                            });
                            
                            // Ensure Model/Variant selects are enabled/disabled correctly after update
                            const makeSelect = form.querySelector('#filter-make-' + context);
                            const modelSelect = form.querySelector('#filter-model-' + context);
                            const variantSelect = form.querySelector('#filter-variant-' + context);
                            modelSelect.disabled = !makeSelect.value;
                            variantSelect.disabled = !modelSelect.value;

                        } else {
                            console.error('AJAX error fetching filter counts:', result.data || 'Unknown error');
                            // Maybe show an error message to the user
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        // Maybe show an error message to the user
                    })
                    .finally(() => {
                         // loadingOverlay removed
                    });
            }

            // --- Event Listeners --- 
            filterSelects.forEach(select => {
                select.addEventListener('change', handleFilterChange);
            });

             // --- Reset Button Listener (Optional) ---
             if (resetButton) {
                 resetButton.addEventListener('click', () => {
                     form.reset(); // Reset native form elements
                     // Manually trigger update after reset to refresh counts/options
                     handleFilterChange(); 
                 });
             }

            // --- Initial Setup --- 
            // Disable Model/Variant initially if Make/Model aren't pre-selected
            const initialMake = form.querySelector('#filter-make-' + context).value;
            const initialModel = form.querySelector('#filter-model-' + context).value;
            if (!initialMake) {
                 form.querySelector('#filter-model-' + context).disabled = true;
            }
             if (!initialModel) {
                 form.querySelector('#filter-variant-' + context).disabled = true;
             }
             // TODO: Consider if an initial AJAX call is needed on page load
             // if filters might be pre-populated (e.g., from URL parameters)
             // handleFilterChange(); // Uncomment to run initial update

        });
    </script>
    <?php
    return ob_get_clean();
}

// Example of how you might call this on a page template:
// require_once get_stylesheet_directory() . '/includes/car-filter-form.php';
// echo display_car_filter_form('homepage'); 