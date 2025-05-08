<?php

require_once get_stylesheet_directory() . '/vendor/autoload.php';

// Include car listings functionality
require_once get_stylesheet_directory() . '/includes/car-listings.php';

// Include car submission functionality
require_once get_stylesheet_directory() . '/includes/car-submission.php';

// Include detailed car listing functionality
require_once get_stylesheet_directory() . '/includes/car-listing-detailed.php';

// Include My Account and My Listings functionality
require_once get_stylesheet_directory() . '/includes/my-account.php';
require_once get_stylesheet_directory() . '/includes/my-listings.php';

// Include Favourite Listings functionality
require_once get_stylesheet_directory() . '/includes/shortcodes/favourite-listings.php';

// Include theme setup and enqueueing functions
require_once get_stylesheet_directory() . '/includes/enqueue.php';

require_once get_stylesheet_directory() . '/includes/registration.php';

// Include custom user roles definitions
require_once get_stylesheet_directory() . '/includes/roles.php';

// Include custom user profile field functions
require_once get_stylesheet_directory() . '/includes/user-profile.php';

// Include backend access and admin bar controls
require_once get_stylesheet_directory() . '/includes/access-control.php';

// Include login/logout and phone authentication functions
require_once get_stylesheet_directory() . '/includes/login-logout.php';

// Include AJAX handlers
require_once get_stylesheet_directory() . '/includes/ajax.php';

// Include Shortcodes
require_once get_stylesheet_directory() . '/includes/shortcodes/account-display.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favorites-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-search-form.php';

// Include admin user favorites column functionality
require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';

/**
 * Add favorites and account buttons to the header
 */
function add_favorites_and_account_buttons() {
    echo '<div class="header-buttons">';
    echo do_shortcode('[favorites_button]');
    echo do_shortcode('[account_display]');
    echo '</div>';
}
add_action('astra_header_right', 'add_favorites_and_account_buttons');

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