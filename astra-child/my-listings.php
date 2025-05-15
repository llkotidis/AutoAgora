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
            <div class="listings-grid">
                <?php while ($user_listings->have_posts()) : $user_listings->the_post(); 
                    $post_id = get_the_ID();
                    $price = get_post_meta($post_id, 'price', true);
                    
                    // Get all car images
                    $featured_image = get_post_thumbnail_id($post_id);
                    $additional_images = get_field('car_images', $post_id);
                    $all_images = array();
                    
                    if ($featured_image) {
                        $all_images[] = $featured_image;
                    }
                    
                    if (is_array($additional_images)) {
                        $all_images = array_merge($all_images, $additional_images);
                    }
                ?>
                    <div class="listing-item">
                        <div class="listing-image-container">
                            <?php if (!empty($all_images)) : 
                                $main_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                            ?>
                                <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, home_url('/car-listing-detailed/'))); ?>" class="listing-image-link">
                                    <img src="<?php echo esc_url($main_image_url); ?>" alt="<?php the_title(); ?>" class="listing-image">
                                    <div class="image-count">
                                        <i class="fas fa-camera"></i>
                                        <span><?php echo count($all_images); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="listing-details">
                            <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, home_url('/car-listing-detailed/'))); ?>" class="listing-title">
                                <?php the_title(); ?> (€<?php echo number_format($price); ?>)
                            </a>
                            <div class="listing-meta">
                                <span class="listing-date">Published: <?php echo get_the_date(); ?></span>
                                <span class="listing-status">Status: <?php echo get_post_status(); ?></span>
                            </div>
                            <div class="listing-actions">
                                <a href="<?php echo get_edit_post_link(); ?>" class="button">Edit</a>
                                <a href="<?php echo get_delete_post_link(); ?>" class="button delete-button" onclick="return confirm('Are you sure you want to delete this listing?');">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php 
        else :
            echo '<p>You haven\'t created any car listings yet.</p>';
            echo '<p><a href="' . esc_url(home_url('/add-listing/')) . '" class="button">Add New Listing</a></p>';
        endif;
        
        wp_reset_postdata();
        ?>
    </div>
    
    <?php
    // Return the buffered content
    return ob_get_clean();
}