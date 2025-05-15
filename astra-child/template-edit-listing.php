<?php
/**
 * Template Name: Edit Car Listing
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Get the car ID from the URL parameter
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

if (!$car_id) {
    wp_redirect(home_url('/my-listings/'));
    exit;
}

// Get the car post
$car = get_post($car_id);

// Check if the car exists and belongs to the current user
if (!$car || $car->post_type !== 'car' || $car->post_author != get_current_user_id()) {
    wp_redirect(home_url('/my-listings/'));
    exit;
}

// Get all car details
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
$body_type = get_post_meta($car_id, 'body_type', true);
$drive_type = get_post_meta($car_id, 'drive_type', true);
$exterior_color = get_post_meta($car_id, 'exterior_color', true);
$interior_color = get_post_meta($car_id, 'interior_color', true);
$description = get_post_meta($car_id, 'description', true);
$number_of_doors = get_post_meta($car_id, 'number_of_doors', true);
$number_of_seats = get_post_meta($car_id, 'number_of_seats', true);

// Get all car images
$featured_image = get_post_thumbnail_id($car_id);
$additional_images = get_field('car_images', $car_id);
$all_images = array();

if ($featured_image) {
    $all_images[] = $featured_image;
}

if (is_array($additional_images)) {
    $all_images = array_merge($all_images, $additional_images);
}

get_header();
?>

<div class="page-edit-listing">
    <div class="ast-container">
        <div class="ast-row">
            <div class="ast-col-md-12">
                <?php
                if (isset($_GET['listing_error'])) {
                    ?>
                    <div class="listing-error-message">
                        <h2><?php esc_html_e('Submission Error', 'astra-child'); ?></h2>
                        <p><?php esc_html_e('There was a problem with your submission. Please check all fields and try again.', 'astra-child'); ?></p>
                    </div>
                    <?php
                }
                ?>
                <h1><?php esc_html_e('Edit Car Listing', 'astra-child'); ?></h1>
                
                <form id="edit-car-listing-form" class="car-listing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('edit_car_listing_nonce', 'edit_car_listing_nonce'); ?>
                    <input type="hidden" name="action" value="edit_car_listing">
                    <input type="hidden" name="car_id" value="<?php echo esc_attr($car_id); ?>">

                    <div class="add-listing-main-row">
                        <div class="add-listing-main-info-column">
                            <div class="form-section basic-details-section">
                                <h2><?php esc_html_e('Basic Details', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="make"><i class="fas fa-car-side"></i> <?php esc_html_e('Make', 'astra-child'); ?></label>
                                        <select id="make" name="make" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Make', 'astra-child'); ?></option>
                                            <?php
                                            foreach ($add_listing_makes as $make_name => $models) {
                                                echo '<option value="' . esc_attr($make_name) . '" ' . selected($make, $make_name, false) . '>' . esc_html($make_name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><i class="fas fa-car"></i> <?php esc_html_e('Model', 'astra-child'); ?></label>
                                        <select id="model" name="model" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Model', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="variant"><i class="fas fa-car-side"></i> <?php esc_html_e('Variant', 'astra-child'); ?></label>
                                        <select id="variant" name="variant" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Variant', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="year"><i class="far fa-calendar-alt"></i> <?php esc_html_e('Year', 'astra-child'); ?></label>
                                        <select id="year" name="year" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Year', 'astra-child'); ?></option>
                                            <?php
                                            for ($y = 2025; $y >= 1948; $y--) {
                                                echo '<option value="' . esc_attr($y) . '" ' . selected($year, $y, false) . '>' . esc_html($y) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="mileage"><i class="fas fa-road"></i> <?php esc_html_e('Mileage', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="mileage" name="mileage" class="form-control" value="<?php echo esc_attr($mileage); ?>" required>
                                            <span class="input-suffix">km</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="price"><i class="fas fa-euro-sign"></i> <?php esc_html_e('Price', 'astra-child'); ?></label>
                                        <input type="text" id="price" name="price" class="form-control" value="<?php echo esc_attr($price); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <label for="location"><i class="fas fa-map-pin"></i> <?php esc_html_e('Location', 'astra-child'); ?></label>
                                    <input type="text" id="location" name="location" class="form-control" value="<?php echo esc_attr($location); ?>" required readonly>
                                    <button type="button" class="btn btn-secondary choose-location-btn">Choose Location ></button>
                                </div>
                            </div>

                            <div class="form-section engine-performance-section">
                                <h2><?php esc_html_e('Engine & Performance', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="engine_capacity"><i class="fas fa-tachometer-alt"></i> <?php esc_html_e('Engine Capacity', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="engine_capacity" name="engine_capacity" class="form-control" value="<?php echo esc_attr($engine_capacity); ?>" required>
                                            <span class="input-suffix">L</span>
                                        </div>
                                    </div>
                                    <div class="form-third">
                                        <label for="fuel_type"><i class="fas fa-gas-pump"></i> <?php esc_html_e('Fuel Type', 'astra-child'); ?></label>
                                        <select id="fuel_type" name="fuel_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Fuel Type', 'astra-child'); ?></option>
                                            <option value="Petrol" <?php selected($fuel_type, 'Petrol'); ?>><?php esc_html_e('Petrol', 'astra-child'); ?></option>
                                            <option value="Diesel" <?php selected($fuel_type, 'Diesel'); ?>><?php esc_html_e('Diesel', 'astra-child'); ?></option>
                                            <option value="Electric" <?php selected($fuel_type, 'Electric'); ?>><?php esc_html_e('Electric', 'astra-child'); ?></option>
                                            <option value="Petrol hybrid" <?php selected($fuel_type, 'Petrol hybrid'); ?>><?php esc_html_e('Petrol hybrid', 'astra-child'); ?></option>
                                            <option value="Diesel hybrid" <?php selected($fuel_type, 'Diesel hybrid'); ?>><?php esc_html_e('Diesel hybrid', 'astra-child'); ?></option>
                                            <option value="Plug-in petrol" <?php selected($fuel_type, 'Plug-in petrol'); ?>><?php esc_html_e('Plug-in petrol', 'astra-child'); ?></option>
                                            <option value="Plug-in diesel" <?php selected($fuel_type, 'Plug-in diesel'); ?>><?php esc_html_e('Plug-in diesel', 'astra-child'); ?></option>
                                            <option value="Bi Fuel" <?php selected($fuel_type, 'Bi Fuel'); ?>><?php esc_html_e('Bi Fuel', 'astra-child'); ?></option>
                                            <option value="Hydrogen" <?php selected($fuel_type, 'Hydrogen'); ?>><?php esc_html_e('Hydrogen', 'astra-child'); ?></option>
                                            <option value="Natural Gas" <?php selected($fuel_type, 'Natural Gas'); ?>><?php esc_html_e('Natural Gas', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="transmission"><i class="fas fa-cogs"></i> <?php esc_html_e('Transmission', 'astra-child'); ?></label>
                                        <select id="transmission" name="transmission" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Transmission', 'astra-child'); ?></option>
                                            <option value="Manual" <?php selected($transmission, 'Manual'); ?>><?php esc_html_e('Manual', 'astra-child'); ?></option>
                                            <option value="Automatic" <?php selected($transmission, 'Automatic'); ?>><?php esc_html_e('Automatic', 'astra-child'); ?></option>
                                            <option value="Semi-Automatic" <?php selected($transmission, 'Semi-Automatic'); ?>><?php esc_html_e('Semi-Automatic', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="drive_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Drive Type', 'astra-child'); ?></label>
                                        <select id="drive_type" name="drive_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Drive Type', 'astra-child'); ?></option>
                                            <option value="Front-Wheel Drive" <?php selected($drive_type, 'Front-Wheel Drive'); ?>><?php esc_html_e('Front-Wheel Drive', 'astra-child'); ?></option>
                                            <option value="Rear-Wheel Drive" <?php selected($drive_type, 'Rear-Wheel Drive'); ?>><?php esc_html_e('Rear-Wheel Drive', 'astra-child'); ?></option>
                                            <option value="All-Wheel Drive" <?php selected($drive_type, 'All-Wheel Drive'); ?>><?php esc_html_e('All-Wheel Drive', 'astra-child'); ?></option>
                                            <option value="4-Wheel Drive" <?php selected($drive_type, '4-Wheel Drive'); ?>><?php esc_html_e('4-Wheel Drive', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-half">
                                        <label for="hp"><i class="fas fa-horse"></i> <?php esc_html_e('HorsePower (Optional)', 'astra-child'); ?></label>
                                        <div class="input-with-suffix">
                                            <input type="text" id="hp" name="hp" class="form-control" value="<?php echo esc_attr(get_post_meta($car_id, 'hp', true)); ?>">
                                            <span class="input-suffix">HP</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section body-design-section">
                                <h2><?php esc_html_e('Body & Design', 'astra-child'); ?></h2>
                                <div class="form-row form-row-thirds">
                                    <div class="form-third">
                                        <label for="body_type"><i class="fas fa-car-side"></i> <?php esc_html_e('Body Type', 'astra-child'); ?></label>
                                        <select id="body_type" name="body_type" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Body Type', 'astra-child'); ?></option>
                                            <option value="Hatchback" <?php selected($body_type, 'Hatchback'); ?>><?php esc_html_e('Hatchback', 'astra-child'); ?></option>
                                            <option value="Saloon" <?php selected($body_type, 'Saloon'); ?>><?php esc_html_e('Saloon', 'astra-child'); ?></option>
                                            <option value="Coupe" <?php selected($body_type, 'Coupe'); ?>><?php esc_html_e('Coupe', 'astra-child'); ?></option>
                                            <option value="Convertible" <?php selected($body_type, 'Convertible'); ?>><?php esc_html_e('Convertible', 'astra-child'); ?></option>
                                            <option value="Estate" <?php selected($body_type, 'Estate'); ?>><?php esc_html_e('Estate', 'astra-child'); ?></option>
                                            <option value="SUV" <?php selected($body_type, 'SUV'); ?>><?php esc_html_e('SUV', 'astra-child'); ?></option>
                                            <option value="MPV" <?php selected($body_type, 'MPV'); ?>><?php esc_html_e('MPV', 'astra-child'); ?></option>
                                            <option value="Pickup" <?php selected($body_type, 'Pickup'); ?>><?php esc_html_e('Pickup', 'astra-child'); ?></option>
                                            <option value="Camper" <?php selected($body_type, 'Camper'); ?>><?php esc_html_e('Camper', 'astra-child'); ?></option>
                                            <option value="Minibus" <?php selected($body_type, 'Minibus'); ?>><?php esc_html_e('Minibus', 'astra-child'); ?></option>
                                            <option value="Limousine" <?php selected($body_type, 'Limousine'); ?>><?php esc_html_e('Limousine', 'astra-child'); ?></option>
                                            <option value="Car Derived Van" <?php selected($body_type, 'Car Derived Van'); ?>><?php esc_html_e('Car Derived Van', 'astra-child'); ?></option>
                                            <option value="Combi Van" <?php selected($body_type, 'Combi Van'); ?>><?php esc_html_e('Combi Van', 'astra-child'); ?></option>
                                            <option value="Panel Van" <?php selected($body_type, 'Panel Van'); ?>><?php esc_html_e('Panel Van', 'astra-child'); ?></option>
                                            <option value="Window Van" <?php selected($body_type, 'Window Van'); ?>><?php esc_html_e('Window Van', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="exterior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Exterior Color', 'astra-child'); ?></label>
                                        <select id="exterior_color" name="exterior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Exterior Color', 'astra-child'); ?></option>
                                            <option value="Black" <?php selected($exterior_color, 'Black'); ?>><?php esc_html_e('Black', 'astra-child'); ?></option>
                                            <option value="White" <?php selected($exterior_color, 'White'); ?>><?php esc_html_e('White', 'astra-child'); ?></option>
                                            <option value="Silver" <?php selected($exterior_color, 'Silver'); ?>><?php esc_html_e('Silver', 'astra-child'); ?></option>
                                            <option value="Gray" <?php selected($exterior_color, 'Gray'); ?>><?php esc_html_e('Gray', 'astra-child'); ?></option>
                                            <option value="Red" <?php selected($exterior_color, 'Red'); ?>><?php esc_html_e('Red', 'astra-child'); ?></option>
                                            <option value="Blue" <?php selected($exterior_color, 'Blue'); ?>><?php esc_html_e('Blue', 'astra-child'); ?></option>
                                            <option value="Green" <?php selected($exterior_color, 'Green'); ?>><?php esc_html_e('Green', 'astra-child'); ?></option>
                                            <option value="Yellow" <?php selected($exterior_color, 'Yellow'); ?>><?php esc_html_e('Yellow', 'astra-child'); ?></option>
                                            <option value="Orange" <?php selected($exterior_color, 'Orange'); ?>><?php esc_html_e('Orange', 'astra-child'); ?></option>
                                            <option value="Purple" <?php selected($exterior_color, 'Purple'); ?>><?php esc_html_e('Purple', 'astra-child'); ?></option>
                                            <option value="Brown" <?php selected($exterior_color, 'Brown'); ?>><?php esc_html_e('Brown', 'astra-child'); ?></option>
                                            <option value="Beige" <?php selected($exterior_color, 'Beige'); ?>><?php esc_html_e('Beige', 'astra-child'); ?></option>
                                            <option value="Gold" <?php selected($exterior_color, 'Gold'); ?>><?php esc_html_e('Gold', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-third">
                                        <label for="interior_color"><i class="fas fa-paint-brush"></i> <?php esc_html_e('Interior Color', 'astra-child'); ?></label>
                                        <select id="interior_color" name="interior_color" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Interior Color', 'astra-child'); ?></option>
                                            <option value="Black" <?php selected($interior_color, 'Black'); ?>><?php esc_html_e('Black', 'astra-child'); ?></option>
                                            <option value="White" <?php selected($interior_color, 'White'); ?>><?php esc_html_e('White', 'astra-child'); ?></option>
                                            <option value="Gray" <?php selected($interior_color, 'Gray'); ?>><?php esc_html_e('Gray', 'astra-child'); ?></option>
                                            <option value="Beige" <?php selected($interior_color, 'Beige'); ?>><?php esc_html_e('Beige', 'astra-child'); ?></option>
                                            <option value="Brown" <?php selected($interior_color, 'Brown'); ?>><?php esc_html_e('Brown', 'astra-child'); ?></option>
                                            <option value="Red" <?php selected($interior_color, 'Red'); ?>><?php esc_html_e('Red', 'astra-child'); ?></option>
                                            <option value="Blue" <?php selected($interior_color, 'Blue'); ?>><?php esc_html_e('Blue', 'astra-child'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row form-row-halves">
                                    <div class="form-half">
                                        <label for="number_of_doors"><i class="fas fa-door-open"></i> <?php esc_html_e('Number of Doors', 'astra-child'); ?></label>
                                        <select id="number_of_doors" name="number_of_doors" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Number of Doors', 'astra-child'); ?></option>
                                            <option value="2" <?php selected($number_of_doors, '2'); ?>>2</option>
                                            <option value="3" <?php selected($number_of_doors, '3'); ?>>3</option>
                                            <option value="4" <?php selected($number_of_doors, '4'); ?>>4</option>
                                            <option value="5" <?php selected($number_of_doors, '5'); ?>>5</option>
                                        </select>
                                    </div>
                                    <div class="form-half">
                                        <label for="number_of_seats"><i class="fas fa-chair"></i> <?php esc_html_e('Number of Seats', 'astra-child'); ?></label>
                                        <select id="number_of_seats" name="number_of_seats" class="form-control" required>
                                            <option value=""><?php esc_html_e('Select Number of Seats', 'astra-child'); ?></option>
                                            <option value="2" <?php selected($number_of_seats, '2'); ?>>2</option>
                                            <option value="3" <?php selected($number_of_seats, '3'); ?>>3</option>
                                            <option value="4" <?php selected($number_of_seats, '4'); ?>>4</option>
                                            <option value="5" <?php selected($number_of_seats, '5'); ?>>5</option>
                                            <option value="6" <?php selected($number_of_seats, '6'); ?>>6</option>
                                            <option value="7" <?php selected($number_of_seats, '7'); ?>>7</option>
                                            <option value="8" <?php selected($number_of_seats, '8'); ?>>8</option>
                                            <option value="9" <?php selected($number_of_seats, '9'); ?>>9</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section vehicle-history-section">
                                <h2><?php esc_html_e('Vehicle History', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <div class="vehicle-history-grid">
                                        <?php
                                        $vehicle_history_options = array(
                                            'no_accidents' => 'No Accidents',
                                            'minor_accidents' => 'Minor Accidents',
                                            'major_accidents' => 'Major Accidents',
                                            'regular_maintenance' => 'Regular Maintenance',
                                            'engine_overhaul' => 'Engine Overhaul',
                                            'transmission_replacement' => 'Transmission Replacement',
                                            'repainted' => 'Repainted',
                                            'bodywork_repair' => 'Bodywork Repair',
                                            'rust_treatment' => 'Rust Treatment',
                                            'no_modifications' => 'No Modifications',
                                            'performance_upgrades' => 'Performance Upgrades',
                                            'cosmetic_modifications' => 'Cosmetic Modifications',
                                            'flood_damage' => 'Flood Damage',
                                            'fire_damage' => 'Fire Damage',
                                            'hail_damage' => 'Hail Damage',
                                            'clear_title' => 'Clear Title',
                                            'no_known_issues' => 'No Known Issues',
                                            'odometer_replacement' => 'Odometer Replacement'
                                        );
                                        $selected_history = get_post_meta($car_id, 'vehicle_history', true);
                                        if (!is_array($selected_history)) {
                                            $selected_history = array();
                                        }
                                        foreach ($vehicle_history_options as $value => $label) {
                                            echo '<div class="vehicle-history-option">';
                                            echo '<input type="checkbox" id="vehiclehistory_' . esc_attr($value) . '" name="vehicle_history[]" value="' . esc_attr($value) . '" ' . checked(in_array($value, $selected_history), true, false) . '>';
                                            echo '<label for="vehiclehistory_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section extras-section">
                                <h2><?php esc_html_e('Extras (Optional)', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <div class="extras-grid">
                                        <?php
                                        $extras_options = array(
                                            'alloy_wheels' => 'Alloy Wheels',
                                            'cruise_control' => 'Cruise Control',
                                            'disabled_accessible' => 'Disabled Accessible',
                                            'keyless_start' => 'Keyless Start',
                                            'rear_view_camera' => 'Rear View Camera',
                                            'start_stop' => 'Start/Stop',
                                            'sunroof' => 'Sunroof',
                                            'heated_seats' => 'Heated Seats',
                                            'android_auto' => 'Android Auto',
                                            'apple_carplay' => 'Apple CarPlay',
                                            'folding_mirrors' => 'Folding Mirrors',
                                            'leather_seats' => 'Leather Seats',
                                            'panoramic_roof' => 'Panoramic Roof',
                                            'parking_sensors' => 'Parking Sensors',
                                            'camera_360' => '360° Camera',
                                            'adaptive_cruise_control' => 'Adaptive Cruise Control',
                                            'blind_spot_mirror' => 'Blind Spot Mirror',
                                            'lane_assist' => 'Lane Assist',
                                            'power_tailgate' => 'Power Tailgate'
                                        );
                                        $selected_extras = get_post_meta($car_id, 'extras', true);
                                        if (!is_array($selected_extras)) {
                                            $selected_extras = array();
                                        }
                                        foreach ($extras_options as $value => $label) {
                                            echo '<div class="extra-option">';
                                            echo '<input type="checkbox" id="extra_' . esc_attr($value) . '" name="extras[]" value="' . esc_attr($value) . '" ' . checked(in_array($value, $selected_extras), true, false) . '>';
                                            echo '<label for="extra_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section description-section">
                                <h2><?php esc_html_e('Description', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <label for="description"><i class="fas fa-align-left"></i> <?php esc_html_e('Description', 'astra-child'); ?></label>
                                    <textarea id="description" name="description" class="form-control" rows="6" required><?php echo esc_textarea($description); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="add-listing-image-column">
                            <div class="add-listing-images-section">
                                <h2><?php esc_html_e('Upload Images', 'astra-child'); ?></h2>
                                <p class="image-upload-info"><?php esc_html_e('Hold CTRL to choose several photos. Maximum 25 images per listing. Maximum file size is 5MB, the formats are .jpg, .jpeg, .png, .gif, .webp', 'astra-child'); ?></p>
                                <p class="image-upload-note"><?php esc_html_e('Note: ads with good photos get more attention', 'astra-child'); ?></p>
                                <div class="image-upload-container">
                                    <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
                                        <div class="upload-message">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p><?php esc_html_e('Drag & Drop Images Here', 'astra-child'); ?></p>
                                            <p class="small"><?php esc_html_e('or click to select files', 'astra-child'); ?></p>
                                        </div>
                                    </div>
                                    <input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                                    <div id="image-preview" class="image-preview">
                                        <?php
                                        if (!empty($all_images)) {
                                            foreach ($all_images as $image_id) {
                                                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                                if ($image_url) {
                                                    ?>
                                                    <div class="image-preview-item">
                                                        <img src="<?php echo esc_url($image_url); ?>" alt="Car image">
                                                        <button type="button" class="remove-image" data-image-id="<?php echo esc_attr($image_id); ?>">&times;</button>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="submit-button gradient-button"><?php esc_html_e('Update Listing', 'astra-child'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Store the makes data
    const makesData = <?php echo json_encode($add_listing_makes); ?>;
    
    // Set initial model and variant options based on the selected make
    const selectedMake = $('#make').val();
    if (selectedMake && makesData[selectedMake]) {
        const modelSelect = $('#model');
        Object.keys(makesData[selectedMake]).forEach(model => {
            modelSelect.append(`<option value="${model}" ${model === '<?php echo esc_js($model); ?>' ? 'selected' : ''}>${model}</option>`);
        });
        
        // Set initial variant options
        const selectedModel = '<?php echo esc_js($model); ?>';
        if (selectedModel && makesData[selectedMake][selectedModel]) {
            const variantSelect = $('#variant');
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    variantSelect.append(`<option value="${variant}" ${variant === '<?php echo esc_js($variant); ?>' ? 'selected' : ''}>${variant}</option>`);
                }
            });
        }
    }
    
    // Handle make selection change
    $('#make').on('change', function() {
        const selectedMake = $(this).val();
        const modelSelect = $('#model');
        const variantSelect = $('#variant');
        
        // Clear existing options
        modelSelect.empty().append('<option value=""><?php esc_html_e('Select Model', 'astra-child'); ?></option>');
        variantSelect.empty().append('<option value=""><?php esc_html_e('Select Variant', 'astra-child'); ?></option>');
        
        if (selectedMake && makesData[selectedMake]) {
            // Add model options
            Object.keys(makesData[selectedMake]).forEach(model => {
                modelSelect.append(`<option value="${model}">${model}</option>`);
            });
        }
    });
    
    // Handle model selection change
    $('#model').on('change', function() {
        const selectedMake = $('#make').val();
        const selectedModel = $(this).val();
        const variantSelect = $('#variant');
        
        // Clear existing options
        variantSelect.empty().append('<option value=""><?php esc_html_e('Select Variant', 'astra-child'); ?></option>');
        
        if (selectedMake && selectedModel && makesData[selectedMake] && makesData[selectedMake][selectedModel]) {
            // Add variant options
            makesData[selectedMake][selectedModel].forEach(variant => {
                if (variant) {
                    variantSelect.append(`<option value="${variant}">${variant}</option>`);
                }
            });
        }
    });

    // Handle image upload and preview
    const fileInput = $('#car_images');
    const fileUploadArea = $('#file-upload-area');
    const imagePreview = $('#image-preview');
    
    // Handle click on upload area
    fileUploadArea.on('click', function() {
        fileInput.trigger('click');
    });
    
    // Handle file selection
    fileInput.on('change', function() {
        handleFiles(this.files);
    });
    
    // Handle drag and drop
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function() {
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        handleFiles(e.originalEvent.dataTransfer.files);
    });
    
    // Handle remove image
    imagePreview.on('click', '.remove-image', function(e) {
        e.preventDefault();
        const imageId = $(this).data('image-id');
        if (imageId) {
            // Add hidden input to track removed images
            $('<input>').attr({
                type: 'hidden',
                name: 'removed_images[]',
                value: imageId
            }).appendTo('#edit-car-listing-form');
        }
        $(this).parent().remove();
    });
    
    function handleFiles(files) {
        const maxFiles = 25;
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Check if adding new files would exceed the limit
        const currentImages = imagePreview.find('.image-preview-item').length;
        if (currentImages + files.length > maxFiles) {
            alert('Maximum ' + maxFiles + ' images allowed.');
            return;
        }
        
        Array.from(files).forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Allowed types: JPG, PNG, GIF, WEBP');
                return;
            }
            
            if (file.size > maxSize) {
                alert('File size exceeds 5MB limit: ' + file.name);
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = $('<div class="image-preview-item">')
                    .append($('<img>').attr('src', e.target.result))
                    .append($('<button type="button" class="remove-image">&times;</button>'));
                imagePreview.append(previewItem);
            };
            reader.readAsDataURL(file);
        });
    }
    
    // Format numbers with commas
    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Remove commas from number
    function unformatNumber(number) {
        return number.toString().replace(/,/g, '');
    }
    
    // Format mileage input
    const mileageInput = $('#mileage');
    mileageInput.on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format price input
    const priceInput = $('#price');
    priceInput.on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Format HP input
    $('#hp').on('input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value.length > 0) {
            value = parseInt(value).toLocaleString();
        }
        $(this).val(value);
        $(this).data('raw-value', value.replace(/[^\d]/g, ''));
    });
    
    // Handle form submission
    $('#edit-car-listing-form').on('submit', function(e) {
        // Get the raw values from data attributes
        const rawMileage = mileageInput.data('raw-value') || unformatNumber(mileageInput.val());
        const rawPrice = priceInput.data('raw-value') || unformatNumber(priceInput.val());
        const rawHp = $('#hp').data('raw-value') || unformatNumber($('#hp').val());
        
        // Create hidden inputs with the raw values
        $('<input>').attr({
            type: 'hidden',
            name: 'mileage',
            value: rawMileage
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'price',
            value: rawPrice
        }).appendTo(this);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'hp',
            value: rawHp
        }).appendTo(this);
        
        // Disable the original inputs
        mileageInput.prop('disabled', true);
        priceInput.prop('disabled', true);
        $('#hp').prop('disabled', true);
    });
});
</script>

<?php
get_footer(); 