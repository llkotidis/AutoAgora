<?php
/**
 * My Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Register the shortcode
add_shortcode('my_listings', 'display_my_listings');

function display_my_listings($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your listings.</p>';
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="my-listings-container">
        <h2>My Car Listings</h2>
        
        <?php
        // Query for user's car listings
        $args = array(
            'post_type' => 'car',
            'author' => $current_user->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $user_listings = new WP_Query($args);
        
        if ($user_listings->have_posts()) :
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user_listings->have_posts()) : $user_listings->the_post(); ?>
                        <tr>
                            <td><?php the_title(); ?></td>
                            <td><?php echo get_the_date(); ?></td>
                            <td><?php echo get_post_status(); ?></td>
                            <td>
                                <a href="<?php the_permalink(); ?>" class="button">View</a>
                                <a href="<?php echo get_edit_post_link(); ?>" class="button">Edit</a>
                                <a href="<?php echo get_delete_post_link(); ?>" class="button" onclick="return confirm('Are you sure you want to delete this listing?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php 
        else :
            echo '<p>You haven\'t created any car listings yet.</p>';
            echo '<p><a href="' . esc_url(home_url('/add-listing/')) . '" class="button">Add New Listing</a></p>';
        endif;
        
        wp_reset_postdata();
        ?>
    </div>
    
    <style>
        .my-listings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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