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
// require_once __DIR__ . '/car-listings-render.php'; // Potential future file

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
        array(), // Dependencies
        filemtime(get_stylesheet_directory() . '/css/car-listings.css') // Versioning based on file modification time
    );

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Removed fetching of filter data (makes, models, variants, locations, prices, years, etc.)
    // as the filter form content is being removed.

    // Build the query arguments using the helper function
    $args = build_car_listings_query_args($atts, $paged);

    // Get car listings
    $car_query = new WP_Query($args);

    // Removed Debugging

    // Start the output
    ?>
    <div class="car-listings-container">
        <!-- Active Filters Bar (structure kept, but no filters to display) -->
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
            <form method="get" class="filters-form">
                <?php 
                // Display the new filter form with 'listings_page' context
                echo display_car_filter_form('listings_page'); 
                ?>
            </form>
            </div>
        </div>

        <!-- Listings Grid -->
        <div class="car-listings-grid">
            <?php
            if ($car_query->have_posts()) :
                while ($car_query->have_posts()) : $car_query->the_post();
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
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">'; // Use clean year in alt
                                    if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                        echo '<a href="' . get_permalink() . '" class="see-all-images" style="display: none;">See All Images</a>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '<button class="carousel-nav prev" style="display: none;"><i class="fas fa-chevron-left"></i></button>';
                            echo '<button class="carousel-nav next" style="display: none;"><i class="fas fa-chevron-right"></i></button>';
                            
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
                                </div>
                                <div class="car-info-boxes">
                                    <div class="info-box">
                                        <span class="info-value"><?php echo number_format($mileage); ?> km</span>
                                    </div>
                                    <div class="info-box">
                                        <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format($price); ?></div>
                                <div class="car-location"><?php echo esc_html($location); ?></div>
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