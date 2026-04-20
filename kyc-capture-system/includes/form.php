<?php
/**
 * Shortcode and form renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue frontend scripts and styles.
 */
function kyc_enqueue_assets() {
	wp_enqueue_style( 'kyc-style', KYC_PLUGIN_URL . 'assets/style.css', array(), KYC_VERSION );
	wp_enqueue_script( 'kyc-script', KYC_PLUGIN_URL . 'assets/script.js', array( 'jquery' ), KYC_VERSION, true );

	wp_localize_script( 'kyc-script', 'kyc_ajax_obj', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'kyc_form_nonce' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'kyc_enqueue_assets' );

/**
 * Renders the form shortcode.
 *
 * @return string HTML output.
 */
function kyc_render_form_shortcode() {
	$full_link = get_option( 'kyc_full_form_link', '#' );

	ob_start();
	?>
	<div id="kyc-modal-overlay" class="kyc-modal-overlay" style="display: none;">
		<div class="kyc-modal">
			<button type="button" class="kyc-modal-close" aria-label="Close modal">&times;</button>
			<div class="kyc-modal-content">
				<h3>Complete Your Profile</h3>
				<form id="kyc-form" class="kyc-form">
					<div class="kyc-msg"></div>
					<div class="kyc-form-group">
						<label for="kyc-first-name">First Name *</label>
						<input type="text" id="kyc-first-name" name="first_name" required>
					</div>
					<div class="kyc-form-group">
						<label for="kyc-phone">Phone Number *</label>
						<input type="tel" id="kyc-phone" name="phone_number" required>
					</div>
					<div class="kyc-form-group">
						<label for="kyc-email">Email</label>
						<input type="email" id="kyc-email" name="email">
					</div>
					<div class="kyc-form-group">
						<label for="kyc-dob">Date of Birth *</label>
						<input type="date" id="kyc-dob" name="date_of_birth" required>
					</div>
					<div class="kyc-form-group kyc-consent-group">
						<label>
							<input type="checkbox" name="consent" required> I consent to the collection of my data. *
						</label>
					</div>
					<input type="hidden" name="action" value="kyc_submit_form">
					<button type="submit" class="kyc-submit-btn">Submit</button>
				</form>
				<div class="kyc-full-link-wrap">
					<a href="<?php echo esc_url( $full_link ); ?>" class="kyc-full-link">Complete full profile</a>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kyc_form', 'kyc_render_form_shortcode' );
