<?php
/**
 * Car Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Include helper files
require_once __DIR__ . '/car-listings-data.php';
require_once __DIR__ . '/car-listings-query.php';
require_once __DIR__ . '/car-filter-form.php';

// Register the shortcode
add_shortcode('car_listings', 'display_car_listings');

function display_car_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Enqueue the stylesheet for this shortcode
    wp_enqueue_style(
        'car-listings-style',
        get_stylesheet_directory_uri() . '/css/car-listings.css',
        array(),
        filemtime(get_stylesheet_directory() . '/css/car-listings.css')
    );

    // Enqueue Mapbox CSS
    wp_enqueue_style(
        'astra-child-mapbox-css',
        get_stylesheet_directory_uri() . '/css/mapbox.css',
        array(),
        ASTRA_CHILD_THEME_VERSION
    );

    // Localize script for AJAX functionality
    wp_localize_script('jquery', 'carListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car')
    ));

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Build the query arguments using the helper function
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => 12,
        'paged' => $paged,
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

    // Start the output
    ?>
    <div class="car-listings-container">
        <!-- Active Filters Bar -->
        <div class="active-filters-bar">
            <div class="active-filters-container">
                <!-- Active filters area -->
            </div>
            <button class="filters-button">Filters</button>
        </div>

        <!-- Filters Popup -->
        <div class="filters-popup-overlay" id="filtersPopup">
            <div class="filters-popup-content">
                <div class="filters-popup-header">
                    <h2>Filter Cars</h2>
                    <button class="close-filters">&times;</button>
                </div>
                <?php 
                echo display_car_filter_form('listings_page'); 
                ?>
            </div>
        </div>

        <!-- Map View Toggle -->
        <div class="view-toggle">
            <button class="view-toggle-btn active" data-view="grid">
                <i class="fas fa-th"></i> Grid
            </button>
            <button class="view-toggle-btn" data-view="map">
                <i class="fas fa-map"></i> Map
            </button>
        </div>

        <!-- Map Container (initially hidden) -->
        <div id="map" class="map-container" style="display: none;"></div>

        <!-- Listings Grid -->
        <div class="car-listings-grid">
            <?php
            if ($car_query->have_posts()) :
                while ($car_query->have_posts()) : $car_query->the_post();
                    // Generate the detail page URL once
                    $car_detail_url = esc_url(get_permalink(get_the_ID()));

                    $make = get_post_meta(get_the_ID(), 'make', true);
                    $model = get_post_meta(get_the_ID(), 'model', true);
                    $variant = get_post_meta(get_the_ID(), 'variant', true);
                    $price = get_post_meta(get_the_ID(), 'price', true);
                    $year = get_post_meta(get_the_ID(), 'year', true);
                    $engine_capacity = get_post_meta(get_the_ID(), 'engine_capacity', true);
                    $transmission = get_post_meta(get_the_ID(), 'transmission', true);
                    $mileage = get_post_meta(get_the_ID(), 'mileage', true);
                    $location_address = get_post_meta(get_the_ID(), 'location_address', true);
                    $location_city = get_post_meta(get_the_ID(), 'location_city', true);
                    $location_district = get_post_meta(get_the_ID(), 'location_district', true);
                    $location_lat = get_post_meta(get_the_ID(), 'location_lat', true);
                    $location_lng = get_post_meta(get_the_ID(), 'location_lng', true);
                    ?>
                    <div class="car-listing-card" 
                         data-lat="<?php echo esc_attr($location_lat); ?>"
                         data-lng="<?php echo esc_attr($location_lng); ?>"
                         data-title="<?php echo esc_attr($year . ' ' . $make . ' ' . $model); ?>"
                         data-price="<?php echo esc_attr($price); ?>"
                         data-url="<?php echo esc_url($car_detail_url); ?>">
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
                                    $clean_year = str_replace(',', '', $year);
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
                                    
                                    $body_type = get_post_meta(get_the_ID(), 'body_type', true);
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
                                        <span class="info-value"><?php echo number_format($mileage); ?> km</span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format($price); ?></div>
                                <div class="car-listing-additional-info">
                                    <?php 
                                    $publication_date = get_post_meta(get_the_ID(), 'publication_date', true);
                                    if (!$publication_date) {
                                        $publication_date = get_the_date('Y-m-d H:i:s');
                                        update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                                    }
                                    $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                                    echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                                    ?>
                                    <p class="car-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php 
                                        $location_parts = array_filter([$location_district, $location_city]);
                                        echo esc_html(implode(', ', $location_parts));
                                        ?>
                                    </p>
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
                'next_text' => 'Next &raquo;'
            ));
            ?>
        </div>
    </div>

    <?php
    // Return the buffered content
    return ob_get_clean();
}