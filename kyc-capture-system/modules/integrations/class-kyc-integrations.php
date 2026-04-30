<?php
/**
 * Module: Integrations — WooCommerce sync + Webhook dispatch.
 * Moved to modules/integrations/ from includes/class-kyc-integrations.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Integrations {

	private static $webhook_url = null;

	public static function init() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'sync_woo_checkout' ), 10, 3 );
		}
		self::$webhook_url = get_option( 'kyc_webhook_url', '' );
		if ( self::$webhook_url ) {
			add_action( 'kyc_customer_captured', array( __CLASS__, 'dispatch_webhook' ), 10, 2 );
			add_action( 'kyc_customer_updated',  array( __CLASS__, 'dispatch_webhook' ), 10, 2 );
		}
	}

	public static function dispatch_webhook( $customer_id, $data ) {
		$url = self::$webhook_url;
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) return;
		wp_remote_post( $url, array(
			'method'      => 'POST',
			'timeout'     => 5,
			'blocking'    => false,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( array_merge( $data, array( 'kyc_customer_id' => $customer_id ) ) ),
			'data_format' => 'body',
		) );
	}

	public static function sync_woo_checkout( $order_id, $posted_data, $order ) {
		$phone      = $order->get_billing_phone();
		$first_name = $order->get_billing_first_name();
		if ( empty( $phone ) || empty( $first_name ) ) return;

		$data = array(
			'first_name' => $first_name,
			'last_name'  => $order->get_billing_last_name(),
			'phone'      => $phone,
			'email'      => $order->get_billing_email(),
		);
		$existing = KYC_DB::get_customer_by_phone( $phone );
		$existing ? KYC_DB::update_customer( $existing['id'], $data ) : KYC_DB::insert_customer( $data );
	}
}
