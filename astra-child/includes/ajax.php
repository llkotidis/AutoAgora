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
                                echo '<a href="' . get_permalink() . '" class="see-all-images" style="display: none;">See All Images</a>';
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
                
                <a href="<?php echo esc_url(add_query_arg('car_id', get_the_ID(), get_permalink(get_page_by_path('car-listing-detailed')))); ?>" class="car-listing-link">
                    <div class="car-listing-details">
                        <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                        <div class="car-specs">
                            <?php echo esc_html($engine_capacity); ?>L
                            <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                            <?php 
                                $body_type = get_post_meta(get_the_ID(), 'body_type', true);
                                echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                            ?>
                            <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                            <?php 
                                $drive_type = get_post_meta(get_the_ID(), 'drive_type', true);
                                echo !empty($drive_type) ? ' ' . esc_html($drive_type) : '';
                            ?>
                        </div>
                        <div class="car-info-boxes">
                            <div class="info-box">
                                <span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage))); ?> km</span>
                            </div>
                            <div class="info-box">
                                <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                            </div>
                        </div>
                        <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price))); ?></div>
                        <div class="car-location"><?php echo esc_html($location); ?></div>
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
    // 1. Verify Nonce
    check_ajax_referer('car_filter_update_nonce', 'nonce'); 

    // 2. Get and Sanitize All Filter Inputs
    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
    $sanitized_filters = array();
    $meta_query = array('relation' => 'AND'); // Start meta query

    // Define filter keys and their types (simple meta, range_min, range_max)
    // Mark multi-select fields
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

    // Build meta_query from received filters
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

    // 3. Query for Matching Post IDs
    $query_args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all matching posts
        'fields'         => 'ids', // Only get post IDs for efficiency
        'meta_query'     => $meta_query,
    );
    $matching_post_ids = get_posts($query_args);

    // 4. Calculate Counts for Each Filter Based on Matching IDs
    $updated_counts = array();

    if (!empty($matching_post_ids)) {
        global $wpdb;
        $post_id_placeholders = implode(',', array_fill(0, count($matching_post_ids), '%d'));

        // Fields to get counts for (excluding range fields)
        $count_fields = ['location', 'make', 'model', 'variant', 'fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type']; 

        foreach ($count_fields as $field_key) {
            $sql = $wpdb->prepare(
                "SELECT meta_value, COUNT(DISTINCT post_id) as count 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key = %s 
                 AND post_id IN ($post_id_placeholders)
                 AND meta_value IS NOT NULL AND meta_value != ''
                 GROUP BY meta_value",
                array_merge([$field_key], $matching_post_ids)
            );
            $results = $wpdb->get_results($sql, OBJECT_K); // Index by meta_value

            $field_counts = array();
            if ($results) {
                foreach ($results as $value => $data) {
                    $field_counts[$value] = (int)$data->count;
                }
            }
            $updated_counts[$field_key] = $field_counts;
        }
        // Note: We don't calculate counts for range inputs (year, mileage, engine) dynamically in this example.
        // Their options remain static, only the main select filters update counts.

    } else {
        // If no posts match, return empty counts for all fields
         $count_fields = ['location', 'make', 'model', 'variant', 'fuel_type', 'transmission', 'exterior_color', 'interior_color', 'body_type', 'drive_type']; 
        foreach ($count_fields as $field_key) {
            $updated_counts[$field_key] = array();
        }
    }
    
    // Handle potential empty model/variant counts if make/model selected
    if (empty($updated_counts['model'])) {
        $updated_counts['model'] = array();
    }
     if (empty($updated_counts['variant'])) {
        $updated_counts['variant'] = array();
    }


    // 5. Send JSON Response
    wp_send_json_success($updated_counts);
    wp_die(); // Always die in AJAX handlers
}

// Hook the new handler
add_action('wp_ajax_update_filter_counts', 'ajax_update_filter_counts_handler');
add_action('wp_ajax_nopriv_update_filter_counts', 'ajax_update_filter_counts_handler'); 