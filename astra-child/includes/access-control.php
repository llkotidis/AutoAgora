<?php
/**
 * Access Control Functions (Admin Bar, Backend Access).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect subscribers and dealerships away from the WordPress backend.
 */
function restrict_backend_access() {
    if ( is_admin() && ! current_user_can( 'edit_posts' ) ) {
        // Check if the current user has the 'subscriber' or 'dealership' role
        if ( current_user_can( 'subscriber' ) || current_user_can( 'dealership' ) ) {
            wp_safe_redirect( home_url() ); // Redirect to the homepage
            exit;
        }
    }
}
add_action( 'admin_init', 'restrict_backend_access' );

/**
 * Remove the admin bar for subscribers and dealerships.
 */
function remove_admin_bar_for_specific_roles() {
    if ( ! is_admin() && ( current_user_can( 'subscriber' ) || current_user_can( 'dealership' ) ) ) {
        return false;
    }
    return true;
}
// add_filter( 'show_admin_bar', 'remove_admin_bar_for_specific_roles' ); // Commented out as hide_admin_bar_completely overrides it

/**
 * Hide the admin bar for all users on the frontend except for administrators.
 */
function hide_admin_bar_except_for_admins() {
    // If the current user is an administrator, show the admin bar
    if ( current_user_can( 'administrator' ) ) {
        return true;
    }
    // Otherwise, hide the admin bar for all other users on the frontend
    return false;
}
add_filter( 'show_admin_bar', 'hide_admin_bar_except_for_admins' ); 