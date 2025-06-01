<?php

require_once get_stylesheet_directory() . '/vendor/autoload.php';

// Include Mapbox assets
require_once get_stylesheet_directory() . '/includes/core/mapbox-assets.php';

// Include car listings functionality
require_once get_stylesheet_directory() . '/includes/car-listings/car-listings.php';

// Include FacetWP car listings functionality
require_once get_stylesheet_directory() . '/includes/car-listings-facetwp/car-listings-facetwp.php';

// Include car submission functionality
require_once get_stylesheet_directory() . '/includes/user-manage-listings/car-submission.php';

// Include detailed car listing functionality
require_once get_stylesheet_directory() . '/includes/car-listing-detailed.php';

// Include My Account and My Listings functionality
require_once get_stylesheet_directory() . '/includes/user-account/my-account/my-account.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-listings/my-listings.php';

// Include Favourite Listings functionality
require_once get_stylesheet_directory() . '/includes/shortcodes/favourite-listings.php';

// Include theme setup and enqueueing functions
require_once get_stylesheet_directory() . '/includes/core/enqueue.php';

// Include image optimization for car listings
require_once get_stylesheet_directory() . '/includes/core/image-optimization.php';

// Include asynchronous upload system
require_once get_stylesheet_directory() . '/includes/core/async-uploads.php';

require_once get_stylesheet_directory() . '/includes/auth/registration.php';

// Include custom user roles definitions
require_once get_stylesheet_directory() . '/includes/auth/roles.php';

// Include forgot password AJAX handlers
require_once get_stylesheet_directory() . '/includes/auth/forgot-password-ajax.php';

// Include custom user profile field functions
require_once get_stylesheet_directory() . '/includes/user-account/user-profile.php';

// Include backend access and admin bar controls
require_once get_stylesheet_directory() . '/includes/auth/access-control.php';

// Include login/logout and phone authentication functions
require_once get_stylesheet_directory() . '/includes/auth/login-logout.php';

// Include AJAX handlers
require_once get_stylesheet_directory() . '/includes/core/ajax.php';

// Include Shortcodes
require_once get_stylesheet_directory() . '/includes/shortcodes/account-display.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favourites-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-search-form.php';

// Include admin user favorites column functionality
require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';

// Include car-images-slider shortcode
require_once get_stylesheet_directory() . '/includes/car-listings-facetwp/car-images-slider.php';

/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'ASTRA_CHILD_THEME_VERSION', '1.0.0' );

// Add Favourites Button to Header
function add_favourites_button_to_header() {
    echo do_shortcode('[favourites_button]');
}
add_action('astra_header_right', 'add_favourites_button_to_header', 5);

// Add this function to handle the cities.json file
function autoagora_enqueue_cities_data() {
    // Get the theme directory URL
    $theme_url = get_stylesheet_directory_uri();
    
    // Add the cities data URL to the page
    wp_localize_script('location-picker', 'locationPickerData', array(
        'citiesJsonUrl' => $theme_url . '/simple_jsons/cities.json'
    ));
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_cities_data');

/**
 * Include SVG icon from assets
 */
function get_svg_icon($icon_name) {
    $svg_path = get_stylesheet_directory() . '/assets/svg/regular/' . $icon_name . '.svg';
    if (file_exists($svg_path)) {
        return file_get_contents($svg_path);
    }
    return '';
}

// Register [car-images-slider] shortcode
function car_images_slider_shortcode($atts) {
    global $post;
    $car_post_id = isset($atts['post_id']) ? $atts['post_id'] : ($post ? $post->ID : null);
    if (!$car_post_id) return '';
    ob_start();
    $featured_image    = get_post_thumbnail_id($car_post_id);
    $additional_images = get_field('car_images', $car_post_id);
    $all_images        = array();
    if ($featured_image) {
        $all_images[] = $featured_image;
    }
    if (is_array($additional_images)) {
        $all_images = array_merge($all_images, $additional_images);
    }
    if (!empty($all_images)) {
        echo '<div class="car-listing-image-container">';
        echo '<div class="car-listing-image-carousel" data-post-id="' . esc_attr($car_post_id) . '">';
        foreach ($all_images as $index => $image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
            $make = get_field('make', $car_post_id);
            $model = get_field('model', $car_post_id);
            $year = get_field('year', $car_post_id);
            if ($image_url) {
                $clean_year = str_replace(',', '', $year);
                echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . esc_attr($index) . '">';
                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">';
                if ($index === count($all_images) - 1 && count($all_images) > 1) {
                    echo '<a href="#" class="see-all-images" style="display: none;">See All Images</a>';
                }
                echo '</div>';
            }
        }
        echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
        echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
        echo '</div>';
        echo '</div>';
    }
    return ob_get_clean();
}
add_shortcode('car-images-slider', 'car_images_slider_shortcode');

