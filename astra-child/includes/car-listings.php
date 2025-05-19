<?php
/**
 * Car Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Include helper files
require_once __DIR__ . '/car-listings-data.php';
require_once __DIR__ . '/car-listings-query.php'; // Added this line
require_once __DIR__ . '/car-filter-form.php'; // Include the new filter form file
require_once __DIR__ . '/geo-utils.php'; // Include geo utility functions


// Register the shortcode
add_shortcode('car_listings', 'display_car_listings');

function display_car_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Enqueue the main stylesheet for this shortcode
    wp_enqueue_style(
        'car-listings-style',
        get_stylesheet_directory_uri() . '/css/car-listings.css',
        array(), 
        filemtime(get_stylesheet_directory() . '/css/car-listings.css')
    );

    // Enqueue assets for the map location filter
    if (!is_admin()) {
        // Enqueue Mapbox GL JS
        wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0');
        wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true);

        // Enqueue Mapbox Geocoder
        wp_enqueue_style('mapbox-geocoder-css', 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css', array('mapbox-gl-css'), '5.0.0');
        wp_enqueue_script('mapbox-geocoder-js', 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js', array('mapbox-gl-js'), '5.0.0', true);
        
        // Enqueue Turf.js for geo calculations (like drawing circles)
        wp_enqueue_script('turf-js', 'https://npmcdn.com/@turf/turf/turf.min.js', array(), '6.5.0', true);


        // Enqueue custom CSS & JS for the map filter
        wp_enqueue_style(
            'car-listings-map-filter-style',
            get_stylesheet_directory_uri() . '/css/car-listings-map-filter.css',
            array('mapbox-gl-css', 'mapbox-geocoder-css'),
            filemtime(get_stylesheet_directory() . '/css/car-listings-map-filter.css')
        );
        wp_enqueue_script(
            'car-listings-map-filter-js',
            get_stylesheet_directory_uri() . '/js/car-listings-map-filter.js',
            array('jquery', 'mapbox-gl-js', 'mapbox-geocoder-js', 'turf-js'),
            filemtime(get_stylesheet_directory() . '/js/car-listings-map-filter.js'),
            true
        );
        
        wp_localize_script('car-listings-map-filter-js', 'carListingsMapFilterData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('filter_listings_by_location_nonce'),
            'mapboxConfig' => array( 
                'accessToken' => defined('MAPBOX_ACCESS_TOKEN') ? MAPBOX_ACCESS_TOKEN : '',
                'style' => 'mapbox://styles/mapbox/streets-v12',
                'defaultZoom' => 8,
                'cyprusCenter' => [33.3823, 35.1856] 
            ),
            'initialFilter' => array(
                'lat' => null,
                'lng' => null,
                'radius' => null, 
                'text' => 'All of Cyprus'
            )
        ));
    }

    // Localize script for AJAX functionality (favorites)
    wp_localize_script('jquery', 'carListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car')
    ));

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Check if a location filter is active via GET parameters
    $has_location_filter_in_url = isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['radius']);


    // Build the query arguments using the helper function
    $args = array(
        'post_type' => 'car',
        // If a location filter is in the URL, load 0 posts initially, JS will fetch correctly.
        // Otherwise, load the default number of posts per page.
        'posts_per_page' => $has_location_filter_in_url ? 0 : $atts['per_page'], 
        'paged' => $paged,
        'orderby' => $atts['orderby'],       // Use shortcode attribute
        'order'   => $atts['order'],         // Use shortcode attribute
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'is_sold',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => 'is_sold',
                'value' => '1',
                'compare' => '!='
            )
        )
    );

    // Get car listings
    $car_query = new WP_Query($args);

    // Removed Debugging

    // Start the output
    ?>
    <div class="car-listings-container">
        <div class="car-listings-location-filter-bar">
            <span id="current-location-filter-text">All of Cyprus</span>
            <button id="change-location-filter-btn" class="button">Change Location</button>
        </div>

        <!-- Active Filters Bar -->
        <div class="active-filters-bar">
            <div class="active-filters-container">
                <!-- JS will populate this with active spec filters -->
            </div>
            <button class="filters-button" id="open-spec-filters-popup-btn">Filters</button>
        </div>

        <!-- Filters Popup -->
        <div class="filters-popup-overlay" id="spec-filters-popup" style="display:none;">
            <div class="filters-popup-content">
                <div class="filters-popup-header">
                    <h2>Filter Cars</h2>
                    <button class="close-filters" id="close-spec-filters-popup-btn">&times;</button>
                </div>
                <?php 
                // Display the new spec filter form
                if (function_exists('autoagora_display_spec_filters')) {
                    echo autoagora_display_spec_filters();
                }
                ?>
            </div>
        </div>

        <!-- Location Filter Modal -->
        <div id="location-filter-modal" class="location-picker-modal" style="display:none;">
            <div class="location-picker-content">
                <div class="location-picker-header">
                    
                    <button class="close-modal" id="close-location-filter-modal">&times;</button>
                </div>
                <div class="location-picker-body">
                    <div class="geocoder-apply-wrapper">
                        <div id="filter-geocoder" class="geocoder"></div>
                        <button id="apply-location-filter-btn" class="choose-location-btn">Apply Location</button>
                    </div>
                    <div id="filter-map-container" class="location-map">
                        <div class="radius-slider-container">
                            <label for="radius-slider">Radius: <span id="radius-value">10</span> km</label>
                            <input type="range" min="1" max="100" value="10" class="slider" id="radius-slider">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Listings Grid -->
        <div class="car-listings-grid">
            <?php
            if ($car_query->have_posts()) :
                while ($car_query->have_posts()) : $car_query->the_post();
                    // Generate the detail page URL once
                    $car_detail_url = esc_url(get_permalink(get_the_ID()));

                    // Get car details
                    $make = get_field('make', get_the_ID());
                    $model = get_field('model', get_the_ID());
                    $variant = get_field('variant', get_the_ID());
                    $year = get_field('year', get_the_ID());
                    $price = get_field('price', get_the_ID());
                    $mileage = get_field('mileage', get_the_ID());
                    $car_city = get_field('car_city', get_the_ID());
                    $car_district = get_field('car_district', get_the_ID());
                    $display_location = '';
                    if (!empty($car_city) && !empty($car_district)) {
                        $display_location = $car_city . ' - ' . $car_district;
                    } elseif (!empty($car_city)) {
                        $display_location = $car_city;
                    } elseif (!empty($car_district)) {
                        $display_location = $car_district;
                    }
                    $engine_capacity = get_field('engine_capacity', get_the_ID());
                    $fuel_type = get_field('fuel_type', get_the_ID());
                    $transmission = get_field('transmission', get_the_ID());
                    $exterior_color = get_field('exterior_color', get_the_ID());
                    $interior_color = get_field('interior_color', get_the_ID());
                    $description = get_field('description', get_the_ID());
                    $body_type = get_field('body_type', get_the_ID());
                    $drive_type = get_field('drive_type', get_the_ID());
                    $number_of_doors = get_field('number_of_doors', get_the_ID());
                    $number_of_seats = get_field('number_of_seats', get_the_ID());
                    $motuntil = get_field('motuntil', get_the_ID());
                    $extras = get_field('extras', get_the_ID());
                    $vehiclehistory = get_field('vehiclehistory', get_the_ID());
                    $publication_date = get_field('publication_date', get_the_ID());
                    ?>
                    <div class="car-listing-card">
                        <?php 
                        // Get all car images
                        $featured_image = get_post_thumbnail_id(get_the_ID());
                        $additional_images = get_field('car_images', get_the_ID());
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
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">';
                                    if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                        echo '<a href="' . $car_detail_url . '" class="see-all-images" style="display: none;">See All Images</a>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                            echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                            
                            $user_id = get_current_user_id();
                            $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                            $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                            $is_favorite = in_array(get_the_ID(), $favorite_cars);
                            $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                            $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                            echo '<button class="' . esc_attr($button_class) . '" data-car-id="' . get_the_ID() . '"><i class="' . esc_attr($heart_class) . '"></i></button>';
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                        
                        <a href="<?php echo $car_detail_url; ?>" class="car-listing-link">
                            <div class="car-listing-details">
                                <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                                <div class="car-specs">
                                    <?php 
                                    $specs_array = array();
                                    if (!empty($engine_capacity)) {
                                        $specs_array[] = esc_html($engine_capacity) . 'L';
                                    }
                                    if (!empty($fuel_type)) {
                                        $specs_array[] = esc_html($fuel_type);
                                    }
                                    if (!empty($body_type)) {
                                        $specs_array[] = esc_html($body_type);
                                    }
                                    if (!empty($transmission)) {
                                        $specs_array[] = esc_html($transmission);
                                    }
                                    
                                    echo implode(' | ', $specs_array);
                                    ?>
                                </div>
                                <div class="car-info-boxes">
                                <div class="info-box">
                                        <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                                    </div>
                                    <div class="info-box">
                                        <span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage ?? '0'))); ?> km</span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price ?? '0'))); ?></div>
                                <div class="car-listing-additional-info">
                                    <?php 
                                    if (!$publication_date) {
                                        $publication_date = get_the_date('Y-m-d H:i:s');
                                        update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                                    }
                                    $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                                    echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                                    ?>
                                    <p class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html($display_location); ?></span></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php
                endwhile;
            else :
                echo '<p class="no-listings">No car listings found.</p>';
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <!-- Pagination -->
        <div class="car-listings-pagination">
            <?php
            echo paginate_links(array(
                'total' => $car_query->max_num_pages,
                'current' => $paged,
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
                'format'  => '?paged=%#%',
            ));
            ?>
        </div>
    </div>

    <?php
    // Return the buffered content
    return ob_get_clean();
}



// AJAX handler for filtering listings
add_action('wp_ajax_filter_listings_by_location', 'autoagora_filter_listings_by_location_ajax');
add_action('wp_ajax_nopriv_filter_listings_by_location', 'autoagora_filter_listings_by_location_ajax');

function autoagora_filter_listings_by_location_ajax() {
    check_ajax_referer('filter_listings_by_location_nonce', 'nonce');

    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;

    // Prepare all filters from POST data for build_car_listings_query_args
    $all_filters_from_post = $_POST;

    // Rename filter_lat, filter_lng, filter_radius to lat, lng, radius for build_car_listings_query_args
    if (isset($all_filters_from_post['filter_lat'])) {
        $all_filters_from_post['lat'] = $all_filters_from_post['filter_lat'];
        unset($all_filters_from_post['filter_lat']);
    }
    if (isset($all_filters_from_post['filter_lng'])) {
        $all_filters_from_post['lng'] = $all_filters_from_post['filter_lng'];
        unset($all_filters_from_post['filter_lng']);
    }
    if (isset($all_filters_from_post['filter_radius'])) {
        $all_filters_from_post['radius'] = $all_filters_from_post['filter_radius'];
        unset($all_filters_from_post['filter_radius']);
    }
    
    // Extract location filter for passing to count functions
    $location_filter = null;
    if (isset($all_filters_from_post['lat']) && $all_filters_from_post['lat'] !== 'null' && 
        isset($all_filters_from_post['lng']) && $all_filters_from_post['lng'] !== 'null' && 
        isset($all_filters_from_post['radius']) && $all_filters_from_post['radius'] !== 'null') {
        $location_filter = array(
            'lat' => floatval($all_filters_from_post['lat']),
            'lng' => floatval($all_filters_from_post['lng']),
            'radius' => floatval($all_filters_from_post['radius'])
        );
    }

    // Remove keys not intended for build_car_listings_query_args as filters
    unset($all_filters_from_post['action']);
    unset($all_filters_from_post['nonce']);
    unset($all_filters_from_post['paged']);
    unset($all_filters_from_post['per_page']);

    // Default atts for query builder
    $atts_for_query = array(
        'per_page' => $per_page,
        'orderby' => isset($all_filters_from_post['orderby']) ? sanitize_text_field($all_filters_from_post['orderby']) : 'date',
        'order'   => isset($all_filters_from_post['order']) ? sanitize_text_field($all_filters_from_post['order']) : 'DESC'
    );

    // Ensure car-listings-query.php is loaded
    require_once __DIR__ . '/car-listings-query.php';

    // Build query args with location filter
    $args = build_car_listings_query_args($atts_for_query, $paged, $all_filters_from_post);
    
    // Get the car query
    $car_query = new WP_Query($args);

    // Get all makes from the database (predefined list)
    $all_makes = get_field_object('make')['choices'];

    // Initialize make counts
    $make_counts = array();
    foreach ($all_makes as $make_value => $make_label) {
        $make_counts[$make_value] = 0;
    }

    // Count makes in the current query results
    if ($car_query->have_posts()) {
        while ($car_query->have_posts()) {
            $car_query->the_post();
            $make = get_field('make');
            if ($make && isset($make_counts[$make])) {
                $make_counts[$make]++;
            }
        }
        wp_reset_postdata();
    }

    // Generate listings HTML
    ob_start();
    if ($car_query->have_posts()) :
        while ($car_query->have_posts()) : $car_query->the_post();
            // ... existing listing HTML generation code ...
        endwhile;
    else :
        echo '<p class="no-listings">No car listings found matching your criteria.</p>';
    endif;
    $listings_html = ob_get_clean();

    // Generate pagination HTML
    ob_start();
    global $wp_query;
    $original_wp_query = $wp_query;
    $wp_query = $car_query;
    echo paginate_links(array(
        'total' => $wp_query->max_num_pages,
        'current' => $paged,
        'prev_text' => '&laquo; Previous',
        'next_text' => 'Next &raquo;',
        'format'  => '?paged=%#%',
    ));
    $pagination_html = ob_get_clean();
    $wp_query = $original_wp_query;
    wp_reset_postdata();

    // Prepare filter counts response
    $filter_counts = array(
        'make' => $make_counts
    );

    // Send response
    wp_send_json_success(array(
        'listings_html' => $listings_html,
        'pagination_html' => $pagination_html,
        'query_vars' => $car_query->query_vars,
        'filter_counts' => $filter_counts
    ));
}

function autoagora_get_dynamic_filter_counts($current_filters) {
    $all_counts = array();

    // Define all your filterable ACF fields and their properties
    $filterable_fields = array(
        'make' => array('type' => 'text', 'meta_key' => 'make'),
        'model' => array('type' => 'text', 'meta_key' => 'model', 'dependent_on' => 'make'),
        'variant' => array('type' => 'text', 'meta_key' => 'variant', 'dependent_on' => 'model'),
        'engine_capacity' => array('type' => 'number_range', 'meta_key' => 'engine_capacity'),
        'transmission' => array('type' => 'select', 'meta_key' => 'transmission'),
        'hp' => array('type' => 'number_range', 'meta_key' => 'hp'),
        'fuel_type' => array('type' => 'select_multiple', 'meta_key' => 'fuel_type'),
        'exterior_color' => array('type' => 'select_multiple', 'meta_key' => 'exterior_color'),
        'interior_color' => array('type' => 'select_multiple', 'meta_key' => 'interior_color'),
        'body_type' => array('type' => 'select_multiple', 'meta_key' => 'body_type'),
        'drive_type' => array('type' => 'select_multiple', 'meta_key' => 'drive_type'),
        'number_of_doors' => array('type' => 'select', 'meta_key' => 'number_of_doors'),
        'number_of_seats' => array('type' => 'select', 'meta_key' => 'number_of_seats'),
        'extras' => array('type' => 'checkbox', 'meta_key' => 'extras'),
        'mileage' => array('type' => 'number_range', 'meta_key' => 'mileage'),
        'year' => array('type' => 'number_range', 'meta_key' => 'year'),
        'price' => array('type' => 'number_range', 'meta_key' => 'price'),
        'availability' => array('type' => 'select', 'meta_key' => 'availability'),
        'mot_until' => array('type' => 'text_date', 'meta_key' => 'motuntil'), // Assuming text or date
        'number_of_owners' => array('type' => 'number_range', 'meta_key' => 'numowners'),
        'is_antique' => array('type' => 'boolean', 'meta_key' => 'isantique'),
        'vehicle_history' => array('type' => 'checkbox', 'meta_key' => 'vehiclehistory')
    );

    // Mapping from ACF field keys to keys expected by JS functions
    $js_to_acf_map = array(
        'make' => 'make',
        'model_by_make' => 'model', // This will be handled specially
        'fuel_type' => 'fuel_type',
        'transmission' => 'transmission',
        'body_type' => 'body_type',
        'drive_type' => 'drive_type',
        'exterior_color' => 'exterior_color',
        'interior_color' => 'interior_color',
        'year' => 'year',
        'engine' => 'engine_capacity', // JS uses 'engine' for form field name 'engine_capacity_min/max'
        'mileage' => 'mileage',
        'price' => 'price',
        'extras' => 'extras',
        'vehicle_history' => 'vehicle_history',
        'number_of_doors' => 'number_of_doors',
        'number_of_seats' => 'number_of_seats',
        'hp' => 'hp',
        'availability' => 'availability',
        'is_antique' => 'is_antique'
        // 'variant', 'mot_until', 'number_of_owners' might need their own UI/JS handling if not covered by generic types
    );


    foreach ($filterable_fields as $field_name_key => $field_config) {
        $meta_key_to_count = $field_config['meta_key'];
        
        // Determine the key to use in the $all_counts array for JS
        $js_target_key = $field_name_key; // Default to field name
        foreach($js_to_acf_map as $js_k => $acf_k) {
            if ($acf_k === $field_name_key) {
                $js_target_key = $js_k;
                break;
            }
        }
        if ($field_name_key === 'model') { // Special case for model
            $js_target_key = 'model_by_make';
        }


        $counting_query_args = array(
            'post_type' => 'car',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
                    array('key' => 'is_sold', 'value' => '1', 'compare' => '!='),
                )
            )
        );

        $active_location_post_ids = null;
        if (isset($current_filters['lat']) && $current_filters['lat'] !== '' && $current_filters['lat'] !== 'null' &&
            isset($current_filters['lng']) && $current_filters['lng'] !== '' && $current_filters['lng'] !== 'null' &&
            isset($current_filters['radius']) && $current_filters['radius'] !== '' && $current_filters['radius'] !== 'null') {

            $all_car_ids_for_loc_query = new WP_Query(array(
                'post_type' => 'car',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => 'is_sold', 'compare' => 'NOT EXISTS'),
                    array('key' => 'is_sold', 'value' => '1', 'compare' => '!='),
                )
            ));
            $matching_location_car_ids = array();
            if ($all_car_ids_for_loc_query->have_posts()) {
                foreach ($all_car_ids_for_loc_query->posts as $car_id_for_loc) {
                    $car_lat = get_post_meta($car_id_for_loc, 'car_latitude', true);
                    $car_lng = get_post_meta($car_id_for_loc, 'car_longitude', true);
                    if ($car_lat && $car_lng && function_exists('autoagora_calculate_distance')) {
                        $distance = autoagora_calculate_distance(
                            floatval($current_filters['lat']), floatval($current_filters['lng']),
                            floatval($car_lat), floatval($car_lng)
                        );
                        if ($distance <= floatval($current_filters['radius'])) {
                            $matching_location_car_ids[] = $car_id_for_loc;
                        }
                    }
                }
            }
            wp_reset_postdata(); // After custom WP_Query

            if (empty($matching_location_car_ids)) {
                $counting_query_args['post__in'] = array(0); // Effectively no posts
            } else {
                $counting_query_args['post__in'] = $matching_location_car_ids;
            }
            $active_location_post_ids = $counting_query_args['post__in'];
        }
        
        $current_meta_query = $counting_query_args['meta_query'];

        foreach ($filterable_fields as $other_field_key => $other_config) {
            if ($other_config['meta_key'] === $meta_key_to_count) continue; // Skip self

            $filter_val_key = $other_config['meta_key']; // e.g. make, model, fuel_type etc.
            
            // For range filters, check _min and _max
            if ($other_config['type'] === 'number_range') {
                if (isset($current_filters[$filter_val_key . '_min']) && $current_filters[$filter_val_key . '_min'] !== '') {
                    $current_meta_query[] = array(
                        'key' => $other_config['meta_key'],
                        'value' => $current_filters[$filter_val_key . '_min'],
                        'type' => 'NUMERIC',
                        'compare' => '>='
                    );
                }
                if (isset($current_filters[$filter_val_key . '_max']) && $current_filters[$filter_val_key . '_max'] !== '') {
                    $current_meta_query[] = array(
                        'key' => $other_config['meta_key'],
                        'value' => $current_filters[$filter_val_key . '_max'],
                        'type' => 'NUMERIC',
                        'compare' => '<='
                    );
                }
            } elseif (isset($current_filters[$filter_val_key]) && !empty($current_filters[$filter_val_key])) {
                $value = $current_filters[$filter_val_key];
                if ($other_config['type'] === 'checkbox' || $other_config['type'] === 'select_multiple') {
                    $value_array = is_array($value) ? $value : array($value);
                    foreach($value_array as $single_value) {
                        if($single_value !== '') {
                             $current_meta_query[] = array(
                                'key' => $other_config['meta_key'],
                                'value' => '"' . sanitize_text_field($single_value) . '"', // ACF stores checkbox values serialized like "value"
                                'compare' => 'LIKE'
                            );
                        }
                    }
                } else if ($other_config['type'] === 'boolean') {
                     $current_meta_query[] = array(
                        'key' => $other_config['meta_key'],
                        'value' => $value == '1' || $value === 'true' ? 1 : 0, // ACF boolean stores 1 or 0
                        'type' => 'NUMERIC',
                        'compare' => '='
                    );
                }
                else { // Text, select
                    $current_meta_query[] = array(
                        'key' => $other_config['meta_key'],
                        'value' => sanitize_text_field($value),
                        'compare' => '='
                    );
                }
            }
        }
        $counting_query_args['meta_query'] = $current_meta_query;

        $relevant_posts_query = new WP_Query($counting_query_args);
        $field_value_counts = array();

        if ($relevant_posts_query->have_posts()) {
            if ($field_name_key === 'model') { // model_by_make
                $make_meta_key = $filterable_fields['make']['meta_key'];
                while ($relevant_posts_query->have_posts()) {
                    $relevant_posts_query->the_post();
                    $make_value = get_post_meta(get_the_ID(), $make_meta_key, true);
                    $model_value = get_post_meta(get_the_ID(), $meta_key_to_count, true);
                    if ($make_value && $model_value) {
                        if (!isset($field_value_counts[$make_value])) $field_value_counts[$make_value] = array();
                        if (!isset($field_value_counts[$make_value][$model_value])) $field_value_counts[$make_value][$model_value] = 0;
                        $field_value_counts[$make_value][$model_value]++;
                    }
                }
            } elseif ($field_config['type'] === 'checkbox' || $field_config['type'] === 'select_multiple') {
                while ($relevant_posts_query->have_posts()) {
                    $relevant_posts_query->the_post();
                    $values = get_post_meta(get_the_ID(), $meta_key_to_count, true);
                    if (is_array($values)) {
                        foreach ($values as $value_item) {
                             if ($value_item !== '' && $value_item !== null) {
                                if (!isset($field_value_counts[$value_item])) $field_value_counts[$value_item] = 0;
                                $field_value_counts[$value_item]++;
                             }
                        }
                    } elseif ($values !== '' && $values !== null) {
                        if (!isset($field_value_counts[$values])) $field_value_counts[$values] = 0;
                        $field_value_counts[$values]++;
                    }
                }
            } else { // Text, select, number_range, boolean
                while ($relevant_posts_query->have_posts()) {
                    $relevant_posts_query->the_post();
                    $value = get_post_meta(get_the_ID(), $meta_key_to_count, true);
                     if ($field_config['type'] === 'boolean') {
                        $value = $value ? '1' : '0'; // Normalize for counting (e.g. "Yes (5)", "No (10)")
                    }
                    if ($value !== '' && $value !== null) {
                        if (!isset($field_value_counts[$value])) $field_value_counts[$value] = 0;
                        $field_value_counts[$value]++;
                    }
                }
                if ($field_config['type'] === 'number_range' || $field_config['meta_key'] === 'year') {
                    ksort($field_value_counts, SORT_NUMERIC);
                } else if ($field_config['type'] !== 'boolean'){ // Don't sort booleans, keep 1 then 0 or vice-versa
                    ksort($field_value_counts); // Sort string keys alphabetically
                }
            }
        }
        wp_reset_postdata();
        $all_counts[$js_target_key] = $field_value_counts;
    }
    return $all_counts;
}