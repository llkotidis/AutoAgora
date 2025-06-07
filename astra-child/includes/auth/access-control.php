<?php
/**
 * Access Control Functions (Admin Bar, Backend Access).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect subscribers, clients, and dealerships away from the WordPress backend.
 */
function restrict_backend_access() {
    if ( is_admin() ) {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Check if the current user has restricted roles
        $restricted_roles = array('subscriber', 'client', 'dealership');
        
        if ( array_intersect( $restricted_roles, $user_roles ) ) {
            wp_safe_redirect( home_url() ); // Redirect to the homepage
            exit;
        }
    }
}
add_action( 'admin_init', 'restrict_backend_access' );

/**
 * Remove the admin bar for subscribers, clients, and dealerships.
 */
function remove_admin_bar_for_specific_roles() {
    if ( ! is_admin() ) {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        $restricted_roles = array('subscriber', 'client', 'dealership');
        
        if ( array_intersect( $restricted_roles, $user_roles ) ) {
            return false;
        }
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

/**
 * Debug function - Add this temporarily to see what's happening
 * Remove this after testing!
 */
function debug_user_access() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Only show for non-admins to avoid clutter
        if ( ! current_user_can( 'administrator' ) ) {
            echo '<div style="background: #f44336; color: white; padding: 10px; position: fixed; top: 0; left: 0; z-index: 99999; width: 100%;">';
            echo 'DEBUG - User: ' . $current_user->user_login . ' | Roles: ' . implode(', ', $user_roles) . ' | Can edit posts: ' . (current_user_can('edit_posts') ? 'YES' : 'NO');
            echo '</div>';
        }
    }
}
add_action( 'wp_footer', 'debug_user_access' );
add_action( 'admin_footer', 'debug_user_access' ); 