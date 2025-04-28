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
    $name = pathinfo($filename, PATHINFO_FILENAME); // Remove .json
    $name = str_replace('_', ' ', $name); // Replace underscores with spaces
    $name = ucwords($name); // Capitalize words
    // Specific replacements if needed (e.g., Bmw -> BMW)
    $name = str_replace('Bmw', 'BMW', $name);
    // Add more specific replacements here if necessary
    return $name;
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

    // --- Get ACF Location Data (existing code) ---
    $location_field_key = 'location'; 
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
        $field = get_field_object( $location_field_key ); 
    }
    $all_possible_locations = array();
    if ( $field && isset($field['choices']) && is_array($field['choices']) ) {
        $all_possible_locations = $field['choices'];
        asort($all_possible_locations);
    }
    $published_location_counts_query = array();
    if (!empty($all_possible_locations)) {
        $published_location_counts_query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value, COUNT(p.ID) as count
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_value IN (" . implode(', ', array_fill(0, count($all_possible_locations), '%s')) . ")
                GROUP BY pm.meta_value",
                array_merge([$location_field_key, 'car', 'publish'], array_keys($all_possible_locations))
            ),
            OBJECT_K 
        );
    }
    // --- End ACF Location Data ---

    // --- Get Make/Model/Variant Data from JSONs ---
    $make_model_variant_data = array();
    $all_makes_from_files = array();
    $json_dir = get_stylesheet_directory() . '/simple_jsons/';

    if (is_dir($json_dir)) {
        $json_files = glob($json_dir . '*.json');
        sort($json_files); // Sort alphabetically

        foreach ($json_files as $file) {
            $make_name_formatted = format_make_name_from_filename(basename($file));
            $all_makes_from_files[] = $make_name_formatted;
            
            $json_content = file_get_contents($file);
            $data = json_decode($json_content, true);
            if ($data && is_array($data)) {
                // Assuming the top-level key in the JSON is the make name we need for structure
                // Use the *formatted* name as the key in our final structure
                $make_key_in_json = key($data); // Get the key as it appears in the JSON (e.g., "BMW")
                if (isset($data[$make_key_in_json])) {
                     $make_model_variant_data[$make_name_formatted] = $data[$make_key_in_json];
                }
            }
        }
    }
    // --- End Make/Model/Variant Data ---

    // --- Get Counts for Makes ---
    $make_counts_query = array();
    if (!empty($all_makes_from_files)) {
         $make_counts_query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value, COUNT(p.ID) as count
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status = %s
                AND pm.meta_value IN (" . implode(', ', array_fill(0, count($all_makes_from_files), '%s')) . ")
                GROUP BY pm.meta_value",
                array_merge(['make', 'car', 'publish'], $all_makes_from_files) // Use 'make' as the meta key
            ),
            OBJECT_K 
        );
    }
    // --- End Make Counts ---
    
    // --- Start Form Output ---
    ob_start();
    // Add a nonce for AJAX security
    $ajax_nonce = wp_create_nonce("car_filter_nonce");
    ?>
    <div class="car-filter-form-container context-<?php echo esc_attr($context); ?>">
        <form id="car-filter-form-<?php echo esc_attr($context); ?>" class="car-filter-form" method="get" action=""> 
            <!-- Add nonce field -->
            <input type="hidden" name="car_filter_nonce" value="<?php echo $ajax_nonce; ?>">
            
            <h2>Find Your Car</h2> 

            <!-- Location Selector (existing) -->
            <div class="filter-form-group filter-group-location">
                <label for="filter-location-<?php echo esc_attr($context); ?>">Location</label>
                <select id="filter-location-<?php echo esc_attr($context); ?>" name="filter_location">
                    <option value="">All Locations</option>
                    <?php 
                    if (!empty($all_possible_locations)):
                        foreach ( $all_possible_locations as $value => $label ) : 
                            $count = isset($published_location_counts_query[$value]) ? $published_location_counts_query[$value]->count : 0;
                            $disabled_attr = ( $count == 0 ) ? ' disabled="disabled"' : '';
                            $display_text = esc_html( $label ); 
                            if ( $count > 0 ) {
                                $display_text .= ' (' . $count . ')';
                            } else {
                                $display_text .= ' (0)';
                            }
                        ?>
                            <option value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled_attr; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php endforeach; 
                    endif; 
                    ?>
                </select>
            </div>

            <!-- Make Selector -->
            <div class="filter-form-group filter-group-make">
                <label for="filter-make-<?php echo esc_attr($context); ?>">Make</label>
                <select id="filter-make-<?php echo esc_attr($context); ?>" name="filter_make">
                    <option value="">All Makes</option>
                     <?php 
                    if (!empty($all_makes_from_files)):
                        foreach ( $all_makes_from_files as $make_name ) : 
                            $count = isset($make_counts_query[$make_name]) ? $make_counts_query[$make_name]->count : 0;
                            $disabled_attr = ( $count == 0 ) ? ' disabled="disabled"' : '';
                            $display_text = esc_html( $make_name ); 
                            if ( $count > 0 ) {
                                $display_text .= ' (' . $count . ')';
                            } else {
                                $display_text .= ' (0)';
                            }
                        ?>
                            <option value="<?php echo esc_attr( $make_name ); ?>"<?php echo $disabled_attr; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php endforeach; 
                    endif; 
                    ?>
                </select>
            </div>

            <!-- Model Selector -->
            <div class="filter-form-group filter-group-model">
                <label for="filter-model-<?php echo esc_attr($context); ?>">Model</label>
                <select id="filter-model-<?php echo esc_attr($context); ?>" name="filter_model" disabled>
                    <option value="">Select Make First</option>
                    <!-- Options populated by JS -->
                </select>
            </div>

            <!-- Variant Selector -->
            <div class="filter-form-group filter-group-variant">
                <label for="filter-variant-<?php echo esc_attr($context); ?>">Variant</label>
                <select id="filter-variant-<?php echo esc_attr($context); ?>" name="filter_variant" disabled>
                    <option value="">Select Model First</option>
                     <!-- Options populated by JS -->
               </select>
            </div>


            <div class="filter-form-actions">
                 <button type="submit" class="filter-submit-button">Search</button>
                 <!-- Reset button might be added later -->
            </div>

        </form>
    </div>

    <?php // Inline JS for dependent dropdowns - Consider moving to a separate .js file and enqueueing ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Get the data structure passed from PHP
            const makeModelVariantData = <?php echo json_encode($make_model_variant_data); ?>;
            const context = '<?php echo esc_js($context); ?>'; // Get context for unique IDs
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>'; // WordPress AJAX URL
            const nonce = '<?php echo $ajax_nonce; ?>'; // Nonce for security
            
            // Get references to the select elements using context-specific IDs
            const locationSelect = document.getElementById('filter-location-' + context);
            const makeSelect = document.getElementById('filter-make-' + context);
            const modelSelect = document.getElementById('filter-model-' + context);
            const variantSelect = document.getElementById('filter-variant-' + context);

            if (!locationSelect || !makeSelect || !modelSelect || !variantSelect) {
                console.error('Filter dropdowns not found for context:', context);
                return; // Stop if elements are missing
            }

            // Function to clear and disable a select element
            function resetSelect(selectElement, defaultText, keepEnabled = false) {
                selectElement.innerHTML = ''; // Clear existing options
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = defaultText;
                selectElement.appendChild(defaultOption);
                selectElement.disabled = !keepEnabled;
            }

            // Function to update dropdown options with counts from AJAX
            function updateOptionsWithCounts(selectElement, countsData) {
                const options = selectElement.options;
                let hasAvailableOptions = false;

                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    if (option.value === '') continue; // Skip the default "All..." option

                    const value = option.value;
                    const count = countsData[value] || 0;
                    
                    // Update text content (assuming base text is just the value)
                    option.textContent = value + ' (' + count + ')'; 
                    
                    // Update disabled state
                    if (count > 0) {
                        option.disabled = false;
                        hasAvailableOptions = true;
                    } else {
                        option.disabled = true;
                    }
                }
                // Optional: Disable the entire select if no options are available (besides default)
                // selectElement.disabled = !hasAvailableOptions && options.length > 1; 
            }

            // AJAX function to fetch counts
            async function fetchFilterCounts(level, currentFilters) {
                 console.log('Fetching counts for:', level, 'with filters:', currentFilters);
                 
                 const formData = new FormData();
                 formData.append('action', 'get_dynamic_filter_counts');
                 formData.append('nonce', nonce);
                 formData.append('target_level', level); // 'model' or 'variant'
                 formData.append('filter_location', currentFilters.location || '');
                 formData.append('filter_make', currentFilters.make || '');
                 formData.append('filter_model', currentFilters.model || '');

                 try {
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    const data = await response.json();
                    console.log('AJAX Response:', data);

                    if (data.success) {
                        return data.data; // Return the counts object
                    } else {
                        console.error('AJAX Error:', data.data || 'Unknown error');
                        return {}; // Return empty object on error
                    }
                 } catch (error) {
                    console.error('Fetch Error:', error);
                    return {}; // Return empty object on fetch error
                 }
            }

            // Event listener for Location selection (to update models/variants if needed)
            locationSelect.addEventListener('change', async function() {
                const selectedMake = makeSelect.value;
                // If a make is already selected, update model counts
                if (selectedMake) {
                    const currentFilters = {
                        location: this.value,
                        make: selectedMake
                    };
                    const modelCounts = await fetchFilterCounts('model', currentFilters);
                    updateOptionsWithCounts(modelSelect, modelCounts);
                    // If a model is also selected, update variant counts too
                    const selectedModel = modelSelect.value;
                    if(selectedModel) {
                         const variantFilters = { ...currentFilters, model: selectedModel };
                         const variantCounts = await fetchFilterCounts('variant', variantFilters);
                         updateOptionsWithCounts(variantSelect, variantCounts);
                    }
                }
                // Potentially update Make counts too, although PHP handles initial load
                // const makeCounts = await fetchFilterCounts('make', { location: this.value }); 
                // updateOptionsWithCounts(makeSelect, makeCounts); 
            });

            // Event listener for Make selection
            makeSelect.addEventListener('change', async function() {
                const selectedMake = this.value;
                const selectedLocation = locationSelect.value;
                
                resetSelect(variantSelect, 'Select Model First'); // Reset variant
                resetSelect(modelSelect, 'Select Make First'); // Reset model 

                if (selectedMake && makeModelVariantData[selectedMake]) {
                    modelSelect.disabled = false;
                    modelSelect.options[0].textContent = 'All Models'; // Change default text

                    // 1. Populate Model options based on STRUCTURE
                    const models = makeModelVariantData[selectedMake];
                    Object.keys(models).sort().forEach(model => {
                        const option = document.createElement('option');
                        option.value = model;
                        option.textContent = model; // Base text, count added by AJAX update
                        option.disabled = true; // Start disabled until counts arrive
                        modelSelect.appendChild(option);
                    });

                    // 2. Fetch and Update Model counts via AJAX
                     const currentFilters = {
                        location: selectedLocation,
                        make: selectedMake
                    };
                    const modelCounts = await fetchFilterCounts('model', currentFilters);
                    updateOptionsWithCounts(modelSelect, modelCounts);

                } 
            });

            // Event listener for Model selection
            modelSelect.addEventListener('change', async function() {
                const selectedMake = makeSelect.value;
                const selectedModel = this.value;
                const selectedLocation = locationSelect.value;

                resetSelect(variantSelect, 'Select Model First'); // Reset variant

                if (selectedMake && selectedModel && 
                    makeModelVariantData[selectedMake] && 
                    makeModelVariantData[selectedMake][selectedModel]) {
                    
                    variantSelect.disabled = false;
                    variantSelect.options[0].textContent = 'All Variants'; // Change default text

                    // 1. Populate Variant options based on STRUCTURE
                    const variants = makeModelVariantData[selectedMake][selectedModel];
                    variants.sort().forEach(variant => {
                        const option = document.createElement('option');
                        option.value = variant;
                        option.textContent = variant; // Base text, count added by AJAX update
                        option.disabled = true; // Start disabled until counts arrive
                        variantSelect.appendChild(option);
                    });

                     // 2. Fetch and Update Variant counts via AJAX
                     const currentFilters = {
                        location: selectedLocation,
                        make: selectedMake,
                        model: selectedModel
                    };
                    const variantCounts = await fetchFilterCounts('variant', currentFilters);
                    updateOptionsWithCounts(variantSelect, variantCounts);
                } 
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// Example of how you might call this on a page template:
// require_once get_stylesheet_directory() . '/includes/car-filter-form.php';
// echo display_car_filter_form('homepage'); 