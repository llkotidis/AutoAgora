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

    // Localize script for AJAX functionality
    wp_localize_script('jquery', 'myListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mark_car_as_sold')
    ));
    
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
                                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="listing-image-link">
                                    <img src="<?php echo esc_url($main_image_url); ?>" alt="<?php the_title(); ?>" class="listing-image">
                                    <div class="image-count">
                                        <i class="fas fa-camera"></i>
                                        <span><?php echo count($all_images); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="listing-details">
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="listing-title">
                                <?php the_title(); ?> (€<?php echo number_format($price); ?>)
                            </a>
                            <div class="listing-meta">
                                <span class="listing-date">Published: <?php echo get_the_date(); ?></span>
                                <span class="listing-status">Status: <?php echo get_post_status(); ?></span>
                            </div>
                            <div class="listing-actions">
                                <a href="<?php echo get_edit_post_link(); ?>" class="button">Edit</a>
                                <?php if (get_field('is_sold', $post_id) == 1) : ?>
                                    <button class="button mark-available-button" data-car-id="<?php echo $post_id; ?>">Mark as Available</button>
                                <?php else : ?>
                                    <button class="button mark-sold-button" data-car-id="<?php echo $post_id; ?>">Mark as Sold</button>
                                <?php endif; ?>
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
    
    <style>
        .my-listings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .listings-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .listing-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .listing-image-container {
            position: relative;
            width: 300px;
            height: 200px;
            flex-shrink: 0;
        }
        
        .listing-image-link {
            display: block;
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        .listing-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .image-count {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .listing-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .listing-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }
        
        .listing-title:hover {
            color: #007bff;
        }
        
        .listing-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            color: #666;
            font-size: 0.9em;
        }
        
        .listing-actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
        }
        
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        
        .button:hover {
            background-color: #005177;
            color: white;
        }
        
        .delete-button {
            background-color: #dc3545;
        }
        
        .delete-button:hover {
            background-color: #c82333;
        }

        .mark-sold-button {
            background-color: #28a745;
        }

        .mark-sold-button:hover {
            background-color: #218838;
        }

        .mark-available-button {
            background-color: #17a2b8;
        }

        .mark-available-button:hover {
            background-color: #138496;
        }

        .sold-badge {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .listing-item {
                flex-direction: column;
            }
            
            .listing-image-container {
                width: 100%;
                height: 200px;
            }
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Function to update car status
        function updateCarStatus(carId, newStatus, button) {
            $.ajax({
                url: myListingsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mark_car_as_sold',
                    car_id: carId,
                    status: newStatus,
                    nonce: myListingsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const listingItem = button.closest('.listing-item');
                        const statusSpan = listingItem.find('.listing-status');
                        
                        if (newStatus === 'sold') {
                            button.remove();
                            listingItem.find('.listing-actions').prepend(
                                '<button class="button mark-available-button" data-car-id="' + carId + '">Mark as Available</button>'
                            );
                            statusSpan.html('Status: <span class="sold-badge">SOLD</span>');
                        } else {
                            button.remove();
                            listingItem.find('.listing-actions').prepend(
                                '<button class="button mark-sold-button" data-car-id="' + carId + '">Mark as Sold</button>'
                            );
                            statusSpan.html('Status: Available');
                        }
                    } else {
                        alert('Error updating car status. Please try again.');
                    }
                },
                error: function() {
                    alert('Error updating car status. Please try again.');
                }
            });
        }

        // Handle Mark as Sold button click
        $(document).on('click', '.mark-sold-button', function() {
            const button = $(this);
            const carId = button.data('car-id');
            
            if (confirm('Are you sure you want to mark this car as sold?')) {
                updateCarStatus(carId, 'sold', button);
            }
        });

        // Handle Mark as Available button click
        $(document).on('click', '.mark-available-button', function() {
            const button = $(this);
            const carId = button.data('car-id');
            
            if (confirm('Are you sure you want to mark this car as available?')) {
                updateCarStatus(carId, 'available', button);
            }
        });
    });
    </script>
    
    <?php
    // Return the buffered content
    return ob_get_clean();
}