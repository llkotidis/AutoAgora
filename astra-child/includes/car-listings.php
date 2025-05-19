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

        <!-- Active Filters Bar (structure kept, but no filters to display) -->
        <?php /*
        <div class="active-filters-bar">
            <div class="active-filters-container">
                <!-- Active filters area - will be empty -->
            </div>
            <button class="filters-button">Filters</button> <!-- Button kept, though popup is empty -->
        </div>

        <!-- Filters Popup -->
        <div class="filters-popup-overlay" id="filtersPopup">
            <div class="filters-popup-content">
                <div class="filters-popup-header">
                    <h2>Filter Cars</h2>
                    <button class="close-filters">&times;</button>
                </div>
                <?php 
                // Display the new filter form with 'listings_page' context
                // echo display_car_filter_form('listings_page'); 
                ?>
            </div>
        </div>
        */ ?>

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
    // car-listings-map-filter.js sends filter_lat, filter_lng, filter_radius directly
    // and forwards other URL parameters as direct keys.
    $all_filters_from_post = $_POST; // Start with all POST data

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

    // Default atts for query builder (can be overridden if passed in $all_filters_from_post)
    $atts_for_query = array(
        'per_page' => $per_page,
        'orderby' => isset($all_filters_from_post['orderby']) ? sanitize_text_field($all_filters_from_post['orderby']) : 'date',
        'order'   => isset($all_filters_from_post['order']) ? sanitize_text_field($all_filters_from_post['order']) : 'DESC'
    );

    // Ensure car-listings-query.php (containing build_car_listings_query_args) is loaded
    require_once __DIR__ . '/car-listings-query.php';

    // Make sure location filter is always included in the car_listings_query_args
    // This ensures location filtering is respected when any filter changes
    if ($location_filter !== null) {
        // The location filter parameters already exist in $all_filters_from_post
        // But we want to ensure they're used correctly in build_car_listings_query_args
        // Simply make sure they're not accidentally removed elsewhere in the code
        $args = build_car_listings_query_args($atts_for_query, $paged, $all_filters_from_post);
    } else {
        $args = build_car_listings_query_args($atts_for_query, $paged, $all_filters_from_post);
    }
    
    $car_query = new WP_Query($args);

    ob_start();
    if ($car_query->have_posts()) :
        while ($car_query->have_posts()) : $car_query->the_post();
            $car_id = get_the_ID();
            $car_detail_url = esc_url(get_permalink($car_id));
            $make = get_field('make', $car_id);
            $model = get_field('model', $car_id);
            $year = get_field('year', $car_id);
            $price = get_field('price', $car_id);
            $mileage = get_field('mileage', $car_id);
            
            $car_city_ajax = get_field('car_city', $car_id);
            $car_district_ajax = get_field('car_district', $car_id);
            $display_location_ajax = '';
            if (!empty($car_city_ajax) && !empty($car_district_ajax)) {
                $display_location_ajax = $car_city_ajax . ' - ' . $car_district_ajax;
            } elseif (!empty($car_city_ajax)) {
                $display_location_ajax = $car_city_ajax;
            } elseif (!empty($car_district_ajax)) {
                $display_location_ajax = $car_district_ajax;
            }

            $engine_capacity = get_field('engine_capacity', $car_id);
            $fuel_type = get_field('fuel_type', $car_id);
            $body_type = get_field('body_type', $car_id);
            $transmission = get_field('transmission', $car_id);
            $publication_date = get_field('publication_date', $car_id);

            $car_latitude_for_card = get_field('car_latitude', $car_id);
            $car_longitude_for_card = get_field('car_longitude', $car_id);
            $card_data_attrs = '';
            // Check if filter_lat and filter_lng were part of the POST request indicating an active filter
            if (isset($_POST['filter_lat']) && $_POST['filter_lat'] !== 'null' && isset($_POST['filter_lng']) && $_POST['filter_lng'] !== 'null' && $car_latitude_for_card && $car_longitude_for_card) {
                $card_data_attrs = ' data-latitude="' . esc_attr($car_latitude_for_card) . '" data-longitude="' . esc_attr($car_longitude_for_card) . '"';
            }
             ?>
            <div class="car-listing-card"<?php echo $card_data_attrs; ?>>
                <?php 
                $featured_image = get_post_thumbnail_id($car_id);
                $additional_images = get_field('car_images', $car_id);
                $all_images = array();
                if ($featured_image) $all_images[] = $featured_image;
                if (is_array($additional_images)) $all_images = array_merge($all_images, $additional_images);
                
                if (!empty($all_images)): ?>
                <div class="car-listing-image-container">
                    <div class="car-listing-image-carousel" data-post-id="<?php echo $car_id; ?>">
                        <?php foreach ($all_images as $index => $image_id):
                            $image_url = wp_get_attachment_image_url($image_id, 'medium');
                            if ($image_url):
                                $clean_year = str_replace(',', '', $year); ?>
                                <div class="car-listing-image<?php echo ($index === 0 ? ' active' : ''); ?>" data-index="<?php echo $index; ?>">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($clean_year . ' ' . $make . ' ' . $model); ?>">
                                    <?php if ($index === count($all_images) - 1 && count($all_images) > 1): ?>
                                        <a href="<?php echo $car_detail_url; ?>" class="see-all-images" style="display: none;">See All Images</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif;
                        endforeach; ?>
                        <button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>
                        <button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>
                        <?php
                        $user_id = get_current_user_id();
                        $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                        $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                        $is_favorite = in_array($car_id, $favorite_cars);
                        $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                        $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                        ?>
                        <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo $car_id; ?>"><i class="<?php echo esc_attr($heart_class); ?>"></i></button>
                    </div>
                </div>
                <?php endif; ?>
                
                <a href="<?php echo $car_detail_url; ?>" class="car-listing-link">
                    <div class="car-listing-details">
                        <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                        <div class="car-specs">
                            <?php 
                            $specs_array = array();
                            if (!empty($engine_capacity)) $specs_array[] = esc_html($engine_capacity) . 'L';
                            if (!empty($fuel_type)) $specs_array[] = esc_html($fuel_type);
                            if (!empty($body_type)) $specs_array[] = esc_html($body_type);
                            if (!empty($transmission)) $specs_array[] = esc_html($transmission);
                            echo implode(' | ', $specs_array);
                            ?>
                        </div>
                        <div class="car-info-boxes">
                            <div class="info-box"><span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span></div>
                            <div class="info-box"><span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage ?? '0'))); ?> km</span></div>
                        </div>
                        <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price ?? '0'))); ?></div>
                        <div class="car-listing-additional-info">
                            <?php 
                            if (!$publication_date) {
                                $publication_date = get_the_date('Y-m-d H:i:s', $car_id);
                                update_post_meta($car_id, 'publication_date', $publication_date);
                            }
                            $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                            echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                            ?>
                            <p class="car-location"><i class="fas fa-map-marker-alt"></i> <span class="location-text"><?php echo esc_html($display_location_ajax); ?></span></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php
        endwhile;
    else :
        echo '<p class="no-listings">No car listings found matching your criteria.</p>';
    endif;
    $listings_html = ob_get_clean();

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

    // Include the car-filter-form.php file for filter counts
    // require_once __DIR__ . '/car-filter-form.php'; // Removed as spec filter form is removed from this page
    
    // Get filter counts for different fields, respecting the location filter
    // This ensures that the filter counts reflect only the cars available within the selected location
    /* // Removed filter_counts generation
    $filter_counts = array();
    if (function_exists('get_counts_for_meta_key')) {
        $make_field_key = 'make';
        $model_field_key = 'model';
        $fuel_type_field_key = 'fuel_type';
        $transmission_field_key = 'transmission';
        $ext_color_field_key = 'exterior_color';
        $int_color_field_key = 'interior_color';
        $body_type_field_key = 'body_type';
        $drive_type_field_key = 'drive_type';
        $year_field_key = 'year';
        $engine_cap_field_key = 'engine_capacity';
        $mileage_field_key = 'mileage';
        
        $filter_counts = array(
            'make' => get_counts_for_meta_key($make_field_key, $location_filter),
            'fuel_type' => get_counts_for_meta_key($fuel_type_field_key, $location_filter),
            'transmission' => get_counts_for_meta_key($transmission_field_key, $location_filter),
            'exterior_color' => get_counts_for_meta_key($ext_color_field_key, $location_filter),
            'interior_color' => get_counts_for_meta_key($int_color_field_key, $location_filter),
            'body_type' => get_counts_for_meta_key($body_type_field_key, $location_filter),
            'drive_type' => get_counts_for_meta_key($drive_type_field_key, $location_filter),
            'year' => get_counts_for_meta_key($year_field_key, $location_filter),
            'engine_capacity' => get_counts_for_meta_key($engine_cap_field_key, $location_filter),
            'mileage' => get_counts_for_meta_key($mileage_field_key, $location_filter)
        );
        
        // Get models grouped by make counts
        $model_counts_by_make = array();
        global $wpdb;
        
        // Get all makes first
        $makes = array_keys($filter_counts['make']);
        
        if (!empty($makes) && $location_filter) {
            // If location filter is active, we need to filter models by location
            $all_car_ids_query = $wpdb->get_results(
                "SELECT p.ID, pm_lat.meta_value as latitude, pm_lng.meta_value as longitude
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id AND pm_lat.meta_key = 'car_latitude'
                 JOIN {$wpdb->postmeta} pm_lng ON p.ID = pm_lng.post_id AND pm_lng.meta_key = 'car_longitude'
                 WHERE p.post_type = 'car'
                 AND p.post_status = 'publish'",
                ARRAY_A
            );
            
            // Calculate which cars are within the radius
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

            if (!empty($matching_car_ids)) {
                $make_placeholders = implode(', ', array_fill(0, count($makes), '%s'));
                $car_id_placeholders = implode(', ', array_fill(0, count($matching_car_ids), '%d'));
                
                $sql = $wpdb->prepare(
                    "SELECT pm_make.meta_value as make, pm_model.meta_value as model, COUNT(p.ID) as count
                     FROM {$wpdb->posts} p
                     JOIN {$wpdb->postmeta} pm_make ON p.ID = pm_make.post_id AND pm_make.meta_key = %s
                     JOIN {$wpdb->postmeta} pm_model ON p.ID = pm_model.post_id AND pm_model.meta_key = %s
                     WHERE p.post_type = 'car'
                     AND p.post_status = 'publish'
                     AND pm_make.meta_value IN ({$make_placeholders})
                     AND pm_model.meta_value IS NOT NULL AND pm_model.meta_value != ''
                     AND p.ID IN ({$car_id_placeholders})
                     GROUP BY pm_make.meta_value, pm_model.meta_value",
                    array_merge([$make_field_key, $model_field_key], $makes, $matching_car_ids)
                );
                
                $model_counts_results = $wpdb->get_results($sql);
                foreach ($model_counts_results as $row) {
                    if (!isset($model_counts_by_make[$row->make])) {
                        $model_counts_by_make[$row->make] = array();
                    }
                    $model_counts_by_make[$row->make][$row->model] = (int)$row->count;
                }
            }
        } else if (!empty($makes)) {
            // No location filter, get all models by make
            $make_placeholders = implode(', ', array_fill(0, count($makes), '%s'));
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
                array_merge([$make_field_key, $model_field_key], $makes)
            );
            $model_counts_results = $wpdb->get_results($sql);
            foreach ($model_counts_results as $row) {
                if (!isset($model_counts_by_make[$row->make])) {
                    $model_counts_by_make[$row->make] = array();
                }
                $model_counts_by_make[$row->make][$row->model] = (int)$row->count;
            }
        }
        
        $filter_counts['model_by_make'] = $model_counts_by_make;
    }
    */ // End of removed filter_counts generation

    wp_send_json_success(array(
        'listings_html' => $listings_html,
        'pagination_html' => $pagination_html,
        'query_vars' => $car_query->query_vars
        // Removed 'filter_counts' => $filter_counts 
    ));
}