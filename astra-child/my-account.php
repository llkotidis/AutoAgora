<?php
/**
 * My Account Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Register the shortcode
add_shortcode('my_account', 'display_my_account');

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
        <h2>My Account Information</h2>
        
        <div class="account-info-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Posts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html($current_user->user_login); ?></td>
                        <td><?php echo esc_html($current_user->display_name); ?></td>
                        <td><?php echo esc_html($current_user->user_email); ?></td>
                        <td><?php 
                            $user_roles = $current_user->roles;
                            echo esc_html(implode(', ', $user_roles)); 
                        ?></td>
                        <td><?php 
                            $user_posts = count_user_posts($current_user->ID, 'car');
                            echo esc_html($user_posts); 
                        ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="button">Edit Profile</a>
                            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="button">Logout</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <style>
        .my-account-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .account-info-table {
            margin-bottom: 30px;
        }
        
        .wp-list-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .wp-list-table th,
        .wp-list-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .wp-list-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .button {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
        }
        
        .button:hover {
            background-color: #005177;
            color: white;
        }
    </style>
    
    <?php
    // Return the buffered content
    return ob_get_clean();
}