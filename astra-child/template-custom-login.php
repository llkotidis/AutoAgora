<?php
/**
 * Template Name: Custom Login Page
 *
 * @package Astra Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<?php if ( astra_page_layout() == 'left-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

	<div id="primary" <?php astra_primary_class(); ?>>

		<?php astra_primary_content_top(); ?>

		<main id="main" class="site-main">
			<article class="post type-page status-publish ast-article-single">
				<div class="entry-content clear">
					<div class="custom-login-container">
						<div class="custom-login-form">
							<h1><?php _e( 'Log In', 'astra-child' ); ?></h1>

							<!-- Display Login Errors -->
							<?php if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) : ?>
								<p class="login-error"><?php esc_html_e( 'Login failed. Please check your phone number and password.', 'astra-child' ); ?></p>
							<?php endif; ?>
							<?php if ( isset( $_GET['registration'] ) && $_GET['registration'] === 'success' ) : ?>
								<p class="login-info"><?php esc_html_e( 'Registration successful. Please log in.', 'astra-child' ); ?></p>
							<?php endif; ?>

							<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
								<p>
									<label for="login_phone_display"><?php _e( 'Phone Number', 'astra-child' ); ?>:</label>
									<input type="tel" name="login_phone_display" id="login_phone_display" class="input" value="" size="20" required="required" autocomplete="username" />
									<input type="hidden" name="log" id="log" value=""> <!-- Hidden field for WP authentication -->
								</p>
								<p>
									<label for="user_pass"><?php _e( 'Password', 'astra-child' ); ?>:</label>
									<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" required="required" autocomplete="current-password" />
								</p>
								<p class="login-remember">
									<label><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <?php _e( 'Remember Me', 'astra-child' ); ?></label>
								</p>
								<p class="login-submit">
									<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Log In', 'astra-child' ); ?>" />
									<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url() ); ?>" />
								</p>
							</form>

							<p class="register-link">
								<?php
								$registration_page = get_page_by_path( 'register' ); // Adjust slug if needed
								if ( $registration_page ) {
									echo '<a href="' . esc_url( get_permalink( $registration_page->ID ) ) . '">' . esc_html__( 'Register', 'astra-child' ) . '</a>';
								}
								?>
							</p>
							<p class="lost-password">
								<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'astra-child' ); ?></a>
							</p>
						</div>
					</div>
				</div>
			</article>
		</main>

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( astra_page_layout() == 'right-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // --- Initialize intl-tel-input for Login Form --- 
    const loginPhoneInput = document.querySelector("#login_phone_display");
    let loginIti = null;
    if (loginPhoneInput) {
         loginIti = window.intlTelInput(loginPhoneInput, {
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch('https://ipinfo.io/json', { headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(data => callback(data.country))
                .catch(() => callback('cy')); // Default to Cyprus
            },
            separateDialCode: true,
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"
        });
    } else {
        console.error("Login form: Phone input #login_phone_display not found.");
    }

    // --- Populate hidden 'log' field on submit --- 
    const loginForm = document.getElementById('loginform');
    const hiddenLogInput = document.getElementById('log');

    if (loginForm && hiddenLogInput && loginIti) {
        loginForm.addEventListener('submit', function(event) {
            const fullPhoneNumber = loginIti.getNumber();
            const password = document.getElementById('user_pass').value;
            
            if (fullPhoneNumber) {
                hiddenLogInput.value = fullPhoneNumber;
            } else {
                // Prevent submission if number is invalid/empty according to the library
                if (!loginIti.isValidNumber()) {
                     alert("<?php esc_attr_e( 'Please enter a valid phone number.', 'astra-child' ); ?>");
                     event.preventDefault();
                     return;
                }
            }

            // Also check password
            if (!password) {
                 alert("<?php esc_attr_e( 'Please enter your password.', 'astra-child' ); ?>");
                 event.preventDefault();
                 return;
            }
        });
    }
});
</script>

<?php get_footer(); ?>