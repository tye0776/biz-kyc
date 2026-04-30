<?php
/**
 * Module: Referral Tracking.
 * Adds `referred_by` and `referral_source` columns (provisioned by activator).
 * Frontend: two optional fields on the profile page.
 * Admin: referral source shown in customer list.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Referrals {
	public static function init() {
		// Referral fields saved via core handle_profile_update AJAX (module-checked)
	}

	public static function render_cell( $source ) {
		return ! empty( $source ) ? esc_html( $source ) : '<span style="color:#aaa">—</span>';
	}
}
