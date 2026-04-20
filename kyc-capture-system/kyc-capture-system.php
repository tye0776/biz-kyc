<?php
/**
 * Plugin Name: KYC Capture System
 * Plugin URI:  https://example.com/kyc-capture-system
 * Description: A production-ready KYC (Know Your Customer) data capture system with popup form, AJAX submission, admin dashboard, and CSV export.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kyc-capture-system
 * Domain Path: /languages
 *
 * @package KYC_Capture_System
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'KYC_VERSION', '1.0.0' );
define( 'KYC_PLUGIN_FILE', __FILE__ );
define( 'KYC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KYC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once KYC_PLUGIN_DIR . 'includes/db.php';
require_once KYC_PLUGIN_DIR . 'includes/form.php';
require_once KYC_PLUGIN_DIR . 'includes/ajax-handler.php';
require_once KYC_PLUGIN_DIR . 'includes/admin-page.php';

/**
 * Plugin activation hook.
 * Runs the database table creation routine.
 */
function kyc_activate() {
	kyc_create_table();
	kyc_create_default_page();
	// Flush rewrite rules after activation.
	flush_rewrite_rules();
}
register_activation_hook( KYC_PLUGIN_FILE, 'kyc_activate' );

/**
 * Creates the default KYC page on activation.
 */
function kyc_create_default_page() {
	if ( get_option( 'kyc_default_page_created' ) ) {
		return;
	}

	$page_title = 'KYC Capture Profile';
	$page_content = '<!-- wp:shortcode -->[kyc_form]<!-- /wp:shortcode -->';

	$page_check = get_page_by_title( $page_title );
	
	if ( ! isset( $page_check->ID ) ) {
		$page_data = array(
			'post_title'    => $page_title,
			'post_content'  => $page_content,
			'post_status'   => 'publish',
			'post_type'     => 'page',
			'post_author'   => 1,
		);
		$page_id = wp_insert_post( $page_data );
		
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'kyc_default_page_created', $page_id );
		}
	} else {
		update_option( 'kyc_default_page_created', $page_check->ID );
	}
}

/**
 * Plugin deactivation hook.
 */
function kyc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( KYC_PLUGIN_FILE, 'kyc_deactivate' );

/**
 * Load plugin textdomain for translations.
 */
function kyc_load_textdomain() {
	load_plugin_textdomain(
		'kyc-capture-system',
		false,
		dirname( plugin_basename( KYC_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'kyc_load_textdomain' );
