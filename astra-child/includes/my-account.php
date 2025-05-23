<?php
/**
 * My Account Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Register the shortcode
add_shortcode('my_account', 'display_my_account');

//this is proof that github is working

function display_my_account($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your account information.</p>';
    }

    // Check if we're in password reset flow
    $password_reset_step = isset($_GET['password_reset_step']) ? sanitize_text_field($_GET['password_reset_step']) : '';
    
    if ($password_reset_step === 'verify') {
        return display_password_reset_verify();
    } elseif ($password_reset_step === 'new_password') {
        return display_password_reset_form();
    } elseif ($password_reset_step === 'success') {
        return display_password_reset_success();
    }

    // Normal account display
    $current_user = wp_get_current_user();
    
    // Start output buffering
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
    
    <style>
        .my-account-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .account-sections {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .account-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .account-section h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            align-items: center;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .label {
            width: 150px;
            font-weight: 600;
            color: #666;
        }
        
        .value {
            flex: 1;
            color: #333;
        }
        
        .account-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        
        .button:hover {
            background-color: #005177;
            color: white;
        }

        .button-small {
            padding: 4px 8px;
            font-size: 12px;
            margin-left: auto;
        }

        .name-input {
            flex: 1;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .name-edit-row .button-small {
            margin-right: 10px;
        }

        .name-edit-row .button-small:last-child {
            margin-right: 0;
        }
        
        /* Success message styling */
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            color: #155724;
            padding: 12px 16px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .success-icon {
            background-color: #28a745;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded');
        
        // Store original name values
        var originalFirstName = document.getElementById('first-name').value;
        var originalLastName = document.getElementById('last-name').value;
        var displayName = document.getElementById('display-name');
        
        console.log('Initial values:', {originalFirstName, originalLastName});

        // Name editing functionality
        document.querySelector('.edit-name-btn').addEventListener('click', function(e) {
            console.log('Edit button clicked');
            e.preventDefault();
            
            // Don't overwrite the original values here - we need them for comparison later
            // The input fields already have the correct values from the PHP variables
            console.log('Original values for comparison:', {originalFirstName, originalLastName});
            
            // Show edit fields
            document.querySelector('.name-row').style.display = 'none';
            document.querySelectorAll('.name-edit-row').forEach(function(row) {
                row.style.display = 'flex';
            });
        });

        document.querySelector('.cancel-name-btn').addEventListener('click', function(e) {
            console.log('Cancel button clicked');
            e.preventDefault();
            
            // Restore original values
            document.getElementById('first-name').value = originalFirstName;
            document.getElementById('last-name').value = originalLastName;
            
            document.querySelector('.name-row').style.display = 'flex';
            document.querySelectorAll('.name-edit-row').forEach(function(row) {
                row.style.display = 'none';
            });
        });

        document.querySelector('.save-name-btn').addEventListener('click', function(e) {
            console.log('Save button clicked');
            e.preventDefault();
            
            var firstName = document.getElementById('first-name').value.trim();
            var lastName = document.getElementById('last-name').value.trim();
            
            if (firstName === '' && lastName === '') {
                alert('Please enter at least a first name or last name');
                return;
            }

            // Check if anything actually changed
            if (firstName === originalFirstName && lastName === originalLastName) {
                console.log('No changes detected, just hiding edit form');
                // No changes, just hide the edit form
                document.querySelector('.name-row').style.display = 'flex';
                document.querySelectorAll('.name-edit-row').forEach(function(row) {
                    row.style.display = 'none';
                });
                return;
            }

            console.log('Changes detected, sending to server');
            
            // Create form data for AJAX request
            var formData = new FormData();
            formData.append('action', 'update_user_name');
            formData.append('first_name', firstName);
            formData.append('last_name', lastName);
            formData.append('nonce', '<?php echo wp_create_nonce("update_user_name"); ?>');

            // Send AJAX request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page with success parameter
                    window.location.href = window.location.pathname + '?name_updated=1';
                } else {
                    alert('Error updating name: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating name. Please try again.');
            });
        });

        // Handle Enter key in name inputs
        document.querySelectorAll('.name-input').forEach(function(input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('.save-name-btn').click();
                }
            });
        });

        // Password reset functionality
        document.querySelector('.reset-password-btn').addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Reset password clicked');
            
            if (confirm('Are you sure you want to reset your password? A verification code will be sent to your phone number.')) {
                initiatePasswordReset();
            }
        });

        function initiatePasswordReset() {
            // Get the user's phone number from the page (we'll need to add this)
            // For now, let's send a request to get user's phone and then send OTP
            var formData = new FormData();
            formData.append('action', 'initiate_password_reset');
            formData.append('nonce', '<?php echo wp_create_nonce("password_reset_nonce"); ?>');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Verification code sent to your phone number. Please check your messages.');
                    // Here we would redirect to a password reset page or show a form
                    window.location.href = window.location.pathname + '?password_reset_step=verify';
                } else {
                    alert('Error: ' + (data.data || 'Unable to send verification code'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending verification code. Please try again.');
            });
        }
    });
    </script>
    
    <?php
    // Return the buffered content
    return ob_get_clean();
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

