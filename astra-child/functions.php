<?php

require_once get_stylesheet_directory() . '/vendor/autoload.php';

// Include Mapbox assets
require_once get_stylesheet_directory() . '/includes/mapbox-assets.php';

// Include car listings functionality
require_once get_stylesheet_directory() . '/includes/car-listings/car-listings.php';

// Include car submission functionality
require_once get_stylesheet_directory() . '/includes/car-submission.php';

// Include detailed car listing functionality
require_once get_stylesheet_directory() . '/includes/car-listing-detailed.php';

// Include My Account and My Listings functionality
require_once get_stylesheet_directory() . '/includes/user-account/my-account/my-account.php';
require_once get_stylesheet_directory() . '/includes/user-account/my-listings/my-listings.php';


// Include Favourite Listings functionality
require_once get_stylesheet_directory() . '/includes/shortcodes/favourite-listings.php';

// Include theme setup and enqueueing functions
require_once get_stylesheet_directory() . '/includes/enqueue.php';

require_once get_stylesheet_directory() . '/includes/auth/registration.php';

// Include custom user roles definitions
require_once get_stylesheet_directory() . '/includes/auth/roles.php';

// Include custom user profile field functions
require_once get_stylesheet_directory() . '/includes/user-account/user-profile.php';

// Include backend access and admin bar controls
require_once get_stylesheet_directory() . '/includes/auth/access-control.php';

// Include login/logout and phone authentication functions
require_once get_stylesheet_directory() . '/includes/auth/login-logout.php';

// Include AJAX handlers
require_once get_stylesheet_directory() . '/includes/ajax.php';

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