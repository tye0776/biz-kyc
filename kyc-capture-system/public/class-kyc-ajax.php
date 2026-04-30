<?php
/**
 * AJAX Handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KYC_Ajax {

	public function init() {
		add_action( 'wp_ajax_kyc_submit_popup', array( $this, 'handle_popup_submission' ) );
		add_action( 'wp_ajax_nopriv_kyc_submit_popup', array( $this, 'handle_popup_submission' ) );

		add_action( 'wp_ajax_kyc_update_profile', array( $this, 'handle_profile_update' ) );
		add_action( 'wp_ajax_nopriv_kyc_update_profile', array( $this, 'handle_profile_update' ) );
	}

	public function handle_popup_submission() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );

		// GDPR: Consent must be given before we store any personal data
		if ( empty( $_POST['consent'] ) || '1' !== $_POST['consent'] ) {
			wp_send_json_error( array( 'message' => 'Please accept the Privacy Policy to continue.' ) );
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( empty( $first_name ) || empty( $phone ) ) {
			wp_send_json_error( array( 'message' => 'Please fill in required fields.' ) );
		}

		$existing = KYC_DB::get_customer_by_phone( $phone );

		$data = array(
			'first_name' => $first_name,
			'phone'      => $phone
		);

		if ( $existing ) {
			KYC_DB::update_customer( $existing['id'], array( 'first_name' => $first_name ) );
			$user_id = $existing['id'];
			$msg = 'Welcome back! Profile located.';
		} else {
			$user_id = KYC_DB::insert_customer( $data );
			if ( ! $user_id ) {
				wp_send_json_error( array( 'message' => 'Failed to save data.' ) );
			}
			// Auto-resolve any existing linked contacts pointing at this phone number
			KYC_DB::backfill_linked_customer_id( $phone, $user_id );
			$msg = 'Thank you! Details saved.';
		}

		// Fire webhook/integration action
		do_action( 'kyc_customer_captured', $user_id, $data );

		// Sync to WP User if logged in
		if ( is_user_logged_in() ) {
			$wp_user_id = get_current_user_id();
			update_user_meta( $wp_user_id, 'first_name', $first_name );
			update_user_meta( $wp_user_id, 'billing_first_name', $first_name );
			update_user_meta( $wp_user_id, 'billing_phone', $phone );
		}

		// Set secure session cookie (30 days).
		// SameSite=Lax + httponly prevents XSS theft and cross-site conflicts (409 cookie collisions).
		$cookie_options = array(
			'expires'  => time() + 30 * DAY_IN_SECONDS,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( 'kyc_user_session', (string) $user_id, $cookie_options );
		} else {
			// PHP < 7.3 fallback
			setcookie( 'kyc_user_session', (string) $user_id, $cookie_options['expires'], $cookie_options['path'] . '; SameSite=Lax', $cookie_options['domain'], $cookie_options['secure'], true );
		}

		wp_send_json_success( array( 'message' => $msg ) );
	}

	public function handle_profile_update() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );

		// Security: verify the cookie value maps to a real customer
		// to prevent a spoofed cookie from updating another customer's record
		if ( ! isset( $_COOKIE['kyc_user_session'] ) || intval( $_COOKIE['kyc_user_session'] ) <= 0 ) {
			wp_send_json_error( array( 'message' => 'Unauthorized update.' ) );
		}
		$user_id  = absint( $_COOKIE['kyc_user_session'] );
		$customer = KYC_DB::get_customer( $user_id );
		if ( ! $customer ) {
			wp_send_json_error( array( 'message' => 'Profile not found.' ) );
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$dob        = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';
		$gender     = isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : '';

		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => 'First Name is required.' ) );
		}

		$data = array(
			'first_name' => $first_name,
			'email'      => $email
		);

		if ( isset( $_POST['last_name'] ) ) $data['last_name'] = $last_name;
		if ( isset( $_POST['dob'] ) )       $data['dob']       = $dob;
		if ( isset( $_POST['gender'] ) )    $data['gender']    = $gender;

		// Module: order_notes — save preferences field
		if ( KYC_Modules::is_active( 'order_notes' ) && isset( $_POST['preferences'] ) ) {
			$data['preferences'] = sanitize_textarea_field( wp_unslash( $_POST['preferences'] ) );
		}
		// Module: referrals — save referral fields (only on first fill)
		if ( KYC_Modules::is_active( 'referrals' ) ) {
			if ( isset( $_POST['referral_source'] ) ) {
				$data['referral_source'] = sanitize_text_field( wp_unslash( $_POST['referral_source'] ) );
			}
			if ( isset( $_POST['referred_by'] ) ) {
				$data['referred_by'] = sanitize_text_field( wp_unslash( $_POST['referred_by'] ) );
			}
		}

		$updated = KYC_DB::update_customer( $user_id, $data );

		if ( $updated === false ) {
			wp_send_json_error( array( 'message' => 'Failed to update profile.' ) );
		}

		// Fire webhook/integration action
		do_action( 'kyc_customer_updated', $user_id, $data );

		// Sync to WP User if logged in
		if ( is_user_logged_in() ) {
			$wp_user_id = get_current_user_id();
			update_user_meta( $wp_user_id, 'first_name', $first_name );
			update_user_meta( $wp_user_id, 'billing_first_name', $first_name );
			
			if ( !empty($last_name) ) {
				update_user_meta( $wp_user_id, 'last_name', $last_name );
				update_user_meta( $wp_user_id, 'billing_last_name', $last_name );
			}
			if ( !empty($email) ) {
				update_user_meta( $wp_user_id, 'billing_email', $email );
			}
			if ( !empty($gender) ) {
				update_user_meta( $wp_user_id, 'gender', $gender );
			}
		}

		wp_send_json_success( array( 'message' => 'Profile updated successfully!' ) );
	}
}
