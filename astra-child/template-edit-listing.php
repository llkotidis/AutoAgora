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
$make = get_field('make', $car_id);
$model = get_field('model', $car_id);
$variant = get_field('variant', $car_id);

// Get the makes data structure
$add_listing_makes = [];
$add_listing_data = get_field('add_listing_data', 'option');
if ($add_listing_data) {
    foreach ($add_listing_data as $make_name => $models) {
        $add_listing_makes[$make_name] = $models;
    }
    ksort($add_listing_makes);
}

// Get other car details
$year = get_field('year', $car_id);
$price = get_field('price', $car_id);
$mileage = get_field('mileage', $car_id);
$location = get_field('location', $car_id);
$engine_capacity = get_field('engine_capacity', $car_id);
$fuel_type = get_field('fuel_type', $car_id);
$transmission = get_field('transmission', $car_id);
$body_type = get_field('body_type', $car_id);
$drive_type = get_field('drive_type', $car_id);
$exterior_color = get_field('exterior_color', $car_id);
$interior_color = get_field('interior_color', $car_id);
$description = get_field('description', $car_id);
$number_of_doors = get_field('number_of_doors', $car_id);
$number_of_seats = get_field('number_of_seats', $car_id);
$hp = get_field('hp', $car_id);
$mot_status = get_field('motuntil', $car_id);
$num_owners = get_field('numowners', $car_id);
$is_antique = get_field('isantique', $car_id);

// Get vehicle history and extras
$vehicle_history = get_field('vehiclehistory', $car_id);
$extras = get_field('extras', $car_id);

// Ensure vehicle_history and extras are arrays and properly formatted
if (!is_array($vehicle_history)) {
    if (!empty($vehicle_history)) {
        $vehicle_history = array($vehicle_history);
    } else {
        $vehicle_history = array();
    }
}

// Convert any serialized data to arrays
if (is_serialized($vehicle_history)) {
    $vehicle_history = maybe_unserialize($vehicle_history);
}

// Ensure we have arrays
if (!is_array($vehicle_history)) {
    $vehicle_history = array();
}
if (!is_array($extras)) {
    $extras = array();
    if (!empty($extras)) {
        $extras = array($extras);
    }
}

// Convert any serialized data to arrays
if (is_serialized($extras)) {
    $extras = maybe_unserialize($extras);
}

// Ensure we have arrays
if (!is_array($extras)) {
    $extras = array();
}

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

// Enqueue jQuery and our custom script
wp_enqueue_script('jquery');
wp_enqueue_script('edit-listing-script', get_stylesheet_directory_uri() . '/js/edit-listing.js', array('jquery'), '1.0.0', true);

// Localize the script with necessary data
wp_localize_script('edit-listing-script', 'editListingData', array(
    'makesData' => $add_listing_makes,
    'selectedMake' => esc_js($make),
    'selectedModel' => esc_js($model),
    'selectedVariant' => esc_js($variant)
));
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
                                        <input type="text" id="make" name="make" class="form-control" value="<?php echo esc_attr($make); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="model"><i class="fas fa-car"></i> <?php esc_html_e('Model', 'astra-child'); ?></label>
                                        <input type="text" id="model" name="model" class="form-control" value="<?php echo esc_attr($model); ?>" readonly>
                                    </div>
                                    <div class="form-third">
                                        <label for="variant"><i class="fas fa-car-side"></i> <?php esc_html_e('Variant', 'astra-child'); ?></label>
                                        <input type="text" id="variant" name="variant" class="form-control" value="<?php echo esc_attr($variant); ?>" readonly>
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
                                            <input type="text" id="hp" name="hp" class="form-control" value="<?php echo esc_attr($hp); ?>">
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

                            <div class="form-section mot-section">
                                <h2><?php esc_html_e('Registration & Background Info', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <label for="motuntil"><i class="fas fa-clipboard-check"></i> <?php esc_html_e('MOT Status', 'astra-child'); ?></label>
                                    <select id="motuntil" name="motuntil" class="form-control">
                                        <option value=""><?php esc_html_e('Select MOT Status', 'astra-child'); ?></option>
                                        <option value="Expired" <?php selected($mot_status, 'Expired'); ?>><?php esc_html_e('Expired', 'astra-child'); ?></option>
                                        <?php
                                        // Get current date
                                        $current_date = new DateTime();
                                        // Set to first day of current month
                                        $current_date->modify('first day of this month');
                                        // Create end date (2 years from now)
                                        $end_date = new DateTime();
                                        $end_date->modify('+2 years');
                                        $end_date->modify('last day of this month');

                                        // Generate options
                                        while ($current_date <= $end_date) {
                                            $value = $current_date->format('Y-m');
                                            $display = $current_date->format('F Y');
                                            echo '<option value="' . esc_attr($value) . '" ' . selected($mot_status, $value, false) . '>' . esc_html($display) . '</option>';
                                            $current_date->modify('+1 month');
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="numowners"><i class="fas fa-users"></i> <?php esc_html_e('Number of Owners', 'astra-child'); ?></label>
                                    <input type="number" id="numowners" name="numowners" class="form-control" min="1" max="99" value="<?php echo esc_attr($num_owners); ?>" required>
                                </div>

                                <div class="form-row">
                                    <div class="checkbox-field">
                                        <input type="checkbox" id="isantique" name="isantique" value="1" <?php checked($is_antique, '1'); ?>>
                                        <label for="isantique"><i class="fas fa-clock"></i> <?php esc_html_e('Written as antique', 'astra-child'); ?></label>
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
                                        
                                        foreach ($vehicle_history_options as $value => $label) {
                                            ?>
                                            <div class="vehicle-history-option">
                                                <input type="checkbox" 
                                                       id="vehiclehistory_<?php echo esc_attr($value); ?>" 
                                                       name="vehiclehistory[]" 
                                                       value="<?php echo esc_attr($value); ?>" 
                                                       <?php checked(in_array($value, (array)$vehicle_history), true); ?>>
                                                <label for="vehiclehistory_<?php echo esc_attr($value); ?>">
                                                    <?php echo esc_html($label); ?>
                                                </label>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section extras-section">
                                <h2><?php esc_html_e('Extras & Features', 'astra-child'); ?></h2>
                                <div class="form-row">
                                    <div class="vehicle-history-grid">
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
                                        
                                        foreach ($extras_options as $value => $label) {
                                            ?>
                                            <div class="vehicle-history-option">
                                                <input type="checkbox" 
                                                       id="extra_<?php echo esc_attr($value); ?>" 
                                                       name="extras[]" 
                                                       value="<?php echo esc_attr($value); ?>" 
                                                       <?php checked(in_array($value, (array)$extras), true); ?>>
                                                <label for="extra_<?php echo esc_attr($value); ?>">
                                                    <?php echo esc_html($label); ?>
                                                </label>
                                            </div>
                                            <?php
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

<?php
get_footer(); 