<?php
/**
 * Car Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Include helper files
require_once __DIR__ . '/car-listings-data.php';
require_once __DIR__ . '/car-listings-query.php'; // Added this line
// require_once __DIR__ . '/car-listings-render.php'; // Potential future file

// Register the shortcode
add_shortcode('car_listings', 'display_car_listings');

function display_car_listings($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 12,
        'orderby' => 'date',
        'order' => 'DESC'
    ), $atts);

    // Enqueue the stylesheet for this shortcode
    wp_enqueue_style(
        'car-listings-style',
        get_stylesheet_directory_uri() . '/css/car-listings.css',
        array(), // Dependencies
        filemtime(get_stylesheet_directory() . '/css/car-listings.css') // Versioning based on file modification time
    );

    // Start output buffering
    ob_start();

    // Get the current page number
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Get data for filters
    $make_data = get_car_makes_with_counts();
    $makes = $make_data['makes'];
    $make_counts = $make_data['counts'];

    $model_data = get_car_models_by_make_with_counts($makes);
    $models_by_make = $model_data['models_by_make'];
    $model_counts = $model_data['model_counts'];

    $variant_data = get_car_variants_by_make_model_with_counts($models_by_make);
    $variants_by_make_model = $variant_data['variants_by_make_model'];
    $variant_counts = $variant_data['variant_counts'];

    $locations = get_car_locations();

    $price_data = get_car_price_ranges_with_counts();
    $prices = $price_data['prices'];
    $price_counts = $price_data['counts'];

    $year_data = get_car_years_with_counts();
    $years = $year_data['years'];
    $year_counts = $year_data['counts'];

    $kilometer_data = get_car_kilometer_ranges_with_counts();
    $kilometers = $kilometer_data['kilometers'];
    $km_counts = $kilometer_data['counts'];

    $engine_size_data = get_car_engine_sizes_with_counts();
    $engine_sizes = $engine_size_data['engine_sizes'];
    $engine_counts = $engine_size_data['counts'];

    $body_type_data = get_car_body_types_with_counts();
    $body_types = $body_type_data['body_types'];
    $body_type_counts = $body_type_data['counts'];

    $fuel_type_data = get_car_fuel_types_with_counts();
    $fuel_types = $fuel_type_data['fuel_types'];
    $fuel_type_counts = $fuel_type_data['counts'];

    $drive_type_data = get_car_drive_types_with_counts();
    $drive_types = $drive_type_data['drive_types'];
    $drive_type_counts = $drive_type_data['counts'];

    $color_data = get_car_colors_with_counts();
    $colors = $color_data['colors'];
    $exterior_color_counts = $color_data['exterior_counts'];
    $interior_color_counts = $color_data['interior_counts'];

    $min_max = get_car_filter_min_max_values();

    // Build the query arguments using the helper function
    $args = build_car_listings_query_args($atts, $paged);

    // Get car listings
    $car_query = new WP_Query($args);

    // --- DEBUGGING START ---
    // echo '<pre>DEBUG: $makes: '; print_r($makes); echo '</pre>';
    // echo '<pre>DEBUG: $make_counts: '; print_r($make_counts); echo '</pre>';
    // echo '<pre>DEBUG: $locations: '; print_r($locations); echo '</pre>';
    // echo '<pre>DEBUG: $colors: '; print_r($colors); echo '</pre>';
    // --- DEBUGGING END ---

    // Start the output
    ?>
    <div class="car-listings-container">
        <!-- Active Filters Bar -->
        <div class="active-filters-bar">
            <div class="active-filters-container">
                <!-- Active filters will be added here dynamically -->
            </div>
            <button class="filters-button">Filters</button>
        </div>

        <!-- Filters Popup -->
        <div class="filters-popup-overlay" id="filtersPopup">
            <div class="filters-popup-content">
                <div class="filters-popup-header">
                    <h2>Filter Cars</h2>
                    <button class="close-filters">&times;</button>
                </div>
            <form method="get" class="filters-form">
                <!-- Location -->
                <div class="filter-group">
                    <label for="location">Location:</label>
                    <select name="location" id="location">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo esc_attr($location); ?>" <?php selected(isset($_GET['location']) && $_GET['location'] === $location); ?>>
                                <?php echo esc_html($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Make, Model, and Variant -->
                <div class="filter-group">
                    <label for="make">Make:</label>
                    <select name="make" id="make">
                        <option value="">All Makes</option>
                        <?php foreach ($makes as $make): ?>
                            <option value="<?php echo esc_attr($make); ?>" <?php selected(isset($_GET['make']) && $_GET['make'] === $make); ?>>
                                <?php echo esc_html($make); ?> (<?php echo isset($make_counts[$make]) ? $make_counts[$make] : 0; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="model">Model:</label>
                    <select name="model" id="model" <?php echo !isset($_GET['make']) ? 'disabled' : ''; ?>>
                        <option value="">All Models</option>
                        <?php if (isset($_GET['make']) && isset($models_by_make[$_GET['make']])): ?>
                            <?php foreach ($models_by_make[$_GET['make']] as $model): ?>
                                <option value="<?php echo esc_attr($model); ?>" <?php selected(isset($_GET['model']) && $_GET['model'] === $model); ?>>
                                    <?php echo esc_html($model); ?> (<?php echo isset($model_counts[$_GET['make']][$model]) ? $model_counts[$_GET['make']][$model] : 0; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="variant">Variant:</label>
                    <select name="variant" id="variant" <?php echo !isset($_GET['make']) || !isset($_GET['model']) ? 'disabled' : ''; ?>>
                        <option value="">All Variants</option>
                        <?php if (isset($_GET['make']) && isset($_GET['model']) && isset($variants_by_make_model[$_GET['make']][$_GET['model']])): ?>
                            <?php foreach ($variants_by_make_model[$_GET['make']][$_GET['model']] as $variant): ?>
                                <option value="<?php echo esc_attr($variant); ?>" <?php selected(isset($_GET['variant']) && $_GET['variant'] === $variant); ?>>
                                    <?php echo esc_html($variant); ?> (<?php echo isset($variant_counts[$_GET['make']][$_GET['model']][$variant]) ? $variant_counts[$_GET['make']][$_GET['model']][$variant] : 0; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Price Range -->
                <div class="filter-group">
                    <label>Price (€)</label>
                    <div class="range-fields">
                        <div class="range-field">
                            <label for="price_min">From</label>
                            <select name="price_min" id="price_min">
                                <option value="">Any</option>
                                <?php foreach ($prices as $price): ?>
                                    <option value="<?php echo esc_attr($price); ?>" <?php selected(isset($_GET['price_min']) && $_GET['price_min'] == $price); ?>>
                                        €<?php echo number_format($price); ?> (<?php echo isset($price_counts[$price]) ? $price_counts[$price] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="range-field">
                            <label for="price_max">To</label>
                            <select name="price_max" id="price_max">
                                <option value="">Any</option>
                                <?php foreach ($prices as $price): ?>
                                    <option value="<?php echo esc_attr($price); ?>" <?php selected(isset($_GET['price_max']) && $_GET['price_max'] == $price); ?>>
                                        €<?php echo number_format($price); ?> (<?php echo isset($price_counts[$price]) ? $price_counts[$price] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- DEBUG: YEAR FILTER CHECK -->
                <!-- Year Range -->
                <div class="filter-group">
                    <label>Year</label>
                    <div class="range-fields">
                        <div class="range-field">
                            <label for="year_min">From</label>
                            <select name="year_min" id="year_min">
                                <option value="">Any</option>
                                <?php foreach ($years as $year): 
                                    $display_year = str_replace(',', '', $year); // Remove comma for display
                                ?>
                                    <option value="<?php echo esc_attr($year); ?>" <?php selected(isset($_GET['year_min']) && $_GET['year_min'] == $year); ?>>
                                        <?php echo esc_html($display_year); ?> (<?php echo isset($year_counts[$year]) ? $year_counts[$year] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="range-field">
                            <label for="year_max">To</label>
                            <select name="year_max" id="year_max">
                                <option value="">Any</option>
                                <?php foreach ($years as $year): 
                                     $display_year = str_replace(',', '', $year); // Remove comma for display
                                ?>
                                    <option value="<?php echo esc_attr($year); ?>" <?php selected(isset($_GET['year_max']) && $_GET['year_max'] == $year); ?>>
                                        <?php echo esc_html($display_year); ?> (<?php echo isset($year_counts[$year]) ? $year_counts[$year] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Kilometer Range -->
                <div class="filter-group">
                    <label>Mileage (km)</label>
                    <div class="range-fields">
                        <div class="range-field">
                            <label for="km_min">From</label>
                            <select name="km_min" id="km_min">
                                <option value="">Any</option>
                                <?php foreach ($kilometers as $km): ?>
                                    <option value="<?php echo esc_attr($km); ?>" <?php selected(isset($_GET['km_min']) && $_GET['km_min'] == $km); ?>>
                                        <?php echo number_format($km); ?> km (<?php echo isset($km_counts[$km]) ? $km_counts[$km] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="range-field">
                            <label for="km_max">To</label>
                            <select name="km_max" id="km_max">
                                <option value="">Any</option>
                                <?php foreach ($kilometers as $km): ?>
                                    <option value="<?php echo esc_attr($km); ?>" <?php selected(isset($_GET['km_max']) && $_GET['km_max'] == $km); ?>>
                                        <?php echo number_format($km); ?> km (<?php echo isset($km_counts[$km]) ? $km_counts[$km] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fuel Type -->
                <div class="filter-group">
                    <label>Fuel Type:</label>
                    <div class="checkbox-dropdown">
                        <button type="button" class="checkbox-dropdown-button">Select Fuel Types</button>
                        <div class="checkbox-group">
                            <?php foreach ($fuel_types as $type): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="fuel_type[]" value="<?php echo esc_attr($type); ?>" 
                                        <?php echo (isset($_GET['fuel_type']) && in_array($type, (array)$_GET['fuel_type'])) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($type); ?> (<?php echo isset($fuel_type_counts[$type]) ? $fuel_type_counts[$type] : 0; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Body Type -->
                <div class="filter-group">
                    <label>Body Type:</label>
                    <div class="checkbox-dropdown">
                        <button type="button" class="checkbox-dropdown-button">Select Body Types</button>
                        <div class="checkbox-group">
                            <?php foreach ($body_types as $type): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="body_type[]" value="<?php echo esc_attr($type); ?>" 
                                        <?php echo (isset($_GET['body_type']) && in_array($type, (array)$_GET['body_type'])) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($type); ?> (<?php echo isset($body_type_counts[$type]) ? $body_type_counts[$type] : 0; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                    <!-- Drive Type -->
                    <div class="filter-group">
                        <label>Drive Type:</label>
                        <div class="checkbox-dropdown">
                            <button type="button" class="checkbox-dropdown-button">Select Drive Types</button>
                            <div class="checkbox-group">
                                <?php foreach ($drive_types as $type): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="drive_type[]" value="<?php echo esc_attr($type); ?>" 
                                            <?php echo (isset($_GET['drive_type']) && in_array($type, (array)$_GET['drive_type'])) ? 'checked' : ''; ?>>
                                        <?php echo esc_html($type); ?> (<?php echo isset($drive_type_counts[$type]) ? $drive_type_counts[$type] : 0; ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <!-- Exterior Color -->
                <div class="filter-group">
                    <label>Exterior Color:</label>
                    <div class="checkbox-dropdown">
                        <button type="button" class="checkbox-dropdown-button">Select Exterior Colors</button>
                        <div class="checkbox-group">
                            <?php foreach ($colors as $color): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="exterior_color[]" value="<?php echo esc_attr($color); ?>" 
                                        <?php echo (isset($_GET['exterior_color']) && in_array($color, (array)$_GET['exterior_color'])) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($color); ?> (<?php echo isset($exterior_color_counts[$color]) ? $exterior_color_counts[$color] : 0; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Interior Color -->
                <div class="filter-group">
                    <label>Interior Color:</label>
                    <div class="checkbox-dropdown">
                        <button type="button" class="checkbox-dropdown-button">Select Interior Colors</button>
                        <div class="checkbox-group">
                            <?php foreach ($colors as $color): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="interior_color[]" value="<?php echo esc_attr($color); ?>" 
                                        <?php echo (isset($_GET['interior_color']) && in_array($color, (array)$_GET['interior_color'])) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($color); ?> (<?php echo isset($interior_color_counts[$color]) ? $interior_color_counts[$color] : 0; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Engine Size Range -->
                <div class="filter-group">
                    <label>Engine Size (L)</label>
                    <div class="range-fields">
                        <div class="range-field">
                            <label for="engine_min">From</label>
                            <select name="engine_min" id="engine_min">
                                <option value="">Any</option>
                                <?php foreach ($engine_sizes as $size): ?>
                                    <option value="<?php echo esc_attr($size); ?>" <?php selected(isset($_GET['engine_min']) && $_GET['engine_min'] == $size); ?>>
                                        <?php echo $size; ?>L (<?php echo isset($engine_counts[$size]) ? $engine_counts[$size] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="range-field">
                            <label for="engine_max">To</label>
                            <select name="engine_max" id="engine_max">
                                <option value="">Any</option>
                                <?php foreach ($engine_sizes as $size): ?>
                                    <option value="<?php echo esc_attr($size); ?>" <?php selected(isset($_GET['engine_max']) && $_GET['engine_max'] == $size); ?>>
                                        <?php echo $size; ?>L (<?php echo isset($engine_counts[$size]) ? $engine_counts[$size] : 0; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                    <div class="filter-actions">
                <button type="submit" class="filter-submit">Apply Filters</button>
                <a href="<?php echo esc_url(remove_query_arg(array_keys($_GET))); ?>" class="reset-filters">Reset Filters</a>
                    </div>
            </form>
            </div>
        </div>

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
                                    $clean_year = str_replace(',', '', $year); // Remove comma from year
                                    echo '<div class="car-listing-image' . ($index === 0 ? ' active' : '') . '" data-index="' . $index . '">';
                                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($clean_year . ' ' . $make . ' ' . $model) . '">'; // Use clean year in alt
                                    if ($index === count($all_images) - 1 && count($all_images) > 1) {
                                        echo '<a href="' . get_permalink() . '" class="see-all-images" style="display: none;">See All Images</a>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '<button class="carousel-nav prev"><i class="fas fa-chevron-left"></i></button>';
                            echo '<button class="carousel-nav next"><i class="fas fa-chevron-right"></i></button>';
                            
                            $user_id = get_current_user_id();
                            $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
                            $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                            $is_favorite = in_array(get_the_ID(), $favorite_cars);
                            $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                            $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';
                            echo '<button class="' . esc_attr($button_class) . '" data-car-id="' . get_the_ID() . '"><i class="' . esc_attr($heart_class) . '"></i></button>';
                            
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
                                        <span class="info-value"><?php echo esc_html(str_replace(',', '', $year)); ?></span>
                                    </div>
                                </div>
                                <div class="car-price">€<?php echo number_format($price); ?></div>
                                <div class="car-location"><?php echo esc_html($location); ?></div>
                            </div>
                        </a>
                    </div>
                <?php
                endwhile;
            else :
                echo '<p class="no-listings">No car listings found.</p>';
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

    <?php

    // Return the buffered content
    return ob_get_clean();
}