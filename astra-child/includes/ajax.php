<?php
/**
 * AJAX Handlers (e.g., for OTP verification).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * AJAX handler to send OTP.
 */
function ajax_send_otp() {
    // Verify AJAX nonce (consider adding this for security if not already done)
    // check_ajax_referer( 'your_nonce_action', 'nonce' );

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    if (empty($phone)) {
        wp_send_json_error(array('message' => esc_html__('Phone number is required.', 'astra-child')));
        return;
    }

    // *** ADD USER EXISTENCE CHECK HERE ***
    $user_by_phone = get_users(array(
        'meta_key' => 'phone_number',
        'meta_value' => $phone,
        'number' => 1,
        'count_total' => false,
    ));
    if (!empty($user_by_phone)) {
        wp_send_json_error(array('message' => esc_html__('This phone number is already registered.', 'astra-child')));
        return; // Stop execution if user exists
    }
    // *** END USER EXISTENCE CHECK ***

    $twilio_sid       = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token     = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing.');
        wp_send_json_error(array('message' => esc_html__('SMS configuration error. Please contact admin.', 'astra-child')));
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($phone, "sms");

        error_log("Verification started: SID " . $verification->sid);
        wp_send_json_success(array('message' => esc_html__('Verification code sent successfully.', 'astra-child')));
    } catch (\Twilio\Exceptions\RestException $e) {
        error_log('Twilio Verify error: ' . $e->getMessage());
        wp_send_json_error(array('message' => esc_html__('Failed to send verification code. Please try again later.', 'astra-child')));
    }
}

add_action('wp_ajax_nopriv_send_otp', 'ajax_send_otp');
add_action('wp_ajax_send_otp', 'ajax_send_otp');


/**
 * AJAX handler to verify OTP.
 */
