<?php
/**
 * Plugin Name: KYC Capture System
 * Plugin URI:  https://github.com/tye0776/kyc-capture-system
 * Description: Modular KYC (Know Your Customer) system for SMEs — load only the features your business needs.
 * Version:     3.0.0
 * Author:      tye0776
 * Author URI:  https://github.com/tye0776
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kyc-capture-system
 * Domain Path: /languages
 *
 * @package KYC_Capture_System
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KYC_VERSION',    '3.0.0' );
define( 'KYC_PLUGIN_FILE', __FILE__ );
define( 'KYC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'KYC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Activation / Deactivation hooks
// -------------------------------------------------------------------------

function activate_kyc_capture_system() {
	require_once KYC_PLUGIN_DIR . 'admin/class-kyc-modules.php';
	require_once KYC_PLUGIN_DIR . 'includes/class-kyc-db.php';
	require_once KYC_PLUGIN_DIR . 'includes/class-kyc-activator.php';
	KYC_Activator::activate();
	// Flag to redirect to onboarding on next admin load
	if ( get_option( 'kyc_onboarding_complete' ) !== '1' ) {
		set_transient( 'kyc_redirect_to_onboarding', 1, 30 );
	}
}

function deactivate_kyc_capture_system() {
	require_once KYC_PLUGIN_DIR . 'includes/class-kyc-deactivator.php';
	KYC_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_kyc_capture_system' );
register_deactivation_hook( __FILE__, 'deactivate_kyc_capture_system' );

// -------------------------------------------------------------------------
// Bootstrap
// -------------------------------------------------------------------------

function run_kyc_capture_system() {

	// Always-loaded core files (needed in all contexts)
	require_once KYC_PLUGIN_DIR . 'admin/class-kyc-modules.php';
	require_once KYC_PLUGIN_DIR . 'includes/class-kyc-db.php';
	require_once KYC_PLUGIN_DIR . 'includes/class-kyc-gdpr.php';

	// Load only active module files (zero overhead for inactive ones)
	KYC_Modules::load();

	// Silent DB upgrade — only when version actually changes
	$stored_version = get_transient( 'kyc_db_version_check' );
	if ( $stored_version === false ) {
		$stored_version = get_option( 'kyc_db_version', '0' );
		set_transient( 'kyc_db_version_check', $stored_version, HOUR_IN_SECONDS );
	}
	if ( $stored_version !== KYC_VERSION ) {
		require_once KYC_PLUGIN_DIR . 'includes/class-kyc-activator.php';
		KYC_Activator::activate();
		update_option( 'kyc_db_version', KYC_VERSION );
		delete_transient( 'kyc_db_version_check' );
	}

	// Initialise all active modules (registers their hooks)
	KYC_Modules::init_all();

	$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

	// Admin context — only load admin class here (not on frontend or AJAX)
	if ( is_admin() && ! $is_ajax ) {
		require_once KYC_PLUGIN_DIR . 'admin/class-kyc-admin.php';
		$admin = new KYC_Admin();
		$admin->init();
	}

	// Frontend (not admin, not AJAX) — only load public class here
	if ( ! is_admin() && ! $is_ajax ) {
		require_once KYC_PLUGIN_DIR . 'public/class-kyc-public.php';
		$public = new KYC_Public();
		$public->init();
	}

	// Core AJAX handlers — only on AJAX requests
	if ( $is_ajax ) {
		require_once KYC_PLUGIN_DIR . 'public/class-kyc-ajax.php';
		( new KYC_Ajax() )->init();
	}

	// GDPR privacy API (needed in admin + AJAX, lightweight filter registration)
	if ( is_admin() || $is_ajax ) {
		add_filter( 'wp_privacy_personal_data_exporters', 'kyc_register_privacy_exporter' );
		add_filter( 'wp_privacy_personal_data_erasers',   'kyc_register_privacy_eraser' );
	}
}

run_kyc_capture_system();
