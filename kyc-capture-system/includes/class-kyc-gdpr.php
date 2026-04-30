<?php
/**
 * GDPR Compliance for KYC Capture System.
 *
 * Registers:
 *  - A personal data exporter (Tools > Export Personal Data)
 *  - A personal data eraser  (Tools > Erase Personal Data)
 *
 * Also provides the callback functions called by the main plugin file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Suggest privacy policy text to the site admin
// -------------------------------------------------------------------------

add_action( 'admin_init', 'kyc_add_privacy_policy_content' );

function kyc_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content = '<p>' .
		__( 'When you use our KYC (Know Your Customer) capture form, we collect your name, phone number, and optionally your email address, date of birth, gender, and relationship/family details. This information is stored in our private database and is used only to serve you better (e.g. birthday reminders, order preferences). We do not share your data with third parties except where you have been informed (e.g. if we use an automation tool like Zapier). You may request a copy or deletion of your data by contacting us.', 'kyc-capture-system' ) .
	'</p>';
	wp_add_privacy_policy_content( __( 'KYC Capture System', 'kyc-capture-system' ), $content );
}

// -------------------------------------------------------------------------
// Filter callbacks — registered in kyc-capture-system.php
// -------------------------------------------------------------------------

/**
 * Register the KYC personal data exporter with WordPress.
 */
function kyc_register_privacy_exporter( $exporters ) {
	$exporters['kyc-capture-system'] = array(
		'exporter_friendly_name' => __( 'KYC Capture System', 'kyc-capture-system' ),
		'callback'               => 'kyc_privacy_exporter',
	);
	return $exporters;
}

/**
 * Register the KYC personal data eraser with WordPress.
 */
function kyc_register_privacy_eraser( $erasers ) {
	$erasers['kyc-capture-system'] = array(
		'eraser_friendly_name' => __( 'KYC Capture System', 'kyc-capture-system' ),
		'callback'             => 'kyc_privacy_eraser',
	);
	return $erasers;
}

// -------------------------------------------------------------------------
// Exporter — called by WP when an admin requests a data export
// -------------------------------------------------------------------------

/**
 * Export all KYC data for a given email address.
 * WP matches by email; we look it up in our customers table.
 *
 * @param string $email_address  The email to look up.
 * @param int    $page           Pagination page (WP passes this for large exports).
 * @return array
 */
function kyc_privacy_exporter( $email_address, $page = 1 ) {
	$export_items = array();

	if ( empty( $email_address ) ) {
		return array( 'data' => $export_items, 'done' => true );
	}

	global $wpdb;
	$customers_table = KYC_DB::get_table_name();
	$contacts_table  = KYC_DB::get_contacts_table_name();

	// Find the customer by email
	$customer = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $customers_table WHERE email = %s LIMIT 1", $email_address ),
		ARRAY_A
	);

	if ( ! $customer ) {
		return array( 'data' => $export_items, 'done' => true );
	}

	// --- Main customer record ---
	$customer_data = array(
		array( 'name' => 'First Name',  'value' => $customer['first_name'] ),
		array( 'name' => 'Last Name',   'value' => $customer['last_name'] ?? '' ),
		array( 'name' => 'Phone',       'value' => $customer['phone'] ),
		array( 'name' => 'Email',       'value' => $customer['email'] ),
		array( 'name' => 'Date of Birth', 'value' => $customer['dob'] ?? '' ),
		array( 'name' => 'Gender',      'value' => $customer['gender'] ?? '' ),
		array( 'name' => 'Date Registered', 'value' => $customer['created_at'] ),
	);

	$export_items[] = array(
		'group_id'    => 'kyc-customer',
		'group_label' => __( 'KYC Customer Profile', 'kyc-capture-system' ),
		'item_id'     => 'kyc-customer-' . $customer['id'],
		'data'        => $customer_data,
	);

	// --- Linked contacts for this customer ---
	$contacts = KYC_DB::get_linked_contacts_by_customer( $customer['id'] );
	foreach ( $contacts as $contact ) {
		$export_items[] = array(
			'group_id'    => 'kyc-linked-contact',
			'group_label' => __( 'KYC Linked Contacts (Family/Friends)', 'kyc-capture-system' ),
			'item_id'     => 'kyc-contact-' . $contact['id'],
			'data'        => array(
				array( 'name' => 'Linked Name',     'value' => $contact['linked_name'] ),
				array( 'name' => 'Linked Phone',    'value' => $contact['linked_phone'] ),
				array( 'name' => 'Relationship',    'value' => $contact['relationship'] ),
				array( 'name' => 'Birthday',        'value' => $contact['dob'] ?? '' ),
				array( 'name' => 'Anniversary',     'value' => $contact['anniversary'] ?? '' ),
			),
		);
	}

	return array( 'data' => $export_items, 'done' => true );
}

// -------------------------------------------------------------------------
// Eraser — called by WP when an admin processes a data erasure request
// -------------------------------------------------------------------------

/**
 * Erase all KYC data for a given email address.
 * Deletes the customer row AND all linked contacts they added.
 *
 * @param string $email_address
 * @param int    $page
 * @return array
 */
function kyc_privacy_eraser( $email_address, $page = 1 ) {
	$items_removed  = 0;
	$items_retained = 0;
	$messages       = array();

	if ( empty( $email_address ) ) {
		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	global $wpdb;
	$customers_table = KYC_DB::get_table_name();
	$contacts_table  = KYC_DB::get_contacts_table_name();

	// Find the customer
	$customer = $wpdb->get_row(
		$wpdb->prepare( "SELECT id FROM $customers_table WHERE email = %s LIMIT 1", $email_address ),
		ARRAY_A
	);

	if ( $customer ) {
		$customer_id = $customer['id'];

		// Delete linked contacts this customer added
		$contacts_deleted = $wpdb->delete( $contacts_table, array( 'customer_id' => $customer_id ), array( '%d' ) );
		if ( $contacts_deleted ) {
			$items_removed += $contacts_deleted;
		}

		// Nullify mutual links pointing AT this customer.
		// Fix: array(null) is not a valid $wpdb->update() format — use a raw prepared query.
		$wpdb->query( $wpdb->prepare(
			"UPDATE $contacts_table SET linked_customer_id = NULL WHERE linked_customer_id = %d",
			$customer_id
		) );

		// Delete the customer record itself
		$deleted = $wpdb->delete( $customers_table, array( 'id' => $customer_id ), array( '%d' ) );
		if ( $deleted ) {
			$items_removed++;
			$messages[] = sprintf(
				/* translators: %s: email address */
				__( 'KYC customer record for %s has been deleted.', 'kyc-capture-system' ),
				$email_address
			);
		}
	}

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}
