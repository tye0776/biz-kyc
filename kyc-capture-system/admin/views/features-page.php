<?php
/**
 * Admin view: Manage Features (module toggle page).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

$registry      = KYC_Modules::get_registry();
$presets       = KYC_Modules::get_presets();
$active        = KYC_Modules::get_active();
$business_type = get_option( 'kyc_business_type', 'custom' );
$saved         = isset( $_GET['saved'] );
?>
<div class="wrap">
	<h1>⚙️ Manage Features</h1>
	<p>Enable or disable modules at any time. Deactivating a module hides its UI but <strong>does not delete any data</strong>.</p>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible"><p>✅ Features saved successfully.</p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'kyc_save_features_action' ); ?>
		<input type="hidden" name="kyc_save_features" value="1">

		<table class="form-table" style="max-width:700px">
			<tr>
				<th scope="row">Business Type</th>
				<td>
					<select name="kyc_business_type" id="kyc_preset_select" onchange="applyPreset(this.value)">
						<?php foreach ( $presets as $key => $preset ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $business_type, $key ); ?>>
							<?php echo esc_html( $preset['label'] ); ?> — <?php echo esc_html( $preset['description'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Changing this applies the preset module selection below.</p>
				</td>
			</tr>
		</table>

		<h2>Active Modules</h2>
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:700px;margin-bottom:24px">
			<?php foreach ( $registry as $key => $module ) :
				$checked = in_array( $key, $active, true );
			?>
			<label id="mod-<?php echo esc_attr( $key ); ?>" style="display:flex;align-items:flex-start;gap:12px;background:#fff;border:1px solid <?php echo $checked ? '#0073aa' : '#e0e0e0'; ?>;border-radius:8px;padding:14px;cursor:pointer;transition:border-color .15s">
				<input type="checkbox" name="kyc_modules[]" value="<?php echo esc_attr( $key ); ?>"
					<?php checked( $checked ); ?> style="margin-top:2px;width:18px;height:18px">
				<span>
					<span style="font-size:22px;display:block;margin-bottom:4px"><?php echo esc_html( $module['icon'] ); ?></span>
					<strong style="font-size:13px"><?php echo esc_html( $module['label'] ); ?></strong><br>
					<small style="color:#646970"><?php echo esc_html( $module['description'] ); ?></small>
				</span>
			</label>
			<?php endforeach; ?>
		</div>

		<?php submit_button( 'Save Features' ); ?>
	</form>
</div>
<script>
const presets = <?php echo wp_json_encode( array_map( fn($p) => $p['modules'], $presets ) ); ?>;
function applyPreset(type) {
	const mods = presets[type] || [];
	document.querySelectorAll('input[name="kyc_modules[]"]').forEach(function(cb) {
		cb.checked = mods.includes(cb.value);
	});
}
</script>
