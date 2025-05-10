<?php
/**
 * The template for displaying single Car posts
 *
 * @package Astra Child
 * @since 1.0.0
 */

get_header(); // Ensure Astra's header is loaded

if (have_posts()) :
    while (have_posts()) : the_post();
        $car_id = get_the_ID(); // Get the ID of the current car post

        // Ensure this is actually a 'car' post type, as a safeguard.
        if (get_post_type($car_id) === 'car') :

            // Get all car details (copied from car-listing-detailed.php and adapted)
            $make = get_post_meta($car_id, 'make', true);
            $model = get_post_meta($car_id, 'model', true);
            $variant = get_post_meta($car_id, 'variant', true);
            $year = get_post_meta($car_id, 'year', true);
            $price = get_post_meta($car_id, 'price', true);
            $mileage = get_post_meta($car_id, 'mileage', true);
            $location = get_post_meta($car_id, 'location', true);
            $engine_capacity = get_post_meta($car_id, 'engine_capacity', true);
            $fuel_type = get_post_meta($car_id, 'fuel_type', true);
            $transmission = get_post_meta($car_id, 'transmission', true);
            $exterior_color = get_post_meta($car_id, 'exterior_color', true);
            $interior_color = get_post_meta($car_id, 'interior_color', true);
            $description = get_post_meta($car_id, 'description', true);

            // Get all car images (copied from car-listing-detailed.php)
            $featured_image = get_post_thumbnail_id($car_id);
            $additional_images = get_field('car_images', $car_id); // ACF field
            $all_images = array();
            
            if ($featured_image) {
                $all_images[] = $featured_image;
            }
            
            if (is_array($additional_images)) {
                $all_images = array_merge($all_images, $additional_images);
            }
            ?>

            <div class="car-listing-detailed-container">
                <!-- Add Gallery Popup HTML -->
                <div class="gallery-popup" style="display: none;">
                    <div class="gallery-popup-content">
                        <button class="back-to-advert-btn">
                            <i class="fas fa-arrow-left"></i> Back to advert
                        </button>
                        <div class="gallery-main-image">
                            <?php 
                            // Set the first image as the initial main image
                            $first_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                            ?>
                            <img src="<?php echo esc_url($first_image_url); ?>" alt="Gallery Image">
                        </div>
                        <div class="gallery-thumbnails">
                            <?php foreach ($all_images as $index => $image_id) : 
                                $thumb_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                $full_url = wp_get_attachment_image_url($image_id, 'large');
                                if ($thumb_url) :
                            ?>
                                <div class="gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-full-url="<?php echo esc_url($full_url); ?>">
                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="Gallery Thumbnail <?php echo $index + 1; ?>">
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="car-listing-header">
                    <a href="javascript:history.back()" class="back-to-results-btn">
                        ← Back to Results
                    </a>
                    <?php
                    // Favorite button logic (copied from car-listing-detailed.php)
                    $user_id = get_current_user_id();
                    $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
                    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                    $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
                    $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart'; 
                    ?>
                    <div class="action-buttons">
                        <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>">
                            <i class="<?php echo esc_attr($heart_class); ?>"></i>
                        </button>
                        <button class="share-btn">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="car-listing-content">
                    <div class="car-listing-top">
                        <!-- Image Gallery -->
                        <?php if (!empty($all_images)) : ?>
                            <div class="car-listing-gallery">
                                <div class="main-image">
                                    <div class="image-count-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span><?php echo count($all_images); ?> photos</span>
                                    </div>
                                    <button class="view-gallery-btn">
                                        <i class="fas fa-images"></i>
                                        View Gallery
                                    </button>
                                    <?php
                                    $main_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                                    if ($main_image_url) :
                                    ?>
                                        <img src="<?php echo esc_url($main_image_url); ?>" 
                                             alt="<?php echo esc_attr($year . ' ' . $make . ' ' . $model); ?>" 
                                             class="clickable-image"
                                             data-image-index="0">
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (count($all_images) > 1) : ?>
                                    <div class="thumbnail-gallery">
                                        <?php 
                                        // Show up to 3 thumbnails (excluding the main image)
                                        $max_thumbnails = 3;
                                        $num_thumbnails = min(count($all_images) - 1, $max_thumbnails);
                                        for ($i = 1; $i <= $num_thumbnails; $i++) : 
                                            $thumb_url = wp_get_attachment_image_url($all_images[$i], 'medium');
                                            if ($thumb_url) :
                                        ?>
                                            <div class="thumbnail">
                                                <img src="<?php echo esc_url($thumb_url); ?>" 
                                                     alt="Thumbnail <?php echo $i + 1; ?>"
                                                     class="clickable-image"
                                                     data-image-index="<?php echo $i; ?>">
                                            </div>
                                        <?php 
                                            endif;
                                        endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Car Details Right Side -->
                        <div class="car-listing-details-right">
                            <h1 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h1>

                            <div class="car-specs">
                                <?php echo esc_html($engine_capacity); ?>L
                                <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                                <?php 
                                    $body_type = get_post_meta($car_id, 'body_type', true);
                                    echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                                ?>
                                <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                                <?php 
                                    $drive_type = get_post_meta($car_id, 'drive_type', true);
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
                                <div class="info-box">
                                    <span class="info-value"><?php echo esc_html($transmission); ?></span>
                                </div>
                                <div class="info-box">
                                    <span class="info-value"><?php echo esc_html($fuel_type); ?></span>
                                </div>
                            </div>

                            <div class="car-price">€<?php echo number_format($price); ?></div>
                            <?php 
                            $publication_date = get_post_meta($car_id, 'publication_date', true);
                            if (!$publication_date) {
                                $publication_date = get_the_date('Y-m-d H:i:s');
                                update_post_meta($car_id, 'publication_date', $publication_date);
                            }
                            $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                            echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                            ?>
                            <div class="car-location"><?php echo esc_html($location); ?></div>
                        </div>
                    </div>

                    <!-- Vehicle Information -->
                    <div class="car-listing-details">
                        <div class="details-section">
                            <h2>Vehicle Information</h2>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Make:</span>
                                    <span class="detail-value"><?php echo esc_html($make); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Model:</span>
                                    <span class="detail-value"><?php echo esc_html($model); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Variant:</span>
                                    <span class="detail-value"><?php echo esc_html($variant); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Year:</span>
                                    <span class="detail-value"><?php echo esc_html($year); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Mileage:</span>
                                    <span class="detail-value"><?php echo number_format($mileage); ?> km</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value"><?php echo esc_html($location); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Engine Capacity:</span>
                                    <span class="detail-value"><?php echo esc_html($engine_capacity); ?>L</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Fuel Type:</span>
                                    <span class="detail-value"><?php echo esc_html($fuel_type); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Transmission:</span>
                                    <span class="detail-value"><?php echo esc_html($transmission); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Exterior Color:</span>
                                    <span class="detail-value"><?php echo esc_html($exterior_color); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Interior Color:</span>
                                    <span class="detail-value"><?php echo esc_html($interior_color); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Section -->
                    <div class="car-listing-details">
                        <div class="details-section">
                            <h2>Description</h2>
                            <div class="description-content">
                                <?php 
                                // Description truncation logic (copied from car-listing-detailed.php)
                                $description_length = strlen($description);
                                $max_length = 360;
                                $truncated_description = $description;
                                $show_read_more = false;

                                if ($description_length > $max_length) {
                                    $truncated_description = substr($description, 0, $max_length);
                                    $last_space = strrpos($truncated_description, ' ');
                                    if ($last_space !== false) {
                                        $truncated_description = substr($truncated_description, 0, $last_space);
                                    }
                                    $truncated_description .= '...';
                                    $show_read_more = true;
                                }
                                ?>
                                <p class="description-text"><?php echo esc_html($truncated_description); ?></p>
                                <?php if ($show_read_more): ?>
                                    <p class="full-description" style="display: none;"><?php echo esc_html($description); ?></p>
                                    <button class="read-more-btn">Read more</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
            /* Styles copied directly from car-listing-detailed.php */
            .car-listing-detailed-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }

            .car-listing-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .car-listing-header h1 {
                margin: 0 0 10px 0;
                font-size: 2em;
            }

            .car-listing-price {
                font-size: 1.8em;
                font-weight: bold;
                color: #007bff;
            }

            .car-listing-content {
                display: flex;
                flex-direction: column;
                gap: 30px;
            }

            .car-listing-top {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }

            .car-listing-gallery {
                width: 100%;
            }

            .main-image {
                margin-bottom: 15px;
                position: relative;
                max-width: 100%;
                max-height: 600px;
                overflow: hidden;
                display: block;
            }

            .image-count-overlay {
                position: absolute;
                top: 15px;
                left: 15px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 2;
            }

            .image-count-overlay i {
                font-size: 16px;
            }

            .image-count-overlay span {
                font-size: 14px;
                font-weight: 500;
            }

            .main-image img {
                width: 100%;
                height: auto;
                max-height: 600px;
                object-fit: cover;
                border-radius: 8px;
                display: block;
            }

            .thumbnail-gallery {
                display: flex;
                gap: 10px;
                overflow-x: auto;
                padding-bottom: 10px;
                width: 100%;
                justify-content: flex-start;
            }

            .clickable-image {
                cursor: pointer;
                transition: opacity 0.2s ease;
                outline: none;
                -webkit-tap-highlight-color: transparent;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }

            .clickable-image:hover {
                opacity: 0.9;
            }

            .clickable-image:focus {
                outline: none;
            }

            .clickable-image:active {
                outline: none;
                -webkit-tap-highlight-color: transparent;
            }

            .thumbnail {
                width: 180px;
                height: 135px;
                border: 2px solid transparent;
                border-radius: 4px;
                overflow: hidden;
                flex-shrink: 0;
                cursor: pointer;
                outline: none;
                -webkit-tap-highlight-color: transparent;
            }

            .thumbnail:focus {
                outline: none;
            }

            .thumbnail:active {
                outline: none;
                -webkit-tap-highlight-color: transparent;
            }

            .thumbnail.active {
                border-color: #007bff;
            }

            .thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .car-listing-details-right {
                padding: 20px;
                border-radius: 8px;
            }

            .car-title {
                margin: 0 0 15px 0;
                font-size: 2em;
                color: #333;
            }

            .description-content {
                margin-bottom: 20px;
                line-height: 1.6;
                color: #333;
            }

            .description-text {
                margin-bottom: 10px;
            }

            .car-specs {
                font-size: 1.1em;
                color: #666;
                margin-bottom: 15px;
            }

            .car-info-boxes {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
            }

            .info-box {
                flex: 1;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px 12px;
                text-align: center;
            }

            .info-value {
                display: block;
                font-size: 0.95em;
                color: #333;
                font-weight: 500;
            }

            .car-price {
                font-size: 1.8em;
                font-weight: bold;
                color: #007bff;
                margin-bottom: 10px;
            }

            .car-location {
                color: #666;
                font-size: 1.1em;
            }

            .car-listing-details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }

            .details-section {
                margin-bottom: 30px;
            }

            .details-section h2 {
                margin: 0 0 20px 0;
                font-size: 1.5em;
                color: #333;
            }

            .details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }

            .detail-item {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .detail-label {
                font-weight: bold;
                color: #666;
            }

            .detail-value {
                color: #333;
            }

            @media (max-width: 768px) {
                .car-listing-top {
                    grid-template-columns: 1fr;
                }
            }

            .view-gallery-btn {
                position: absolute;
                bottom: 15px;
                right: 15px;
                background: white;
                color: #000;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: background-color 0.2s ease;
                z-index: 2;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .view-gallery-btn:hover {
                background: #e0e0e0;
            }

            .view-gallery-btn i,
            .view-gallery-btn span {
                color: #000;
            }

            .back-to-results-btn {
                display: inline-block;
                color: #007bff;
                text-decoration: none;
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: 500;
            }

            .back-to-results-btn:hover {
                text-decoration: underline;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
            }

            /* Override favorite button position for detailed page */
            .favorite-btn {
                position: static; /* Reset position */
            }

            .favorite-btn, .share-btn {
                background: none;
                border: none;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.3s ease;
            }

            .favorite-btn:hover, .share-btn:hover {
                background: #f0f0f0;
            }

            .favorite-btn i, .share-btn i {
                font-size: 18px;
                color: #333;
            }

            .favorite-btn.active i {
                color: #ff0000;
            }

            .read-more-btn {
                background: none;
                border: none;
                color: #007bff;
                cursor: pointer;
                padding: 0;
                font-size: 14px;
                font-weight: 500;
            }

            .read-more-btn:hover {
                text-decoration: underline;
            }

            /* Gallery Popup Styles */
            .gallery-popup {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                z-index: 1000;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .gallery-popup-content {
                width: 90%;
                height: 90%;
                position: relative;
                display: flex;
                flex-direction: column;
                padding-bottom: 120px; /* Add padding to ensure thumbnails are visible */
            }

            .back-to-advert-btn {
                position: absolute;
                top: 20px;
                left: 20px;
                background: #007bff;
                border: none;
                color: white;
                font-size: 16px;
                cursor: pointer;
                padding: 8px 16px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 1001;
                transition: background-color 0.2s ease;
            }

            .back-to-advert-btn:hover {
                background: #0056b3;
            }

            .back-to-advert-btn i {
                font-size: 14px;
            }

            .gallery-main-image {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                margin-bottom: 20px;
                max-height: calc(100%); /* Ensure space for thumbnails */
                width: 100%;
                padding: 20px;
            }

            .gallery-main-image img {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                width: auto;
                height: auto;
                min-width: 80%;
                min-height: 80%;
            }

            .gallery-thumbnails {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 100px;
                display: flex;
                gap: 10px;
                overflow-x: auto;
                padding: 10px 0;
                justify-content: center;
                background: rgba(0, 0, 0, 0.5); /* Add slight background to ensure visibility */
            }

            .gallery-thumbnail {
                width: 120px;
                height: 80px;
                cursor: pointer;
                border: 2px solid transparent;
                border-radius: 4px;
                overflow: hidden;
                flex-shrink: 0;
            }

            .gallery-thumbnail.active {
                border-color: #007bff;
            }

            .gallery-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            </style>

            <script>
            // Add WordPress AJAX data
            const carListingsData = {
                ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('toggle_favorite_car'); ?>'
            };

            document.addEventListener('DOMContentLoaded', function() {
                const thumbnails = document.querySelectorAll('.thumbnail');
                const mainImage = document.querySelector('.main-image img');
                
                const preloadImages = () => {
                    thumbnails.forEach(thumb => {
                        const img = new Image();
                        img.src = thumb.dataset.fullUrl;
                    });
                };
                
                preloadImages();

                thumbnails.forEach(thumb => {
                    thumb.addEventListener('click', function() {
                        thumbnails.forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        const newImageUrl = this.dataset.fullUrl;
                        if (newImageUrl && mainImage) {
                            mainImage.src = newImageUrl;
                        }
                    });
                });

                const readMoreBtn = document.querySelector('.read-more-btn');
                if (readMoreBtn) {
                    readMoreBtn.addEventListener('click', function() {
                        const descriptionText = document.querySelector('.description-text');
                        const fullDescription = document.querySelector('.full-description');
                        
                        if (descriptionText && fullDescription) {
                            if (descriptionText.style.display === 'none') {
                                descriptionText.style.display = 'block';
                                fullDescription.style.display = 'none';
                                this.textContent = 'Read more';
                            } else {
                                descriptionText.style.display = 'none';
                                fullDescription.style.display = 'block';
                                this.textContent = 'Show less';
                            }
                        }
                    });
                }

                const favoriteBtn = document.querySelector('.favorite-btn');
                if (favoriteBtn) {
                    favoriteBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                            alert('Please log in to add favorites. (Error: Script data missing)');
                            return;
                        }

                        const carId = this.getAttribute('data-car-id');
                        const isActive = this.classList.contains('active');
                        const heartIcon = this.querySelector('i');

                        this.classList.toggle('active');
                        if (isActive) {
                            heartIcon.classList.remove('fas');
                            heartIcon.classList.add('far');
                        } else {
                            heartIcon.classList.remove('far');
                            heartIcon.classList.add('fas');
                        }

                        const formData = new FormData();
                        formData.append('action', 'toggle_favorite_car');
                        formData.append('car_id', carId);
                        formData.append('is_favorite', !isActive ? '1' : '0');
                        formData.append('nonce', carListingsData.nonce);

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
                            }
                        })
                        .catch(error => {
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
                }

                // Add Gallery Popup Functionality
                const viewGalleryBtn = document.querySelector('.view-gallery-btn');
                const galleryPopup = document.querySelector('.gallery-popup');
                const backToAdvertBtn = document.querySelector('.back-to-advert-btn');
                const galleryMainImage = document.querySelector('.gallery-main-image img');
                const galleryThumbnails = document.querySelectorAll('.gallery-thumbnail');
                let lastActiveThumbnailIndex = 0; // Track the last active thumbnail

                // Function to open gallery with specific image
                function openGalleryWithImage(imageIndex) {
                    galleryPopup.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    
                    // Remove active class from all thumbnails
                    galleryThumbnails.forEach(thumb => thumb.classList.remove('active'));
                    
                    // Set the clicked thumbnail as active
                    if (galleryThumbnails[imageIndex]) {
                        galleryThumbnails[imageIndex].classList.add('active');
                        galleryThumbnails[imageIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        
                        // Update main image
                        const newImageUrl = galleryThumbnails[imageIndex].dataset.fullUrl;
                        if (newImageUrl && galleryMainImage) {
                            galleryMainImage.src = newImageUrl;
                        }
                    }
                    
                    lastActiveThumbnailIndex = imageIndex;
                }

                // Add click handlers to all clickable images
                document.querySelectorAll('.clickable-image').forEach(img => {
                    img.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.blur(); // Remove focus after click
                        const imageIndex = parseInt(this.dataset.imageIndex);
                        openGalleryWithImage(imageIndex);
                    });
                });

                if (viewGalleryBtn && galleryPopup) {
                    viewGalleryBtn.addEventListener('click', function() {
                        openGalleryWithImage(lastActiveThumbnailIndex); // Use last active index instead of 0
                    });
                }

                // Restore back to advert button functionality
                if (backToAdvertBtn) {
                    backToAdvertBtn.addEventListener('click', function() {
                        galleryPopup.style.display = 'none';
                        document.body.style.overflow = ''; // Restore scrolling
                    });
                }

                // Restore click outside to close functionality
                galleryPopup.addEventListener('click', function(e) {
                    if (e.target === galleryPopup) {
                        galleryPopup.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                });

                // Handle gallery thumbnail clicks
                galleryThumbnails.forEach((thumb, index) => {
                    thumb.addEventListener('click', function() {
                        galleryThumbnails.forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        lastActiveThumbnailIndex = index;
                        const newImageUrl = this.dataset.fullUrl;
                        if (newImageUrl && galleryMainImage) {
                            galleryMainImage.src = newImageUrl;
                        }
                    });
                });
            });
            </script>
            <?php

        else:
            // If for some reason a non-car post is accessed via this template.
            echo '<p class="error-message">This content is not a car listing.</p>';
        endif; // End check for 'car' post type

    endwhile;
else :
    // If no post is found (standard WordPress Loop practice)
    get_template_part('template-parts/content', 'none'); // Or your theme's way of showing "not found"
endif;

get_footer(); // Ensure Astra's footer is loaded
?> 