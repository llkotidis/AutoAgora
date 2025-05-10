<?php
/**
 * Favorite Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Register the shortcode
add_shortcode('favourite_listings', 'display_favourite_listings');

function display_favourite_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Enqueue the necessary scripts and localize data
    wp_enqueue_script('car-listings', get_stylesheet_directory_uri() . '/js/car-listings.js', array('jquery'), filemtime(get_stylesheet_directory() . '/js/car-listings.js'), true);
    wp_localize_script('car-listings', 'carListingsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('toggle_favorite_car')
    ));

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Get user's favorite car IDs from user meta
    $user_id = get_current_user_id();
    $favorite_car_ids = get_user_meta($user_id, 'favorite_cars', true);
    
    // If no favorites, show a message
    if (empty($favorite_car_ids)) {
        ?>
        <div class="favorite-listings-container">
            <div class="no-favorites-message">
                <h2>No Favorite Cars Yet</h2>
                <p>You haven't added any cars to your favorites yet.</p>
                <a href="<?php echo esc_url(get_permalink(get_page_by_path('car-listings'))); ?>" class="browse-cars-button">Browse Cars</a>
            </div>
        </div>
        <style>
            .favorite-listings-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 20px;
                text-align: center;
            }
            .no-favorites-message {
                background: #f9f9f9;
                border-radius: 8px;
                padding: 40px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .no-favorites-message h2 {
                margin-bottom: 15px;
                color: #333;
            }
            .no-favorites-message p {
                margin-bottom: 25px;
                color: #666;
            }
            .browse-cars-button {
                display: inline-block;
                background: #007bff;
                color: white;
                padding: 12px 24px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
                transition: background 0.3s ease;
            }
            .browse-cars-button:hover {
                background: #0056b3;
                color: white;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    // Query arguments for favorite car listings
    $args = array(
        'post_type' => 'car',
        'posts_per_page' => $atts['per_page'],
        'paged' => $paged,
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
        'post_status' => 'publish',
        'post__in' => $favorite_car_ids, // Only get favorite cars
        'no_found_rows' => false, // We need pagination
    );

    // Get car listings
    $car_query = new WP_Query($args);

    // Start the output
    ?>
    <div class="favorite-listings-container">
        <h1 class="favorite-listings-title">My Favorite Cars</h1>
        <?php if ($car_query->found_posts > 0): ?>
        <div class="results-counter">Showing <span class="count"><?php echo esc_html($car_query->found_posts); ?></span> results</div>
        <?php endif; ?>
        
        <!-- Listings Grid -->
        <div class="car-listings-grid">
            <?php
            if ($car_query->have_posts()) :
                while ($car_query->have_posts()) : $car_query->the_post();
                    $make = get_post_meta(get_the_ID(), 'make', true);
                    $model = get_post_meta(get_the_ID(), 'model', true);
                    $variant = get_post_meta(get_the_ID(), 'variant', true);
                    $price = get_post_meta(get_the_ID(), 'price', true);
                    $year = get_post_meta(get_the_ID(), 'year', true);
                    $engine_capacity = get_post_meta(get_the_ID(), 'engine_capacity', true);
                    $transmission = get_post_meta(get_the_ID(), 'transmission', true);
                    $mileage = get_post_meta(get_the_ID(), 'mileage', true);
                    $location = get_post_meta(get_the_ID(), 'location', true);
                    ?>
                    <div class="car-listing-card">
                        <?php 
                        // Get all car images
                        $featured_image = get_post_thumbnail_id(get_the_ID());
                        $additional_images = get_field('car_images', get_the_ID());
                        $all_images = array();
                        
                        if ($featured_image) {
                            $all_images[] = $featured_image;
                        }
                        
                        if (is_array($additional_images)) {
                            $all_images = array_merge($all_images, $additional_images);
                        }
                        
                        if (!empty($all_images)) {
                            echo '<div class="car-listing-image-container">';
                            echo '<div class="car-listing-image-carousel" data-post-id="' . get_the_ID() . '">';
                            
                            foreach ($all_images as $index => $image_id) {
                                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                                if ($image_url) {
                                    echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . $index . '">';
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($year . ' ' . $make . ' ' . $model) . '">';
                                    if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                        echo '<a href="' . get_permalink() . '" class="see-all-images" style="display: none;">See All Images</a>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                            echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                            echo '<button class="favorite-btn active" data-car-id="' . get_the_ID() . '"><i class="fas fa-heart"></i></button>';
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                        
                        <a href="<?php echo esc_url(add_query_arg('car_id', get_the_ID(), get_permalink(get_page_by_path('car-listing-detailed')))); ?>" class="car-listing-link">
                            <div class="car-listing-details">
                                <h2 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h2>
                                <div class="car-specs">
                                    <?php echo esc_html($engine_capacity); ?>L
                                    <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                                    <?php 
                                        $body_type = get_post_meta(get_the_ID(), 'body_type', true);
                                        echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                                    ?>
                                    <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                                    <?php 
                                        $drive_type = get_post_meta(get_the_ID(), 'drive_type', true);
                                        echo !empty($drive_type) ? ' ' . esc_html($drive_type) : '';
                                    ?>
                                </div>
                                <div class="car-info-boxes">
                                    <div class="info-box">
                                        <span class="info-value"><?php echo number_format($mileage); ?> km</span>
                                    </div>
                                    <div class="info-box">
                                        <span class="info-value"><?php echo esc_html($year); ?></span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format($price); ?></div>
                                <?php 
                                $publication_date = get_post_meta(get_the_ID(), 'publication_date', true);
                                if (!$publication_date) {
                                    $publication_date = get_the_date('Y-m-d H:i:s');
                                    update_post_meta(get_the_ID(), 'publication_date', $publication_date);
                                }
                                $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                                echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                                ?>
                                <div class="car-location"><?php echo esc_html($location); ?></div>
                            </div>
                        </a>
                    </div>
                <?php
                endwhile;
            else :
                echo '<p class="no-listings">No favorite car listings found.</p>';
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <!-- Pagination -->
        <div class="car-listings-pagination">
            <?php
            echo paginate_links(array(
                'total' => $car_query->max_num_pages,
                'current' => $paged,
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;'
            ));
            ?>
        </div>
    </div>

    <style>
        .favorite-listings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .favorite-listings-title {
            margin-bottom: 30px;
            font-size: 28px;
            color: #333;
            text-align: center;
        }

        .car-listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .car-listing-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
            position: relative;
        }

        .car-listing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .car-listing-link {
            text-decoration: none !important;
            color: inherit;
            display: block;
        }

        .car-listing-link:hover {
            text-decoration: none !important;
        }

        .car-title {
            margin: 0 0 8px 0;
            font-size: 1.50em !important;
            font-weight: bold;
            color: #000;
        }

        .car-specs {
            font-size: 0.95em !important;
            color: #000;
            margin-bottom: 12px;
            line-height: 1.4;
            text-decoration: none !important;
        }

        .car-info-boxes {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .info-box {
            flex: 1;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px 10px;
            text-align: center;
        }

        .info-value {
            display: block;
            font-size: 0.95em !important;
            color: #000;
            font-weight: 500;
            text-decoration: none !important;
        }

        .car-price {
            font-size: 1.3em !important;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 8px;
            text-decoration: none !important;
        }

        .car-publication-date {
            color: #666;
            margin-bottom: 0;
            font-size: 0.95em !important;
            text-decoration: none !important;
        }

        .car-location {
            color: #000;
            margin-bottom: 0;
            font-size: 1.1em !important;
            text-decoration: none !important;
        }

        .carousel-nav, .see-all-images {
            z-index: 10;
            position: absolute;
        }

        .car-listing-image-carousel {
            position: relative;
        }

        .car-listing-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .car-listing-image-carousel {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .car-listing-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .car-listing-image.active {
            opacity: 1;
        }

        .car-listing-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 10px 15px;
            cursor: pointer;
            z-index: 2;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-nav:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        .carousel-nav.prev {
            left: 10px;
        }

        .carousel-nav.next {
            right: 10px;
        }

        .see-all-images {
            text-decoration: none !important;
            position: absolute;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 2;
            transition: background 0.3s ease;
            text-align: center;
        }

        .see-all-images:hover {
            background: rgba(0, 0, 0, 0.9);
            text-decoration: none !important;
        }

        .car-listing-details {
            padding: 15px;
            text-decoration: none !important;
        }

        .car-listings-pagination {
            text-align: center;
            margin-top: 30px;
        }

        .car-listings-pagination .page-numbers {
            padding: 8px 15px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
        }

        .car-listings-pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.2s ease;
        }

        .favorite-btn i {
            font-size: 24px;
            color: #ff0000;
            transition: all 0.2s ease;
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .favorite-btn:hover i {
            transform: scale(1.1);
        }

        .favorite-btn.active i {
            opacity: 1;
        }

        /* Results Counter Styles */
        .results-counter {
            text-align: left;
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #333;
            font-weight: 500;
            padding-left: 10px;
        }

        .results-counter .count {
            font-weight: bold;
            color: #007bff;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Carousel functionality
        document.querySelectorAll('.car-listing-image-carousel').forEach(carousel => {
            const images = carousel.querySelectorAll('.car-listing-image');
            const prevBtn = carousel.querySelector('.carousel-nav.prev');
            const nextBtn = carousel.querySelector('.carousel-nav.next');
            const seeAllImagesBtn = carousel.querySelector('.see-all-images');
            let currentIndex = 0;

            // Function to update image visibility
            const updateImages = () => {
                images.forEach((img, index) => {
                    img.classList.toggle('active', index === currentIndex);
                });

                // Update navigation buttons
                prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
                nextBtn.style.display = currentIndex === images.length - 1 ? 'none' : 'flex';

                // Update "See All Images" button visibility
                if (seeAllImagesBtn) {
                    seeAllImagesBtn.style.display = currentIndex === images.length - 1 ? 'block' : 'none';
                }
            };

            // Initialize
            updateImages();

            // Event listeners for navigation
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateImages();
                }
            });

            nextBtn.addEventListener('click', () => {
                if (currentIndex < images.length - 1) {
                    currentIndex++;
                    updateImages();
                }
            });
        });

        // --- Favorite button functionality ---
        const favoriteBtns = document.querySelectorAll('.favorite-btn');
        if (favoriteBtns.length > 0) {
            favoriteBtns.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if user is logged in (nonce should be available)
                    if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                        alert('Please log in to add favorites.');
                        return;
                    }

                    const carId = this.getAttribute('data-car-id');
                    const isActive = this.classList.contains('active');
                    const heartIcon = this.querySelector('i');
                    const card = this.closest('.car-listing-card');

                    // Optimistic UI update
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    } else {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    }

                    // Prepare AJAX data
                    const formData = new FormData();
                    formData.append('action', 'toggle_favorite_car');
                    formData.append('car_id', carId);
                    formData.append('is_favorite', !isActive ? '1' : '0');
                    formData.append('nonce', carListingsData.nonce);

                    // Send AJAX request
                    fetch(carListingsData.ajaxurl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            // Revert UI on failure
                            this.classList.toggle('active');
                            if (isActive) {
                                heartIcon.classList.remove('far');
                                heartIcon.classList.add('fas');
                            } else {
                                heartIcon.classList.remove('fas');
                                heartIcon.classList.add('far');
                            }
                            console.error('Favorite toggle failed:', data);
                            alert('Failed to update favorites. Please try again.');
                        } else {
                            // If we're on the favorites page and removing a favorite, remove the card
                            if (isActive) {
                                // Add fade-out animation
                                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'translateY(-20px)';
                                
                                // Remove the card after animation
                                setTimeout(() => {
                                    card.remove();
                                    
                                    // Check if there are any cards left
                                    const remainingCards = document.querySelectorAll('.car-listing-card');
                                    if (remainingCards.length === 0) {
                                        // Reload the page to show the "no favorites" message
                                        window.location.reload();
                                    }
                                }, 300);
                            }
                        }
                    })
                    .catch(error => {
                        // Revert UI on network/fetch error
                        this.classList.toggle('active');
                        if (isActive) {
                            heartIcon.classList.remove('far');
                            heartIcon.classList.add('fas');
                        } else {
                            heartIcon.classList.remove('fas');
                            heartIcon.classList.add('far');
                        }
                        console.error('Error:', error);
                        alert('Failed to update favorites. An error occurred.');
                    });
                });
            });
        }
    });
    </script>
    <?php

    // Return the buffered content
    return ob_get_clean();
}

// AJAX handler for toggling favorite cars
function toggle_favorite_car() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'toggle_favorite_car')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    // Get car ID and favorite status
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $is_favorite = isset($_POST['is_favorite']) ? (bool)$_POST['is_favorite'] : false;
    
    if ($car_id <= 0) {
        wp_send_json_error('Invalid car ID');
        return;
    }
    
    // Get current user's favorite cars
    $user_id = get_current_user_id();
    $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
    
    // Initialize as empty array if not set
    if (!is_array($favorite_cars)) {
        $favorite_cars = array();
    }
    
    // Add or remove car from favorites
    if ($is_favorite) {
        // Add to favorites if not already there
        if (!in_array($car_id, $favorite_cars)) {
            $favorite_cars[] = $car_id;
        }
    } else {
        // Remove from favorites
        $favorite_cars = array_diff($favorite_cars, array($car_id));
    }
    
    // Update user meta
    update_user_meta($user_id, 'favorite_cars', $favorite_cars);
    
    wp_send_json_success(array(
        'favorite_cars' => $favorite_cars,
        'is_favorite' => $is_favorite
    ));
}
add_action('wp_ajax_toggle_favorite_car', 'toggle_favorite_car');