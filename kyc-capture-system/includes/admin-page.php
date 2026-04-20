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
	$page_hook = add_menu_page(
		__( 'KYC Settings', 'kyc-capture-system' ),
		__( 'KYC Settings', 'kyc-capture-system' ),
		'manage_options',
		'kyc-settings',
		'kyc_settings_page',
		'dashicons-id',
		100
	);

	add_action( "admin_print_scripts-{$page_hook}", 'kyc_admin_enqueue_color_picker' );
}
add_action( 'admin_menu', 'kyc_admin_menu' );

function kyc_admin_enqueue_color_picker() {
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
}

/**
 * Register settings.
 */
function kyc_register_settings() {
	register_setting( 'kyc_settings_group', 'kyc_main_color', 'sanitize_hex_color' );
	register_setting( 'kyc_settings_group', 'kyc_accent_color', 'sanitize_hex_color' );
	register_setting( 'kyc_settings_group', 'kyc_btn_position', 'sanitize_text_field' );
	register_setting( 'kyc_settings_group', 'kyc_btn_text', 'sanitize_text_field' );
	register_setting( 'kyc_settings_group', 'kyc_btn_icon', 'sanitize_text_field' );
	register_setting( 'kyc_settings_group', 'kyc_additional_fields', 'kyc_sanitize_array' );
}
add_action( 'admin_init', 'kyc_register_settings' );

function kyc_sanitize_array( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}
	return array_map( 'sanitize_text_field', $input );
}

/**
 * Admin page HTML.
 */
function kyc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$additional_fields = get_option( 'kyc_additional_fields', array() );
	if ( ! is_array( $additional_fields ) ) $additional_fields = array();

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
					<th scope="row">Main Color</th>
					<td>
						<input type="text" name="kyc_main_color" value="<?php echo esc_attr( get_option( 'kyc_main_color', '#0073aa' ) ); ?>" class="kyc-color-picker" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Accent Color</th>
					<td>
						<input type="text" name="kyc_accent_color" value="<?php echo esc_attr( get_option( 'kyc_accent_color', '#005177' ) ); ?>" class="kyc-color-picker" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Floating Button Position</th>
					<td>
						<select name="kyc_btn_position">
							<?php
							$positions = array(
								'bottom-right' => 'Bottom Right',
								'bottom-left'  => 'Bottom Left',
								'center-right' => 'Middle Right',
								'center-left'  => 'Middle Left',
							);
							$current_pos = get_option( 'kyc_btn_position', 'bottom-right' );
							foreach ( $positions as $val => $label ) {
								echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current_pos, $val, false ) . '>' . esc_html( $label ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Floating Button Text</th>
					<td>
						<input type="text" name="kyc_btn_text" value="<?php echo esc_attr( get_option( 'kyc_btn_text', '' ) ); ?>" class="regular-text" placeholder="e.g. Complete KYC" />
						<p class="description">Leave blank to show only the icon.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Floating Button Icon (Dashicons)</th>
					<td>
						<input type="text" name="kyc_btn_icon" value="<?php echo esc_attr( get_option( 'kyc_btn_icon', 'dashicons-id-alt' ) ); ?>" class="regular-text" />
						<p class="description">Enter a valid Dashicons class (e.g. <code>dashicons-id-alt</code>, <code>dashicons-edit</code>). <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">View Dashicons</a>.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Additional Fields (Full Profile)</th>
					<td>
						<p>Select which optional fields to include on the full profile page:</p>
						<label>
							<input type="checkbox" name="kyc_additional_fields[]" value="last_name" <?php checked( in_array( 'last_name', $additional_fields ) ); ?>>
							Last Name
						</label><br>
						<label>
							<input type="checkbox" name="kyc_additional_fields[]" value="gender" <?php checked( in_array( 'gender', $additional_fields ) ); ?>>
							Gender
						</label><br>
						<label>
							<input type="checkbox" name="kyc_additional_fields[]" value="dob" <?php checked( in_array( 'dob', $additional_fields ) ); ?>>
							Date of Birth (Day & Month)
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<h2>Export Records</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'kyc_export_nonce', 'kyc_export_field' ); ?>
			<input type="hidden" name="kyc_export" value="1">
			<?php submit_button( 'Export to CSV', 'secondary', 'submit', false ); ?>
		</form>
	</div>

	<script>
	jQuery(document).ready(function($){
		if(typeof $.fn.wpColorPicker === 'function') {
			$('.kyc-color-picker').wpColorPicker();
		}
	});
	</script>
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
		
		fputs( $output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );

		$headers = array_keys( $results[0] );
		fputcsv( $output, $headers );

		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'kyc_handle_export' );