function ajax_verify_otp() {
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $code = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';

    if (empty($phone) || empty($code)) {
        wp_send_json_error(array('message' => esc_html__('Phone and code are required.', 'astra-child')));
        return;
    }

    $twilio_sid       = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token     = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        wp_send_json_error(array('message' => esc_html__('Twilio Verify config is missing.', 'astra-child')));
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification_check = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create([
                'to' => $phone,
                'code' => $code
            ]);

        if ($verification_check->status === 'approved') {
            wp_send_json_success(array('message' => esc_html__('Phone number verified successfully!', 'astra-child')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Invalid verification code.', 'astra-child')));
        }
    } catch (\Twilio\Exceptions\RestException $e) {
        error_log('Twilio Verify check error: ' . $e->getMessage());
        wp_send_json_error(array('message' => esc_html__('Verification failed. Try again.', 'astra-child')));
    }
}

add_action('wp_ajax_nopriv_verify_otp', 'ajax_verify_otp');
add_action('wp_ajax_verify_otp', 'ajax_verify_otp');

/**
 * AJAX handler for filtering car listings.
 */
function ajax_filter_car_listings_handler() {
    // Verify Nonce
    check_ajax_referer('filter_car_listings_nonce', 'nonce');

    // Get parameters from AJAX request (use $_POST)
    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;

    // --- Sanitize filter values (Add more as needed!) ---
    $sanitized_filters = array();
    foreach ($filters as $key => $value) {
        if (is_array($value)) {
            $sanitized_filters[$key] = array_map('sanitize_text_field', $value);
        } else {
            // Basic sanitization, adjust based on field type (e.g., intval for numeric)
            if (in_array($key, ['price_min', 'price_max', 'year_min', 'year_max', 'km_min', 'km_max'])) {
                 $sanitized_filters[$key] = intval($value);
            } elseif (in_array($key, ['engine_min', 'engine_max'])) {
                 $sanitized_filters[$key] = floatval($value); // Allow decimals for engine size
            } else {
                $sanitized_filters[$key] = sanitize_text_field($value);
            }
        }
    }
    // --- End Sanitization ---

    // Prepare args for WP_Query using your existing logic
    // Reconstruct 'atts' based on sanitized filters for the query function if needed
    $atts_for_query = array(
         'per_page' => 12, // Or get from $filters if passed
         'orderby' => 'date', // Or get from $filters if passed
         'order' => 'DESC' // Or get from $filters if passed
     );

    // Include the query builder file
    require_once get_stylesheet_directory() . '/includes/car-listings-query.php';
    // require_once get_stylesheet_directory() . '/includes/template-tags.php'; // File does not exist - Removed

    // Build query args - Assuming build_car_listings_query_args accepts filters directly now
    $args = build_car_listings_query_args($atts_for_query, $paged, $sanitized_filters); 

    $car_query = new WP_Query($args);

    // Generate HTML for listings
    ob_start();
    if ($car_query->have_posts()) :
        while ($car_query->have_posts()) : $car_query->the_post();
            // --- START Card Rendering Logic (copied from includes/car-listings.php) ---
            $make = get_field('make', get_the_ID());
            $model = get_field('model', get_the_ID());
            $variant = get_field('variant', get_the_ID());
            $price = get_field('price', get_the_ID());
            $year = get_field('year', get_the_ID());
            $mileage = get_field('mileage', get_the_ID());
            $car_city_handler = get_field('car_city', get_the_ID());
            $car_district_handler = get_field('car_district', get_the_ID());
            $display_location_handler = '';
            if (!empty($car_city_handler) && !empty($car_district_handler)) {
                $display_location_handler = $car_city_handler . ' - ' . $car_district_handler;
            } elseif (!empty($car_city_handler)) {
                $display_location_handler = $car_city_handler;
            } elseif (!empty($car_district_handler)) {
                $display_location_handler = $car_district_handler;
            }
            $engine_capacity = get_field('engine_capacity', get_the_ID());
            $transmission = get_field('transmission', get_the_ID());
            $body_type = get_field('body_type', get_the_ID());
            ?>
            <div class="car-listing-card">
                <?php 
                // Get all car images
                $featured_image = get_post_thumbnail_id(get_the_ID());
                $additional_images = function_exists('get_field') ? get_field('car_images', get_the_ID()) : null; // Check if ACF is active
                $all_images = array();
                
                if ($featured_image) {
                    $all_images[] = $featured_image;
                }
                
                if (is_array($additional_images)) {
                    $all_images = array_merge($all_images, $additional_images);
                }
                
                if (!empty($all_images)) {
                    echo '<div class="car-listing-image-container">';
                    echo '<div class="car-listing-image-carousel" data-post-id="' . get_the_ID() . '">';
                    
                    foreach ($all_images as $index => $image_id) {
                        $image_url = wp_get_attachment_image_url($image_id, 'medium');
                        if ($image_url) {
                            $clean_year = str_replace(',', '', $year); // Remove comma from year
                            echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . $index . '">';
                            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">'; // Use clean year in alt
                            if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                echo '<a href="' . esc_url(get_permalink(get_the_ID())) . '" class="see-all-images" style="display: none;">See All Images</a>';
                            }
                            echo '</div>';
                        }
                    }
                    
                    $user_id = get_current_user_id();
                    $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                    $is_favorite = in_array(get_the_ID(), $favorite_cars);
                    $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                    echo '<button class="' . esc_attr($button_class) . '" data-car-id="' . get_the_ID() . '"><i class="' . esc_attr($heart_class) . '"></i></button>';
                    
                    echo '<button class="carousel-nav prev" style="display: none;"><i class="fas fa-chevron-left"></i></button>';
                    echo '<button class="carousel-nav next" style="display: none;"><i class="fas fa-chevron-right"></i></button>';
                    
                    echo '</div>'; // close .car-listing-image-carousel
                    echo '</div>'; // close .car-listing-image-container
                }
                ?>
                
                <a href="<?php echo esc_url(get_permalink(get_the_ID())); ?>" class="car-listing-link">
                    <div class="car-listing-details">
                        <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                        <div class="car-specs">
                            <?php 
                            $specs_array = array();
                            if (!empty($engine_capacity)) {
                                $specs_array[] = esc_html($engine_capacity) . 'L';
                            }

                            // $fuel_type = get_post_meta(get_the_ID(), 'fuel_type', true); // Removed
                            // if (!empty($fuel_type)) { // Removed
                            //     $specs_array[] = esc_html($fuel_type); // Removed
                            // } // Removed
                            
                            if (!empty($body_type)) {
                                $specs_array[] = esc_html($body_type);
                            }

                            if (!empty($transmission)) {
                                $specs_array[] = esc_html($transmission);
                            }
                            
                            $drive_type = get_field('drive_type', get_the_ID());
                            if (!empty($drive_type)) {
                               $specs_array[] = esc_html($drive_type);
                            }

                            echo implode(' | ', $specs_array);
                            ?>
                        </div>
                        <div class="car-info-boxes">
                            <div class="info-box">
                                <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                            </div>
                            <div class="info-box">
                                <span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage))); ?> km</span>
                            </div>
                        </div>
                        <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price))); ?></div>
                        <div class="car-listing-additional-info">
                            <?php 
                            $publication_date = get_field('publication_date', get_the_ID());
                            if (!$publication_date) {
                                $publication_date = get_the_date('Y-m-d H:i:s');
                                update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                            }
                            $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                            echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                            ?>
                            <p class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html($display_location_handler); ?></span></p>
                        </div>
                    </div>
                </a>
            </div>
            <?php
            // --- END Card Rendering Logic ---
        endwhile;
    else :
        echo '<p class="no-listings">No car listings found matching your criteria.</p>';
    endif;
    $listings_html = ob_get_clean();

    // Generate HTML for pagination
    ob_start();
    echo paginate_links(array(
        'total' => $car_query->max_num_pages,
        'current' => $paged,
        'format' => '?paged=%#%',
        'base' => str_replace( PHP_INT_MAX, '%#%', esc_url( get_pagenum_link( PHP_INT_MAX ) ) ), // Correct base for AJAX
        'prev_text' => '&laquo; Previous',
        'next_text' => 'Next &raquo;'
    ));
    $pagination_html = ob_get_clean();

    wp_reset_postdata();

    // Send JSON response
    wp_send_json_success(array(
        'listings_html' => $listings_html,
        'pagination_html' => $pagination_html
    ));

    // Always die in functions echoing AJAX content
    wp_die();
}
add_action('wp_ajax_filter_car_listings', 'ajax_filter_car_listings_handler');
add_action('wp_ajax_nopriv_filter_car_listings', 'ajax_filter_car_listings_handler');

