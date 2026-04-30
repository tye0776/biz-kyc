<?php
/**
 * Fired during plugin deactivation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KYC_Deactivator {

	/**
	 * Deactivate plugin.
	 * We intentionally do NOT drop the custom database table here to prevent data loss.
	 */
	public static function deactivate() {
		// Clear daily reminder cron event
		$timestamp = wp_next_scheduled( 'kyc_daily_reminder_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'kyc_daily_reminder_check' );
		}

		// Clear scheduling transient so it re-registers correctly after reactivation
		delete_transient( 'kyc_cron_scheduled' );

		// Note: we intentionally do NOT call flush_rewrite_rules() here
		// because this plugin registers no custom post types or rewrite rules.
	}
}
