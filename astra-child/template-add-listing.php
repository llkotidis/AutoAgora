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
                            <?php
                        } elseif ( isset( $_GET['listing_errors'] ) ) {
                            ?>
                            <div class="listing-error-message">
                                <h2><?php esc_html_e( 'Submission Error', 'astra-child' ); ?></h2>
                                <p><?php echo esc_html( $_GET['listing_errors'] ); ?></p>
                            </div>
                            <h1><?php esc_html_e( 'Add New Car Listing', 'astra-child' ); ?></h1>
                            <?php
                        } else {
						?>
                        <h1><?php esc_html_e( 'Add New Car Listing', 'astra-child' ); ?></h1>
                        
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
												<label for="make"><?php esc_html_e( 'Make', 'astra-child' ); ?> *</label>
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
												<label for="model"><?php esc_html_e( 'Model', 'astra-child' ); ?> *</label>
												<select id="model" name="model" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Model', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="variant"><?php esc_html_e( 'Variant', 'astra-child' ); ?> *</label>
												<select id="variant" name="variant" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Variant', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>

										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="year"><?php esc_html_e( 'Year', 'astra-child' ); ?> *</label>
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
												<label for="mileage"><?php esc_html_e( 'Mileage (km)', 'astra-child' ); ?> *</label>
												<input type="number" id="mileage" name="mileage" min="0" class="form-control" required>
											</div>
											<div class="form-third">
												<label for="price"><?php esc_html_e( 'Price (€)', 'astra-child' ); ?> *</label>
												<input type="number" id="price" name="price" min="0" class="form-control" required>
											</div>
										</div>

										<div class="form-row">
											<label for="location"><?php esc_html_e( 'Location', 'astra-child' ); ?> *</label>
											<input type="text" id="location" name="location" class="form-control" required>
										</div>
									</div>

									<div class="form-section engine-performance-section">
										<h2><?php esc_html_e( 'Engine & Performance', 'astra-child' ); ?></h2>
										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="engine_capacity"><?php esc_html_e( 'Engine Capacity', 'astra-child' ); ?> *</label>
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
												<label for="fuel_type"><?php esc_html_e( 'Fuel Type', 'astra-child' ); ?> *</label>
												<select id="fuel_type" name="fuel_type" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Fuel Type', 'astra-child' ); ?></option>
													<option value="Petrol"><?php esc_html_e( 'Petrol', 'astra-child' ); ?></option>
													<option value="Diesel"><?php esc_html_e( 'Diesel', 'astra-child' ); ?></option>
													<option value="Electric"><?php esc_html_e( 'Electric', 'astra-child' ); ?></option>
													<option value="Hybrid"><?php esc_html_e( 'Hybrid', 'astra-child' ); ?></option>
													<option value="Plug-in Hybrid"><?php esc_html_e( 'Plug-in Hybrid', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="transmission"><?php esc_html_e( 'Transmission', 'astra-child' ); ?> *</label>
												<select id="transmission" name="transmission" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Transmission', 'astra-child' ); ?></option>
													<option value="Automatic"><?php esc_html_e( 'Automatic', 'astra-child' ); ?></option>
													<option value="Manual"><?php esc_html_e( 'Manual', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>

										<div class="form-row">
											<label for="drive_type"><?php esc_html_e( 'Drive Type', 'astra-child' ); ?> *</label>
											<select id="drive_type" name="drive_type" class="form-control" required>
												<option value=""><?php esc_html_e( 'Select Drive Type', 'astra-child' ); ?></option>
												<option value="Front-Wheel Drive"><?php esc_html_e( 'Front-Wheel Drive', 'astra-child' ); ?></option>
												<option value="Rear-Wheel Drive"><?php esc_html_e( 'Rear-Wheel Drive', 'astra-child' ); ?></option>
												<option value="All-Wheel Drive"><?php esc_html_e( 'All-Wheel Drive', 'astra-child' ); ?></option>
												<option value="Four-Wheel Drive"><?php esc_html_e( 'Four-Wheel Drive', 'astra-child' ); ?></option>
											</select>
										</div>
									</div>

									<div class="form-section body-design-section">
										<h2><?php esc_html_e( 'Body & Design', 'astra-child' ); ?></h2>
										<div class="form-row form-row-thirds">
											<div class="form-third">
												<label for="body_type"><?php esc_html_e( 'Body Type', 'astra-child' ); ?> *</label>
												<select id="body_type" name="body_type" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Body Type', 'astra-child' ); ?></option>
													<option value="Sedan"><?php esc_html_e( 'Sedan', 'astra-child' ); ?></option>
													<option value="Hatchback"><?php esc_html_e( 'Hatchback', 'astra-child' ); ?></option>
													<option value="SUV"><?php esc_html_e( 'SUV', 'astra-child' ); ?></option>
													<option value="Crossover"><?php esc_html_e( 'Crossover', 'astra-child' ); ?></option>
													<option value="Coupe"><?php esc_html_e( 'Coupe', 'astra-child' ); ?></option>
													<option value="Convertible"><?php esc_html_e( 'Convertible', 'astra-child' ); ?></option>
													<option value="Wagon"><?php esc_html_e( 'Wagon', 'astra-child' ); ?></option>
													<option value="Van"><?php esc_html_e( 'Van', 'astra-child' ); ?></option>
													<option value="Pickup"><?php esc_html_e( 'Pickup', 'astra-child' ); ?></option>
													<option value="Minivan"><?php esc_html_e( 'Minivan', 'astra-child' ); ?></option>
													<option value="Sports Car"><?php esc_html_e( 'Sports Car', 'astra-child' ); ?></option>
													<option value="Luxury Car"><?php esc_html_e( 'Luxury Car', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="exterior_color"><?php esc_html_e( 'Exterior Color', 'astra-child' ); ?> *</label>
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
													<option value="Orange"><?php esc_html_e( 'Orange', 'astra-child' ); ?></option>
													<option value="Brown"><?php esc_html_e( 'Brown', 'astra-child' ); ?></option>
													<option value="Beige"><?php esc_html_e( 'Beige', 'astra-child' ); ?></option>
													<option value="Purple"><?php esc_html_e( 'Purple', 'astra-child' ); ?></option>
													<option value="Pink"><?php esc_html_e( 'Pink', 'astra-child' ); ?></option>
													<option value="Gold"><?php esc_html_e( 'Gold', 'astra-child' ); ?></option>
													<option value="Bronze"><?php esc_html_e( 'Bronze', 'astra-child' ); ?></option>
												</select>
											</div>
											<div class="form-third">
												<label for="interior_color"><?php esc_html_e( 'Interior Color', 'astra-child' ); ?> *</label>
												<select id="interior_color" name="interior_color" class="form-control" required>
													<option value=""><?php esc_html_e( 'Select Interior Color', 'astra-child' ); ?></option>
													<option value="Black"><?php esc_html_e( 'Black', 'astra-child' ); ?></option>
													<option value="White"><?php esc_html_e( 'White', 'astra-child' ); ?></option>
													<option value="Gray"><?php esc_html_e( 'Gray', 'astra-child' ); ?></option>
													<option value="Beige"><?php esc_html_e( 'Beige', 'astra-child' ); ?></option>
													<option value="Brown"><?php esc_html_e( 'Brown', 'astra-child' ); ?></option>
													<option value="Red"><?php esc_html_e( 'Red', 'astra-child' ); ?></option>
													<option value="Blue"><?php esc_html_e( 'Blue', 'astra-child' ); ?></option>
													<option value="Green"><?php esc_html_e( 'Green', 'astra-child' ); ?></option>
													<option value="Yellow"><?php esc_html_e( 'Yellow', 'astra-child' ); ?></option>
													<option value="Orange"><?php esc_html_e( 'Orange', 'astra-child' ); ?></option>
													<option value="Purple"><?php esc_html_e( 'Purple', 'astra-child' ); ?></option>
													<option value="Pink"><?php esc_html_e( 'Pink', 'astra-child' ); ?></option>
													<option value="Gold"><?php esc_html_e( 'Gold', 'astra-child' ); ?></option>
													<option value="Bronze"><?php esc_html_e( 'Bronze', 'astra-child' ); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="add-listing-description-section">
								<h2><?php esc_html_e( 'Description', 'astra-child' ); ?></h2>
								<div class="form-row">
									<textarea id="description" name="description" class="form-control" rows="5" required></textarea>
								</div>
							</div>

							<div class="add-listing-images-section">
								<h2><?php esc_html_e( 'Upload Images', 'astra-child' ); ?></h2>
								<div class="form-row image-upload-container">
									<div class="file-upload-area" id="file-upload-area">
										<div class="upload-message">
											<i class="fas fa-cloud-upload-alt"></i>
											<p><?php esc_html_e( 'Drag & drop images here or click to upload', 'astra-child' ); ?></p>
											<p class="small"><?php esc_html_e( 'Supported formats: JPG, PNG, GIF, WebP', 'astra-child' ); ?></p>
										</div>
										<input type="file" id="car_images" name="car_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" required>
									</div>
									<div id="image-preview" class="image-preview"></div>
								</div>
							</div>

							<div class="form-row">
								<button type="submit" class="submit-button gradient-button"><?php esc_html_e( 'Submit Listing', 'astra-child' ); ?></button>
							</div>
						</form>

						<script>
						// Standalone JavaScript for debugging and make/model/variant selection
						document.addEventListener('DOMContentLoaded', function() {
							console.log("[Add Listing] DOM content loaded");
							
							// SIMPLIFIED FILE UPLOAD HANDLER - Start with vanilla JavaScript only
							// The file input element
							const fileInput = document.getElementById('car_images');
							// The area to click or drop files onto
							const fileUploadArea = document.getElementById('file-upload-area');
							// Where image previews will appear
							const imagePreview = document.getElementById('image-preview');
							
							// Handle click on upload area - simple direct approach
							fileUploadArea.addEventListener('click', function(e) {
								console.log('[Add Listing] Upload area clicked');
								fileInput.click();
							});
							
							// Handle when files are selected through the file dialog
							fileInput.addEventListener('change', function(e) {
								console.log('[Add Listing] Files selected through file dialog:', this.files.length);
								handleFiles(this.files);
							});
							
							// Handle drag and drop
							fileUploadArea.addEventListener('dragover', function(e) {
								e.preventDefault();
								this.classList.add('dragover');
							});
							
							fileUploadArea.addEventListener('dragleave', function(e) {
								e.preventDefault();
								this.classList.remove('dragover');
							});
							
							fileUploadArea.addEventListener('drop', function(e) {
								e.preventDefault();
								this.classList.remove('dragover');
								console.log('[Add Listing] Files dropped:', e.dataTransfer.files.length);
								handleFiles(e.dataTransfer.files);
							});
							
							// Process the files - common function for both methods
							function handleFiles(files) {
								console.log('[Add Listing] Processing', files.length, 'files');
								
								const maxFiles = 10;
								const maxFileSize = 5 * 1024 * 1024; // 5MB
								
								// Check if too many files
								if (fileInput.files.length + files.length > maxFiles) {
									alert('Maximum ' + maxFiles + ' files allowed');
									return;
								}
								
								// Create a DataTransfer object to manage files
								const dataTransfer = new DataTransfer();
								
								// Add existing files first
								for (let i = 0; i < fileInput.files.length; i++) {
									dataTransfer.items.add(fileInput.files[i]);
								}
								
								// Process each new file
								for (let i = 0; i < files.length; i++) {
									const file = files[i];
									
									// Check if duplicate
									let isDuplicate = false;
									for (let j = 0; j < fileInput.files.length; j++) {
										if (fileInput.files[j].name === file.name && 
											fileInput.files[j].size === file.size) {
											isDuplicate = true;
											break;
										}
									}
									
									if (isDuplicate) {
										console.log('[Add Listing] Skipping duplicate file:', file.name);
										continue;
									}
									
									// Validate file type
									if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
										alert('Only JPG, PNG, GIF, and WebP files are allowed');
										continue;
									}
									
									// Validate file size
									if (file.size > maxFileSize) {
										alert('File size must be less than 5MB');
										continue;
									}
									
									// Add valid file to our collection
									dataTransfer.items.add(file);
									
									// Create preview for this file
									createPreviewForFile(file);
								}
								
								// Update the file input with all files
								fileInput.files = dataTransfer.files;
								console.log('[Add Listing] Updated file input, now has', fileInput.files.length, 'files');
							}
							
							// Create preview for a single file
							function createPreviewForFile(file) {
								console.log('[Add Listing] Creating preview for:', file.name);
								
								// Create a FileReader to read the image
								const reader = new FileReader();
								
								reader.onload = function(e) {
									// Create preview container
									const previewItem = document.createElement('div');
									previewItem.className = 'image-preview-item';
									
									// Create image element
									const img = document.createElement('img');
									img.src = e.target.result;
									img.alt = file.name;
									
									// Create remove button
									const removeBtn = document.createElement('div');
									removeBtn.className = 'remove-image';
									removeBtn.innerHTML = '<i class="fas fa-times"></i>';
									
									// Add click handler to remove button
									removeBtn.addEventListener('click', function() {
										removeFile(file.name);
										previewItem.remove();
									});
									
									// Add image and button to preview item
									previewItem.appendChild(img);
									previewItem.appendChild(removeBtn);
									
									// Add to preview container
									imagePreview.appendChild(previewItem);
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
								
								// Add all files except the one to remove
								for (let i = 0; i < fileInput.files.length; i++) {
									if (fileInput.files[i].name !== fileName) {
										dataTransfer.items.add(fileInput.files[i]);
									}
								}
								
								// Update the file input
								fileInput.files = dataTransfer.files;
								console.log('[Add Listing] File removed, now has', fileInput.files.length, 'files');
							}
							
							// Use vanilla JS selectors for consistency with search form logic
							const makeSelect = document.getElementById('make');
							const modelSelect = document.getElementById('model');
							const variantSelect = document.getElementById('variant');
							const carData = {};
							
							// Form validation and submission handling
							const form = document.getElementById('add-car-listing-form');
							if (form) {
								form.addEventListener('submit', function(e) {
									console.log("[Add Listing] Form submission started");
									
									// Basic form validation
									let isValid = true;
									const requiredFields = form.querySelectorAll('[required]');
									const missingFields = [];
									
									requiredFields.forEach(field => {
										// Check if it's a file input with no files
										if (field.type === 'file' && field.files.length === 0) {
											isValid = false;
											field.classList.add('error');
											missingFields.push(field.name);
											console.log("[Add Listing] Missing files for:", field.name);
										}
										// Check if other fields are empty
										else if (!field.value.trim()) {
											isValid = false;
											field.classList.add('error');
											missingFields.push(field.name);
											console.log("[Add Listing] Empty required field:", field.name);
										} else {
											field.classList.remove('error');
										}
									});
									
									if (!isValid) {
										e.preventDefault();
										alert('Please fill in all required fields: ' + missingFields.join(', '));
										console.log("[Add Listing] Form validation failed");
										return false;
									}
									
									console.log("[Add Listing] Form validation passed");
									return true;
								});
							}
							
							// Load all JSON data
							<?php 
							$json_files = glob(get_stylesheet_directory() . '/simple_jsons/*.json');
							foreach ($json_files as $file): 
								$json_data = json_decode(file_get_contents($file), true);
								if ($json_data) {
									$make = key($json_data);
									echo "carData['" . esc_js($make) . "'] = " . json_encode($json_data[$make]) . ";\n";
								}
							endforeach; 
							?>

							// --- JS DEBUG --- 
							console.log("[Add Listing JS Debug] carData loaded from JSON files:", carData);
							console.log("[Add Listing JS Debug] Available makes:", Object.keys(carData));
							// --- END JS DEBUG ---

							// --- JS DEBUG --- 
							console.log("[Add Listing JS Debug] Found #make element:", makeSelect);
							console.log("[Add Listing JS Debug] Found #model element:", modelSelect);
							console.log("[Add Listing JS Debug] Found #variant element:", variantSelect);
							// --- END JS DEBUG ---


							// --- Vanilla JS Event Listeners (like search form) ---

							// Update models when make changes
							if (makeSelect) { 
								makeSelect.addEventListener('change', function() {
									const make = this.value;
									console.log('[Add Listing] Make changed. Value:', make);
									console.log('[Add Listing] Make option display text:', this.options[this.selectedIndex].text);
									
									// Clear previous options
									modelSelect.innerHTML = '<option value="">Select Model</option>';
									variantSelect.innerHTML = '<option value="">Select Variant</option>'; 
									
									// CASE SENSITIVITY FIX - Try to find a case-insensitive match
									let matchedMake = null;
									if (make) {
										// First try exact match
										if (carData[make]) {
											matchedMake = make;
										} else {
											// Try case-insensitive match if exact match fails
											const makeLowerCase = make.toLowerCase();
											for (const key in carData) {
												if (key.toLowerCase() === makeLowerCase) {
													matchedMake = key;
													console.log('[Add Listing] Found case-insensitive match. Selected:', make, 'Matched:', matchedMake);
													break;
												}
											}
										}
									}
									
									if (matchedMake) {
										console.log('[Add Listing] Models available for', matchedMake, ':', Object.keys(carData[matchedMake]));
										modelSelect.disabled = false;
										Object.keys(carData[matchedMake]).forEach(model => {
											const option = document.createElement('option');
											option.value = model;
											option.textContent = model;
											modelSelect.appendChild(option);
										});
									} else {
										modelSelect.disabled = true;
										variantSelect.disabled = true;
										if (make) {
											console.error("[Add Listing] No data found in carData for make:", make);
											console.log("[Add Listing] Available makes are:", Object.keys(carData));
										}
									}
								});
							}

							// Update variants when model changes
							if (modelSelect) {
								modelSelect.addEventListener('change', function() {
									const make = makeSelect.value;
									const model = this.value;
									console.log("[Add Listing] Model changed. Selected Make:", make, "Selected Model:", model);
									
									// Clear previous options
									variantSelect.innerHTML = '<option value="">Select Variant</option>';
									
									// CASE SENSITIVITY FIX - Try to find a case-insensitive match for make
									let matchedMake = null;
									if (make) {
										// First try exact match
										if (carData[make]) {
											matchedMake = make;
										} else {
											// Try case-insensitive match if exact match fails
											const makeLowerCase = make.toLowerCase();
											for (const key in carData) {
												if (key.toLowerCase() === makeLowerCase) {
													matchedMake = key;
													break;
												}
											}
										}
									}
									
									if (matchedMake && model && carData[matchedMake][model]) {
										console.log('[Add Listing] Variants available for', model, ':', carData[matchedMake][model]);
										variantSelect.disabled = false;
										carData[matchedMake][model].forEach(variant => {
											const option = document.createElement('option');
											option.value = variant;
											option.textContent = variant;
											variantSelect.appendChild(option);
										});
									} else {
										variantSelect.disabled = true;
										if (make && model) {
											console.error("[Add Listing] No variant data found. Make:", make, "Model:", model);
											if (matchedMake) {
												console.log("[Add Listing] Models available for", matchedMake, ":", Object.keys(carData[matchedMake]));
											}
										}
									}
								});
							}
						});

						// Check if jQuery is properly loaded before running jQuery-dependent code
						function initJQueryFeatures() {
							if (typeof jQuery === 'undefined') {
								console.error('[Add Listing] jQuery is not loaded! File upload handling will not work.');
								// Try again after a short delay
								setTimeout(initJQueryFeatures, 500);
								return;
							}
							
							console.log('[Add Listing] jQuery is loaded, initializing file upload handling');
							
							jQuery(document).ready(function($) {
								// --- jQuery File upload handling --- 
								const fileInput = $('#car_images');
								const fileUploadArea = $('#file-upload-area');
								const imagePreview = $('#image-preview');
								const maxFiles = 10;
								const maxFileSize = 5 * 1024 * 1024; // 5MB

								// DO NOT ADD ANY CLICK HANDLERS HERE - they are now in vanilla JS above
								
								// Handle drag and drop
								fileUploadArea.on('dragover', function(e) {
									e.preventDefault();
									$(this).addClass('dragover');
								}).on('dragleave', function(e) {
									e.preventDefault();
									$(this).removeClass('dragover');
								}).on('drop', function(e) {
									e.preventDefault();
									$(this).removeClass('dragover');
									
									const files = e.originalEvent.dataTransfer.files;
									handleFiles(files);
								});

								// jQuery handler for file input change
								fileInput.on('change', function() {
									console.log('[Add Listing] jQuery detected file input change');
									handleFiles(this.files);
								});

								// The handleFiles function (which uses jQuery internally for preview)
								function handleFiles(files) {
									console.log('[Add Listing] handleFiles called with', files.length, 'files');
									
									if (files.length + imagePreview.children().length > maxFiles) {
										alert('Maximum ' + maxFiles + ' files allowed');
										return;
									}

									// Create a new DataTransfer object for the updated files
									const dataTransfer = new DataTransfer();
									
									// First, add existing files to the dataTransfer (but don't process again)
									const existingFiles = fileInput[0].files;
									for (let i = 0; i < existingFiles.length; i++) {
										dataTransfer.items.add(existingFiles[i]);
									}
									
									// Process new files
									Array.from(files).forEach(file => {
										// Skip if file is already in the input (prevent duplicates)
										let isDuplicate = false;
										for (let i = 0; i < existingFiles.length; i++) {
											if (existingFiles[i].name === file.name && 
												existingFiles[i].size === file.size && 
												existingFiles[i].type === file.type) {
												isDuplicate = true;
												break;
											}
										}
										
										if (isDuplicate) {
											console.log('[Add Listing] Skipping duplicate file:', file.name);
											return; // Skip this file if it's a duplicate
										}
										
										if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
											alert('Only JPG, PNG, GIF, and WebP files are allowed');
											return;
										}

										if (file.size > maxFileSize) {
											alert('File size must be less than 5MB');
											return;
										}

										// Add this file to the dataTransfer object
										dataTransfer.items.add(file);
										console.log('[Add Listing] Added file to transfer:', file.name);

										// Create and add preview immediately
										createImagePreview(file);
									});

									// Update the file input with only the files in dataTransfer
									fileInput[0].files = dataTransfer.files;
									console.log('[Add Listing] Updated file input with', dataTransfer.files.length, 'files');
								}
								
								// Separate function for creating image previews
								function createImagePreview(file) {
									console.log('[Add Listing] Creating preview for:', file.name);
									const reader = new FileReader();
									
									reader.onload = function(e) {
										console.log('[Add Listing] File read complete, creating preview element');
										// Create preview item with jQuery
										const previewItem = $('<div>', { 
											class: 'image-preview-item'
										}).append(
											$('<img>', { 
												src: e.target.result,
												alt: file.name
											}),
											$('<div>', { 
												class: 'remove-image',
												html: '<i class="fas fa-times"></i>'
											})
										);

										// Append to the preview container
										imagePreview.append(previewItem);
										console.log('[Add Listing] Preview appended to container');
									};
									
									reader.onerror = function() {
										console.error('[Add Listing] Error reading file:', file.name);
									};
									
									reader.readAsDataURL(file);
								}

								// Handle image removal
								imagePreview.on('click', '.remove-image', function() {
									const index = $(this).closest('.image-preview-item').index();
									const dataTransfer = new DataTransfer();
									const existingFiles = fileInput[0].files;
									
									for (let i = 0; i < existingFiles.length; i++) {
										if (i !== index) {
											dataTransfer.items.add(existingFiles[i]);
										}
									}
									
									fileInput[0].files = dataTransfer.files;
									$(this).closest('.image-preview-item').remove();
								});
								// --- End jQuery File upload handling ---
							});
						}
						
						// Initialize jQuery features with a safety check
						initJQueryFeatures();
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