/**
 * AJAX handler to get variant counts for the car filter form.
 */
function get_variant_counts_ajax_handler() {
    // 1. Verify Nonce
    check_ajax_referer('car_filter_variant_nonce', 'nonce');

    // 2. Get and Sanitize Inputs
    $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';

    if (empty($make) || empty($model)) {
        wp_send_json_error('Missing make or model parameters.');
        return;
    }

    // 3. Query Database
    global $wpdb;
    $variant_counts = array();

    try {
        $sql = $wpdb->prepare(
            "SELECT pm_variant.meta_value as variant, COUNT(p.ID) as count
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_make ON p.ID = pm_make.post_id AND pm_make.meta_key = 'make' AND pm_make.meta_value = %s
             JOIN {$wpdb->postmeta} pm_model ON p.ID = pm_model.post_id AND pm_model.meta_key = 'model' AND pm_model.meta_value = %s
             JOIN {$wpdb->postmeta} pm_variant ON p.ID = pm_variant.post_id AND pm_variant.meta_key = 'variant'
             WHERE p.post_type = 'car'
             AND p.post_status = 'publish'
             AND pm_variant.meta_value IS NOT NULL AND pm_variant.meta_value != ''
             GROUP BY pm_variant.meta_value",
            $make,
            $model
        );
        $results = $wpdb->get_results($sql, OBJECT_K); // Index by variant name

        if ($results) {
             // Extract counts into a simple variant => count array
             foreach ($results as $variant_name => $data) {
                $variant_counts[$variant_name] = (int)$data->count;
             }
        }
        // If no results, $variant_counts remains empty, which is correct.

        wp_send_json_success($variant_counts);

    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Database error in get_variant_counts_ajax_handler: " . $e->getMessage());
        wp_send_json_error('Database error fetching variant counts.');
    }
}
// Hook for logged-in users
add_action('wp_ajax_get_variant_counts', 'get_variant_counts_ajax_handler');
// Hook for non-logged-in users (optional, if the filter is for everyone)
add_action('wp_ajax_nopriv_get_variant_counts', 'get_variant_counts_ajax_handler');

/**
 * AJAX handler to dynamically update counts for all filter fields.
 */
