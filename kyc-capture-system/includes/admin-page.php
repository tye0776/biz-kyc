<?php
/**
 * Admin dashboard and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menu.
 */
function kyc_admin_menu() {
	add_menu_page(
		__( 'KYC Settings', 'kyc-capture-system' ),
		__( 'KYC Settings', 'kyc-capture-system' ),
		'manage_options',
		'kyc-settings',
		'kyc_settings_page',
		'dashicons-id',
		100
	);
}
add_action( 'admin_menu', 'kyc_admin_menu' );

/**
 * Register settings.
 */
function kyc_register_settings() {
	register_setting( 'kyc_settings_group', 'kyc_full_form_link', 'esc_url_raw' );
}
add_action( 'admin_init', 'kyc_register_settings' );

/**
 * Admin page HTML.
 */
function kyc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		
		<form method="post" action="options.php">
			<?php
			settings_fields( 'kyc_settings_group' );
			do_settings_sections( 'kyc_settings_group' );
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Full Form Link</th>
					<td>
						<input type="url" name="kyc_full_form_link" value="<?php echo esc_attr( get_option( 'kyc_full_form_link', '#' ) ); ?>" class="regular-text" />
						<p class="description">Enter the URL where users can complete their full profile.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<h2>Export Records</h2>
		<p>Download all KYC records as a CSV file.</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'kyc_export_nonce', 'kyc_export_field' ); ?>
			<input type="hidden" name="kyc_export" value="1">
			<?php submit_button( 'Export to CSV', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handle CSV Export.
 */
function kyc_handle_export() {
	if ( isset( $_POST['kyc_export'] ) && isset( $_POST['kyc_export_field'] ) && wp_verify_nonce( $_POST['kyc_export_field'], 'kyc_export_nonce' ) ) {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'kyc_list';
		$results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );

		if ( empty( $results ) ) {
			wp_die( 'No records found to export.' );
		}

		$filename = 'kyc_export_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		
		// Add BOM to fix UTF-8 in Excel
		fputs( $output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );

		// Add column headers
		$headers = array_keys( $results[0] );
		fputcsv( $output, $headers );

		// Add data rows
		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'kyc_handle_export' );
