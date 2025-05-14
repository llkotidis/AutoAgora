<?php
/**
 * Template Name: Add Car Listing
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Ensure jQuery is loaded
wp_enqueue_script('jquery');

// Enqueue Font Awesome
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');

get_header(); ?>

<?php if ( astra_page_layout() == 'left-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

	<div id="primary" <?php astra_primary_class(); ?>>

		<?php astra_primary_content_top(); ?>

		<main id="main" class="site-main">
			<article class="post type-page status-publish ast-article-single page-add-listing">
				<div class="entry-content clear">
					<?php
					if ( is_user_logged_in() ) {
                        // Check for success message - use listing_submitted parameter from car-submission.php
                        if ( isset( $_GET['listing_submitted'] ) && $_GET['listing_submitted'] == 'success' ) {
                            ?>
                            <div class="listing-success-message">
                                <h2><?php esc_html_e( 'Your listing has been submitted successfully!', 'astra-child' ); ?></h2>
                                <p><?php esc_html_e( 'Thank you for submitting your car listing. It will be reviewed by our team and published soon.', 'astra-child' ); ?></p>
                                <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button"><?php esc_html_e( 'Return to Home', 'astra-child' ); ?></a></p>
                            </div>
                            <?php
                        } elseif ( isset( $_GET['listing_error'] ) ) {
                            ?>
                            <div class="listing-error-message">
                                <h2><?php esc_html_e( 'Submission Error', 'astra-child' ); ?></h2>
                                <p><?php esc_html_e( 'There was a problem with your submission. Please check all fields and try again.', 'astra-child' ); ?></p>
                            </div>
                            <h1><?php esc_html_e( 'Add New Car Listing', 'astra-child' ); ?></h1>
                            <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in «My Listings» on the top of the site.', 'astra-child' ); ?></p>
                            <?php
                        } elseif ( isset( $_GET['listing_errors'] ) ) {
                            ?>
                            <div class="listing-error-message">
                                <h2><?php esc_html_e( 'Submission Error', 'astra-child' ); ?></h2>
                                <p><?php echo esc_html( $_GET['listing_errors'] ); ?></p>
                            </div>
                            <h1><?php esc_html_e( 'Add New Car Listing', 'astra-child' ); ?></h1>
                            <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in «My Listings» on the top of the site.', 'astra-child' ); ?></p>
                            <?php
                        } else {
						?>
                        <h1><?php esc_html_e( 'Add New Car Listing', 'astra-child' ); ?></h1>
                        <p class="listing-note"><?php esc_html_e( 'Note: Duplicate listings will be flagged and removed. You can find all your ads in «My Listings» on the top of the site.', 'astra-child' ); ?></p>
                        
                        <?php
                        // Display error messages if any
                        if (isset($_GET['error']) && !empty($_GET['error'])) {
                            $error_messages = [];
                            
                            if ($_GET['error'] === 'validation') {
                                $error_messages[] = esc_html__('Please fill in all required fields', 'astra-child');
                                
                                // Check for specific validation errors
                                if (isset($_GET['fields']) && !empty($_GET['fields'])) {
                                    $missing_fields = explode(',', sanitize_text_field($_GET['fields']));
                                    echo '<div class="form-error-message">';
                                    echo '<p>' . esc_html__('The following fields are required:', 'astra-child') . '</p>';
                                    echo '<ul>';
                                    foreach ($missing_fields as $field) {
                                        echo '<li>' . esc_html(ucfirst(str_replace('_', ' ', $field))) . '</li>';
                                    }
                                    echo '</ul>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="form-error-message">';
                                    echo '<p>' . esc_html__('Please fill in all required fields.', 'astra-child') . '</p>';
                                    echo '</div>';
                                }
                            } elseif ($_GET['error'] === 'images') {
                                echo '<div class="form-error-message">';
                                echo '<p>' . esc_html__('Please upload at least one image for your listing.', 'astra-child') . '</p>';
                                echo '</div>';
                            } elseif ($_GET['error'] === 'post_creation') {
                                echo '<div class="form-error-message">';
                                echo '<p>' . esc_html__('There was a problem creating your listing. Please try again.', 'astra-child') . '</p>';
                                echo '</div>';
                            } elseif ($_GET['error'] === 'generic') {
                                echo '<div class="form-error-message">';
                                echo '<p>' . esc_html__('There was a problem with your submission. Please try again.', 'astra-child') . '</p>';
                                echo '</div>';
                            }
                        }
						
						// Get Makes data using PHP before the form
						$add_listing_makes = [];
						$add_listing_jsons_dir = get_stylesheet_directory() . '/simple_jsons/';
						if (is_dir($add_listing_jsons_dir)) {
							$add_listing_files = glob($add_listing_jsons_dir . '*.json');
							foreach ($add_listing_files as $add_listing_file) {
								$add_listing_json_content = file_get_contents($add_listing_file);
								if ($add_listing_json_content === false) continue; 
								$add_listing_data = json_decode($add_listing_json_content, true);
								if (json_last_error() !== JSON_ERROR_NONE) continue;
								if ($add_listing_data) {
									$make_name = array_key_first($add_listing_data);
									if ($make_name) {
										$add_listing_makes[$make_name] = $add_listing_data[$make_name];
									}
								}
							}
							// Sort by make name while preserving keys
							ksort($add_listing_makes);
						}
						// (Keep PHP error logs if desired for now)

						// Display the add listing form here
						?>
						<form id="add-car-listing-form" class="car-listing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'add_car_listing_nonce', 'add_car_listing_nonce' ); ?>
							<input type="hidden" name="action" value="add_new_car_listing">
							<input type="hidden" name="post_type" value="car">

							<div class="add-listing-main-row">
								<div class="add-listing-main-info-column">
									<div class="form-section basic-details-section">
										<h2><?php esc_html_e( 'Basic Details', 'astra-child' ); ?></h2>
										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="make"><i class="fas fa-car-side"></i> <?php esc_html_e( 'Make', 'astra-child' ); ?></label>
												<select id="make" name="make" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Make', 'astra-child' ); ?></option>
													<?php
													foreach ( $add_listing_makes as $make_name => $models ) {
														echo '<option value="' . esc_attr( $make_name ) . '">' . esc_html( $make_name ) . '</option>';
													}
													?>
												</select>
											</div>
											<div class="form-third">
												<label for="model"><i class="fas fa-car"></i> <?php esc_html_e( 'Model', 'astra-child' ); ?></label>
												<select id="model" name="model" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Model', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="variant"><i class="fas fa-car-side"></i> <?php esc_html_e( 'Variant', 'astra-child' ); ?></label>
												<select id="variant" name="variant" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Variant', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>

										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="year"><i class="far fa-calendar-alt"></i> <?php esc_html_e( 'Year', 'astra-child' ); ?></label>
												<select id="year" name="year" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Year', 'astra-child' ); ?></option>
													<?php
													for ($year = 2025; $year >= 1948; $year--) {
														echo '<option value="' . esc_attr($year) . '">' . esc_html($year) . '</option>';
													}
													?>
												</select>
											</div>
											<div class="form-third">
												<label for="mileage"><i class="fas fa-road"></i> <?php esc_html_e( 'Mileage', 'astra-child' ); ?></label>
												<div class="input-with-suffix">
													<input type="text" id="mileage" name="mileage" class="form-control" required>
													<span class="input-suffix">km</span>
												</div>
											</div>
											<div class="form-third">
												<label for="price"><i class="fas fa-euro-sign"></i> <?php esc_html_e( 'Price', 'astra-child' ); ?></label>
												<input type="text" id="price" name="price" class="form-control" required>
											</div>
										</div>

										<div class="form-row">
											<label for="car_city"><i class="fas fa-city"></i> <?php esc_html_e( 'City', 'astra-child' ); ?></label>
											<select id="car_city" name="car_city" class="form-control" required>
												<option value=""><?php esc_html_e( 'Select City', 'astra-child' ); ?></option>
												<option value="limassol"><?php esc_html_e( 'Limassol', 'astra-child' ); ?></option>
												<option value="nicosia"><?php esc_html_e( 'Nicosia', 'astra-child' ); ?></option>
												<option value="larnaca"><?php esc_html_e( 'Larnaca', 'astra-child' ); ?></option>
												<option value="paphos"><?php esc_html_e( 'Paphos', 'astra-child' ); ?></option>
												<option value="ayia_napa"><?php esc_html_e( 'Ayia Napa', 'astra-child' ); ?></option>
											</select>
										</div>

										<div class="form-row">
											<label for="car_district"><i class="fas fa-map-marker-alt"></i> <?php esc_html_e( 'District', 'astra-child' ); ?></label>
											<select id="car_district" name="car_district" class="form-control" required>
												<option value=""><?php esc_html_e( 'Select District', 'astra-child' ); ?></option>
											</select>
										</div>

										<div class="form-row">
											<label for="car_address"><i class="fas fa-map-pin"></i> <?php esc_html_e( 'Address', 'astra-child' ); ?></label>
											<input type="text" id="car_address" name="car_address" class="form-control" required>
										</div>

										<div class="form-row">
											<label><i class="fas fa-map"></i> <?php esc_html_e( 'Location on Map', 'astra-child' ); ?></label>
											<div id="car-map" style="height: 400px; width: 100%; margin-top: 10px;"></div>
											<input type="hidden" id="car_latitude" name="car_latitude" required>
											<input type="hidden" id="car_longitude" name="car_longitude" required>
										</div>

										<div class="form-row">
											<label for="availability"><i class="fas fa-truck"></i> <?php esc_html_e( 'Availability', 'astra-child' ); ?></label>
											<select id="availability" name="availability" class="form-control" required>
												<option value=""><?php esc_html_e( 'Select Availability', 'astra-child' ); ?></option>
												<option value="In Stock"><?php esc_html_e( 'In Stock', 'astra-child' ); ?></option>
												<option value="In Transit"><?php esc_html_e( 'In Transit', 'astra-child' ); ?></option>
											</select>
										</div>
									</div>

									<div class="form-section engine-performance-section">
										<h2><?php esc_html_e( 'Engine & Performance', 'astra-child' ); ?></h2>
										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="engine_capacity"><i class="fas fa-tachometer-alt"></i> <?php esc_html_e( 'Engine Capacity', 'astra-child' ); ?></label>
												<select id="engine_capacity" name="engine_capacity" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Engine Capacity', 'astra-child' ); ?></option>
													<?php
													for ($capacity = 0.4; $capacity <= 12.0; $capacity += 0.1) {
														$formatted_capacity = number_format($capacity, 1);
														echo '<option value="' . esc_attr($formatted_capacity) . '">' . esc_html($formatted_capacity) . '</option>';
													}
													?>
												</select>
											</div>
											<div class="form-third">
												<label for="fuel_type"><i class="fas fa-gas-pump"></i> <?php esc_html_e( 'Fuel Type', 'astra-child' ); ?></label>
												<select id="fuel_type" name="fuel_type" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Fuel Type', 'astra-child' ); ?></option>
													<option value="Petrol"><?php esc_html_e( 'Petrol', 'astra-child' ); ?></option>
													<option value="Diesel"><?php esc_html_e( 'Diesel', 'astra-child' ); ?></option>
													<option value="Electric"><?php esc_html_e( 'Electric', 'astra-child' ); ?></option>
													<option value="Petrol hybrid"><?php esc_html_e( 'Petrol hybrid', 'astra-child' ); ?></option>
													<option value="Diesel hybrid"><?php esc_html_e( 'Diesel hybrid', 'astra-child' ); ?></option>
													<option value="Plug-in petrol"><?php esc_html_e( 'Plug-in petrol', 'astra-child' ); ?></option>
													<option value="Plug-in diesel"><?php esc_html_e( 'Plug-in diesel', 'astra-child' ); ?></option>
													<option value="Bi Fuel"><?php esc_html_e( 'Bi Fuel', 'astra-child' ); ?></option>
													<option value="Hydrogen"><?php esc_html_e( 'Hydrogen', 'astra-child' ); ?></option>
													<option value="Natural Gas"><?php esc_html_e( 'Natural Gas', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="transmission"><i class="fas fa-cog"></i> <?php esc_html_e( 'Transmission', 'astra-child' ); ?></label>
												<select id="transmission" name="transmission" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Transmission', 'astra-child' ); ?></option>
													<option value="Automatic"><?php esc_html_e( 'Automatic', 'astra-child' ); ?></option>
													<option value="Manual"><?php esc_html_e( 'Manual', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>

										<div class="form-row form-row-halves">
											<div class="form-half">
												<label for="drive_type"><i class="fas fa-car-side"></i> <?php esc_html_e( 'Drive Type', 'astra-child' ); ?></label>
												<select id="drive_type" name="drive_type" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Drive Type', 'astra-child' ); ?></option>
													<option value="Front-Wheel Drive"><?php esc_html_e( 'Front-Wheel Drive', 'astra-child' ); ?></option>
													<option value="Rear-Wheel Drive"><?php esc_html_e( 'Rear-Wheel Drive', 'astra-child' ); ?></option>
													<option value="All-Wheel Drive"><?php esc_html_e( 'All-Wheel Drive', 'astra-child' ); ?></option>
													<option value="Four-Wheel Drive"><?php esc_html_e( 'Four-Wheel Drive', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-half">
												<label for="hp"><i class="fas fa-horse"></i> <?php esc_html_e( 'HorsePower (Optional)', 'astra-child' ); ?></label>
												<div class="input-with-suffix">
													<input type="text" id="hp" name="hp" class="form-control" min="0" step="1">
													<span class="input-suffix">HP</span>
												</div>
											</div>
										</div>
									</div>

									<div class="form-section body-design-section">
										<h2><?php esc_html_e( 'Body & Design', 'astra-child' ); ?></h2>
										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="body_type"><i class="fas fa-car-side"></i> <?php esc_html_e( 'Body Type', 'astra-child' ); ?></label>
												<select id="body_type" name="body_type" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Body Type', 'astra-child' ); ?></option>
													<option value="Hatchback"><?php esc_html_e( 'Hatchback', 'astra-child' ); ?></option>
													<option value="Saloon"><?php esc_html_e( 'Saloon', 'astra-child' ); ?></option>
													<option value="Coupe"><?php esc_html_e( 'Coupe', 'astra-child' ); ?></option>
													<option value="Convertible"><?php esc_html_e( 'Convertible', 'astra-child' ); ?></option>
													<option value="Estate"><?php esc_html_e( 'Estate', 'astra-child' ); ?></option>
													<option value="SUV"><?php esc_html_e( 'SUV', 'astra-child' ); ?></option>
													<option value="MPV"><?php esc_html_e( 'MPV', 'astra-child' ); ?></option>
													<option value="Pickup"><?php esc_html_e( 'Pickup', 'astra-child' ); ?></option>
													<option value="Camper"><?php esc_html_e( 'Camper', 'astra-child' ); ?></option>
													<option value="Minibus"><?php esc_html_e( 'Minibus', 'astra-child' ); ?></option>
													<option value="Limousine"><?php esc_html_e( 'Limousine', 'astra-child' ); ?></option>
													<option value="Car Derived Van"><?php esc_html_e( 'Car Derived Van', 'astra-child' ); ?></option>
													<option value="Combi Van"><?php esc_html_e( 'Combi Van', 'astra-child' ); ?></option>
													<option value="Panel Van"><?php esc_html_e( 'Panel Van', 'astra-child' ); ?></option>
													<option value="Window Van"><?php esc_html_e( 'Window Van', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="number_of_doors"><i class="fas fa-door-closed"></i> <?php esc_html_e( 'Number of Doors', 'astra-child' ); ?></label>
												<select id="number_of_doors" name="number_of_doors" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Number of Doors', 'astra-child' ); ?></option>
													<?php
													$door_options = array(0, 2, 3, 4, 5, 6, 7);
													foreach ($door_options as $doors) {
														echo '<option value="' . esc_attr($doors) . '">' . esc_html($doors) . '</option>';
													}
													?>
												</select>
											</div>
											<div class="form-third">
												<label for="number_of_seats"><i class="fas fa-chair"></i> <?php esc_html_e( 'Number of Seats', 'astra-child' ); ?></label>
												<select id="number_of_seats" name="number_of_seats" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Number of Seats', 'astra-child' ); ?></option>
													<?php
													$seat_options = array(1, 2, 3, 4, 5, 6, 7, 8);
													foreach ($seat_options as $seats) {
														echo '<option value="' . esc_attr($seats) . '">' . esc_html($seats) . '</option>';
													}
													?>
												</select>
											</div>
										</div>

										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="exterior_color"><i class="fas fa-palette"></i> <?php esc_html_e( 'Exterior Color', 'astra-child' ); ?></label>
												<select id="exterior_color" name="exterior_color" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Exterior Color', 'astra-child' ); ?></option>
													<option value="Black"><?php esc_html_e( 'Black', 'astra-child' ); ?></option>
													<option value="White"><?php esc_html_e( 'White', 'astra-child' ); ?></option>
													<option value="Silver"><?php esc_html_e( 'Silver', 'astra-child' ); ?></option>
													<option value="Gray"><?php esc_html_e( 'Gray', 'astra-child' ); ?></option>
													<option value="Red"><?php esc_html_e( 'Red', 'astra-child' ); ?></option>
													<option value="Blue"><?php esc_html_e( 'Blue', 'astra-child' ); ?></option>
													<option value="Green"><?php esc_html_e( 'Green', 'astra-child' ); ?></option>
													<option value="Yellow"><?php esc_html_e( 'Yellow', 'astra-child' ); ?></option>
													<option value="Brown"><?php esc_html_e( 'Brown', 'astra-child' ); ?></option>
													<option value="Beige"><?php esc_html_e( 'Beige', 'astra-child' ); ?></option>
													<option value="Orange"><?php esc_html_e( 'Orange', 'astra-child' ); ?></option>
													<option value="Purple"><?php esc_html_e( 'Purple', 'astra-child' ); ?></option>
													<option value="Gold"><?php esc_html_e( 'Gold', 'astra-child' ); ?></option>
													<option value="Bronze"><?php esc_html_e( 'Bronze', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="interior_color"><i class="fas fa-palette"></i> <?php esc_html_e( 'Interior Color', 'astra-child' ); ?></label>
												<select id="interior_color" name="interior_color" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Interior Color', 'astra-child' ); ?></option>
													<option value="Black"><?php esc_html_e( 'Black', 'astra-child' ); ?></option>
													<option value="Gray"><?php esc_html_e( 'Gray', 'astra-child' ); ?></option>
													<option value="Beige"><?php esc_html_e( 'Beige', 'astra-child' ); ?></option>
													<option value="Brown"><?php esc_html_e( 'Brown', 'astra-child' ); ?></option>
													<option value="White"><?php esc_html_e( 'White', 'astra-child' ); ?></option>
													<option value="Red"><?php esc_html_e( 'Red', 'astra-child' ); ?></option>
													<option value="Blue"><?php esc_html_e( 'Blue', 'astra-child' ); ?></option>
													<option value="Tan"><?php esc_html_e( 'Tan', 'astra-child' ); ?></option>
													<option value="Cream"><?php esc_html_e( 'Cream', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>
									</div>

									<div class="form-section mot-section">
										<h2><?php esc_html_e( 'Registration & Background Info', 'astra-child' ); ?></h2>
										<div class="form-row">
											<label for="motuntil"><i class="fas fa-clipboard-check"></i> <?php esc_html_e( 'MOT Status', 'astra-child' ); ?></label>
											<select id="motuntil" name="motuntil" class="form-control">
												<option value=""><?php esc_html_e( 'Select MOT Status', 'astra-child' ); ?></option>
												<option value="Expired"><?php esc_html_e( 'Expired', 'astra-child' ); ?></option>
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
													echo '<option value="' . esc_attr($value) . '">' . esc_html($display) . '</option>';
													$current_date->modify('+1 month');
												}
												?>
											</select>
										</div>

										<div class="form-row">
											<label for="numowners"><i class="fas fa-users"></i> <?php esc_html_e( 'Number of Owners', 'astra-child' ); ?></label>
											<input type="number" id="numowners" name="numowners" class="form-control" min="1" max="99" required>
										</div>

										<div class="form-row">
											<div class="checkbox-field">
												<input type="checkbox" id="isantique" name="isantique" value="1">
												<label for="isantique"><i class="fas fa-clock"></i> <?php esc_html_e( 'Written as antique', 'astra-child' ); ?></label>
											</div>
										</div>

										<div class="form-row">
											<label><i class="fas fa-history"></i> <?php esc_html_e( 'Vehicle History', 'astra-child' ); ?></label>
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
													echo '<div class="vehicle-history-option">';
													echo '<input type="checkbox" id="vehiclehistory_' . esc_attr($value) . '" name="vehiclehistory[]" value="' . esc_attr($value) . '">';
													echo '<label for="vehiclehistory_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
													echo '</div>';
												}
												?>
											</div>
										</div>
									</div>

									<div class="form-section extras-section">
										<h2><?php esc_html_e( 'Extras (Optional)', 'astra-child' ); ?></h2>
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
												foreach ($extras_options as $value => $label) {
													echo '<div class="extra-option">';
													echo '<input type="checkbox" id="extra_' . esc_attr($value) . '" name="extras[]" value="' . esc_attr($value) . '">';
													echo '<label for="extra_' . esc_attr($value) . '">' . esc_html($label) . '</label>';
													echo '</div>';
												}
												?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="add-listing-description-section">
								<h2><?php esc_html_e( 'Description', 'astra-child' ); ?></h2>
								<p class="description-guidelines-green"><?php esc_html_e( 'Focus on condition, upgrades, or unique features.', 'astra-child' ); ?></p>
								<p class="description-guidelines-avoid"><?php esc_html_e( '❗ Avoid:', 'astra-child' ); ?></p>
								<ul class="description-guidelines-list">
									<li><?php esc_html_e( 'Contact info', 'astra-child' ); ?></li>
									<li><?php esc_html_e( 'Specs already filled in fields', 'astra-child' ); ?></li>
									<li><?php esc_html_e( 'Repetitive or irrelevant details', 'astra-child' ); ?></li>
								</ul>
								<div class="form-row">
									<textarea id="description" name="description" class="form-control" rows="5" required></textarea>
								</div>
							</div>

							<div class="add-listing-images-section">
								<h2><?php esc_html_e( 'Upload Images', 'astra-child' ); ?></h2>
								<p class="image-upload-info"><?php esc_html_e( 'Hold CTRL to choose several photos. Maximum 25 images per listing. Maximum file size is 5MB, the formats are .jpg, .jpeg, .png, .gif, .webp', 'astra-child' ); ?></p>
								<p class="image-upload-note"><?php esc_html_e( 'Note: ads with good photos get more attention', 'astra-child' ); ?></p>
								<div class="image-upload-container">
									<div class="file-upload-area" id="file-upload-area" role="button" tabindex="0">
										<div class="upload-message">
											<i class="fas fa-cloud-upload-alt"></i>
											<p><?php esc_html_e( 'Drag & Drop Images Here', 'astra-child' ); ?></p>
											<p class="small"><?php esc_html_e( 'or click to select files', 'astra-child' ); ?></p>
										</div>
									</div>
									<input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" required>
									<div id="image-preview" class="image-preview"></div>
								</div>
							</div>

							<div class="form-row">
								<button type="submit" class="submit-button gradient-button"><?php esc_html_e( 'Submit Listing', 'astra-child' ); ?></button>
							</div>
						</form>

						<script>
						// Debug check for jQuery
						console.log('[Add Listing] jQuery version:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'not loaded');
						
						jQuery(document).ready(function($) {
							console.log('[Add Listing] jQuery ready');
							
							// Store the makes data
							const makesData = <?php echo json_encode($add_listing_makes); ?>;
							
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
										if (variant) { // Only add non-empty variants
											variantSelect.append(`<option value="${variant}">${variant}</option>`);
										}
									});
								}
							});
							
							const fileInput = $('#car_images');
							const fileUploadArea = $('#file-upload-area');
							const imagePreview = $('#image-preview');
							
							// Add mileage formatting
							const mileageInput = $('#mileage');
							const priceInput = $('#price');
							
							// Format number with commas
							function formatNumber(number) {
								return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
							}
							
							// Remove commas and convert to number
							function unformatNumber(formattedNumber) {
								return parseInt(formattedNumber.replace(/[^0-9]/g, '')) || 0;
							}
							
							// Format mileage with commas
							mileageInput.on('input', function(e) {
								let value = this.value.replace(/[km,]/g, '');
								value = value.replace(/[^\d]/g, '');
								if (value) {
									const formattedValue = formatNumber(value);
									this.value = formattedValue;
									$(this).data('raw-value', value);
								} else {
									this.value = '';
									$(this).data('raw-value', 0);
								}
							});

							// Handle price input event for real-time formatting
							priceInput.on('input', function(e) {
								// Get the raw value without commas and euro sign
								let value = this.value.replace(/[€,]/g, '');
								
								// Only allow numbers
								value = value.replace(/[^\d]/g, '');
								
								// Format with commas and euro sign
								if (value) {
									const formattedValue = '€' + formatNumber(value);
									
									// Update the display value
									this.value = formattedValue;
									
									// Store the raw value in a data attribute
									$(this).data('raw-value', unformatNumber(value));
								} else {
									this.value = '';
									$(this).data('raw-value', 0);
								}
							});
							
							// Format HP input with thousand separators
							$('#hp').on('input', function() {
								// Remove any non-digit characters
								let value = $(this).val().replace(/[^\d]/g, '');
								
								// Add thousand separators
								if (value.length > 0) {
									value = parseInt(value).toLocaleString();
								}
								
								// Update the input value
								$(this).val(value);
								// Store the raw value
								$(this).data('raw-value', value.replace(/[^\d]/g, ''));
							});

							// Handle form submission to use raw values
							$('#add-car-listing-form').on('submit', function(e) {
								// Get the raw values from data attributes
								const rawMileage = mileageInput.data('raw-value') || unformatNumber(mileageInput.val());
								const rawPrice = priceInput.data('raw-value') || unformatNumber(priceInput.val().replace('€', ''));
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
								
								// Remove the original inputs from submission
								mileageInput.prop('disabled', true);
								priceInput.prop('disabled', true);
								$('#hp').prop('disabled', true);
							});
							
							console.log('[Add Listing] Elements found:', {
								fileInput: fileInput.length,
								fileUploadArea: fileUploadArea.length,
								imagePreview: imagePreview.length
							});
							
							// Handle click on upload area
							fileUploadArea.on('click', function(e) {
								console.log('[Add Listing] Upload area clicked');
								fileInput.trigger('click');
							});
							
							// Handle when files are selected through the file dialog
							fileInput.on('change', function(e) {
								console.log('[Add Listing] Files selected through file dialog:', this.files.length);
								if (this.files.length > 0) {
									// For file dialog selection, we want to replace the current files
									handleFiles(this.files, true);
								}
							});
							
							// Handle drag and drop
							fileUploadArea.on('dragover', function(e) {
								e.preventDefault();
								$(this).addClass('dragover');
							});
							
							fileUploadArea.on('dragleave', function(e) {
								e.preventDefault();
								$(this).removeClass('dragover');
							});
							
							fileUploadArea.on('drop', function(e) {
								e.preventDefault();
								$(this).removeClass('dragover');
								console.log('[Add Listing] Files dropped:', e.originalEvent.dataTransfer.files.length);
								// For drag and drop, we want to append to existing files
								handleFiles(e.originalEvent.dataTransfer.files, false);
							});
							
							// Process the files - common function for both methods
							function handleFiles(files, isFileDialog) {
								console.log('[Add Listing] Processing', files.length, 'files, isFileDialog:', isFileDialog);
								
								const maxFiles = 25;
								const maxFileSize = 5 * 1024 * 1024; // 5MB
								
								// Get current files from input
								const currentFiles = isFileDialog ? [] : Array.from(fileInput[0].files);
								console.log('[Add Listing] Current files:', currentFiles.length);
								
								// Check if too many files
								if (currentFiles.length + files.length > maxFiles) {
									alert('Maximum ' + maxFiles + ' files allowed');
									return;
								}
								
								// Create a DataTransfer object to manage files
								const dataTransfer = new DataTransfer();
								
								// Add existing files first (only for drag and drop)
								if (!isFileDialog) {
									currentFiles.forEach(file => {
										dataTransfer.items.add(file);
									});
								}
								
								// Process each new file
								Array.from(files).forEach(file => {
									// Check if duplicate (only for drag and drop)
									if (!isFileDialog) {
										const isDuplicate = currentFiles.some(
											existingFile => existingFile.name === file.name && existingFile.size === file.size
										);
										
										if (isDuplicate) {
											console.log('[Add Listing] Skipping duplicate file:', file.name);
											return; // Skip this file
										}
									}
									
									// Validate file type
									if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
										alert('Only JPG, PNG, GIF, and WebP files are allowed');
										return; // Skip this file
									}
									
									// Validate file size
									if (file.size > maxFileSize) {
										alert('File size must be less than 5MB');
										return; // Skip this file
									}
									
									// Add valid file to our collection
									dataTransfer.items.add(file);
									
									// Create preview for this file
									createPreviewForFile(file);
								});
								
								// Update the file input with all files
								fileInput[0].files = dataTransfer.files;
								console.log('[Add Listing] Updated file input, now has', fileInput[0].files.length, 'files');
							}
							
							// Create preview for a single file
							function createPreviewForFile(file) {
								console.log('[Add Listing] Creating preview for:', file.name);
								
								// Create a FileReader to read the image
								const reader = new FileReader();
								
								reader.onload = function(e) {
									console.log('[Add Listing] File read complete, creating preview');
									
									// Create preview container
									const previewItem = $('<div>').addClass('image-preview-item');
									
									// Create image element
									const img = $('<img>').attr({
										'src': e.target.result,
										'alt': file.name
									});
									
									// Create remove button
									const removeBtn = $('<div>').addClass('remove-image')
										.html('<i class="fas fa-times"></i>')
										.on('click', function() {
											removeFile(file.name);
											previewItem.remove();
										});
									
									// Add image and button to preview item
									previewItem.append(img).append(removeBtn);
									
									// Add to preview container
									imagePreview.append(previewItem);
									console.log('[Add Listing] Preview added for:', file.name);
								};
								
								reader.onerror = function() {
									console.error('[Add Listing] Error reading file:', file.name);
								};
								
								// Start reading the file
								reader.readAsDataURL(file);
							}
							
							// Remove a file by name
							function removeFile(fileName) {
								console.log('[Add Listing] Removing file:', fileName);
								
								const dataTransfer = new DataTransfer();
								const currentFiles = Array.from(fileInput[0].files);
								
								// Add all files except the one to remove
								currentFiles.forEach(file => {
									if (file.name !== fileName) {
										dataTransfer.items.add(file);
									}
								});
								
								// Update the file input
								fileInput[0].files = dataTransfer.files;
								console.log('[Add Listing] After removal, file input has', fileInput[0].files.length, 'files');
							}
						});
						</script>
						<?php
                        }
					} else {
						$login_url = wp_login_url( get_permalink() );
						$register_page = get_page_by_path( 'register' ); // Assuming you have a 'register' page

						echo '<div class="login-required-message">';
						echo '<h1>' . esc_html__( 'Please Log in to Submit a Car Listing', 'astra-child' ) . '</h1>';
						echo '<p>';
						echo '<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log In', 'astra-child' ) . '</a>';

						if ( $register_page ) {
							echo ' | <a href="' . esc_url( get_permalink( $register_page->ID ) ) . '">' . esc_html__( 'Register', 'astra-child' ) . '</a>';
						}
						echo '</p>';
						echo '</div>';
					}
					?>
				</div>
			</article>
		</main>

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( astra_page_layout() == 'right-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

<?php get_footer(); ?>