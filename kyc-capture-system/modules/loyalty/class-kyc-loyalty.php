<?php
/**
 * Module: Loyalty Points / Visit Counter.
 * Adds a `loyalty_points` column (provisioned by activator).
 * Admin: star count + increment button in customer list.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Loyalty {

	public static function init() {
		add_action( 'wp_ajax_kyc_increment_loyalty', array( __CLASS__, 'handle_increment' ) );
	}

	public static function handle_increment() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		if ( ! $customer_id ) wp_send_json_error();

		// Security: verify customer exists before updating
		if ( ! KYC_DB::get_customer( $customer_id ) ) {
			wp_send_json_error( array( 'message' => 'Customer not found.' ) );
		}

		global $wpdb;
		$table = KYC_DB::get_table_name();
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET loyalty_points = loyalty_points + 1 WHERE id = %d",
			$customer_id
		) );
		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT loyalty_points FROM $table WHERE id = %d", $customer_id ) );
		wp_send_json_success( array( 'points' => $new ) );
	}

	/**
	 * Render loyalty cell: star count + one-click increment button.
	 */
	public static function render_cell( $points, $customer_id ) {
		$points = (int) $points;
		$stars  = $points > 0 ? str_repeat( '⭐', min( $points, 5 ) ) : '—';
		return sprintf(
			'<span class="kyc-loyalty-pts" data-id="%d">%s <small style="color:#888">(%d)</small></span> '
			. '<button class="kyc-loyalty-add button button-small" data-id="%d" title="Add visit">+1</button>',
			$customer_id, esc_html( $stars ), $points, $customer_id
		);
	}
}
