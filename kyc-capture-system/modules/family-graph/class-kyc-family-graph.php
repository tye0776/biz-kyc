<?php
/**
 * Module: Family Graph — init class.
 * Actual AJAX logic is in class-kyc-contacts-ajax.php (loaded separately on AJAX requests).
 * Frontend HTML is rendered by KYC_Public conditionally.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Family_Graph {
	public static function init() {
		// Hooks for this module are handled by:
		// - KYC_Public::enqueue_assets() for JS (checks KYC_Modules::is_active)
		// - KYC_Public::render_shortcode() for HTML section
		// - KYC_Contacts_Ajax (loaded by KYC_Modules::init_all on AJAX requests)
	}
}
