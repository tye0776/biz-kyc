<?php
/**
 * Settings Page View.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
				<th scope="row">Enable Popup</th>
				<td>
					<label>
						<input type="checkbox" name="kyc_enable_popup" value="1" <?php checked( 1, get_option( 'kyc_enable_popup', 1 ), true ); ?> />
						Show popup on the frontend
					</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Popup Delay (seconds)</th>
				<td>
					<input type="number" name="kyc_popup_delay" value="<?php echo esc_attr( get_option( 'kyc_popup_delay', 3 ) ); ?>" class="small-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Main Theme Color</th>
				<td>
					<input type="color" name="kyc_main_color" value="<?php echo esc_attr( get_option( 'kyc_main_color', '#0073aa' ) ); ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Accent Theme Color</th>
				<td>
					<input type="color" name="kyc_accent_color" value="<?php echo esc_attr( get_option( 'kyc_accent_color', '#005177' ) ); ?>" />
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
					<input type="text" name="kyc_btn_text" value="<?php echo esc_attr( get_option( 'kyc_btn_text', 'Complete KYC' ) ); ?>" class="regular-text" />
					<p class="description">Leave blank to show only the icon.</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Floating Button Icon (Dashicons)</th>
				<td>
					<input type="text" name="kyc_btn_icon" value="<?php echo esc_attr( get_option( 'kyc_btn_icon', 'dashicons-id-alt' ) ); ?>" class="regular-text" />
					<p class="description">Enter a valid Dashicons class (e.g. <code>dashicons-id-alt</code>, <code>dashicons-edit</code>).</p>
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
			<?php if ( KYC_Modules::is_active( 'integrations' ) ) : ?>
			<tr valign="top">
				<th scope="row">Webhook URL (Zapier/Make)</th>
				<td>
					<input type="url" name="kyc_webhook_url" value="<?php echo esc_attr( get_option( 'kyc_webhook_url', '' ) ); ?>" class="regular-text" placeholder="https://hooks.zapier.com/..." />
					<p class="description">Automatically send new customer captures to this URL.</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<?php if ( KYC_Modules::is_active( 'reminders' ) ) : ?>
		<h2>🎂 Birthday &amp; Anniversary Reminder Settings</h2>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Notification Email</th>
				<td>
					<input type="email" name="kyc_notification_email" value="<?php echo esc_attr( get_option( 'kyc_notification_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
					<p class="description">Shop owner email address to receive birthday &amp; anniversary reminders. Defaults to WordPress admin email.</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Reminder Lead Days</th>
				<td>
					<input type="number" name="kyc_reminder_lead_days" value="<?php echo esc_attr( get_option( 'kyc_reminder_lead_days', 3 ) ); ?>" class="small-text" min="1" max="30" />
					<p class="description">Send email reminders this many days before a birthday or anniversary (default: 3).</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Upcoming Dates Look-Ahead (days)</th>
				<td>
					<input type="number" name="kyc_reminder_look_ahead" value="<?php echo esc_attr( get_option( 'kyc_reminder_look_ahead', 30 ) ); ?>" class="small-text" min="7" max="90" />
					<p class="description">How many days ahead to show events on the Upcoming Dates admin page (default: 30).</p>
				</td>
			</tr>
		</table>
		<?php endif; ?>
		<?php submit_button(); ?>
	</form>
</div>
