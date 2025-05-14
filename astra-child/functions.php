<?php

require_once get_stylesheet_directory() . '/vendor/autoload.php';

// Define Mapbox token constant
define('MAPBOX_ACCESS_TOKEN', getenv('MAPBOX_ACCESS_TOKEN'));

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

// Include Mapbox functionality
require_once get_stylesheet_directory() . '/includes/mapbox.php';

// Include Shortcodes
require_once get_stylesheet_directory() . '/includes/shortcodes/account-display.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/favourites-button.php';
require_once get_stylesheet_directory() . '/includes/shortcodes/car-search-form.php';

// Include admin user favorites column functionality
require_once get_stylesheet_directory() . '/includes/admin/user-favorites-column.php';


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