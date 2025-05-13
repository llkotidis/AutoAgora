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
            $make = get_post_meta(get_the_ID(), 'make', true);
            $model = get_post_meta(get_the_ID(), 'model', true);
            $variant = get_post_meta(get_the_ID(), 'variant', true);
            $price = get_post_meta(get_the_ID(), 'price', true);
            $year = get_post_meta(get_the_ID(), 'year', true);
            $engine_capacity = get_post_meta(get_the_ID(), 'engine_capacity', true);
            $transmission = get_post_meta(get_the_ID(), 'transmission', true);
            $mileage = get_post_meta(get_the_ID(), 'mileage', true);
            $location = get_post_meta(get_the_ID(), 'location', true);
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
                            
                            $body_type = get_post_meta(get_the_ID(), 'body_type', true);
                            if (!empty($body_type)) {
                                $specs_array[] = esc_html($body_type);
                            }

                            if (!empty($transmission)) {
                                $specs_array[] = esc_html($transmission);
                            }
                            
                            $drive_type = get_post_meta(get_the_ID(), 'drive_type', true);
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
                        <?php 
                        $publication_date = get_post_meta(get_the_ID(), 'publication_date', true);
                        if (!$publication_date) {
                            $publication_date = get_the_date('Y-m-d H:i:s');
                            update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                        }
                        $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                        echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                        ?>
                        <div class="car-location"><i class="fas fa-map-marker-alt"></i><?php echo esc_html($location); ?></div>
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

    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
    $debug_info['received_filters'] = $filters; // Log received filters

    $sanitized_filters = array();
    $meta_query = array('relation' => 'AND'); 

    // Define filter keys (copy from later or centralize)
    $filter_keys = array(
        'location'       => ['type' => 'simple', 'multi' => false],
        'make'           => ['type' => 'simple', 'multi' => false],
        'model'          => ['type' => 'simple', 'multi' => false],
        'variant'        => ['type' => 'simple', 'multi' => false],
        'fuel_type'      => ['type' => 'simple', 'multi' => true],
        'transmission'   => ['type' => 'simple', 'multi' => true],
        'exterior_color' => ['type' => 'simple', 'multi' => true],
        'interior_color' => ['type' => 'simple', 'multi' => true],
        'body_type'      => ['type' => 'simple', 'multi' => true],
        'drive_type'     => ['type' => 'simple', 'multi' => true],
        'year_min'       => ['type' => 'range_min', 'multi' => false],
        'year_max'       => ['type' => 'range_max', 'multi' => false],
        'engine_min'     => ['type' => 'range_min', 'multi' => false],
        'engine_max'     => ['type' => 'range_max', 'multi' => false],
        'mileage_min'    => ['type' => 'range_min', 'multi' => false],
        'mileage_max'    => ['type' => 'range_max', 'multi' => false],
    );

    // Build the MAIN meta_query from received filters
    foreach ($filter_keys as $key => $config) {
        $type = $config['type'];
        $is_multi = $config['multi'];
        $base_key = str_replace(array('_min', '_max'), '', $key); // Get the ACF field name
        $value = isset($filters[$key]) ? $filters[$key] : '';

        if (!empty($value)) {
            // Sanitize differently based on multi-select or not
             if ($is_multi) {
                 // Expecting comma-separated string, sanitize each part
                 $value_array = explode(',', $value);
                 $sanitized_value = array_map('sanitize_text_field', $value_array);
                 $sanitized_value = array_filter($sanitized_value); // Remove empty values after sanitization
                 if (empty($sanitized_value)) continue; // Skip if no valid values remain
             } else {
                 $sanitized_value = sanitize_text_field($value); // Basic sanitization for single values
             }

            if ($type === 'simple') {
                 if ($is_multi && is_array($sanitized_value)) {
                     $meta_query[] = array(
                         'key'     => $base_key,
                         'value'   => $sanitized_value, // Pass the array
                         'compare' => 'IN', 
                    );
                 } elseif (!$is_multi) {
                     $meta_query[] = array(
                         'key'     => $base_key,
                         'value'   => $sanitized_value,
                         'compare' => '=',
                     );
                 }
            } elseif ($type === 'range_min') {
                 $meta_query[] = array(
                    'key'     => $base_key,
                    'value'   => floatval($sanitized_value), // Use floatval for numeric comparison
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                 );
            } elseif ($type === 'range_max') {
                 $meta_query[] = array(
                    'key'     => $base_key,
                    'value'   => floatval($sanitized_value), // Use floatval for numeric comparison
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                 );
            }
        }
    }
    $debug_info['built_main_meta_query'] = $meta_query; // Log the built query

    // 3. Query for Matching Post IDs (Initial Query)
    $query_args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => -1, 
        'fields'         => 'ids', 
        'meta_query'     => $meta_query,
    );
    $matching_post_ids = get_posts($query_args);
    $debug_info['initial_matching_post_ids'] = $matching_post_ids; // Log initial results

    // 4. Calculate Counts for Each Filter Based on Matching IDs
    $updated_counts = array();
    // Define the known filter keys and their configs again (or get from a central place)
    $filter_keys = array(
        'location'       => ['type' => 'simple', 'multi' => false],
        'make'           => ['type' => 'simple', 'multi' => false],
        'model'          => ['type' => 'simple', 'multi' => false],
        'variant'        => ['type' => 'simple', 'multi' => false],
        'fuel_type'      => ['type' => 'simple', 'multi' => true],
        'transmission'   => ['type' => 'simple', 'multi' => true],
        'exterior_color' => ['type' => 'simple', 'multi' => true],
        'interior_color' => ['type' => 'simple', 'multi' => true],
        'body_type'      => ['type' => 'simple', 'multi' => true],
        'drive_type'     => ['type' => 'simple', 'multi' => true],
        'year_min'       => ['type' => 'range_min', 'multi' => false],
        'year_max'       => ['type' => 'range_max', 'multi' => false],
        'engine_min'     => ['type' => 'range_min', 'multi' => false],
        'engine_max'     => ['type' => 'range_max', 'multi' => false],
        'mileage_min'    => ['type' => 'range_min', 'multi' => false],
        'mileage_max'    => ['type' => 'range_max', 'multi' => false],
    );
    $all_field_keys_to_count = array_keys($filter_keys); // Get all keys including ranges initially
    $count_fields = array_filter($all_field_keys_to_count, function($key) use ($filter_keys) {
        // Only get counts for fields that are not range min/max
        // Also exclude 'location' as it should remain static
        return $filter_keys[$key]['type'] === 'simple' && $key !== 'location';
    });

    // Calculate $temp_meta_query_engine correctly --- 
    $temp_meta_query_engine = array('relation' => 'AND'); 
    foreach ($filter_keys as $key => $config) {
        // Skip engine min/max keys when building this query
        if (in_array($key, ['engine_min', 'engine_max'])) {
            continue;
        }
        
        $type = $config['type'];
        $is_multi = $config['multi'];
        $base_key = str_replace(array('_min', '_max'), '', $key);
        $value = isset($filters[$key]) ? $filters[$key] : '';

        if (!empty($value)) {
             // (Sanitization logic copied from main loop above - needs to be consistent)
             if ($is_multi) {
                 $value_array = explode(',', $value);
                 $sanitized_value = array_map('sanitize_text_field', $value_array);
                 $sanitized_value = array_filter($sanitized_value); 
                 if (empty($sanitized_value)) continue;
             } else {
                 // Special handling for numeric range values
                 if ($type === 'range_min' || $type === 'range_max') {
                     $sanitized_value = floatval($value); // Ensure numeric type for comparison
                 } else {
                     $sanitized_value = sanitize_text_field($value);
                 }
             }
             
             // Add clause to $temp_meta_query_engine
             if ($type === 'simple') {
                 if ($is_multi && is_array($sanitized_value)) {
                     $temp_meta_query_engine[] = array(
                         'key'     => $base_key,
                         'value'   => $sanitized_value,
                         'compare' => 'IN', 
                    );
                 } elseif (!$is_multi) {
                     $temp_meta_query_engine[] = array(
                         'key'     => $base_key,
                         'value'   => $sanitized_value,
                         'compare' => '=',
                     );
                 }
            } elseif ($type === 'range_min') { // Keep other range filters (year, mileage)
                 $temp_meta_query_engine[] = array(
                    'key'     => $base_key,
                    'value'   => $sanitized_value, // Already floatval'd
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                 );
            } elseif ($type === 'range_max') {
                 $temp_meta_query_engine[] = array(
                    'key'     => $base_key,
                    'value'   => $sanitized_value, // Already floatval'd
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                 );
            }
        }
    }
    // --- End Calculation ---

    // Use $temp_meta_query_engine to get the base pool of IDs for engine calcs
    $query_args_for_ids = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all matching posts
        'fields'         => 'ids', // Only get post IDs for efficiency
        'meta_query'     => $temp_meta_query_engine, // Use the query *without* engine filters
    );
    $matching_engine_pool_query = new WP_Query($query_args_for_ids);
    $matching_post_ids_for_engine = $matching_engine_pool_query->get_posts();
    
    // Prepare for SQL IN clause for engine queries
    $post_id_placeholders_engine = '0'; // Default if no IDs match
    $prepared_post_ids_for_engine = [0]; 
    if (!empty($matching_post_ids_for_engine)) {
         $post_id_placeholders_engine = implode(',', array_fill(0, count($matching_post_ids_for_engine), '%d'));
         $prepared_post_ids_for_engine = $matching_post_ids_for_engine;
    }
    // --- Store IDs in Debug Info --- 
    $debug_info['matching_ids_for_engine'] = $prepared_post_ids_for_engine;
    
    global $wpdb;
    // Use the original $matching_post_ids for simple fields for now
    $post_id_placeholders_simple = !empty($matching_post_ids) ? implode(',', array_fill(0, count($matching_post_ids), '%d')) : '0'; 
    $prepared_post_ids_simple = !empty($matching_post_ids) ? $matching_post_ids : [0];

        foreach ($count_fields as $field_key) {
        // --- START New Logic for Single/Multi Select Counts ---
            $config = $filter_keys[$field_key];
            $is_multi = $config['multi'];
            $field_counts = array();
            $sql = '';

        // Determine the correct pool of IDs based on hierarchy or exclusion
        $contextual_meta_query = ['relation' => 'AND'];
        // IMPORTANT: Reset $relevant_filters to the original $filters for each field calculation
        $relevant_filters = $filters; 

            if ($is_multi) {
            // For multi-select, base counts on filters EXCEPT the current one
            unset($relevant_filters[$field_key]);
        } else {
            // For single-select (Make, Model, Variant), base counts on hierarchy
             switch ($field_key) {
                case 'make':
                    unset($relevant_filters['model']);
                    unset($relevant_filters['variant']);
                    break;
                case 'model':
                    unset($relevant_filters['variant']);
                    break;
                case 'variant':
                    // No change needed
                    break;
                // Add other simple fields if they have dependencies
            }
        }

        // Build the contextual meta query based on remaining $relevant_filters
        foreach ($filter_keys as $context_key => $context_config) {
             // Skip the field we are currently counting
             if ($context_key === $field_key) continue;
             // Only include filters present in $relevant_filters
             if (isset($relevant_filters[$context_key]) && !empty($relevant_filters[$context_key])) {
                  $context_value = $relevant_filters[$context_key];
                  $context_base_key = str_replace(array('_min', '_max'), '', $context_key);
                  $context_type = $context_config['type'];
                  $context_is_multi = $context_config['multi'];

                 // Full sanitization and clause building logic
                 if ($context_is_multi) {
                     $context_value_array = explode(',', $context_value);
                     $context_sanitized_value = array_map('sanitize_text_field', $context_value_array);
                     $context_sanitized_value = array_filter($context_sanitized_value);
                     if (empty($context_sanitized_value)) continue;
                 } elseif ($context_type === 'range_min' || $context_type === 'range_max') {
                     // Make sure to use floatval for numeric ranges here
                     $context_sanitized_value = floatval($context_value);
                 } else {
                     $context_sanitized_value = sanitize_text_field($context_value);
                 }

                 // Add clause
                 if ($context_type === 'simple') {
                     if ($context_is_multi && is_array($context_sanitized_value)) {
                         $contextual_meta_query[] = array('key' => $context_base_key, 'value' => $context_sanitized_value, 'compare' => 'IN');
                     } elseif (!$context_is_multi) {
                         $contextual_meta_query[] = array('key' => $context_base_key, 'value' => $context_sanitized_value, 'compare' => '=');
                     }
                 } elseif ($context_type === 'range_min') { 
                     $contextual_meta_query[] = array('key' => $context_base_key, 'value' => $context_sanitized_value, 'compare' => '>=', 'type' => 'NUMERIC');
                 } elseif ($context_type === 'range_max') {
                     $contextual_meta_query[] = array('key' => $context_base_key, 'value' => $context_sanitized_value, 'compare' => '<=', 'type' => 'NUMERIC');
                 }
            }
        }
        
        // Query for IDs based on this contextual query
         $query_args_context = array(
            'post_type' => 'car', 'post_status' => 'publish',
            'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => $contextual_meta_query
        );
        $contextual_matching_ids = get_posts($query_args_context);
        
        // Prepare IDs for SQL
        $contextual_placeholders = '0';
        $contextual_prepared_ids = [0];
        if (!empty($contextual_matching_ids)) {
            $contextual_placeholders = implode(',', array_fill(0, count($contextual_matching_ids), '%d'));
            $contextual_prepared_ids = $contextual_matching_ids;
        }

        // Prepare the SQL to count the current field based on contextual IDs
         if (!empty($contextual_matching_ids)) {
                     $sql = $wpdb->prepare(
                         "SELECT meta_value, COUNT(DISTINCT post_id) as count 
                          FROM {$wpdb->postmeta} 
                          WHERE meta_key = %s 
                  AND post_id IN ({$contextual_placeholders})
                          AND meta_value IS NOT NULL AND meta_value != ''
                          GROUP BY meta_value",
                 array_merge([$field_key], $contextual_prepared_ids)
                     );
                 } else {
             $sql = ''; 
             $field_counts = array();
                 }
        // --- END New Logic ---

            if ($sql) { // Only query if SQL was generated
                 $results = $wpdb->get_results($sql, OBJECT_K);
                 if ($results) {
                     foreach ($results as $value => $data) {
                         $field_counts[$value] = (int)$data->count;
                     }
                 }
            }
            $updated_counts[$field_key] = $field_counts;
        }
        
        // --- Calculate Counts for Engine Capacity --- 
        $engine_field_key = 'engine_capacity'; // The actual meta key
        $engine_counts = array();

    // --- Calculate Exact Engine Counts (using the reliable IDs from $prepared_post_ids_for_engine) --- 
    if (!empty($matching_post_ids_for_engine)) {
        $sql_engine = $wpdb->prepare(
            "SELECT meta_value, COUNT(DISTINCT post_id) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             AND post_id IN ({$post_id_placeholders_engine}) 
             AND meta_value IS NOT NULL AND meta_value != ''
             GROUP BY meta_value",
            array_merge([$engine_field_key], $prepared_post_ids_for_engine) 
        );
        $engine_results = $wpdb->get_results($sql_engine, OBJECT_K);
         if ($engine_results) {
             foreach ($engine_results as $value => $data) {
                 $formatted_key = number_format(floatval($value), 1); 
                 $engine_counts[$formatted_key] = (int)$data->count;
             }
         }
    }
    // If $matching_post_ids_for_engine was empty, $engine_counts remains empty, which is correct.
    $updated_counts[$engine_field_key.'_counts'] = $engine_counts;
    // --- End Exact Engine Capacity Count --- 

    // --- Calculate Cumulative Engine Counts (now considering opposite selected bound) --- 
        $engine_min_cumulative_counts = [];
        $engine_max_cumulative_counts = [];
    
    // Get the currently selected min/max values to refine counts
    $selected_engine_min = isset($filters['engine_min']) && is_numeric($filters['engine_min']) ? floatval($filters['engine_min']) : null;
    $selected_engine_max = isset($filters['engine_max']) && is_numeric($filters['engine_max']) ? floatval($filters['engine_max']) : null;
    $debug_info['selected_engine_min'] = $selected_engine_min;
    $debug_info['selected_engine_max'] = $selected_engine_max;

    // Define the engine capacity list here as it's not available from the form's scope
    $engine_capacities = [0.0, 0.5, 0.7, 1.0, 1.2, 1.4, 1.6, 1.8, 1.9, 2.0, 2.2, 2.4, 2.6, 3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0, 6.5, 7.0]; 
    $engine_size_list_for_query = $engine_capacities; // Use the locally defined list

        if (!empty($engine_size_list_for_query)) {
            foreach ($engine_size_list_for_query as $size_threshold) {
                 $formatted_threshold_key = number_format(floatval($size_threshold), 1);
                 
             // Calculate counts only if there are posts matching the other filters
             if (!empty($matching_post_ids_for_engine)) {
                 
                 // --- Min Count Calculation (>= threshold AND <= selected_max) --- 
                 $min_sql_base = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(10,1)) >= %f";
                 $min_sql_params_base = [$engine_field_key, floatval($size_threshold)];
                 
                 // Add constraint for selected max value, if set
                 if ($selected_engine_max !== null) {
                     $min_sql_base .= " AND CAST(meta_value AS DECIMAL(10,1)) <= %f";
                     $min_sql_params_base[] = $selected_engine_max;
                 }

                 // Add IN clause part
                 $min_sql_base .= " AND post_id IN ({$post_id_placeholders_engine})";

                 // Final params: base params + post IDs
                 $final_min_params = array_merge($min_sql_params_base, $prepared_post_ids_for_engine);

                 // Prepare final query
                 $sql_min = $wpdb->prepare($min_sql_base, $final_min_params);

                 $min_count_result = (int) $wpdb->get_var($sql_min);
                 $engine_min_cumulative_counts[$formatted_threshold_key] = $min_count_result;

                 // --- Log specific threshold (Store in Debug Info) --- 
                 if (abs($size_threshold - 4.5) < 0.01) { // Check for 4.5 threshold
                     $debug_info['query_4_5_min'] = ['sql' => $sql_min, 'result' => $min_count_result]; 
                 }
                 // --- End Log ---
                 
                 // --- Max Count Calculation (<= threshold AND >= selected_min) --- 
                 $max_sql_base = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(10,1)) <= %f";
                 $max_sql_params_base = [$engine_field_key, floatval($size_threshold)];

                 // Add constraint for selected min value, if set
                 if ($selected_engine_min !== null) {
                     $max_sql_base .= " AND CAST(meta_value AS DECIMAL(10,1)) >= %f";
                     $max_sql_params_base[] = $selected_engine_min;
                 }

                 // Add IN clause part
                 $max_sql_base .= " AND post_id IN ({$post_id_placeholders_engine})";

                 // Final params: base params + post IDs
                 $final_max_params = array_merge($max_sql_params_base, $prepared_post_ids_for_engine);

                 // Prepare final query
                 $sql_max = $wpdb->prepare($max_sql_base, $final_max_params);
                 
                 $max_count_result = (int) $wpdb->get_var($sql_max);
                 $engine_max_cumulative_counts[$formatted_threshold_key] = $max_count_result;

                 // --- Log specific threshold (Store in Debug Info) ---
                  if (abs($size_threshold - 4.5) < 0.01) { // Check for 4.5 threshold
                      $debug_info['query_4_5_max'] = ['sql' => $sql_max, 'result' => $max_count_result]; 
                 }
                 // --- End Log ---

            } else {
                // If no posts match other filters, all counts are 0
                 $engine_min_cumulative_counts[$formatted_threshold_key] = 0;
                 $engine_max_cumulative_counts[$formatted_threshold_key] = 0;
            }
        }
    } // End if (!empty(...))
        $updated_counts['engine_min_cumulative_counts'] = $engine_min_cumulative_counts;
        $updated_counts['engine_max_cumulative_counts'] = $engine_max_cumulative_counts;
        // --- End Cumulative Engine Counts --- 

    // --- Calculate Counts for Mileage --- 
    $mileage_field_key = 'mileage'; 
    $mileage_min_cumulative_counts = [];
    $mileage_max_cumulative_counts = [];
    
    // Get selected min/max mileage
    $selected_mileage_min = isset($filters['mileage_min']) && is_numeric($filters['mileage_min']) ? intval($filters['mileage_min']) : null;
    $selected_mileage_max = isset($filters['mileage_max']) && is_numeric($filters['mileage_max']) ? intval($filters['mileage_max']) : null;
    $debug_info['selected_mileage_min'] = $selected_mileage_min;
    $debug_info['selected_mileage_max'] = $selected_mileage_max;

    // Use the same base pool of IDs as engine (those matching non-engine/non-mileage filters)
    // NOTE: This assumes mileage filters should behave like engine filters relative to other filters.
    // If mileage should consider engine filters, $temp_meta_query_engine needs adjustment.
    $query_args_mileage_ids = array(
        'post_type' => 'car', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids',
         // Build a meta query excluding mileage AND engine filters? 
         // For now, let's assume we reuse the engine pool logic
         // Need to rebuild $temp_meta_query_mileage similar to $temp_meta_query_engine, excluding mileage_min/max
        'meta_query' => $temp_meta_query_engine // Reusing engine pool logic for now
    );
    $matching_mileage_pool_query = new WP_Query($query_args_mileage_ids);
    $matching_post_ids_for_mileage = $matching_mileage_pool_query->get_posts();
    
    $post_id_placeholders_mileage = '0'; 
    $prepared_post_ids_for_mileage = [0]; 
    if (!empty($matching_post_ids_for_mileage)) {
         $post_id_placeholders_mileage = implode(',', array_fill(0, count($matching_post_ids_for_mileage), '%d'));
         $prepared_post_ids_for_mileage = $matching_post_ids_for_mileage;
    }
    $debug_info['matching_ids_for_mileage'] = $prepared_post_ids_for_mileage;

    // Define the mileage options list again (needs centralization)
    $mileages_list = [];
    for ($i = 0; $i <= 50000; $i += 5000) { $mileages_list[] = $i; }
    for ($i = 60000; $i <= 150000; $i += 10000) { $mileages_list[] = $i; }
    for ($i = 200000; $i <= 300000; $i += 50000) { $mileages_list[] = $i; }

    if (!empty($mileages_list)) {
        foreach ($mileages_list as $mileage_threshold) {
            $formatted_mileage_key = intval($mileage_threshold); // Use integer key

            if (!empty($matching_post_ids_for_mileage)) {
                // Min Mileage Count (>= threshold AND <= selected_max)
                $min_sql_base = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) >= %d AND post_id IN ({$post_id_placeholders_mileage})";
                $min_sql_params_base = [$mileage_field_key, $formatted_mileage_key];
                if ($selected_mileage_max !== null) {
                    $min_sql_base .= " AND CAST(meta_value AS UNSIGNED) <= %d";
                    $min_sql_params_base[] = $selected_mileage_max;
                }
                $final_min_params = array_merge($min_sql_params_base, $prepared_post_ids_for_mileage);
                $sql_min = $wpdb->prepare($min_sql_base, $final_min_params);
                $mileage_min_cumulative_counts[$formatted_mileage_key] = (int) $wpdb->get_var($sql_min);

                // Max Mileage Count (<= threshold AND >= selected_min)
                $max_sql_base = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) <= %d AND post_id IN ({$post_id_placeholders_mileage})";
                $max_sql_params_base = [$mileage_field_key, $formatted_mileage_key];
                if ($selected_mileage_min !== null) {
                    $max_sql_base .= " AND CAST(meta_value AS UNSIGNED) >= %d";
                    $max_sql_params_base[] = $selected_mileage_min;
                }
                $final_max_params = array_merge($max_sql_params_base, $prepared_post_ids_for_mileage);
                $sql_max = $wpdb->prepare($max_sql_base, $final_max_params);
                $mileage_max_cumulative_counts[$formatted_mileage_key] = (int) $wpdb->get_var($sql_max);
                
                 // Add specific debug if needed
                 // if ($formatted_mileage_key == 10000) { ... } 
        
    } else {
                $mileage_min_cumulative_counts[$formatted_mileage_key] = 0;
                $mileage_max_cumulative_counts[$formatted_mileage_key] = 0;
            }
        }
    }
    $updated_counts['mileage_min_cumulative_counts'] = $mileage_min_cumulative_counts;
    $updated_counts['mileage_max_cumulative_counts'] = $mileage_max_cumulative_counts;
    // --- End Mileage Counts ---

    // --- Calculate Counts for Year --- 
    $year_field_key = 'year'; 
    $year_min_cumulative_counts = [];
    $year_max_cumulative_counts = [];
    
    // Get selected min/max year
    $selected_year_min = isset($filters['year_min']) && is_numeric($filters['year_min']) ? intval($filters['year_min']) : null;
    $selected_year_max = isset($filters['year_max']) && is_numeric($filters['year_max']) ? intval($filters['year_max']) : null;
    $debug_info['selected_year_min'] = $selected_year_min;
    $debug_info['selected_year_max'] = $selected_year_max;

    // Reuse the same base pool as engine/mileage (matching non-engine/non-mileage/non-year filters)
    // Needs clarification if this pool should be different
    $query_args_year_ids = array(
        'post_type' => 'car', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => $temp_meta_query_engine // Reusing the engine/mileage base pool logic
    );
    $matching_year_pool_query = new WP_Query($query_args_year_ids);
    $matching_post_ids_for_year = $matching_year_pool_query->get_posts();
    
    $post_id_placeholders_year = '0'; 
    $prepared_post_ids_for_year = [0]; 
    if (!empty($matching_post_ids_for_year)) {
         $post_id_placeholders_year = implode(',', array_fill(0, count($matching_post_ids_for_year), '%d'));
         $prepared_post_ids_for_year = $matching_post_ids_for_year;
    }
    $debug_info['matching_ids_for_year'] = $prepared_post_ids_for_year;

    // Define the years list again (needs centralization)
    $current_year_php = date('Y');
    $years_list = range($current_year_php, 1990); 

    if (!empty($years_list)) {
        foreach ($years_list as $year_threshold) {
            $formatted_year_key = intval($year_threshold); // Use integer key

            if (!empty($matching_post_ids_for_year)) {
                // Min Year Count (>= threshold AND <= selected_max)
                $min_sql_base = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) >= %d AND post_id IN ({$post_id_placeholders_year})";
                $min_sql_params_base = [$year_field_key, $formatted_year_key];
                if ($selected_year_max !== null) {
                    $min_sql_base .= " AND CAST(meta_value AS UNSIGNED) <= %d";
                    $min_sql_params_base[] = $selected_year_max;
                }
                $final_min_params = array_merge($min_sql_params_base, $prepared_post_ids_for_year);
                $sql_min = $wpdb->prepare($min_sql_base, $final_min_params);
                $year_min_cumulative_counts[$formatted_year_key] = (int) $wpdb->get_var($sql_min);

                // Max Year Count (<= threshold AND >= selected_min)
                $sql_parts = [];
                $sql_params = [];

                // Base condition
                $sql_parts[] = "meta_key = %s";
                $sql_params[] = $year_field_key;
                $sql_parts[] = "CAST(meta_value AS UNSIGNED) <= %d";
                $sql_params[] = $formatted_year_key;

                // Add constraint for selected min value, if set
                if ($selected_year_min !== null) {
                    $sql_parts[] = "CAST(meta_value AS UNSIGNED) >= %d";
                    $sql_params[] = $selected_year_min;
                }
                
                // Construct the WHERE clause
                $where_clause = implode(' AND ', $sql_parts);
                
                // Prepare the final query, including the IN clause placeholders
                $sql_max = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE {$where_clause} AND post_id IN ({$post_id_placeholders_year})", 
                    array_merge($sql_params, $prepared_post_ids_for_year) // Merge value params and ID params
                );
                $year_max_cumulative_counts[$formatted_year_key] = (int) $wpdb->get_var($sql_max);

            } else {
                $year_min_cumulative_counts[$formatted_year_key] = 0;
                $year_max_cumulative_counts[$formatted_year_key] = 0;
            }
        }
    }
    $updated_counts['year_min_cumulative_counts'] = $year_min_cumulative_counts;
    $updated_counts['year_max_cumulative_counts'] = $year_max_cumulative_counts;
    // --- End Year Counts ---

    // --- Add Debug Info to Response --- 
    $updated_counts['_debug_info'] = $debug_info;

    // 5. Send JSON Response
    wp_send_json_success($updated_counts);
    wp_die(); // Always die in AJAX handlers
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