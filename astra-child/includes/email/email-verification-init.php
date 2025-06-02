<?php
/**
 * Email Verification Initialization
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize email_verified field for all existing users
 * One-time function to set email_verified = '0' for all users who don't have it
 */
function initialize_email_verified_for_existing_users() {
    // Get all users who don't have the email_verified meta field
    $users_without_verification = get_users(array(
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'email_verified',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => 'email_verified',
                'value' => '',
                'compare' => '='
            )
        ),
        'number' => -1, // Get all users
    ));

    $updated_count = 0;
    foreach ($users_without_verification as $user) {
        update_user_meta($user->ID, 'email_verified', '0');
        $updated_count++;
    }

    return $updated_count;
}

/**
 * Set default email_verified = '0' for new users during registration
 * Hook into the same user creation process used by registration
 */
function set_default_email_verified_for_new_user($user_id) {
    // Set email_verified to '0' for all new users
    update_user_meta($user_id, 'email_verified', '0');
}
add_action('user_register', 'set_default_email_verified_for_new_user');

/**
 * Admin function to initialize email verification for existing users
 * Add ?init_email_verification=1 to any admin page URL to run this
 * Only admins can run this
 */
function admin_initialize_email_verification() {
    if (isset($_GET['init_email_verification']) && $_GET['init_email_verification'] == '1') {
        // Only allow admins to initialize
        if (!current_user_can('administrator')) {
            wp_die('Access denied.');
        }

        $updated_count = initialize_email_verified_for_existing_users();
        
        // Show success message
        add_action('admin_notices', function() use ($updated_count) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Email Verification Initialized:</strong> Updated ' . $updated_count . ' users with email_verified = "0"</p>';
            echo '</div>';
        });
    }
}
add_action('admin_init', 'admin_initialize_email_verification'); 