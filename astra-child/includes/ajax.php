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
    $all_filter_definitions = [
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
        foreach (array_keys($all_filter_definitions) as $filter_key_for_zero_count) {
            if ($all_filter_definitions[$filter_key_for_zero_count]['type'] === 'range') {
                // For range filters, we need to provide the cumulative count structure even if it's all zeros.
                $all_counts[$filter_key_for_zero_count . '_min_cumulative_counts'] = array();
                $all_counts[$filter_key_for_zero_count . '_max_cumulative_counts'] = array();
                // Initialize with all possible options having 0 count.
                // This requires knowing the $choices for these ranges.
                // This part might need to fetch $year_choices, $mileage_choices, $engine_choices similarly to how it's done in car-filter-form.php
                // For now, let JS handle displaying options and PHP just sends empty count arrays if no base cars.
            } else {
                $all_counts[$filter_key_for_zero_count] = array();
            }
        }
        // Make sure specific make/model/variant structures are also empty if needed by JS
        $all_counts['make'] = [];
        $all_counts['modelByMake'] = []; // Or however JS expects it
        $all_counts['variantByModel'] = [];

        wp_send_json_success($all_counts);
        return;
    }

    // 4. Calculate Counts for Each Filter Based on Potentially Further Refined Matching IDs
    $all_counts = array();
    global $wpdb;

    foreach ($filters as $field_to_count => $value) {
        $definition_to_count = $all_filter_definitions[$field_to_count] ?? null;
        if ($definition_to_count) {
            // --- Calculate counts for the current $field_to_count ---
            $current_field_meta_query = $meta_query; // Start with base meta query from active filters
            $current_field_tax_query = $tax_query;   // Start with base tax query
            $current_field_post_in = $matching_post_ids; // Crucially, work with IDs that ALREADY MATCHED other filters AND location

            // Temporarily remove the filter for the field we are currently counting,
            // to get available options for THIS field based on OTHER active filters.
            if ($definition_to_count['type'] === 'simple' || $definition_to_count['type'] === 'taxonomy') {
                // Remove the filter for the current field from the query
                $current_field_meta_query = array('relation' => 'AND');
                $current_field_tax_query = array('relation' => 'AND');
                $current_field_post_in = $matching_post_ids; // Use the existing matching IDs
            }

            // Build the query to count the current field based on the contextual query
            $count_query_args = array(
                'post_type'      => 'car',
                'post_status'    => 'publish',
                'fields'         => 'ids', 
                'posts_per_page' => -1,
                'meta_query'     => $current_field_meta_query, 
                'tax_query'      => $current_field_tax_query,
                // IMPORTANT: Apply the base set of IDs (already filtered by location and other specs)
                // This was missing, counts were too broad.
                'post__in'       => $current_field_post_in 
            );

            // If counting a range, we don't modify the $current_field_meta_query for the range itself further,
            // the get_cumulative_range_counts_for_field handles it by iterating over $matching_post_ids.

            if ($definition_to_count['type'] !== 'range') {
                $field_values_query = new WP_Query($count_query_args);
                $ids_for_this_field_count = $field_values_query->posts;
            } else {
                // For range, we use the $matching_post_ids directly with the helper
                $ids_for_this_field_count = $matching_post_ids;
            }

            // --- Calculate counts for the current $field_to_count ---
            $field_counts = array();
            foreach ($ids_for_this_field_count as $pid) {
                $value = get_post_meta($pid, $field_to_count, true);
                if (!empty($value)) {
                    if (is_array($value)) { // Handle ACF fields that might return array (e.g. checkbox)
                        foreach ($value as $single_val) {
                            $field_counts[$single_val] = ($field_counts[$single_val] ?? 0) + 1;
                        }
                    } else {
                        $field_counts[$value] = ($field_counts[$value] ?? 0) + 1;
                    }
                }
            }
            $all_counts[$field_to_count] = $field_counts;
        }
    }
    $debug_info['final_all_counts'] = $all_counts;

    wp_send_json_success($all_counts); // $all_counts now contains counts for each filter field
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