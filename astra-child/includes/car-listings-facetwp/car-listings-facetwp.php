<?php
/**
 * Car Listings FacetWP Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Car_Listings_FacetWP {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('car-listings-facetwp', array($this, 'render_car_listings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_load_car_listings', array($this, 'ajax_load_car_listings'));
        add_action('wp_ajax_nopriv_load_car_listings', array($this, 'ajax_load_car_listings'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'car-listings-facetwp',
            get_stylesheet_directory_uri() . '/includes/car-listings-facetwp/car-listings-facetwp.js',
            array('jquery'),
            filemtime(get_stylesheet_directory() . '/includes/car-listings-facetwp/car-listings-facetwp.js'),
            true
        );

        wp_enqueue_style(
            'car-listings-facetwp',
            get_stylesheet_directory_uri() . '/includes/car-listings-facetwp/car-listings-facetwp.css',
            array(),
            filemtime(get_stylesheet_directory() . '/includes/car-listings-facetwp/car-listings-facetwp.css')
        );

        wp_localize_script('car-listings-facetwp', 'carListingsFacetWP', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('car_listings_facetwp_nonce')
        ));
    }

    public function render_car_listings($atts) {
        $atts = shortcode_atts(array(
            'posts_per_page' => 12,
            'columns' => 3,
        ), $atts);

        ob_start();
        ?>
        <div class="car-listings-facetwp-container" 
             data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>"
             data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <div class="car-listings-grid">
                <?php $this->render_listings(); ?>
            </div>
            <div class="car-listings-loading" style="display: none;">
                <div class="loading-spinner"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_listings() {
        $args = $this->get_query_args();
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_listing_card();
            }
            wp_reset_postdata();
        } else {
            echo '<div class="no-listings-found">No cars found matching your criteria.</div>';
        }
    }

    private function render_listing_card($car_post_id = null) {
        if (!$car_post_id) {
            $car_post_id = get_the_ID();
        }
        $car_detail_url = esc_url(get_permalink($car_post_id));
        $make             = get_field('make', $car_post_id);
        $model            = get_field('model', $car_post_id);
        $variant          = get_field('variant', $car_post_id);
        $year             = get_field('year', $car_post_id);
        $price            = get_field('price', $car_post_id);
        $mileage          = get_field('mileage', $car_post_id);
        $car_city         = get_field('car_city', $car_post_id);
        $car_district     = get_field('car_district', $car_post_id);
        $display_location = '';
        if (!empty($car_city) && !empty($car_district)) {
            $display_location = $car_city . ' - ' . $car_district;
        } elseif (!empty($car_city)) {
            $display_location = $car_city;
        } elseif (!empty($car_district)) {
            $display_location = $car_district;
        }
        $engine_capacity  = get_field('engine_capacity', $car_post_id);
        $fuel_type        = get_field('fuel_type', $car_post_id);
        $transmission     = get_field('transmission', $car_post_id);
        $body_type        = get_field('body_type', $car_post_id);
        $publication_date = get_field('publication_date', $car_post_id);
        $latitude         = get_field('car_latitude', $car_post_id);
        $longitude        = get_field('car_longitude', $car_post_id);
        ?>
        <div class="car-listing-card"
             data-city="<?php echo esc_attr($car_city); ?>"
             data-district="<?php echo esc_attr($car_district); ?>"
             data-latitude="<?php echo esc_attr($latitude); ?>"
             data-longitude="<?php echo esc_attr($longitude); ?>"
             data-post-id="<?php echo esc_attr($car_post_id); ?>">
            <?php
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
                    if ($image_url) {
                        $clean_year = str_replace(',', '', $year);
                        echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . esc_attr($index) . '">';
                        echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">';
                        if ($index === count($all_images) - 1 && count($all_images) > 1) {
                            echo '<a href="' . $car_detail_url . '" class="see-all-images" style="display: none;">See All Images</a>';
                        }
                        echo '</div>';
                    }
                }
                echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                $user_id       = get_current_user_id();
                $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                $is_favorite   = in_array($car_post_id, $favorite_cars);
                $button_class  = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                $heart_class   = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                echo '<button class="' . esc_attr($button_class) . '" data-car-id="' . esc_attr($car_post_id) . '"><i class="' . esc_attr($heart_class) . '"></i></button>';
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
                            <span class="info-value"><?php echo esc_html(str_replace(',', '', $year ?? '')); ?></span>
                        </div>
                        <div class="info-box">
                            <span class="info-value"><?php echo number_format(floatval(str_replace(',', '', $mileage ?? '0'))); ?> km</span>
                        </div>
                    </div>
                    <div class="car-price">€<?php echo number_format(floatval(str_replace(',', '', $price ?? '0'))); ?></div>
                    <div class="car-listing-additional-info">
                        <?php
                        if (!$publication_date) {
                            $publication_date = get_the_date('Y-m-d H:i:s', $car_post_id);
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
    }

    private function get_query_args() {
        $args = array(
            'post_type' => 'car',
            'posts_per_page' => 12,
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        );

        // Get FacetWP parameters from URL
        $price_range = isset($_GET['_price']) ? explode(',', $_GET['_price']) : array();
        $transmission = isset($_GET['_transmission']) ? $_GET['_transmission'] : '';
        $interior_color = isset($_GET['_interior_color']) ? $_GET['_interior_color'] : '';

        // Add meta queries based on FacetWP parameters
        $meta_query = array('relation' => 'AND');

        if (!empty($price_range)) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => array($price_range[0], $price_range[1]),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN'
            );
        }

        if (!empty($transmission)) {
            $meta_query[] = array(
                'key' => '_transmission',
                'value' => $transmission,
                'compare' => '='
            );
        }

        if (!empty($interior_color)) {
            $meta_query[] = array(
                'key' => '_interior_color',
                'value' => $interior_color,
                'compare' => '='
            );
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    public function ajax_load_car_listings() {
        check_ajax_referer('car_listings_facetwp_nonce', 'nonce');

        ob_start();
        $this->render_listings();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html
        ));
    }
}

// Initialize the class
Car_Listings_FacetWP::get_instance(); 