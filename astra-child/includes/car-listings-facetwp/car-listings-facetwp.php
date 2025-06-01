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

    private function render_listing_card() {
        $price = get_post_meta(get_the_ID(), '_price', true);
        $transmission = get_post_meta(get_the_ID(), '_transmission', true);
        $interior_color = get_post_meta(get_the_ID(), '_interior_color', true);
        ?>
        <div class="car-listing-card">
            <div class="car-listing-image">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('medium'); ?>
                <?php endif; ?>
            </div>
            <div class="car-listing-details">
                <h3 class="car-listing-title"><?php the_title(); ?></h3>
                <div class="car-listing-price"><?php echo esc_html(number_format($price, 2)); ?></div>
                <div class="car-listing-specs">
                    <span class="transmission"><?php echo esc_html($transmission); ?></span>
                    <span class="interior-color"><?php echo esc_html($interior_color); ?></span>
                </div>
                <a href="<?php the_permalink(); ?>" class="view-details-btn">View Details</a>
            </div>
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