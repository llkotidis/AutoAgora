<?php
/**
 * Email Verification System - Twilio Verify
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send email verification using Twilio Verify
 */
function send_verification_email($user_id, $email) {
    // Get Twilio configuration
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing for email verification.');
        return false;
    }

    try {
        $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

        // Send verification email via Twilio Verify
        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($email, "email");

        error_log("Email verification started via Twilio Verify: SID " . $verification->sid . " for email: " . $email);
        
        // Store the verification session for this user
        set_transient('email_verification_' . $user_id, array(
            'email' => $email,
            'verification_sid' => $verification->sid,
            'timestamp' => time()
        ), 600); // 10 minutes expiry

        return true;

    } catch (Exception $e) {
        error_log('Twilio Verify email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify email verification code
 */
function verify_email_verification_code($user_id, $code) {
    // Get verification session
    $verification_session = get_transient('email_verification_' . $user_id);
    
    if (!$verification_session) {
        error_log('Email verification: No session found for user ' . $user_id);
        return false;
    }

    // Get Twilio configuration
    $twilio_sid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $twilio_token = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $twilio_verify_sid = defined('TWILIO_VERIFY_SID') ? TWILIO_VERIFY_SID : '';

    if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_verify_sid)) {
        error_log('Twilio Verify configuration is missing for email verification.');
        return false;
    }

    try {
        $twilio = new \Twilio\Rest\Client($twilio_sid, $twilio_token);

        // Verify the code via Twilio Verify
        $verification_check = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create([
                'to' => $verification_session['email'],
                'code' => $code
            ]);

        if ($verification_check->status === 'approved') {
            // Update user email and verification status
            wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $verification_session['email']
            ));
            
            // Mark email as verified
            update_user_meta($user_id, 'email_verified', '1');
            
            // Clean up the verification session
            delete_transient('email_verification_' . $user_id);
            
            error_log("Email verification successful for user " . $user_id . " with email: " . $verification_session['email']);
            return true;
        } else {
            error_log('Email verification failed: Invalid code for user ' . $user_id);
            return false;
        }

    } catch (Exception $e) {
        error_log('Twilio Verify email verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Handle email verification URL clicks
 */
function handle_email_verification() {
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $token = sanitize_text_field($_GET['token']);
        $verification_data = verify_email_verification_token($token);
        
        if ($verification_data) {
            $user_id = $verification_data['user_id'];
            $email = $verification_data['email'];
            
            // Update user email and verification status
            wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $email
            ));
            
            // Mark email as verified
            update_user_meta($user_id, 'email_verified', '1');
            
            // Redirect to my-account with success message
            wp_redirect(home_url('/my-account/?email_verified=success'));
            exit;
        } else {
            // Invalid or expired token
            wp_redirect(home_url('/my-account/?email_verified=error'));
            exit;
        }
    }
}

// Hook to handle verification URLs
add_action('template_redirect', 'handle_email_verification'); 