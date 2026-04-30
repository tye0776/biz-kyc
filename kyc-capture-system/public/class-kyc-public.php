<?php
/**
 * Public facing logic (shortcode, popup, styles).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KYC_Public {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
		add_shortcode( 'kyc_form', array( $this, 'render_shortcode' ) );
		add_shortcode( 'kyc_profile_form', array( $this, 'render_shortcode' ) );
	}

	public function enqueue_assets() {
		// Only enqueue if popup is enabled or if we are on the profile page
		$enable_popup = get_option( 'kyc_enable_popup', 1 );
		global $post;
		$is_profile_page = is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'kyc_form' ) || has_shortcode( $post->post_content, 'kyc_profile_form' ) );

		if ( ! $enable_popup && ! $is_profile_page ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'kyc-public-css', KYC_PLUGIN_URL . 'public/css/kyc-public.css', array(), KYC_VERSION );
		wp_enqueue_script( 'kyc-public-js', KYC_PLUGIN_URL . 'public/js/kyc-public.js', array( 'jquery' ), KYC_VERSION, true );

		// Only load contacts JS when family_graph module is active on the profile page
		if ( $is_profile_page && KYC_Modules::is_active( 'family_graph' ) ) {
			wp_enqueue_script( 'kyc-contacts-js', KYC_PLUGIN_URL . 'modules/family-graph/js/kyc-contacts.js', array( 'jquery' ), KYC_VERSION, true );
			wp_localize_script( 'kyc-contacts-js', 'kycContactsObj', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'kyc_action_nonce' ),
			) );
		}

		$main_color = get_option( 'kyc_main_color', '#0073aa' );
		$accent_color = get_option( 'kyc_accent_color', '#005177' );
		$position = get_option( 'kyc_btn_position', 'bottom-right' );
		
		$custom_css = "
			:root {
				--kyc-main-color: {$main_color};
				--kyc-accent-color: {$accent_color};
			}
		";
		
		if ( $position === 'bottom-left' ) {
			$custom_css .= ".kyc-floating-btn-wrap { bottom: 20px; left: 20px; right: auto; }";
		} elseif ( $position === 'center-right' ) {
			$custom_css .= ".kyc-floating-btn-wrap { top: 50%; right: 20px; bottom: auto; transform: translateY(-50%); }";
		} elseif ( $position === 'center-left' ) {
			$custom_css .= ".kyc-floating-btn-wrap { top: 50%; left: 20px; right: auto; bottom: auto; transform: translateY(-50%); }";
		} else {
			$custom_css .= ".kyc-floating-btn-wrap { bottom: 20px; right: 20px; left: auto; }";
		}

		wp_add_inline_style( 'kyc-public-css', $custom_css );

		wp_localize_script( 'kyc-public-js', 'kycObj', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'kyc_action_nonce' ),
			'delay'   => get_option( 'kyc_popup_delay', 3 ) * 1000
		) );
	}

	public function render_popup() {
		if ( ! get_option( 'kyc_enable_popup', 1 ) ) {
			return;
		}

		$btn_text = get_option( 'kyc_btn_text', 'Complete KYC' );
		$btn_icon = get_option( 'kyc_btn_icon', 'dashicons-id-alt' );

		$full_page_id = get_option( 'kyc_default_page_created' );
		$full_link = $full_page_id ? get_permalink( $full_page_id ) : '#';
		?>
		<div class="kyc-floating-btn-wrap">
			<button id="kyc-trigger-btn" class="kyc-floating-btn">
				<?php if ( $btn_icon ) : ?>
					<span class="dashicons <?php echo esc_attr( $btn_icon ); ?>" style="margin-right: 5px; line-height: inherit;"></span>
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
							<input type="tel" id="kyc-popup-phone" name="phone" required>
						</div>
						<div class="kyc-form-group kyc-consent-group">
							<label class="kyc-consent-label">
								<input type="checkbox" id="kyc-popup-consent" name="consent" value="1" required>
								I agree to the storage and use of my data as described in the
								<?php
								$pp_id = get_option( 'wp_page_for_privacy_policy' );
								if ( $pp_id ) {
									echo '<a href="' . esc_url( get_permalink( $pp_id ) ) . '" target="_blank">Privacy Policy</a>.';
								} else {
									echo 'Privacy Policy.';
								}
								?>
							</label>
						</div>
						<input type="hidden" name="action" value="kyc_submit_popup">
						<button type="submit" class="kyc-submit-btn">Submit</button>
					</form>
					<div class="kyc-full-link-wrap" style="text-align: center; margin-top: 15px;">
						<a href="<?php echo esc_url( $full_link ); ?>" class="kyc-full-link" style="color: var(--kyc-main-color); text-decoration: none; font-size: 14px;">Complete full profile</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_shortcode() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$cookie_name = 'kyc_user_session';
		$customer = null;

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$user_id = intval( $_COOKIE[ $cookie_name ] );
			if ( $user_id > 0 ) {
				$customer = KYC_DB::get_customer( $user_id );
			}
		}

		$first_name = $customer ? $customer['first_name'] : '';
		$last_name  = $customer ? $customer['last_name'] : '';
		$phone      = $customer ? $customer['phone'] : '';
		$email      = $customer ? $customer['email'] : '';
		$dob        = $customer ? $customer['dob'] : '';
		$gender     = $customer ? $customer['gender'] : '';

		$additional_fields = get_option( 'kyc_additional_fields', array() );
		if ( ! is_array( $additional_fields ) ) $additional_fields = array();

		ob_start();
		?>
		<div class="kyc-full-profile-wrap">
			<?php if ( ! $customer ) : ?>
				<p>Please complete the quick KYC popup first to securely edit your profile. If you've already completed it on another device, you will need to re-verify.</p>
			<?php else : ?>
				<form id="kyc-full-form" class="kyc-form">
					<div class="kyc-msg"></div>

					<div class="kyc-form-group">
						<label for="kyc-first-name">First Name *</label>
						<input type="text" id="kyc-first-name" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
					</div>

					<?php if ( in_array( 'last_name', $additional_fields ) ) : ?>
					<div class="kyc-form-group">
						<label for="kyc-last-name">Last Name</label>
						<input type="text" id="kyc-last-name" name="last_name" value="<?php echo esc_attr( $last_name ); ?>">
					</div>
					<?php endif; ?>

					<div class="kyc-form-group">
						<label for="kyc-phone">Phone Number (Cannot be changed)</label>
						<input type="tel" id="kyc-phone" value="<?php echo esc_attr( $phone ); ?>" readonly style="background:#f1f1f1;">
					</div>

					<div class="kyc-form-group">
						<label for="kyc-email">Email</label>
						<input type="email" id="kyc-email" name="email" value="<?php echo esc_attr( $email ); ?>">
					</div>

					<?php if ( in_array( 'dob', $additional_fields ) ) : ?>
					<div class="kyc-form-group">
						<label for="kyc-dob">Date of Birth</label>
						<input type="date" id="kyc-dob" name="dob" value="<?php echo esc_attr( $dob ); ?>">
					</div>
					<?php endif; ?>

					<?php if ( in_array( 'gender', $additional_fields ) ) : ?>
					<div class="kyc-form-group">
						<label for="kyc-gender">Gender</label>
						<select id="kyc-gender" name="gender" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
							<option value="">Select...</option>
							<option value="Male" <?php selected( $gender, 'Male' ); ?>>Male</option>
							<option value="Female" <?php selected( $gender, 'Female' ); ?>>Female</option>
							<option value="Other" <?php selected( $gender, 'Other' ); ?>>Other</option>
						</select>
					</div>
					<?php endif; ?>

					<input type="hidden" name="action" value="kyc_update_profile">
					<button type="submit" class="kyc-submit-btn">Save Profile</button>
				</form>

				<?php if ( KYC_Modules::is_active( 'order_notes' ) ) : ?>
				<div class="kyc-form-group" style="margin-top:20px">
					<label for="kyc-preferences">📝 Preferences / Notes</label>
					<textarea id="kyc-preferences" name="preferences" rows="3" form="kyc-full-form"
						style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:14px;resize:vertical"
						placeholder="e.g. No nuts, prefers fondant, favourite flavour: Red Velvet"><?php echo esc_textarea( $customer['preferences'] ?? '' ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( KYC_Modules::is_active( 'referrals' ) && empty( $customer['referral_source'] ) ) : ?>
				<div style="margin-top:16px;padding:16px;background:#f9f9f9;border-radius:6px;border:1px solid #e5e5e5">
					<p style="font-size:13px;font-weight:600;margin-bottom:10px">🤝 How did you find us?</p>
					<div class="kyc-form-group">
						<select name="referral_source" form="kyc-full-form" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
							<option value="">Select…</option>
							<option>Word of mouth</option>
							<option>Social media</option>
							<option>Google search</option>
							<option>Friend / Family</option>
							<option>Flyer / Poster</option>
							<option>Returning customer</option>
							<option>Other</option>
						</select>
					</div>
					<div class="kyc-form-group">
						<input type="tel" name="referred_by" form="kyc-full-form" placeholder="Referrer's phone number (optional)"
							style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:14px">
					</div>
				</div>
				<?php endif; ?>

				<p class="kyc-gdpr-note">
					<small>
						🔒 Your data is stored securely and used only to serve you better.
						<?php
						$pp_id = get_option( 'wp_page_for_privacy_policy' );
						if ( $pp_id ) {
							echo '<a href="' . esc_url( get_permalink( $pp_id ) ) . '" target="_blank">Privacy Policy</a>. ';
						}
						?>
						<a href="<?php echo esc_url( site_url( '/wp-login.php?action=erasureRequest' ) ); ?>" style="color: #d63638;">Request data deletion &rarr;</a>
					</small>
				</p>

				<?php
				// ---- My Family & Reminders section — only if module active ----
				if ( KYC_Modules::is_active( 'family_graph' ) ) :
					// Use the already-validated customer ID — do not re-read the cookie
					$contacts = KYC_DB::get_linked_contacts_by_customer( $customer['id'] );
				?>
				<div class="kyc-family-section">
					<h3>👨‍👩‍👧 My Family &amp; Reminders</h3>
					<p>Add family members and friends so we can remind you (and the shop!) of their birthdays and anniversaries. When they register and link you back by phone number, your relationship will be officially confirmed! 🔗</p>

					<button id="kyc-add-contact-btn" class="kyc-submit-btn" style="margin-bottom: 16px;">+ Add Family Member</button>

					<p id="kyc-contacts-empty" style="color: #888;" <?php echo empty( $contacts ) ? '' : 'style="display:none"'; ?>>You haven't linked anyone yet.</p>

					<table id="kyc-contacts-table" class="kyc-contacts-table" <?php echo empty( $contacts ) ? 'style="display:none"' : ''; ?>>
						<thead>
							<tr>
								<th>Name</th>
								<th>Phone</th>
								<th>Relationship</th>
								<th>Birthday</th>
								<th>Anniversary</th>
								<th>Status</th>
								<th></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>

					<!-- Hidden root element carries the initial contacts JSON for JS -->
					<div id="kyc-contacts-root" style="display:none"
						 data-contacts="<?php echo esc_attr( wp_json_encode( $contacts ) ); ?>">
					</div>
				</div>

				<!-- Add/Edit Family Member Modal -->
				<div id="kyc-contact-modal-overlay" class="kyc-modal-overlay" style="display:none;">
					<div class="kyc-modal">
						<button type="button" id="kyc-contact-modal-close" class="kyc-modal-close" aria-label="Close">&times;</button>
						<div class="kyc-modal-content">
							<h3 id="kyc-contact-modal-title">Add Family Member</h3>
							<div id="kyc-contact-msg" style="display:none; margin-bottom:10px; font-weight:bold;"></div>
							<form id="kyc-contact-form" class="kyc-form">
								<input type="hidden" id="kyc-contact-id" name="contact_id" value="">

								<div class="kyc-form-group">
									<label for="kyc-contact-linked-name">Their Name *</label>
									<input type="text" id="kyc-contact-linked-name" name="linked_name" required>
								</div>

								<div class="kyc-form-group">
									<label for="kyc-contact-linked-phone">Their Phone Number *</label>
									<input type="tel" id="kyc-contact-linked-phone" name="linked_phone" required>
								</div>

								<div class="kyc-form-group">
									<label for="kyc-contact-relationship">Relationship *</label>
									<select id="kyc-contact-relationship" name="relationship" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
										<option value="Wife">Wife</option>
										<option value="Husband">Husband</option>
										<option value="Son">Son</option>
										<option value="Daughter">Daughter</option>
										<option value="Mother">Mother</option>
										<option value="Father">Father</option>
										<option value="Brother">Brother</option>
										<option value="Sister">Sister</option>
										<option value="Friend">Friend</option>
										<option value="In-Law">In-Law</option>
										<option value="Other">Other (specify below)</option>
									</select>
								</div>

								<div class="kyc-form-group" id="kyc-custom-relationship-wrap" style="display:none;">
									<label for="kyc-contact-custom-relationship">Specify Relationship</label>
									<input type="text" id="kyc-contact-custom-relationship" name="custom_relationship" placeholder="e.g. Cousin, Colleague…">
								</div>

								<div class="kyc-form-group">
									<label for="kyc-contact-dob">Their Birthday</label>
									<input type="date" id="kyc-contact-dob" name="dob">
								</div>

								<div class="kyc-form-group">
									<label for="kyc-contact-anniversary">Anniversary Date</label>
									<input type="date" id="kyc-contact-anniversary" name="anniversary">
								</div>

								<p style="font-size: 13px; color: #666; margin-bottom: 12px;">Please enter at least a birthday or anniversary date.</p>

								<button type="submit" class="kyc-submit-btn kyc-contact-submit-btn">Save</button>
							</form>
						</div>
					</div>
				</div>

				<?php endif; // family_graph module ?>

			<?php endif; // customer logged in ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
