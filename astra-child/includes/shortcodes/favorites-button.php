<?php
/**
 * Favorites Button Shortcode [favorites_button]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function favorites_button_shortcode() {
    ob_start();
    
    $favorites_page = get_page_by_path('favourite-listings');
    if ($favorites_page) {
        ?>
        <div class="favorites-button">
            <a href="<?php echo esc_url(get_permalink($favorites_page->ID)); ?>">
                <i class="fas fa-heart"></i>
                <span>Saved</span>
            </a>
        </div>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('favorites_button', 'favorites_button_shortcode'); 