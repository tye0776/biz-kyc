<?php
/**
 * AJAX handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kyc_handle_form_submission() {
	check_ajax_referer( 'kyc_form_nonce', 'security' );

	global $wpdb;
	$table_name = $wpdb->prefix . 'kyc_list';

	$form_type    = isset( $_POST['form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['form_type'] ) ) : 'popup';
	$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$phone_number = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
	$consent      = isset( $_POST['consent'] ) ? 1 : 0;

	if ( empty( $first_name ) || empty( $phone_number ) || empty( $consent ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in required fields.' ) );
	}

	$data = array(
		'first_name'   => $first_name,
		'phone_number' => $phone_number,
		'consent'      => $consent,
	);
	$format = array( '%s', '%s', '%d' );

	if ( $form_type === 'full' ) {
		if ( isset( $_POST['last_name'] ) ) {
			$data['last_name'] = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
			$format[] = '%s';
		}
		if ( isset( $_POST['email'] ) ) {
			$data['email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
			$format[] = '%s';
		}
		if ( isset( $_POST['gender'] ) ) {
			$data['gender'] = sanitize_text_field( wp_unslash( $_POST['gender'] ) );
			$format[] = '%s';
		}
		if ( isset( $_POST['dob_month'] ) && isset( $_POST['dob_day'] ) && !empty($_POST['dob_month']) && !empty($_POST['dob_day']) ) {
			$m = str_pad( (int) $_POST['dob_month'], 2, '0', STR_PAD_LEFT );
			$d = str_pad( (int) $_POST['dob_day'], 2, '0', STR_PAD_LEFT );
			$data['date_of_birth'] = "2000-$m-$d"; // default generic year
			$format[] = '%s';
		}
	}

	// Update or Insert in wp_kyc_list
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE phone_number = %s", $phone_number ) );

	if ( $exists ) {
		$updated = $wpdb->update( $table_name, $data, array( 'phone_number' => $phone_number ), $format, array( '%s' ) );
		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'Failed to update profile.' ) );
		}
	} else {
		$data['date_added'] = current_time( 'mysql' );
		$format[] = '%s';
		$inserted = $wpdb->insert( $table_name, $data, $format );
		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => 'Failed to submit form.' ) );
		}
	}

	// WP User Sync Integration
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		
		update_user_meta( $user_id, 'first_name', $first_name );
		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_phone', $phone_number );

		if ( isset( $data['last_name'] ) && !empty( $data['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', $data['last_name'] );
			update_user_meta( $user_id, 'billing_last_name', $data['last_name'] );
		}
		if ( isset( $data['email'] ) && !empty( $data['email'] ) ) {
			update_user_meta( $user_id, 'billing_email', $data['email'] );
		}
		if ( isset( $data['gender'] ) ) {
			update_user_meta( $user_id, 'gender', $data['gender'] );
		}
	}

	wp_send_json_success( array( 'message' => 'Profile saved successfully!' ) );
}
add_action( 'wp_ajax_kyc_submit_form', 'kyc_handle_form_submission' );
add_action( 'wp_ajax_nopriv_kyc_submit_form', 'kyc_handle_form_submission' );
