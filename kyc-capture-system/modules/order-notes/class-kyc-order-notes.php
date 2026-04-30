<?php
/**
 * Module: Order Notes / Customer Preferences.
 * Adds a `preferences` text column (provisioned by activator).
 * Frontend: textarea on profile page.
 * Admin: truncated note in customer list.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Order_Notes {

	public static function init() {
		// Preferences field is saved via the core handle_profile_update AJAX
		// (class-kyc-ajax.php checks KYC_Modules::is_active before reading the field)
	}

	/**
	 * Render preferences cell for admin list.
	 */
	public static function render_cell( $value ) {
		if ( empty( $value ) ) return '<span style="color:#aaa">—</span>';
		$short = wp_trim_words( $value, 8, '...' );
		return '<span title="' . esc_attr( $value ) . '">' . esc_html( $short ) . '</span>';
	}
}
