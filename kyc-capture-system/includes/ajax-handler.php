<?php
/**
 * AJAX handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle form submission.
 */
function kyc_handle_form_submission() {
	check_ajax_referer( 'kyc_form_nonce', 'security' );

	global $wpdb;
	$table_name = $wpdb->prefix . 'kyc_list';

	// Sanitize inputs
	$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$phone_number = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
	$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$dob          = isset( $_POST['date_of_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ) ) : '';
	$consent      = isset( $_POST['consent'] ) ? 1 : 0;

	// Validation
	if ( empty( $first_name ) || empty( $phone_number ) || empty( $dob ) || empty( $consent ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in all required fields and provide consent.' ) );
	}

	// Format DOB properly
	$dob_formatted = date( 'Y-m-d', strtotime( $dob ) );

	$data = array(
		'first_name'    => $first_name,
		'phone_number'  => $phone_number,
		'email'         => $email,
		'date_of_birth' => $dob_formatted,
		'consent'       => $consent,
		'date_added'    => current_time( 'mysql' ),
	);

	$format = array( '%s', '%s', '%s', '%s', '%d', '%s' );

	// Check if phone number exists
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE phone_number = %s", $phone_number ) );

	if ( $exists ) {
		// Update record
		$updated = $wpdb->update(
			$table_name,
			$data,
			array( 'phone_number' => $phone_number ),
			$format,
			array( '%s' )
		);

		if ( false !== $updated ) {
			wp_send_json_success( array( 'message' => 'Profile updated successfully.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update profile. Please try again.' ) );
		}
	} else {
		// Insert new record
		$inserted = $wpdb->insert( $table_name, $data, $format );

		if ( $inserted ) {
			wp_send_json_success( array( 'message' => 'Thank you! Your profile has been captured.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to submit form. Please try again.' ) );
		}
	}
}
add_action( 'wp_ajax_kyc_submit_form', 'kyc_handle_form_submission' );
add_action( 'wp_ajax_nopriv_kyc_submit_form', 'kyc_handle_form_submission' );
