<?php
/**
 * Car Filter Form Functionality - REMOVED
 * 
 * This file previously contained functions to generate and handle 
 * the car filtering form for specifications. This functionality has been removed.
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// All functions related to display_car_filter_form, its helpers, 
// and the enqueueing of car-filter.js have been removed from this file.
// The location filter functionality is handled separately in car-listings.php and car-listings-map-filter.js.

// The geo-utils.php is still required by other parts of the theme for location features.
require_once __DIR__ . '/geo-utils.php';

?>