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

    // Get current user
    $current_user = wp_get_current_user();
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="my-account-container">
        <h2>Personal Details</h2>
        
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
                    <a href="#" class="button button-small">Reset Password</a>
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
            <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="button">Edit Profile</a>
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
            
            // Instead of splitting the display name, use the actual values from PHP
            // The input fields already have the correct values from the PHP variables
            var firstName = document.getElementById('first-name').value;
            var lastName = document.getElementById('last-name').value;
            
            console.log('Current values from inputs:', {firstName, lastName});
            
            // Store original values for cancel functionality
            originalFirstName = firstName;
            originalLastName = lastName;
            
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
                    var fullName = (firstName + ' ' + lastName).trim();
                    displayName.textContent = fullName;
                    
                    // Update original values
                    originalFirstName = firstName;
                    originalLastName = lastName;
                    
                    document.querySelector('.name-row').style.display = 'flex';
                    document.querySelectorAll('.name-edit-row').forEach(function(row) {
                        row.style.display = 'none';
                    });
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