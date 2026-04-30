<?php
/**
 * Fired during plugin activation. Builds tables dynamically based on active modules.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Activator {

	public static function activate() {
		self::migrate_and_create_table();
		self::create_default_page();
	}

	private static function migrate_and_create_table() {
		global $wpdb;
		$table           = $wpdb->prefix . 'kyc_customers';
		$old_table       = $wpdb->prefix . 'kyc_list';
		$charset_collate = $wpdb->get_charset_collate();

		// ---- Build column list dynamically based on active modules ----
		$base_columns = "
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) DEFAULT NULL,
			phone varchar(20) NOT NULL,
			email varchar(100) DEFAULT NULL,
			dob date DEFAULT NULL,
			gender varchar(20) DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL";

		// Module columns — only appended if module is active
		$module_columns = '';
		if ( class_exists( 'KYC_Modules' ) ) {
			foreach ( KYC_Modules::get_active_columns() as $col_def ) {
				$module_columns .= ",\n\t\t\t" . $col_def;
			}
		}

		$sql = "CREATE TABLE $table ($base_columns$module_columns,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// ---- Linked contacts table — only if family_graph module is active ----
		$needs_contacts = ! class_exists( 'KYC_Modules' ) || KYC_Modules::is_active( 'family_graph' );
		if ( $needs_contacts ) {
			$contacts_table = $wpdb->prefix . 'kyc_linked_contacts';
			$contacts_sql   = "CREATE TABLE $contacts_table (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				customer_id mediumint(9) NOT NULL,
				linked_name varchar(100) NOT NULL,
				linked_phone varchar(20) NOT NULL,
				relationship varchar(100) NOT NULL DEFAULT 'Other',
				dob date DEFAULT NULL,
				anniversary date DEFAULT NULL,
				linked_customer_id mediumint(9) DEFAULT NULL,
				created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY customer_linked_phone (customer_id, linked_phone)
			) $charset_collate;";
			dbDelta( $contacts_sql );
		}

		// ---- Legacy data migration (one-time) ----
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_table'" ) === $old_table ) {
			$rows = $wpdb->get_results( "SELECT * FROM $old_table", ARRAY_A );
			foreach ( (array) $rows as $r ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE phone = %s", $r['phone_number'] ) );
				if ( ! $exists ) {
					$wpdb->insert( $table, array(
						'first_name' => $r['first_name'],
						'last_name'  => $r['last_name'] ?? null,
						'phone'      => $r['phone_number'],
						'email'      => ! empty( $r['email'] ) ? $r['email'] : null,
						'dob'        => $r['date_of_birth'] ?? null,
						'gender'     => $r['gender'] ?? null,
						'created_at' => $r['date_added'],
						'updated_at' => $r['date_added'],
					), array( '%s','%s','%s','%s','%s','%s','%s','%s' ) );
				}
			}
			$wpdb->query( "DROP TABLE IF EXISTS $old_table" );
		}
	}

	private static function create_default_page() {
		if ( get_option( 'kyc_default_page_created' ) ) return;

		$existing = new WP_Query( array(
			'post_type'              => 'page',
			'post_status'            => array( 'publish', 'draft', 'private' ),
			'title'                  => 'My KYC Profile',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		if ( $existing->have_posts() ) {
			update_option( 'kyc_default_page_created', $existing->posts[0]->ID );
			return;
		}

		$id = wp_insert_post( array(
			'post_title'   => 'My KYC Profile',
			'post_content' => '<!-- wp:shortcode -->[kyc_form]<!-- /wp:shortcode -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => 1,
		) );

		if ( $id && ! is_wp_error( $id ) ) update_option( 'kyc_default_page_created', $id );
	}
}