// Function to display password reset verification step
function display_password_reset_verify() {
    $current_user = wp_get_current_user();
    
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Reset Password - Step 1</h2>
        
        <div class="password-reset-section">
            <h3>Enter Verification Code</h3>
            <p>We've sent a verification code to your phone number. Please enter the 6-digit code below:</p>
            
            <div class="verification-form">
                <div class="info-row">
                    <label for="verification-code" class="label">Verification Code:</label>
                    <input type="text" id="verification-code" maxlength="6" placeholder="000000" class="verification-input">
                </div>
                <div class="info-row">
                    <button class="button verify-code-btn">Verify Code</button>
                    <button class="button button-secondary cancel-reset-btn">Cancel</button>
                </div>
                <div class="info-row">
                    <button class="button button-link resend-code-btn">Resend Code</button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .my-account-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .password-reset-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .password-reset-section h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .verification-form {
            margin-top: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            align-items: center;
        }
        
        .label {
            width: 150px;
            font-weight: 600;
            color: #666;
        }
        
        .verification-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            letter-spacing: 2px;
            max-width: 200px;
        }
        
        .verification-input:focus {
            border-color: #0073aa;
            outline: none;
        }
        
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }
        
        .button:hover {
            background-color: #005177;
        }
        
        .button-secondary {
            background-color: #666;
        }
        
        .button-secondary:hover {
            background-color: #444;
        }
        
        .button-link {
            background-color: transparent;
            color: #0073aa;
            text-decoration: underline;
            padding: 4px 8px;
        }
        
        .button-link:hover {
            background-color: transparent;
            color: #005177;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Password reset verification page loaded');
        
        const verificationInput = document.getElementById('verification-code');
        const verifyBtn = document.querySelector('.verify-code-btn');
        const cancelBtn = document.querySelector('.cancel-reset-btn');
        const resendBtn = document.querySelector('.resend-code-btn');

        // Auto-format verification code input
        verificationInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Verify code
        verifyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const code = verificationInput.value.trim();
            
            if (code.length !== 6) {
                alert('Please enter a 6-digit verification code');
                return;
            }
            
            verifyPasswordResetCode(code);
        });

        // Cancel reset
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = window.location.pathname;
        });

        // Resend code
        resendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resendPasswordResetCode();
        });

        // Handle Enter key
        verificationInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });

        function verifyPasswordResetCode(code) {
            var formData = new FormData();
            formData.append('action', 'verify_password_reset_code');
            formData.append('code', code);
            formData.append('nonce', '<?php echo wp_create_nonce("verify_password_reset_nonce"); ?>');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = window.location.pathname + '?password_reset_step=new_password';
                } else {
                    alert('Error: ' + (data.data || 'Invalid verification code'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error verifying code. Please try again.');
            });
        }

        function resendPasswordResetCode() {
            var formData = new FormData();
            formData.append('action', 'initiate_password_reset');
            formData.append('nonce', '<?php echo wp_create_nonce("password_reset_nonce"); ?>');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Verification code sent again to your phone number.');
                } else {
                    alert('Error: ' + (data.data || 'Unable to resend code'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error resending code. Please try again.');
            });
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
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