function ajax_update_filter_counts_handler() {
    check_ajax_referer('car_filter_update_nonce', 'nonce');
    $debug_info = [];

    $raw_filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();

    // Define all filterable fields, their meta keys, types, and how they are stored/queried.
    // Type can be: 'simple', 'multi' (for ACF checkbox/multi-select stored as array or comma-separated string),
    // 'range_year', 'range_price', 'range_mileage', 'range_engine_capacity'
    // 'is_numeric' helps in direct numeric comparisons for ranges.
    // 'multi_source_type' indicates if ACF stores it as an array or comma-separated string for 'multi' type.
    $filter_definitions = [
        'make'           => ['meta_key' => 'make', 'type' => 'simple'],
        'model'          => ['meta_key' => 'model', 'type' => 'simple'],
        'variant'        => ['meta_key' => 'variant', 'type' => 'simple'],
        'location'       => ['meta_key' => 'car_city', 'type' => 'simple'], // Assuming car_city or a combined field
        'year'           => ['meta_key' => 'year', 'type' => 'range_year', 'is_numeric' => true],
        'price'          => ['meta_key' => 'price', 'type' => 'range_price', 'is_numeric' => true],
        'mileage'        => ['meta_key' => 'mileage', 'type' => 'range_mileage', 'is_numeric' => true],
        'engine_capacity'=> ['meta_key' => 'engine_capacity', 'type' => 'range_engine_capacity', 'is_numeric' => true], // Note: value might be "1.6L" string
        'fuel_type'      => ['meta_key' => 'fuel_type', 'type' => 'multi', 'multi_source_type' => 'array'], // Example: ACF Checkbox
        'transmission'   => ['meta_key' => 'transmission', 'type' => 'multi', 'multi_source_type' => 'array'],
        'body_type'      => ['meta_key' => 'body_type', 'type' => 'multi', 'multi_source_type' => 'array'],
        'drive_type'     => ['meta_key' => 'drive_type', 'type' => 'multi', 'multi_source_type' => 'array'],
        'exterior_color' => ['meta_key' => 'exterior_color', 'type' => 'multi', 'multi_source_type' => 'array'],
        'interior_color' => ['meta_key' => 'interior_color', 'type' => 'multi', 'multi_source_type' => 'array'],
        // Add other filters here as needed
    ];

    // Sanitize all incoming filters
    $sanitized_filters = array();
    foreach ($raw_filters as $key => $value) {
        if (empty($value)) continue;

        $base_key = str_replace(['_min', '_max'], '', $key);
        $definition = $filter_definitions[$base_key] ?? null;

        if (is_array($value)) {
            $sanitized_filters[$key] = array_map('sanitize_text_field', $value);
        } elseif (isset($definition['is_numeric']) && $definition['is_numeric']) {
            if (is_numeric(str_replace(',', '', $value))) {
                 $sanitized_filters[$key] = floatval(str_replace(',', '', $value));
            } else {
                $sanitized_filters[$key] = sanitize_text_field($value); // e.g. "1.6L"
            }
        } else {
            $sanitized_filters[$key] = sanitize_text_field($value);
        }
    }
    $debug_info['sanitized_filters'] = $sanitized_filters;

    // ---- START: Step 1 - Get Post IDs based on Location Filter (if active) ----
    $location_filtered_post_ids = null;
    $filter_lat    = isset($sanitized_filters['lat']) ? floatval($sanitized_filters['lat']) : null;
    $filter_lng    = isset($sanitized_filters['lng']) ? floatval($sanitized_filters['lng']) : null;
    $filter_radius = isset($sanitized_filters['radius']) ? floatval($sanitized_filters['radius']) : null;

    if ($filter_lat !== null && $filter_lng !== null && $filter_radius !== null && $filter_radius > 0) {
        $all_car_ids_args = array(
            'post_type'      => 'car',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array('key' => 'car_latitude', 'compare' => 'EXISTS'),
                array('key' => 'car_longitude', 'compare' => 'EXISTS'),
                array(
                    'relation' => 'OR',
                    array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
                    array('key' => 'is_sold', 'value' => '1', 'compare' => '!=')
                )
            ),
        );
        $all_car_ids_query = new WP_Query($all_car_ids_args);
        $candidate_post_ids = $all_car_ids_query->posts;
        $location_filtered_post_ids = array();

        if (!empty($candidate_post_ids) && function_exists('autoagora_calculate_distance')) {
            foreach ($candidate_post_ids as $car_id) {
                $car_latitude  = get_post_meta($car_id, 'car_latitude', true);
                $car_longitude = get_post_meta($car_id, 'car_longitude', true);
                if ($car_latitude && $car_longitude) {
                    $distance = autoagora_calculate_distance($filter_lat, $filter_lng, $car_latitude, $car_longitude);
                    if ($distance <= $filter_radius) {
                        $location_filtered_post_ids[] = $car_id;
                    }
                }
            }
        }
        if (empty($location_filtered_post_ids)) {
            $location_filtered_post_ids = array(0); // Ensures no posts if location filter yields no results
        }
    }
    $debug_info['location_filtered_post_ids'] = $location_filtered_post_ids;
    // ---- END: Step 1 ----

    $all_counts = array();

    if (is_array($location_filtered_post_ids) && $location_filtered_post_ids === [0]) {
        foreach ($filter_definitions as $field_key => $definition) {
            if (strpos($definition['type'], 'range_') === 0) {
                $base_meta_key = $definition['meta_key'];
                $all_counts[$base_meta_key . '_min_cumulative_counts'] = array();
                $all_counts[$base_meta_key . '_max_cumulative_counts'] = array();
            } else {
                $all_counts[$field_key] = array();
            }
        }
        wp_send_json_success($all_counts);
        return;
    }
    
    // ---- START: Step 2 - Iterate through each filter definition to calculate its counts ----
    foreach ($filter_definitions as $field_key_to_count => $definition_to_count) {
        $meta_key_to_count = $definition_to_count['meta_key'];
        $type_to_count = $definition_to_count['type'];

        $contextual_meta_query = array('relation' => 'AND');
        foreach ($sanitized_filters as $filter_key => $filter_value) {
            if ($filter_key === $field_key_to_count || $filter_key === $field_key_to_count . '_min' || $filter_key === $field_key_to_count . '_max\') {
                continue; 
            }
            if (in_array($filter_key, ['lat', 'lng', 'radius', 'location_name'])) {
                continue;
            }

            $base_filter_key = str_replace(['_min', '_max'], '', $filter_key);
            $current_field_def = $filter_definitions[$base_filter_key] ?? null;
            if (!$current_field_def) continue;

            $meta_key_for_filter = $current_field_def['meta_key'];
            
            if (strpos($current_field_def['type'], 'range_') === 0) {
                $compare = '';
                if (str_ends_with($filter_key, '_min')) $compare = '>=';
                if (str_ends_with($filter_key, '_max')) $compare = '<=';
                
                if ($compare) {
                     $contextual_meta_query[] = array(
                        'key'     => $meta_key_for_filter,
                        'value'   => $filter_value,
                        'compare' => $compare,
                        'type'    => ($current_field_def['is_numeric'] ?? false) ? 'NUMERIC' : 'CHAR',
                    );
                }
            } elseif ($current_field_def['type'] === 'multi') {
                $values_array = is_array($filter_value) ? $filter_value : array_map('trim', explode(',', $filter_value));
                if (!empty($values_array)) {
                    $sub_query_for_multi = array('relation' => 'OR'); // Car must match ANY of the selected values for THIS specific multi-filter
                    foreach($values_array as $single_val){
                        // This logic assumes ACF stores choices (like checkbox) as an array,
                        // and a single post's meta value for this key would be an array of selected terms.
                        // A LIKE query is often used if the stored value is a serialized array string.
                        // If it's an actual array of simple strings, direct comparison might work differently.
                        // For simplicity, if ACF stores as "value1", "value2", direct '=' could work if $filter_value is just one item.
                        // If $filter_value has multiple items for a single filter key (e.g. fuel_type=Petrol,Diesel), this loop handles it.
                         $sub_query_for_multi[] = array(
                            'key' => $meta_key_for_filter,
                            'value' => $single_val, 
                            'compare' => '=', // Assuming direct match for individual values from multi-select
                        );
                    }
                    if(count($sub_query_for_multi) > 1) { // only add if there are conditions
                        $contextual_meta_query[] = $sub_query_for_multi;
                    }
                }
            } else { // Simple type
                $contextual_meta_query[] = array(
                    'key'     => $meta_key_for_filter,
                    'value'   => $filter_value,
                    'compare' => '=',
                );
            }
        }
        
        $contextual_meta_query[] = array(
            'relation' => 'OR',
            array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
            array('key' => 'is_sold', 'value' => '1', 'compare' => '!=')
        );

        $args_for_contextual_ids = array(
            'post_type'      => 'car',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $contextual_meta_query,
        );

        if ($location_filtered_post_ids !== null) {
            $args_for_contextual_ids['post__in'] = $location_filtered_post_ids;
             if (empty($location_filtered_post_ids)) {
                $args_for_contextual_ids['post__in'] = [0];
            }
        }
        
        $debug_info['counts_for'][$field_key_to_count]['query_args'] = $args_for_contextual_ids;
        $contextual_ids_query = new WP_Query($args_for_contextual_ids);
        $ids_to_get_values_from = $contextual_ids_query->posts;
        $debug_info['counts_for'][$field_key_to_count]['found_ids_count_for_counting_this_field'] = count($ids_to_get_values_from);


        if (empty($ids_to_get_values_from)) {
            if (strpos($type_to_count, 'range_') === 0) {
                $all_counts[$meta_key_to_count . '_min_cumulative_counts'] = array();
                $all_counts[$meta_key_to_count . '_max_cumulative_counts'] = array();
            } else {
                $all_counts[$field_key_to_count] = array();
            }
            continue; 
        }

        // ---- START: Step 3 - Extract and count values for the current field ----
        if (strpos($type_to_count, 'range_') === 0) {
            $range_choices = get_default_range_choices($meta_key_to_count, $ids_to_get_values_from); // Helper needed
            $is_numeric_range = $definition_to_count['is_numeric'] ?? false;
            $cumulative_counts = get_cumulative_range_counts_for_field($meta_key_to_count, $range_choices, $ids_to_get_values_from, $is_numeric_range); // Helper needed
            $all_counts[$meta_key_to_count . '_min_cumulative_counts'] = $cumulative_counts['min'];
            $all_counts[$meta_key_to_count . '_max_cumulative_counts'] = $cumulative_counts['max'];

        } else { 
            $field_value_counts = array();
            foreach ($ids_to_get_values_from as $pid) {
                $value = get_post_meta($pid, $meta_key_to_count, true);
                if ($type_to_count === 'multi') {
                    $actual_values = array();
                     // ACF Checkbox fields return an array of selected values directly.
                    if ($definition_to_count['multi_source_type'] === 'array' && is_array($value)) {
                        $actual_values = $value;
                    } 
                    // Handle cases where multi-select might be stored as comma-separated string
                    elseif (is_string($value) && strpos($value, ',') !== false && ($definition_to_count['multi_source_type'] === 'comma_separated' || !isset($definition_to_count['multi_source_type']))) {
                        $actual_values = array_map('trim', explode(',', $value));
                    }
                    // Fallback for single string values in a field defined as 'multi'
                    elseif (is_string($value) && !empty($value)) { 
                         $actual_values = [$value];
                    }


                    foreach ($actual_values as $v_single) {
                        if (!empty($v_single)) {
                           $field_value_counts[$v_single] = ($field_value_counts[$v_single] ?? 0) + 1;
                        }
                    }
                } else { // Simple type
                    if (!empty($value)) {
                        if (is_array($value)) { 
                            foreach($value as $v_single) if(!empty($v_single)) $field_value_counts[$v_single] = ($field_value_counts[$v_single] ?? 0) + 1;
                        } else {
                             $field_value_counts[$value] = ($field_value_counts[$value] ?? 0) + 1;
                        }
                    }
                }
            }
            $all_counts[$field_key_to_count] = $field_value_counts;
        }
        // ---- END: Step 3 ----
    }
    // ---- END: Step 2 ----

    // Add make/model/variant structure (from JSON files, used by car-filter.js for cascading dropdowns)
    $make_model_variant_data = array();
    $json_dir = get_stylesheet_directory() . '/simple_jsons/';
    if (function_exists('format_make_name_from_filename') && is_dir($json_dir)) { // Ensure helper exists
        $json_files = glob($json_dir . '*.json');
        if($json_files){
            sort($json_files);
            foreach ($json_files as $file) {
                $make_name = format_make_name_from_filename(basename($file, '.json'));
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['models'])) {
                    $make_model_variant_data[$make_name] = [];
                    foreach($data['models'] as $model_data){
                        $model_name = $model_data['model'];
                        $make_model_variant_data[$make_name][$model_name] = $model_data['variants'] ?? [];
                    }
                }
            }
        }
    }
    $all_counts['makeModelVariantStructure'] = $make_model_variant_data;


    // $all_counts['_debug_info'] = $debug_info; 
    wp_send_json_success($all_counts);
}

