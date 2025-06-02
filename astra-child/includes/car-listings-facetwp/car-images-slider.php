<?php
/*
Plugin Name: Car Images Slider Shortcode
Description: Provides the [car-images-slider] shortcode for car image carousels.
Version: 1.0
Author: Your Name
*/

if (!function_exists('car_images_slider_shortcode')) {
    function car_images_slider_shortcode($atts) {
        global $post;
        $car_post_id = isset($atts['post_id']) ? $atts['post_id'] : ($post ? $post->ID : null);
        if (!$car_post_id) return '';
        // Enqueue carousel assets only when shortcode is used
        add_action('wp_enqueue_scripts', 'car_images_slider_enqueue_assets');
        ob_start();
        $featured_image    = function_exists('get_post_thumbnail_id') ? get_post_thumbnail_id($car_post_id) : null;
        $additional_images = function_exists('get_field') ? get_field('car_images', $car_post_id) : array();
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
                $make = function_exists('get_field') ? get_field('make', $car_post_id) : '';
                $model = function_exists('get_field') ? get_field('model', $car_post_id) : '';
                $year = function_exists('get_field') ? get_field('year', $car_post_id) : '';
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
}