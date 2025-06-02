<?php
/**
 * FacetWP Car Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode('facetwp_car_listings', 'display_facetwp_car_listings');

function display_facetwp_car_listings($atts) {
    // Enqueue the main stylesheet for this shortcode
    wp_enqueue_style(
        'car-listings-style',
        get_stylesheet_directory_uri() . '/includes/car-listings/car-listings.css',
        array(), 
        filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings.css')
    );

    // Enqueue the map filter stylesheet
    wp_enqueue_style(
        'car-listings-map-filter-style',
        get_stylesheet_directory_uri() . '/includes/car-listings/car-listings-map-filter.css',
        array(),
        filemtime(get_stylesheet_directory() . '/includes/car-listings/car-listings-map-filter.css')
    );

    // Start output buffering
    ob_start();

    // Get the current post
    global $post;
    if (!$post) {
        return '';
    }

    // Get car details
    $make = get_field('make', $post->ID);
    $model = get_field('model', $post->ID);
    $variant = get_field('variant', $post->ID);
    $year = get_field('year', $post->ID);
    $price = get_field('price', $post->ID);
    $mileage = get_field('mileage', $post->ID);
    $car_city = get_field('car_city', $post->ID);
    $car_district = get_field('car_district', $post->ID);
    $display_location = '';
    if (!empty($car_city) && !empty($car_district)) {
        $display_location = $car_city . ' - ' . $car_district;
    } elseif (!empty($car_city)) {
        $display_location = $car_city;
    } elseif (!empty($car_district)) {
        $display_location = $car_district;
    }
    $engine_capacity = get_field('engine_capacity', $post->ID);
    $fuel_type = get_field('fuel_type', $post->ID);
    $transmission = get_field('transmission', $post->ID);
    $description = get_field('description', $post->ID);
    $publication_date = get_field('publication_date', $post->ID);

    // Generate the detail page URL
    $car_detail_url = esc_url(get_permalink($post->ID));

    // Start the car listing card output
    ?>
    <div class="car-listing-card">
        <div class="car-listing-image-carousel">
            <?php 
            // Get all car images
            $featured_image = get_post_thumbnail_id($post->ID);
            $additional_images = get_field('car_images', $post->ID);
            $all_images = array();
            
            if ($featured_image) {
                $all_images[] = $featured_image;
            }
            
            if (is_array($additional_images)) {
                $all_images = array_merge($all_images, $additional_images);
            }

            if (!empty($all_images)) :
                foreach ($all_images as $index => $image_id) :
                    $image_url = wp_get_attachment_image_url($image_id, 'large');
                    if ($image_url) :
                        ?>
                        <div class="car-listing-image <?php echo $index === 0 ? 'active' : ''; ?>">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                        </div>
                        <?php
                    endif;
                endforeach;
            endif;
            ?>

            <?php if (count($all_images) > 1) : ?>
                <button class="carousel-nav prev" aria-label="Previous image">&lt;</button>
                <button class="carousel-nav next" aria-label="Next image">&gt;</button>
                <div class="image-counter">1/<?php echo count($all_images); ?></div>
            <?php endif; ?>

            <?php if (!empty($all_images)) : ?>
                <a href="<?php echo esc_url($car_detail_url); ?>" class="see-all-images">See All Images</a>
            <?php endif; ?>
        </div>

        <div class="car-listing-details">
            <h3 class="car-title">
                <?php 
                $title_parts = array_filter([$make, $model, $variant, $year]);
                echo esc_html(implode(' ', $title_parts));
                ?>
            </h3>

            <div class="car-price">
                €<?php echo number_format($price, 0, '.', ','); ?>
            </div>

            <div class="car-specs">
                <div class="car-info-boxes">
                    <?php if ($mileage) : ?>
                        <div class="info-box">
                            <span class="info-value"><?php echo number_format($mileage, 0, '.', ','); ?> km</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($engine_capacity) : ?>
                        <div class="info-box">
                            <span class="info-value"><?php echo esc_html($engine_capacity); ?>L</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($fuel_type) : ?>
                        <div class="info-box">
                            <span class="info-value"><?php echo esc_html($fuel_type); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($transmission) : ?>
                        <div class="info-box">
                            <span class="info-value"><?php echo esc_html($transmission); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($display_location) : ?>
                <div class="car-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo esc_html($display_location); ?>
                </div>
            <?php endif; ?>

            <?php if ($description) : ?>
                <div class="car-description">
                    <?php echo wp_trim_words($description, 20, '...'); ?>
                </div>
            <?php endif; ?>

            <a href="<?php echo esc_url($car_detail_url); ?>" class="view-details">View Details</a>

            <?php if (is_user_logged_in()) : ?>
                <button class="favorite-btn" data-car-id="<?php echo esc_attr($post->ID); ?>">
                    <i class="far fa-heart"></i>
                </button>
            <?php endif; ?>

            <?php if ($publication_date) : ?>
                <div class="car-publication-date">
                    Listed: <?php echo date('d/m/Y', strtotime($publication_date)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    // Get the buffered content
    $output = ob_get_clean();

    return $output;
} 