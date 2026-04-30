<?php
/**
 * AJAX handlers for Linked Contacts (Family Social Graph).
 * Moved to modules/family-graph/ — identical to previous public/class-kyc-contacts-ajax.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Contacts_Ajax {

	public function init() {
		add_action( 'wp_ajax_kyc_save_linked_contact',        array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_nopriv_kyc_save_linked_contact', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_kyc_delete_linked_contact',        array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_nopriv_kyc_delete_linked_contact', array( $this, 'handle_delete' ) );
	}

	private function get_session_customer() {
		if ( ! isset( $_COOKIE['kyc_user_session'] ) || intval( $_COOKIE['kyc_user_session'] ) <= 0 ) {
			wp_send_json_error( array( 'message' => 'Session not found. Please complete KYC first.' ) );
		}
		$customer = KYC_DB::get_customer( intval( $_COOKIE['kyc_user_session'] ) );
		if ( ! $customer ) wp_send_json_error( array( 'message' => 'Profile not found.' ) );
		return $customer;
	}

	public function handle_save() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );
		$customer    = $this->get_session_customer();
		$customer_id = $customer['id'];

		$contact_id   = absint( $_POST['contact_id'] ?? 0 );
		$linked_name  = sanitize_text_field( wp_unslash( $_POST['linked_name']  ?? '' ) );
		$linked_phone = sanitize_text_field( wp_unslash( $_POST['linked_phone'] ?? '' ) );
		$relationship = sanitize_text_field( wp_unslash( $_POST['relationship'] ?? 'Other' ) );
		$dob          = sanitize_text_field( wp_unslash( $_POST['dob']          ?? '' ) );
		$anniversary  = sanitize_text_field( wp_unslash( $_POST['anniversary']  ?? '' ) );

		if ( 'Other' === $relationship && ! empty( $_POST['custom_relationship'] ) ) {
			$relationship = sanitize_text_field( wp_unslash( $_POST['custom_relationship'] ) );
		}
		if ( empty( $linked_name ) || empty( $linked_phone ) ) {
			wp_send_json_error( array( 'message' => 'Name and Phone are required.' ) );
		}
		if ( empty( $dob ) && empty( $anniversary ) ) {
			wp_send_json_error( array( 'message' => 'Please enter at least a birthday or anniversary date.' ) );
		}

		$data = array(
			'customer_id'  => $customer_id,
			'linked_name'  => $linked_name,
			'linked_phone' => $linked_phone,
			'relationship' => $relationship,
			'dob'          => ! empty( $dob )         ? $dob         : null,
			'anniversary'  => ! empty( $anniversary ) ? $anniversary : null,
		);

		if ( $contact_id > 0 ) {
			global $wpdb;
			$table = KYC_DB::get_contacts_table_name();
			$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM $table WHERE id = %d", $contact_id ) );
			if ( $owner !== $customer_id ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
			KYC_DB::update_linked_contact( $contact_id, $data );
			$row_id = $contact_id;
		} else {
			$row_id = KYC_DB::insert_linked_contact( $data );
			if ( ! $row_id ) wp_send_json_error( array( 'message' => 'This phone number is already linked to your profile.' ) );
		}

		global $wpdb;
		$table = KYC_DB::get_contacts_table_name();
		$saved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $row_id ), ARRAY_A );
		wp_send_json_success( array( 'message' => 'Contact saved successfully.', 'contact' => $saved ) );
	}

	public function handle_delete() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );
		$customer    = $this->get_session_customer();
		$contact_id  = absint( $_POST['contact_id'] ?? 0 );
		if ( ! $contact_id ) wp_send_json_error( array( 'message' => 'Invalid contact.' ) );
		if ( KYC_DB::delete_linked_contact( $contact_id, $customer['id'] ) ) {
			wp_send_json_success( array( 'message' => 'Contact removed.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not delete contact.' ) );
		}
	}
}
