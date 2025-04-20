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
    
    <?php
    // Return the buffered content
    return ob_get_clean();
}