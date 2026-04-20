<?php
/**
 * Forms and Frontend Output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue frontend scripts and styles.
 */
function kyc_enqueue_assets() {
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'kyc-style', KYC_PLUGIN_URL . 'assets/style.css', array(), KYC_VERSION );
	wp_enqueue_script( 'kyc-script', KYC_PLUGIN_URL . 'assets/script.js', array( 'jquery' ), KYC_VERSION, true );

	wp_localize_script( 'kyc-script', 'kyc_ajax_obj', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'kyc_form_nonce' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'kyc_enqueue_assets' );

/**
 * Print dynamic CSS.
 */
function kyc_print_dynamic_css() {
	$main_color = get_option( 'kyc_main_color', '#0073aa' );
	$accent_color = get_option( 'kyc_accent_color', '#005177' );
    $position = get_option( 'kyc_btn_position', 'bottom-right' );
	?>
	<style>
		:root {
			--kyc-main-color: <?php echo esc_attr( $main_color ); ?>;
			--kyc-accent-color: <?php echo esc_attr( $accent_color ); ?>;
		}
        <?php if ( $position === 'bottom-left' ) : ?>
        .kyc-floating-btn-wrap { bottom: 20px; left: 20px; }
        <?php elseif ( $position === 'center-right' ) : ?>
        .kyc-floating-btn-wrap { top: 50%; right: 20px; transform: translateY(-50%); }
        <?php elseif ( $position === 'center-left' ) : ?>
        .kyc-floating-btn-wrap { top: 50%; left: 20px; transform: translateY(-50%); }
        <?php else : ?>
        .kyc-floating-btn-wrap { bottom: 20px; right: 20px; }
        <?php endif; ?>
	</style>
	<?php
}
add_action( 'wp_head', 'kyc_print_dynamic_css' );

/**
 * Render Floating Button and Popup in footer.
 */
function kyc_render_floating_popup() {
    $btn_text = get_option( 'kyc_btn_text', '' );
    $btn_icon = get_option( 'kyc_btn_icon', 'dashicons-id-alt' );

    $full_page_id = get_option( 'kyc_default_page_created' );
    $full_link = $full_page_id ? get_permalink($full_page_id) : '#';

    ?>
    <div class="kyc-floating-btn-wrap">
        <button id="kyc-trigger-btn" class="kyc-floating-btn">
            <?php if ( $btn_icon ) : ?>
                <span class="dashicons <?php echo esc_attr( $btn_icon ); ?>"></span>
            <?php endif; ?>
            <?php if ( $btn_text ) : ?>
                <span class="kyc-btn-text"><?php echo esc_html( $btn_text ); ?></span>
            <?php endif; ?>
        </button>
    </div>

    <div id="kyc-modal-overlay" class="kyc-modal-overlay" style="display: none;">
		<div class="kyc-modal">
			<button type="button" class="kyc-modal-close" aria-label="Close modal">&times;</button>
			<div class="kyc-modal-content">
				<h3>Quick KYC</h3>
				<form id="kyc-popup-form" class="kyc-form">
					<div class="kyc-msg"></div>
					<div class="kyc-form-group">
						<label for="kyc-popup-first-name">First Name *</label>
						<input type="text" id="kyc-popup-first-name" name="first_name" required>
					</div>
					<div class="kyc-form-group">
						<label for="kyc-popup-phone">Phone Number *</label>
						<input type="tel" id="kyc-popup-phone" name="phone_number" required>
					</div>
					<div class="kyc-form-group kyc-consent-group">
						<label>
							<input type="checkbox" name="consent" required> I consent to the collection of my data. *
						</label>
					</div>
					<input type="hidden" name="action" value="kyc_submit_form">
					<input type="hidden" name="form_type" value="popup">
					<button type="submit" class="kyc-submit-btn">Submit</button>
				</form>
				<div class="kyc-full-link-wrap">
					<a href="<?php echo esc_url( $full_link ); ?>" class="kyc-full-link">Complete full profile</a>
				</div>
			</div>
		</div>
	</div>
    <?php
}
add_action( 'wp_footer', 'kyc_render_floating_popup' );

/**
 * Renders the Full Profile form shortcode.
 */
function kyc_render_form_shortcode() {
    $additional_fields = get_option( 'kyc_additional_fields', array() );
	if ( ! is_array( $additional_fields ) ) $additional_fields = array();

	ob_start();
	?>
    <div class="kyc-full-profile-wrap">
        <form id="kyc-full-form" class="kyc-form kyc-full-form">
            <div class="kyc-msg"></div>

            <div class="kyc-form-row">
                <div class="kyc-form-group">
                    <label for="kyc-first-name">First Name *</label>
                    <input type="text" id="kyc-first-name" name="first_name" required>
                </div>
                <?php if ( in_array('last_name', $additional_fields) ) : ?>
                <div class="kyc-form-group">
                    <label for="kyc-last-name">Last Name</label>
                    <input type="text" id="kyc-last-name" name="last_name">
                </div>
                <?php endif; ?>
            </div>

            <div class="kyc-form-row">
                <div class="kyc-form-group">
                    <label for="kyc-phone">Phone Number *</label>
                    <input type="tel" id="kyc-phone" name="phone_number" required>
                </div>
                <div class="kyc-form-group">
                    <label for="kyc-email">Email</label>
                    <input type="email" id="kyc-email" name="email">
                </div>
            </div>

            <div class="kyc-form-row">
                <?php if ( in_array('dob', $additional_fields) ) : ?>
                <div class="kyc-form-group">
                    <label for="kyc-dob">Date of Birth (Month & Day)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="dob_month" placeholder="MM" min="1" max="12" style="width: 50%;">
                        <input type="number" name="dob_day" placeholder="DD" min="1" max="31" style="width: 50%;">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ( in_array('gender', $additional_fields) ) : ?>
                <div class="kyc-form-group">
                    <label for="kyc-gender">Gender</label>
                    <select id="kyc-gender" name="gender" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                        <option value="">Select...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="kyc-form-group kyc-consent-group">
                <label>
                    <input type="checkbox" name="consent" required> I consent to the collection of my data. *
                </label>
            </div>
            <input type="hidden" name="action" value="kyc_submit_form">
            <input type="hidden" name="form_type" value="full">
            <button type="submit" class="kyc-submit-btn">Save Profile</button>
        </form>
    </div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kyc_form', 'kyc_render_form_shortcode' );
