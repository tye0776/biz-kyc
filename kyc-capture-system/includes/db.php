<?php
/**
 * Database operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the plugin database table.
 */
function kyc_create_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'kyc_list';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		first_name varchar(100) NOT NULL,
		last_name varchar(100) DEFAULT '' NOT NULL,
		phone_number varchar(20) NOT NULL,
		email varchar(100) DEFAULT '' NOT NULL,
		date_of_birth date NOT NULL,
		gender varchar(20) DEFAULT '' NOT NULL,
		consent tinyint(1) NOT NULL,
		date_added datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY phone_number (phone_number)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
