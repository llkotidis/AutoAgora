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
    $debug_info['raw_posted_filters'] = $filters;

    // --- Extract Location Filters --- 
    $location_lat = isset($filters['lat']) && $filters['lat'] !== 'null' && $filters['lat'] !== '' ? floatval($filters['lat']) : null;
    $location_lng = isset($filters['lng']) && $filters['lng'] !== 'null' && $filters['lng'] !== '' ? floatval($filters['lng']) : null;
    $location_radius = isset($filters['radius']) && $filters['radius'] !== 'null' && $filters['radius'] !== '' ? floatval($filters['radius']) : null;
    $debug_info['parsed_location'] = ['lat' => $location_lat, 'lng' => $location_lng, 'radius' => $location_radius];

    // Remove location keys from $filters so they don't interfere with spec filtering logic below
    unset($filters['lat'], $filters['lng'], $filters['radius'], $filters['location_name']);

    // --- Determine Base Set of Car IDs based on Location (if provided) ---
    $location_filtered_post_ids = null; // Null means no location filter applied
    if ($location_lat !== null && $location_lng !== null && $location_radius !== null) {
        global $wpdb;
        $location_filtered_post_ids = array(); 

        if (!function_exists('autoagora_calculate_distance')) {
            require_once __DIR__ . '/geo-utils.php'; // Ensure geo-utils is loaded
        }

        $all_cars_with_geo = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, pm_lat.meta_value AS latitude, pm_lng.meta_value AS longitude
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id AND pm_lat.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} pm_lng ON p.ID = pm_lng.post_id AND pm_lng.meta_key = %s
                 WHERE p.post_type = 'car' AND p.post_status = 'publish'",
                'car_latitude', 'car_longitude'
            )
        );
        $debug_info['all_cars_with_geo_count'] = count($all_cars_with_geo);

        foreach ($all_cars_with_geo as $car_geo) {
            if (!empty($car_geo->latitude) && !empty($car_geo->longitude) && function_exists('autoagora_calculate_distance')) {
                $distance = autoagora_calculate_distance($location_lat, $location_lng, floatval($car_geo->latitude), floatval($car_geo->longitude));
                if ($distance <= $location_radius) {
                    $location_filtered_post_ids[] = $car_geo->ID;
                }
            }
        }
        $debug_info['location_filtered_ids_count'] = count($location_filtered_post_ids);
        // If location filter is active but yields no results, ensure subsequent queries find nothing.
        if (empty($location_filtered_post_ids)) {
            $location_filtered_post_ids = array(0); // Query for post ID 0 will return no posts.
        }
    }

    // Define filter fields and their types (simple meta, taxonomy, range)
    // IMPORTANT: 'meta_key' should be the actual ACF field key.
    // 'choices_function' can be used if PHP needs to know all possible choices for zero-count scenarios.
    $all_filter_definitions = [
        // Location is handled separately, not part of this spec filter definition for counts.
        'make'           => ['type' => 'simple',       'meta_key' => 'make',           'multi' => false],
        'model'          => ['type' => 'simple',       'meta_key' => 'model',          'multi' => false],
        'variant'        => ['type' => 'simple',       'meta_key' => 'variant',        'multi' => false],
        'fuel_type'      => ['type' => 'simple',       'meta_key' => 'fuel_type',      'multi' => true],
        'transmission'   => ['type' => 'simple',       'meta_key' => 'transmission',   'multi' => true],
        'exterior_color' => ['type' => 'simple',       'meta_key' => 'exterior_color', 'multi' => true],
        'interior_color' => ['type' => 'simple',       'meta_key' => 'interior_color', 'multi' => true],
        'body_type'      => ['type' => 'simple',       'meta_key' => 'body_type',      'multi' => true],
        'drive_type'     => ['type' => 'simple',       'meta_key' => 'drive_type',     'multi' => true],
        'year_min'       => ['type' => 'range_min',    'meta_key' => 'year',           'multi' => false, 'choices_function' => 'get_year_choices_for_filter'],
        'year_max'       => ['type' => 'range_max',    'meta_key' => 'year',           'multi' => false, 'choices_function' => 'get_year_choices_for_filter'],
        'engine_min'     => ['type' => 'range_min',    'meta_key' => 'engine_capacity','multi' => false, 'choices_function' => 'get_engine_choices_for_filter'],
        'engine_max'     => ['type' => 'range_max',    'meta_key' => 'engine_capacity','multi' => false, 'choices_function' => 'get_engine_choices_for_filter'],
        'mileage_min'    => ['type' => 'range_min',    'meta_key' => 'mileage',        'multi' => false, 'choices_function' => 'get_mileage_choices_for_filter'],
        'mileage_max'    => ['type' => 'range_max',    'meta_key' => 'mileage',        'multi' => false, 'choices_function' => 'get_mileage_choices_for_filter'],
    ];

    // Build the MAIN meta_query from received filters
    $meta_query = array('relation' => 'AND'); 
    $tax_query = array('relation' => 'AND');
    $post_ids_from_specs = null; // Used if we need to intersect with location

    foreach ($filters as $key => $value) {
        $definition = $all_filter_definitions[$key] ?? null;
        if ($definition) {
            $type = $definition['type'];
            $is_multi = $definition['multi'];
            $base_key = str_replace(array('_min', '_max'), '', $key); // Get the ACF field name

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
    }
    $debug_info['built_main_meta_query'] = $meta_query; // Log the built query

    // 3. Build the Initial WP_Query args based on SPEC filters only
    $query_args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'fields'         => 'ids', // We only need IDs for counting
        'posts_per_page' => -1,    // Get all matching posts
        'meta_query'     => $meta_query,
        'tax_query'      => $tax_query,
    );

    // --- Apply Location Filter to the Base Query for Specs ---
    if ($location_filtered_post_ids !== null) {
        if (empty($location_filtered_post_ids)) { // No cars matched location
            $query_args['post__in'] = array(0); // Ensure no results
        } else {
            $query_args['post__in'] = $location_filtered_post_ids;
        }
    }
    $debug_info['base_query_args_for_counts'] = $query_args;

    $base_query_for_counts = new WP_Query($query_args);
    $matching_post_ids = $base_query_for_counts->posts;
    $debug_info['base_matching_post_ids_count_after_specs_and_location'] = count($matching_post_ids);

    if (empty($matching_post_ids)) {
        // If no cars match the current COMBINATION of spec filters and location,
        // then counts for all other fields will be zero.
        $all_counts = array();
        // Populate $all_counts with zero counts for all defined filters
        foreach (array_keys($all_filter_definitions) as $filter_key_for_zero_count) {
             $def = $all_filter_definitions[$filter_key_for_zero_count];
             $def_type = $def['type'];
             $meta_key_for_choices = $def['meta_key'] ?? $filter_key_for_zero_count;

             if ($def_type === 'range_min') { // Only need to init for _min or _max once per range base
                 $all_counts[$meta_key_for_choices . '_min_cumulative_counts'] = array();
                 $all_counts[$meta_key_for_choices . '_max_cumulative_counts'] = array();
             } elseif ($def_type === 'simple') {
                 $all_counts[$filter_key_for_zero_count] = array(); // JS expects an object/array for counts
                 // If you have a way to get all possible choices for this simple field from PHP, you could initialize them to 0 here.
                 // For example, if choices_function was defined for simple types:
                 // if (isset($def['choices_function']) && function_exists($def['choices_function'])) {
                 //    $choices = $def['choices_function']();
                 //    foreach (array_keys($choices) as $choice_val) {
                 //        $all_counts[$filter_key_for_zero_count][$choice_val] = 0;
                 //    }
                 // }
             }
        }
        // Ensure specific keys expected by JS for make/model/variant are present
        if (!isset($all_counts['make'])) $all_counts['make'] = [];
        if (!isset($all_counts['model'])) $all_counts['model'] = [];
        if (!isset($all_counts['variant'])) $all_counts['variant'] = [];

        $debug_info['zero_counts_because_no_base_matches'] = $all_counts;
        wp_send_json_success($all_counts);
        return;
    }

    // 4. Calculate Counts for Each Filter Based on Potentially Further Refined Matching IDs
    $all_counts = array();
    global $wpdb;

    // Iterate over ALL defined filter fields to calculate their available counts
    foreach ($all_filter_definitions as $field_to_count => $definition_to_count) {
        // $field_to_count is like 'make', 'year_min', 'fuel_type'
        // $definition_to_count is its corresponding entry from $all_filter_definitions

        $temp_meta_query = $meta_query; // Base meta query from active filters
        $temp_tax_query = $tax_query;   // Base tax query

        // If the field_to_count is ITSELF an active filter, we need to temporarily remove it
        // from $temp_meta_query or $temp_tax_query to get counts for its available options
        // based on *other* active filters.
        $meta_key_of_field_being_counted = $definition_to_count['meta_key'] ?? $field_to_count;

        if (isset($filters[$field_to_count])) { // Check if the field_to_count itself is an active filter
            if ($definition_to_count['type'] === 'simple' || strpos($definition_to_count['type'], 'range_') === 0) {
                $new_temp_meta = array('relation' => 'AND');
                foreach ($temp_meta_query as $idx => $q_part) {
                    if (is_array($q_part) && isset($q_part['key']) && $q_part['key'] === $meta_key_of_field_being_counted) {
                        // Skip this part if it's filtering the field we are currently counting
                    } else {
                        $new_temp_meta[] = $q_part;
                    }
                }
                $temp_meta_query = $new_temp_meta;
            } elseif ($definition_to_count['type'] === 'taxonomy') {
                // Similar logic for tax_query if needed, though not common for car specs here
            }
        }
        
        // Construct the query to get IDs for calculating counts for the current $field_to_count
        $ids_for_this_count_args = array(
            'post_type'      => 'car',
            'post_status'    => 'publish',
            'fields'         => 'ids', 
            'posts_per_page' => -1,
            'meta_query'     => $temp_meta_query, 
            'tax_query'      => $temp_tax_query,
        );
        if ($location_filtered_post_ids !== null) {
            // If location filter is active, all count queries must respect it.
            $ids_for_this_count_args['post__in'] = $location_filtered_post_ids;
        }

        $ids_for_this_field_value_query = new WP_Query($ids_for_this_count_args);
        $ids_to_get_values_from = $ids_for_this_field_value_query->posts;

        if (empty($ids_to_get_values_from)) {
            // If no posts match (e.g., location + other spec filters yield no results for this specific field's context)
            // Initialize counts for this field as empty/zero.
            if (strpos($definition_to_count['type'], 'range_') === 0) {
                $base_range_key = $definition_to_count['meta_key'];
                $all_counts[$base_range_key . '_min_cumulative_counts'] = array();
                $all_counts[$base_range_key . '_max_cumulative_counts'] = array();
            } else {
                $all_counts[$field_to_count] = array();
            }
            continue; // Skip to next field_to_count
        }

        // Now, get the actual values for $field_to_count from $ids_to_get_values_from and count them
        $actual_meta_key_to_get = $definition_to_count['meta_key'];

        if ($definition_to_count['type'] === 'simple') {
            $field_counts = array();
            foreach ($ids_to_get_values_from as $pid) {
                $value = get_post_meta($pid, $actual_meta_key_to_get, true);
                if ($definition_to_count['multi'] && is_string($value)) { // Handle comma-separated stored as string for multi-select
                    $value_array = array_map('trim', explode(',', $value));
                    foreach ($value_array as $v_single) {
                        if (!empty($v_single)) {
                           $field_counts[$v_single] = ($field_counts[$v_single] ?? 0) + 1;
                        }
                    }
                } elseif (is_array($value)) { // Handle ACF fields that genuinely return array (e.g. checkbox)
                     foreach($value as $v_single){
                        if (!empty($v_single)) {
                           $field_counts[$v_single] = ($field_counts[$v_single] ?? 0) + 1;
                        }
                    }
                } elseif (!empty($value) && is_string($value)) { // Single value
                    $field_counts[$value] = ($field_counts[$value] ?? 0) + 1;
                }
            }
            $all_counts[$field_to_count] = $field_counts;
        } elseif (strpos($definition_to_count['type'], 'range_') === 0) {
            $choices_func_name = $definition_to_count['choices_function'] ?? null;
            $range_choices = [];
            if ($choices_func_name && function_exists($choices_func_name)) {
                $range_choices = $choices_func_name(); // e.g. get_year_choices_for_filter()
            } else {
                // Fallback to get distinct values from the DB if no choices function is defined
                // This ensures even dynamically added years/mileages etc., could be counted.
                $distinct_values = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN (" . implode(',', array_fill(0, count($ids_to_get_values_from), '%d')) . ") ORDER BY CAST(meta_value AS SIGNED) ASC",
                    array_merge([$actual_meta_key_to_get], $ids_to_get_values_from)
                ));
                foreach ($distinct_values as $dv) {
                    if (is_numeric($dv)) $range_choices[strval($dv)] = strval($dv);
                }
            }

            $cumulative_counts = get_cumulative_range_counts_for_field($actual_meta_key_to_get, $range_choices, $ids_to_get_values_from);
            $all_counts[$actual_meta_key_to_get . '_min_cumulative_counts'] = $cumulative_counts['min_counts'];
            $all_counts[$actual_meta_key_to_get . '_max_cumulative_counts'] = $cumulative_counts['max_counts'];
        }
        // Add other types like 'taxonomy' if needed
    }
    $debug_info['final_all_counts'] = $all_counts;

    // Ensure specific keys expected by JS for make/model/variant are present, even if empty
    if (!isset($all_counts['make'])) $all_counts['make'] = [];
    if (!isset($all_counts['model'])) $all_counts['model'] = [];
    if (!isset($all_counts['variant'])) $all_counts['variant'] = [];

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
function get_cumulative_range_counts_for_field($meta_key, $range_choices, $post_ids) {
    $cumulative_counts = array('min_counts' => array(), 'max_counts' => array());
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
        $cumulative_counts['min_counts'][$choice] = $min_counts[$choice] ?? 0;
        $cumulative_counts['max_counts'][$choice] = $min_counts[$choice] ?? 0;
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