// Helper function to get default choices for a range if specific choices_function isn't available
// This is a basic fallback and might need to be more sophisticated
function get_default_range_choices($meta_key, $post_ids) {
    if (empty($post_ids)) return [];
    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
    
    // Ensure $post_ids are actual integers if they are not already
    $safe_post_ids = array_map('intval', $post_ids);

    $query_params = array_merge([$meta_key], $safe_post_ids);

    $query = $wpdb->prepare(
        "SELECT DISTINCT meta_value AS value 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = %s AND post_id IN ({$placeholders}) 
         ORDER BY CAST(meta_value AS SIGNED) ASC", // Ensure numeric sort for ranges
        $query_params
    );
    $results = $wpdb->get_col($query);
    $choices = array();
    foreach ($results as $val) {
        if (is_numeric($val)) { // Ensure values are numeric for ranges
             $choices[strval($val)] = strval($val); // Store as string keys/values if they represent numeric ranges
        }
    }
    return $choices;
}

/**
 * Helper function to calculate cumulative counts for range filters.
 */
function get_cumulative_range_counts_for_field($meta_key, $range_choices, $post_ids, $is_numeric_range) {
    $cumulative_counts = array('min' => 0, 'max' => 0);
    $min_counts = array();
    $max_counts = array();

    foreach ($post_ids as $pid) {
        $value = get_post_meta($pid, $meta_key, true);
        if (!empty($value)) {
            if (is_array($value)) {
                foreach ($value as $single_val) {
                    $min_counts[$single_val] = ($min_counts[$single_val] ?? 0) + 1;
                }
            } else {
                $min_counts[$value] = ($min_counts[$value] ?? 0) + 1;
            }
        }
    }

    foreach ($range_choices as $choice) {
        $cumulative_counts['min'] += $min_counts[$choice] ?? 0;
        $cumulative_counts['max'] += $min_counts[$choice] ?? 0;
    }

    if ($is_numeric_range) {
        $cumulative_counts['min'] = floatval($cumulative_counts['min']);
        $cumulative_counts['max'] = floatval($cumulative_counts['max']);
    }

    return $cumulative_counts;
}

