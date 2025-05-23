<?php
/**
 * My Account Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Include separated files
require_once get_stylesheet_directory() . '/includes/my-account/my-account-display.php';
require_once get_stylesheet_directory() . '/includes/my-account/password-reset.php';

// Register the shortcode
add_shortcode('my_account', 'display_my_account');

//this is proof that github is working

function display_my_account($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your account information.</p>';
    }

    // Enqueue separated CSS and JS files
    wp_enqueue_style('my-account-display-css', get_stylesheet_directory_uri() . '/includes/my-account/my-account-display.css', array(), '1.0.0');
    wp_enqueue_script('my-account-display-js', get_stylesheet_directory_uri() . '/includes/my-account/my-account-display.js', array(), '1.0.0', true);
    
    // Localize script with AJAX data
    wp_localize_script('my-account-display-js', 'MyAccountAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'update_user_name_nonce' => wp_create_nonce('update_user_name'),
        'password_reset_nonce' => wp_create_nonce('password_reset_nonce')
    ));

    // Check if we're in password reset flow
    $password_reset_step = isset($_GET['password_reset_step']) ? sanitize_text_field($_GET['password_reset_step']) : '';
    
    // Enqueue password reset files if in password reset flow
    if ($password_reset_step) {
        wp_enqueue_style('password-reset-css', get_stylesheet_directory_uri() . '/includes/my-account/password-reset.css', array(), '1.0.0');
        wp_enqueue_script('password-reset-js', get_stylesheet_directory_uri() . '/includes/my-account/password-reset.js', array(), '1.0.0', true);
        
        // Localize password reset script with AJAX data
        wp_localize_script('password-reset-js', 'PasswordResetAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'password_reset_nonce' => wp_create_nonce('password_reset_nonce'),
            'verify_password_reset_nonce' => wp_create_nonce('verify_password_reset_nonce'),
            'update_password_reset_nonce' => wp_create_nonce('update_password_reset_nonce')
        ));
    }
    
    if ($password_reset_step === 'verify') {
        return display_password_reset_verify();
    } elseif ($password_reset_step === 'new_password') {
        return display_password_reset_form();
    } elseif ($password_reset_step === 'success') {
        return display_password_reset_success();
    }

    // Normal account display - use the separated function
    $current_user = wp_get_current_user();
    return display_my_account_main($current_user);
}

// Add AJAX handler for updating user name
add_action('wp_ajax_update_user_name', 'handle_update_user_name');
function handle_update_user_name() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_user_name')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get and sanitize input
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

    // Get current user
    $user_id = get_current_user_id();

    // Update user meta - if we got here, the client detected changes
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);

    // Since we've validated the user and nonce, and the client only sends when there are changes,
    // we can assume the update was successful
    wp_send_json_success('Name updated successfully');
}

// Add AJAX handler for initiating password reset
add_action('wp_ajax_initiate_password_reset', 'handle_initiate_password_reset');
function handle_initiate_password_reset() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    $phone_number = get_user_meta($user_id, 'phone_number', true);

    if (empty($phone_number)) {
        wp_send_json_error('No phone number found for your account');
        return;
    }

    // Use the existing Twilio configuration and logic
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing.');
        wp_send_json_error('SMS configuration error. Please contact admin.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($phone_number, "sms");

        error_log("Password reset verification started: SID " . $verification->sid);
        
        // Store the verification session for this user
        set_transient('password_reset_' . $user_id, array(
            'phone' => $phone_number,
            'verification_sid' => $verification->sid,
            'timestamp' => time()
        ), 300); // 5 minutes expiry

        wp_send_json_success('Verification code sent successfully');
    } catch (Exception $e) {
        error_log('Twilio Verify error: ' . $e->getMessage());
        wp_send_json_error('Failed to send verification code. Please try again later.');
    }
}

// Add AJAX handler for verifying password reset code
add_action('wp_ajax_verify_password_reset_code', 'handle_verify_password_reset_code');
function handle_verify_password_reset_code() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'verify_password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    if (empty($code) || strlen($code) !== 6) {
        wp_send_json_error('Invalid verification code format');
        return;
    }

    // Get current user and verification session
    $user_id = get_current_user_id();
    $reset_session = get_transient('password_reset_' . $user_id);

    if (!$reset_session) {
        wp_send_json_error('Verification session expired. Please start over.');
        return;
    }

    // Use Twilio to verify the code
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        wp_send_json_error('SMS configuration error. Please contact admin.');
        return;
    }

    $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

    try {
        $verification_check = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create([
                'to' => $reset_session['phone'],
                'code' => $code
            ]);

        if ($verification_check->status === 'approved') {
            // Store verified session for password update
            set_transient('password_reset_verified_' . $user_id, array(
                'verified' => true,
                'timestamp' => time()
            ), 600); // 10 minutes to complete password reset
            
            // Clean up the verification session
            delete_transient('password_reset_' . $user_id);
            
            wp_send_json_success('Code verified successfully');
        } else {
            wp_send_json_error('Invalid verification code');
        }
    } catch (Exception $e) {
        error_log('Twilio verification check error: ' . $e->getMessage());
        wp_send_json_error('Error verifying code. Please try again.');
    }
}

// Add AJAX handler for updating password
add_action('wp_ajax_update_password_reset', 'handle_update_password_reset');
function handle_update_password_reset() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_password_reset_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Get current user
    $user_id = get_current_user_id();
    
    // Check if user has verified session
    $verified_session = get_transient('password_reset_verified_' . $user_id);
    
    if (!$verified_session || !$verified_session['verified']) {
        wp_send_json_error('Session expired. Please start over.');
        return;
    }

    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    if (empty($new_password) || strlen($new_password) < 8) {
        wp_send_json_error('Password must be at least 8 characters long');
        return;
    }

    // Update the user's password
    $user_data = array(
        'ID' => $user_id,
        'user_pass' => $new_password
    );

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error('Failed to update password: ' . $result->get_error_message());
        return;
    }

    // Clean up the verified session
    delete_transient('password_reset_verified_' . $user_id);
    
    // Log the password change
    error_log("Password reset completed for user ID: $user_id");

    wp_send_json_success('Password updated successfully');
}