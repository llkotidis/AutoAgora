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

    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
    $debug_info['received_filters'] = $filters; // Log received filters

    // --- Extract location filter from POST (if present) ---
    $location_filter = null;
    if (isset($_POST['lat']) && isset($_POST['lng']) && isset($_POST['radius']) &&
        $_POST['lat'] !== '' && $_POST['lng'] !== '' && $_POST['radius'] !== '') {
        $location_filter = array(
            'lat' => floatval($_POST['lat']),
            'lng' => floatval($_POST['lng']),
            'radius' => floatval($_POST['radius'])
        );
    }
    // --- End location extraction ---

    // --- Get all car IDs within the location filter (if set) ---
    $matching_car_ids = null;
    if ($location_filter) {
        global $wpdb;
        $all_car_ids_query = $wpdb->get_results(
            "SELECT p.ID, pm_lat.meta_value as latitude, pm_lng.meta_value as longitude
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id AND pm_lat.meta_key = 'car_latitude'
             JOIN {$wpdb->postmeta} pm_lng ON p.ID = pm_lng.post_id AND pm_lng.meta_key = 'car_longitude'
             WHERE p.post_type = 'car'
             AND p.post_status = 'publish'",
            ARRAY_A
        );
        $matching_car_ids = array();
        foreach ($all_car_ids_query as $car) {
            if (!empty($car['latitude']) && !empty($car['longitude'])) {
                $distance = autoagora_calculate_distance(
                    $location_filter['lat'],
                    $location_filter['lng'],
                    floatval($car['latitude']),
                    floatval($car['longitude'])
                );
                if ($distance <= $location_filter['radius']) {
                    $matching_car_ids[] = $car['ID'];
                }
            }
        }
        // If no cars match, use [0] to prevent SQL errors
        if (empty($matching_car_ids)) {
            $matching_car_ids = array(0);
        }
    }
    // --- End get car IDs in location ---

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
    if ($matching_car_ids !== null) {
        $query_args['post__in'] = $matching_car_ids;
    }
    $matching_post_ids = get_posts($query_args);
    $debug_info['initial_matching_post_ids'] = $matching_post_ids; // Log initial results

    // 4. Calculate Counts for Each Filter Based on Matching IDs
    $updated_counts = array();
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
        return $filter_keys[$key]['type'] === 'simple' && $key !== 'location';
    });

    $temp_meta_query_engine = array('relation' => 'AND'); 
    foreach ($filter_keys as $key => $config) {
        if (in_array($key, ['engine_min', 'engine_max'])) {
            continue;
        }
        $type = $config['type'];
        $is_multi = $config['multi'];
        $base_key = str_replace(array('_min', '_max'), '', $key);
        $value = isset($filters[$key]) ? $filters[$key] : '';
        if (!empty($value)) {
             if ($is_multi) {
                 $value_array = explode(',', $value);
                 $sanitized_value = array_map('sanitize_text_field', $value_array);
                 $sanitized_value = array_filter($sanitized_value); 
                 if (empty($sanitized_value)) continue;
             } else {
                 if ($type === 'range_min' || $type === 'range_max') {
                     $sanitized_value = floatval($value);
                 } else {
                     $sanitized_value = sanitize_text_field($value);
                 }
             }
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
            } elseif ($type === 'range_min') {
                 $temp_meta_query_engine[] = array(
                    'key'     => $base_key,
                    'value'   => $sanitized_value,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                 );
            } elseif ($type === 'range_max') {
                 $temp_meta_query_engine[] = array(
                    'key'     => $base_key,
                    'value'   => $sanitized_value,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                 );
            }
        }
    }
    $query_args_for_ids = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all matching posts
        'fields'         => 'ids', // Only get post IDs for efficiency
        'meta_query'     => $temp_meta_query_engine, // Use the query *without* engine filters
    );
    if ($matching_car_ids !== null) {
        $query_args_for_ids['post__in'] = $matching_car_ids;
    }
    $matching_engine_pool_query = new WP_Query($query_args_for_ids);
    $matching_post_ids_for_engine = $matching_engine_pool_query->get_posts();
    $post_id_placeholders_engine = '0'; // Default if no IDs match
    $prepared_post_ids_for_engine = [0]; 
    if (!empty($matching_post_ids_for_engine)) {
         $post_id_placeholders_engine = implode(',', array_fill(0, count($matching_post_ids_for_engine), '%d'));
         $prepared_post_ids_for_engine = $matching_post_ids_for_engine;
    }
    $debug_info['matching_ids_for_engine'] = $prepared_post_ids_for_engine;

    global $wpdb;
    $post_id_placeholders_simple = !empty($matching_post_ids) ? implode(',', array_fill(0, count($matching_post_ids), '%d')) : '0'; 
    $prepared_post_ids_simple = !empty($matching_post_ids) ? $matching_post_ids : [0];

    foreach ($count_fields as $field_key) {
        $config = $filter_keys[$field_key];
        $is_multi = $config['multi'];
        $field_counts = array();
        $sql = '';
        $contextual_meta_query = ['relation' => 'AND'];
        $relevant_filters = $filters; 
        if ($is_multi) {
            unset($relevant_filters[$field_key]);
        } else {
            switch ($field_key) {
                case 'make':
                    unset($relevant_filters['model']);
                    unset($relevant_filters['variant']);
                    break;
                case 'model':
                    unset($relevant_filters['variant']);
                    break;
            }
        }
        foreach ($filter_keys as $context_key => $context_config) {
             if ($context_key === $field_key) continue;
             if (isset($relevant_filters[$context_key]) && !empty($relevant_filters[$context_key])) {
                  $context_value = $relevant_filters[$context_key];
                  $context_base_key = str_replace(array('_min', '_max'), '', $context_key);
                  $context_type = $context_config['type'];
                  $context_is_multi = $context_config['multi'];
                 if ($context_is_multi) {
                     $context_value_array = explode(',', $context_value);
                     $context_sanitized_value = array_map('sanitize_text_field', $context_value_array);
                     $context_sanitized_value = array_filter($context_sanitized_value);
                     if (empty($context_sanitized_value)) continue;
                 } elseif ($context_type === 'range_min' || $context_type === 'range_max') {
                     $context_sanitized_value = floatval($context_value);
                 } else {
                     $context_sanitized_value = sanitize_text_field($context_value);
                 }
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
        $query_args_context = array(
            'post_type' => 'car', 'post_status' => 'publish',
            'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => $contextual_meta_query
        );
        if ($matching_car_ids !== null) {
            $query_args_context['post__in'] = $matching_car_ids;
        }
        $contextual_matching_ids = get_posts($query_args_context);
        $contextual_placeholders = '0';
        $contextual_prepared_ids = [0];
        if (!empty($contextual_matching_ids)) {
            $contextual_placeholders = implode(',', array_fill(0, count($contextual_matching_ids), '%d'));
            $contextual_prepared_ids = $contextual_matching_ids;
        }
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
        if ($sql) {
            $results = $wpdb->get_results($sql, OBJECT_K);
            if ($results) {
                foreach ($results as $value => $data) {
                    $field_counts[$value] = (int)$data->count;
                }
            }
        }
        $updated_counts[$field_key] = $field_counts;
    }
    // Instead of using hardcoded lists for filter options, dynamically generate them from the filtered car IDs
    // For each filter field, get the unique values present in the filtered set (matching_car_ids)
    $dynamic_filter_options = array();
    if (!empty($matching_car_ids) && is_array($matching_car_ids)) {
        global $wpdb;
        $fields_to_query = [
            'make', 'model', 'variant', 'fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type', 'year', 'engine_capacity', 'mileage'
        ];
        foreach ($fields_to_query as $field_key) {
            $placeholders = implode(',', array_fill(0, count($matching_car_ids), '%d'));
            $sql = $wpdb->prepare(
                "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($placeholders) AND meta_value IS NOT NULL AND meta_value != ''",
                array_merge([$field_key], $matching_car_ids)
            );
            $results = $wpdb->get_col($sql);
            // For numeric fields, sort numerically
            if (in_array($field_key, ['year', 'engine_capacity', 'mileage'])) {
                $results = array_map('floatval', $results);
                sort($results, SORT_NUMERIC);
            } else {
                sort($results, SORT_STRING);
            }
            $dynamic_filter_options[$field_key] = $results;
        }
    }
    $updated_counts['dynamic_filter_options'] = $dynamic_filter_options;
    // ... (rest of the function remains unchanged, but for all other queries, add post__in or IN clause with $matching_car_ids if set)
    // --- Calculate Counts for Engine Capacity --- 
    // ...
    // When building $query_args_mileage_ids, $query_args_year_ids, etc., also add post__in if $matching_car_ids !== null
    // ...
    // At the end, send the response as before
    wp_send_json_success($updated_counts);
    wp_die();
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

// Define the years list again (needs centralization)
$current_year_php = date('Y');
$years_list = range($current_year_php, 1948); // Descending order from current year to 1948 