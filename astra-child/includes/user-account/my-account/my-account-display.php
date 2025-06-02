<?php
/**
 * My Account Display HTML/PHP
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the main my account page
 */
function display_my_account_main($current_user) {
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Personal Details</h2>
        
        <?php if (isset($_GET['name_updated']) && $_GET['name_updated'] == '1'): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                Name successfully updated
            </div>
        <?php endif; ?>
        
        <div class="account-sections">
            <div class="account-section">
                <h3>Sign In Details</h3>
                <div class="info-row">
                    <span class="label">Phone Number:</span>
                    <span class="value"><?php echo esc_html($current_user->user_login); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Password:</span>
                    <span class="value">******</span>
                    <button class="button button-small reset-password-btn">Reset Password</button>
                </div>
            </div>

            <div class="account-section">
                <h3>Personal Details</h3>
                <div class="info-row name-row">
                    <span class="label">Name:</span>
                    <span class="value" id="display-name"><?php echo esc_html(trim($current_user->first_name . ' ' . $current_user->last_name)); ?></span>
                    <button class="button button-small edit-name-btn">Edit</button>
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label">First Name:</span>
                    <input type="text" id="first-name" value="<?php echo esc_attr($current_user->first_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label">Last Name:</span>
                    <input type="text" id="last-name" value="<?php echo esc_attr($current_user->last_name); ?>" class="name-input">
                </div>
                <div class="info-row name-edit-row" style="display: none;">
                    <span class="label"></span>
                    <button class="button button-small save-name-btn">Save Changes</button>
                    <button class="button button-small cancel-name-btn">Cancel</button>
                </div>
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo esc_html($current_user->user_email); ?></span>
                    <?php
                    // Check email verification status - now properly initialized to '0' or '1'
                    $email_verified = get_user_meta($current_user->ID, 'email_verified', true);
                    if ($email_verified === '1') {
                        echo '<span class="email-status verified">✅ Verified</span>';
                    } else {
                        echo '<span class="email-status not-verified">❌ Not Verified</span>';
                    }
                    ?>
                </div>
                <div class="info-row">
                    <span class="label">Role:</span>
                    <span class="value"><?php 
                        $user_roles = $current_user->roles;
                        echo esc_html(implode(', ', $user_roles)); 
                    ?></span>
                </div>
            </div>
        </div>

        <div class="account-actions">
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="button">Logout</a>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
} 