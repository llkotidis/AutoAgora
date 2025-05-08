<?php
/**
 * Favorites Button Shortcode [favorites_button]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates favorites button shortcode [favorites_button].
 * Shows a button that links to the favorite listings page.
 *
 * @return string HTML for the favorites button.
 */
function favorites_button_shortcode() {
    ob_start();

    // Get the favorites page by its slug
    $favorites_page = get_page_by_path('favourite-listings');
    
    if ($favorites_page) {
        ?>
        <div class="favorites-button-container">
            <a href="<?php echo esc_url(get_permalink($favorites_page->ID)); ?>" class="favorites-button">
                <i class="far fa-heart"></i>
                <span class="favorites-text">Favorites</span>
            </a>
        </div>
        <?php
    }

    return ob_get_clean();
}

add_shortcode('favorites_button', 'favorites_button_shortcode');

/**
 * Enqueue styles for the favorites button
 */
function favorites_button_styles() {
    wp_enqueue_style(
        'favorites-button-style',
        get_stylesheet_directory_uri() . '/css/favorites-button.css',
        array(),
        filemtime(get_stylesheet_directory() . '/css/favorites-button.css')
    );
}
add_action('wp_enqueue_scripts', 'favorites_button_styles'); 