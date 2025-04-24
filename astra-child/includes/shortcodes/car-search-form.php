<?php
/**
 * Car Search Form Shortcode [car_search_form].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Car Search Form Shortcode
function car_search_form_shortcode() {
    global $wpdb;
    
    // Get all unique makes from the database with counts
    $makes_query = $wpdb->get_results(
        "SELECT meta_value, COUNT(*) as count 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'make' 
        AND post_id IN (
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'car' 
            AND post_status = 'publish'
        )
        GROUP BY meta_value
        ORDER BY meta_value ASC"
    );
    
    $makes = [];
    $make_counts = [];
    foreach ($makes_query as $row) {
        $makes[] = $row->meta_value;
        $make_counts[$row->meta_value] = $row->count;
    }
    
    // Get all unique models for each make with counts
    $models_by_make = array();
    $model_counts = array();
    foreach ($makes as $make) {
        $models_query = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'model' 
            AND post_id IN (
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'make' 
                AND meta_value = %s
                AND post_id IN (
                    SELECT ID 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'car' 
                    AND post_status = 'publish'
                )
            )
            GROUP BY meta_value
            ORDER BY meta_value ASC",
            $make
        ));
        
        $models = [];
        $model_counts[$make] = [];
        foreach ($models_query as $row) {
            $models[] = $row->meta_value;
            $model_counts[$make][$row->meta_value] = $row->count;
        }
        
        $models_by_make[$make] = $models;
    }
    
    // Get all unique variants for each make and model with counts
    $variants_by_make_model = array();
    $variant_counts = array();
    foreach ($models_by_make as $make => $models) {
        $variants_by_make_model[$make] = array();
        $variant_counts[$make] = array();
        
        foreach ($models as $model) {
            $variants_query = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_value, COUNT(*) as count 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'variant' 
                AND post_id IN (
                    SELECT post_id 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'make' 
                    AND meta_value = %s
                    AND post_id IN (
                        SELECT post_id 
                        FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'model' 
                        AND meta_value = %s
                        AND post_id IN (
                            SELECT ID 
                            FROM {$wpdb->posts} 
                            WHERE post_type = 'car' 
                            AND post_status = 'publish'
                        )
                    )
                )
                GROUP BY meta_value
                ORDER BY meta_value ASC",
                $make,
                $model
            ));
            
            $variants = [];
            $variant_counts[$make][$model] = [];
            foreach ($variants_query as $row) {
                $variants[] = $row->meta_value;
                $variant_counts[$make][$model][$row->meta_value] = $row->count;
            }
            
            $variants_by_make_model[$make][$model] = $variants;
        }
    }

    // Cyprus cities
    $cities = array(
        'Nicosia', 'Limassol', 'Larnaca', 'Paphos', 'Famagusta',
        'Kyrenia', 'Ayia Napa', 'Protaras', 'Polis', 'Peyia'
    );
    sort($cities);

    // Generate year options (current year down to 1990)
    $current_year = date('Y');
    $years = range($current_year, 1990);

    // Generate price options (1k increments up to 100k)
    $prices = range(0, 100000, 1000);

    // Generate kilometer options (10k increments up to 300k)
    $kilometers = range(0, 300000, 10000);

    // Body types
    $body_types = array(
        'Sedan', 'Hatchback', 'SUV', 'Crossover', 'Coupe',
        'Convertible', 'Estate', 'Van', 'Pickup', 'MPV'
    );

    // Colors
    $colors = array(
        'Black', 'White', 'Silver', 'Gray', 'Red',
        'Blue', 'Green', 'Yellow', 'Orange', 'Brown',
        'Beige', 'Purple', 'Pink', 'Gold', 'Bronze'
    );

    // Engine sizes (1.0 to 6.0 in 0.1 increments)
    $engine_sizes = array();
    for ($i = 1.0; $i <= 6.0; $i += 0.1) {
        $engine_sizes[] = number_format($i, 1);
    }

    // Fuel types
    $fuel_types = array(
        'Petrol', 'Diesel', 'Hybrid', 'Electric', 'LPG',
        'CNG', 'Bio-Diesel', 'Hydrogen'
    );

    ob_start();
    ?>
    <div class="car-search-container">
        <form id="car-search-form" class="car-search-form">
            <h1>Find your next car in Cyprus</h1>
            
            <!-- Location on its own row -->
            <div class="form-row location-row">
                <div class="form-group">
                    <label for="location">Location</label>
                    <select id="location" name="location">
                        <option value="">Select Location</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo esc_attr($city); ?>"><?php echo esc_html($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Make and Model together -->
            <div class="form-row make-model-row">
                <div class="form-group">
                    <label for="make">Make</label>
                    <select id="make" name="make">
                        <option value="">Select Make</option>
                        <?php foreach ($makes as $make): ?>
                            <option value="<?php echo esc_attr($make); ?>"><?php echo esc_html($make); ?> (<?php echo $make_counts[$make]; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <select id="model" name="model" disabled>
                        <option value="">Select Model</option>
                    </select>
                </div>
            </div>

            <!-- Variant on its own row -->
            <div class="form-row variant-row">
                <div class="form-group">
                    <label for="variant">Variant</label>
                    <select id="variant" name="variant" disabled>
                        <option value="">Select Variant</option>
                    </select>
                </div>
            </div>

            <!-- Min and Max Price together -->
            <div class="form-row price-row">
                <div class="form-group">
                    <label for="price_min">Min Price (€)</label>
                    <select id="price_min" name="price_min">
                        <option value="">Select Min Price</option>
                        <?php foreach ($prices as $price): ?>
                            <option value="<?php echo esc_attr($price); ?>">€<?php echo number_format($price); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price_max">Max Price (€)</label>
                    <select id="price_max" name="price_max">
                        <option value="">Select Max Price</option>
                        <?php foreach ($prices as $price): ?>
                            <option value="<?php echo esc_attr($price); ?>">€<?php echo number_format($price); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Ensure this error message container is present -->
            <div class="form-error-message" id="car-search-error" style="display: none; color: red; margin-bottom: 15px; text-align: center;"></div>

            <button type="submit" class="gradient-button">Search Cars</button>
            
            <div class="more-options-link">
                <a href="#" id="toggle-more-options">
                    More options 
                    <span class="chevron">›</span>
                </a>
            </div>
        </form>

        <div id="more-options" class="more-options">
            <h2>Additional options</h2>
            <!-- Min and Max Year together -->
            <div class="form-row year-row">
                <div class="form-group">
                    <label for="year_min">Min Year</label>
                    <select id="year_min" name="year_min">
                        <option value="">Select Min Year</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="year_max">Max Year</label>
                    <select id="year_max" name="year_max">
                        <option value="">Select Max Year</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Min and Max Kilometers together -->
            <div class="form-row km-row">
                <div class="form-group">
                    <label for="km_min">Min Kilometers</label>
                    <select id="km_min" name="km_min">
                        <option value="">Select Min KM</option>
                        <?php foreach ($kilometers as $km): ?>
                            <option value="<?php echo esc_attr($km); ?>"><?php echo number_format($km); ?> km</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="km_max">Max Kilometers</label>
                    <select id="km_max" name="km_max">
                        <option value="">Select Max KM</option>
                        <?php foreach ($kilometers as $km): ?>
                            <option value="<?php echo esc_attr($km); ?>"><?php echo number_format($km); ?> km</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

             <!-- Fuel Type on its own -->
            <div class="form-row fuel-row">
                <div class="form-group">
                    <label for="fuel_type">Fuel Type</label>
                    <select id="fuel_type" name="fuel_type">
                        <option value="">Select Fuel Type</option>
                        <?php foreach ($fuel_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Body Type and Color together -->
            <div class="form-row body-color-row">
                <div class="form-group">
                    <label for="body_type">Body Type</label>
                    <select id="body_type" name="body_type">
                        <option value="">Select Body Type</option>
                        <?php foreach ($body_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="exterior_color">Exterior Color</label>
                    <select id="exterior_color" name="exterior_color">
                        <option value="">Select Exterior Color</option>
                        <?php foreach ($colors as $color): ?>
                            <option value="<?php echo esc_attr($color); ?>"><?php echo esc_html($color); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Interior Color on its own -->
            <div class="form-row interior-color-row">
                <div class="form-group">
                    <label for="interior_color">Interior Color</label>
                    <select id="interior_color" name="interior_color">
                        <option value="">Select Interior Color</option>
                        <?php foreach ($colors as $color): ?>
                            <option value="<?php echo esc_attr($color); ?>"><?php echo esc_html($color); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Min and Max Engine Size together -->
            <div class="form-row engine-row">
                <div class="form-group">
                    <label for="engine_min">Min Engine Size</label>
                    <select id="engine_min" name="engine_min">
                        <option value="">Select Min Size</option>
                        <?php foreach ($engine_sizes as $size): ?>
                            <option value="<?php echo esc_attr($size); ?>"><?php echo $size; ?>L</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="engine_max">Max Engine Size</label>
                    <select id="engine_max" name="engine_max">
                        <option value="">Select Max Size</option>
                        <?php foreach ($engine_sizes as $size): ?>
                            <option value="<?php echo esc_attr($size); ?>"><?php echo $size; ?>L</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const makeSelect = document.getElementById('make');
        const modelSelect = document.getElementById('model');
        const variantSelect = document.getElementById('variant');
        const carData = <?php echo json_encode($variants_by_make_model); ?>;
        const modelCounts = <?php echo json_encode($model_counts); ?>;
        const variantCounts = <?php echo json_encode($variant_counts); ?>;
        const moreOptionsLink = document.getElementById('toggle-more-options');
        const moreOptions = document.getElementById('more-options');
        const searchForm = document.getElementById('car-search-form'); // Get the form
        const errorElement = document.getElementById('car-search-error'); // Get the error div

        // --- DEBUGGING --- 
        console.log('Car Search Script Loaded.');
        console.log('Search Form Found:', searchForm);
        console.log('Error Element Found:', errorElement);
        // --- END DEBUGGING ---

        // Function to hide error message
        const hideError = () => {
            if (errorElement && errorElement.style.display === 'block') {
                console.log('Hiding error message due to field input/change.'); // DEBUG
                errorElement.style.display = 'none';
                errorElement.textContent = '';
            }
        };

        // Add listeners to form elements to hide error on input/change
        if (searchForm) {
            const formElementsToListen = searchForm.querySelectorAll('select, input:not([type="submit"]):not([type="button"])');
            formElementsToListen.forEach(element => {
                element.addEventListener('change', hideError); // Use 'change' for selects
                // Add 'input' listener for text fields if needed in the future
                // if (element.type === 'text' || element.type === 'number') {
                //    element.addEventListener('input', hideError);
                // }
            });
        }

        // Toggle more options
        if (moreOptionsLink && moreOptions) { // Check elements exist
            moreOptionsLink.addEventListener('click', function(e) {
                e.preventDefault();
                const parentLink = this; // The link itself
                const isShowing = moreOptions.classList.contains('show');
                
                if (!isShowing) {
                    // First make it visible but still transparent
                    moreOptions.style.display = 'flex';
                    // Force a reflow
                    moreOptions.offsetHeight;
                    // Then add the show class to trigger the animation
                    moreOptions.classList.add('show');
                } else {
                    // Remove the show class to trigger the fade out
                    moreOptions.classList.remove('show');
                    // Wait for the animation to complete before hiding
                    setTimeout(() => {
                        if (!moreOptions.classList.contains('show')) {
                            moreOptions.style.display = 'none';
                        }
                    }, 100); // Match the transition duration
                }
                
                // this.querySelector('.chevron-icon').classList.toggle('rotate'); // Don't toggle class on icon
                parentLink.classList.toggle('rotate'); // Toggle class on parent link
                
                // Keep the container in row layout and toggle expanded class
                const container = document.querySelector('.car-search-container');
                if (container) { // Check container exists
                    // container.style.flexDirection = 'row'; // Might not be needed if CSS handles layout
                    container.classList.toggle('expanded');
                }
            });
        } else {
             console.error("Could not find #toggle-more-options or #more-options elements.");
        }

        // Update models when make changes
        makeSelect.addEventListener('change', function() {
            const make = this.value;
            modelSelect.innerHTML = '<option value="">Select Model</option>';
            variantSelect.innerHTML = '<option value="">Select Variant</option>';
            
            if (make && carData[make]) {
                modelSelect.disabled = false;
                Object.keys(carData[make]).forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model + ' (' + modelCounts[make][model] + ')';
                    modelSelect.appendChild(option);
                });
            } else {
                modelSelect.disabled = true;
                variantSelect.disabled = true;
            }
        });

        // Update variants when model changes
        modelSelect.addEventListener('change', function() {
            const make = makeSelect.value;
            const model = this.value;
            variantSelect.innerHTML = '<option value="">Select Variant</option>';
            
            if (make && model && carData[make][model]) {
                variantSelect.disabled = false;
                carData[make][model].forEach(variant => {
                    const option = document.createElement('option');
                    option.value = variant;
                    option.textContent = variant + ' (' + variantCounts[make][model][variant] + ')';
                    variantSelect.appendChild(option);
                });
            } else {
                variantSelect.disabled = true;
            }
        });

        // Form submission
        // Ensure the form element exists before adding listener
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                console.log('--- Form Submit Event Fired ---'); // DEBUG

                let hasValue = false;
                const formElements = this.elements;

                // Ensure the error element exists
                if (!errorElement) {
                    console.error("Search form error element '#car-search-error' not found.");
                    // Optionally submit anyway or show a generic alert
                    // alert('An error occurred validating the form.'); 
                    return; 
                }
                
                errorElement.textContent = ''; // Clear previous errors
                errorElement.style.display = 'none'; // Hide error message initially

                // Check if at least one field has a value (excluding the submit button)
                for (let i = 0; i < formElements.length; i++) {
                    const element = formElements[i];
                    
                    // Skip buttons or elements without names
                    if (!element.name || element.type === 'submit' || element.type === 'button') {
                        continue;
                    }
                    
                    // Check based on element type
                    if (element.tagName === 'SELECT') {
                        // --- DEBUGGING --- 
                        console.log('Checking Select:', element.name, 'Index:', element.selectedIndex);
                        // --- END DEBUGGING ---
                        if (element.selectedIndex > 0) { // Assumes index 0 is the placeholder "Select..."
                            hasValue = true;
                            // --- DEBUGGING ---
                            console.log('-> hasValue set to true by select:', element.name);
                            // --- END DEBUGGING ---
                            break;
                        }
                    } else if (element.value && element.value.trim() !== '') { // Check other input types (text, number, etc.)
                        hasValue = true;
                        // --- DEBUGGING ---
                        console.log('-> hasValue set to true by input:', element.name);
                        // --- END DEBUGGING ---
                        break;
                    }
                }
                
                // --- DEBUGGING --- 
                console.log('Validation loop finished. hasValue:', hasValue);
                // --- END DEBUGGING ---

                if (!hasValue) {
                    // --- DEBUGGING --- 
                    console.log('No value found. Displaying error message.');
                    if (!errorElement) {
                        console.error('Cannot display error because errorElement is null!');
                    } else {
                         errorElement.textContent = 'Please select at least one search criteria.';
                         errorElement.style.display = 'block'; // Show the error message
                    }
                    // --- END DEBUGGING ---
                    return; // Stop submission if no criteria are selected
                }

                // If validation passes, proceed with form submission logic
                // Replace console.log with actual submission (e.g., redirect with query params)
                console.log('Form validation passed. Submitting search...');
                
                const formData = new FormData(this);
                const params = new URLSearchParams();
                formData.forEach((value, key) => {
                    // Only add parameters that have a value
                    if (value && value.trim() !== '') {
                        params.append(key, value);
                    }
                });
                
                // Construct the URL for the search results page (replace '/car_listings/' if needed)
                const searchUrl = '<?php echo home_url("/car_listings/"); ?>?' + params.toString();
                window.location.href = searchUrl; // Redirect to search results
            });
        } else {
            console.error("Search form element '#car-search-form' not found.");
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('car_search_form', 'car_search_form_shortcode');