// Hook the new handler
add_action('wp_ajax_update_filter_counts', 'ajax_update_filter_counts_handler');
add_action('wp_ajax_nopriv_update_filter_counts', 'ajax_update_filter_counts_handler'); 

/**
 * AJAX handler for marking a car as sold
 */
add_action('wp_ajax_mark_car_as_sold', 'handle_mark_car_as_sold');

function handle_mark_car_as_sold() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mark_car_as_sold')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Get car ID
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    if (!$car_id) {
        wp_send_json_error('Invalid car ID');
        return;
    }

    // Check if user owns the car
    $car = get_post($car_id);
    if (!$car || $car->post_author != get_current_user_id()) {
        wp_send_json_error('Unauthorized');
        return;
    }

    // Get the new status
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'sold';
    if (!in_array($status, array('sold', 'available'))) {
        wp_send_json_error('Invalid status');
        return;
    }

    // Update car status using ACF field
    update_field('is_sold', $status === 'sold' ? 1 : 0, $car_id);
    
    wp_send_json_success();
}

// Handle car status toggle
add_action('wp_ajax_toggle_car_status', 'handle_toggle_car_status');

function handle_toggle_car_status() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'toggle_car_status_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get and validate car ID
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    if (!$car_id) {
        wp_send_json_error('Invalid car ID');
        return;
    }

    // Check if user owns the car
    $car = get_post($car_id);
    if (!$car || $car->post_author != get_current_user_id()) {
        wp_send_json_error('Unauthorized');
        return;
    }

    // Get the new status
    $mark_as_sold = isset($_POST['mark_as_sold']) ? filter_var($_POST['mark_as_sold'], FILTER_VALIDATE_BOOLEAN) : false;

    // Update the ACF field - ensure we're using the correct value format
    $result = update_field('is_sold', $mark_as_sold ? '1' : '0', $car_id);
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update car status');
    }
} 