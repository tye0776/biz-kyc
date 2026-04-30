<?php
/**
 * Onboarding Wizard — shown once after plugin activation.
 * Pure PHP/HTML with minimal inline JS for step navigation. No extra HTTP requests.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

// Handle form submission
if ( isset( $_POST['kyc_wizard_submit'] ) && check_admin_referer( 'kyc_wizard_action' ) ) {
	$business_type = sanitize_text_field( $_POST['kyc_business_type'] ?? 'custom' );
	$presets       = KYC_Modules::get_presets();

	if ( $business_type === 'custom' ) {
		$raw_modules = $_POST['kyc_modules'] ?? array();
		$modules     = array_map( 'sanitize_text_field', (array) $raw_modules );
	} else {
		$modules = isset( $presets[ $business_type ] ) ? $presets[ $business_type ]['modules'] : array();
	}

	KYC_Modules::save( $modules );
	update_option( 'kyc_business_type', $business_type );
	update_option( 'kyc_onboarding_complete', '1' );

	wp_redirect( add_query_arg( array( 'page' => 'kyc-customers', 'kyc_welcome' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}

$presets  = KYC_Modules::get_presets();
$registry = KYC_Modules::get_registry();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KYC Setup — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<?php wp_head(); ?>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#f0f0f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1d2327}
.kyc-wizard-wrap{max-width:820px;margin:48px auto;padding:0 16px}
.kyc-wizard-header{text-align:center;margin-bottom:32px}
.kyc-wizard-header h1{font-size:28px;font-weight:700;color:#1d2327}
.kyc-wizard-header p{color:#646970;margin-top:8px;font-size:15px}
.kyc-wizard-logo{font-size:40px;margin-bottom:12px}
.kyc-step{display:none}.kyc-step.active{display:block}
.kyc-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.kyc-biz-card{background:#fff;border:2px solid #e0e0e0;border-radius:10px;padding:20px 16px;cursor:pointer;transition:border-color .15s,box-shadow .15s;text-align:center;position:relative}
.kyc-biz-card:hover{border-color:#0073aa;box-shadow:0 2px 8px rgba(0,115,170,.15)}
.kyc-biz-card.selected{border-color:#0073aa;background:#f0f7fc}
.kyc-biz-card input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.kyc-biz-card .icon{font-size:32px;display:block;margin-bottom:8px}
.kyc-biz-card strong{font-size:15px;display:block;margin-bottom:4px}
.kyc-biz-card small{color:#646970;font-size:12px;line-height:1.4}
.kyc-module-list{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px}
.kyc-module-item{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px;cursor:pointer;transition:border-color .15s}
.kyc-module-item:hover{border-color:#0073aa}
.kyc-module-item input[type=checkbox]{margin-top:2px;flex-shrink:0;width:18px;height:18px;cursor:pointer}
.kyc-module-item .micon{font-size:22px;flex-shrink:0}
.kyc-module-item .mtxt strong{display:block;font-size:13px;margin-bottom:2px}
.kyc-module-item .mtxt small{color:#646970;font-size:12px;line-height:1.3}
.kyc-module-item.checked{border-color:#0073aa;background:#f0f7fc}
.kyc-actions{display:flex;justify-content:space-between;align-items:center;margin-top:16px}
.kyc-btn{padding:10px 24px;border-radius:5px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s}
.kyc-btn-primary{background:#0073aa;color:#fff}.kyc-btn-primary:hover{opacity:.9}
.kyc-btn-ghost{background:transparent;color:#0073aa;border:2px solid #0073aa}.kyc-btn-ghost:hover{background:#f0f7fc}
.kyc-step-indicator{display:flex;justify-content:center;gap:8px;margin-bottom:32px}
.kyc-dot{width:10px;height:10px;border-radius:50%;background:#ddd;transition:background .2s}
.kyc-dot.active{background:#0073aa}
.kyc-panel{background:#fff;border-radius:12px;padding:32px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.kyc-panel h2{font-size:20px;margin-bottom:6px}
.kyc-panel>p{color:#646970;font-size:14px;margin-bottom:24px}
@media(max-width:600px){.kyc-module-list{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="kyc-wizard-wrap">

	<div class="kyc-wizard-header">
		<div class="kyc-wizard-logo">🏪</div>
		<h1>Welcome to KYC Capture System</h1>
		<p>Answer 2 quick questions so we can load only the features your business needs.</p>
	</div>

	<div class="kyc-step-indicator">
		<div class="kyc-dot active" id="dot-1"></div>
		<div class="kyc-dot" id="dot-2"></div>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'kyc_wizard_action' ); ?>
		<input type="hidden" name="kyc_wizard_submit" value="1">
		<input type="hidden" name="kyc_business_type" id="kyc_business_type_hidden" value="food_bakery">

		<!-- STEP 1: Choose business type -->
		<div class="kyc-step active" id="step-1">
			<div class="kyc-panel">
				<h2>What type of business do you run?</h2>
				<p>We'll automatically select the right features for you. You can adjust them in the next step.</p>

				<div class="kyc-card-grid">
					<?php foreach ( $presets as $key => $preset ) : ?>
					<label class="kyc-biz-card <?php echo $key === 'food_bakery' ? 'selected' : ''; ?>" data-type="<?php echo esc_attr( $key ); ?>">
						<input type="radio" name="_biz_radio" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, 'food_bakery' ); ?>>
						<span class="icon"><?php echo esc_html( substr( $preset['label'], 0, 2 ) ); ?></span>
						<strong><?php echo esc_html( preg_replace('/^[^\s]+ /', '', $preset['label'] ) ); ?></strong>
						<small><?php echo esc_html( $preset['description'] ); ?></small>
					</label>
					<?php endforeach; ?>
				</div>

				<div class="kyc-actions">
					<span style="color:#646970;font-size:13px;">Step 1 of 2</span>
					<button type="button" class="kyc-btn kyc-btn-primary" onclick="goToStep2()">Next →</button>
				</div>
			</div>
		</div>

		<!-- STEP 2: Confirm / adjust modules -->
		<div class="kyc-step" id="step-2">
			<div class="kyc-panel">
				<h2>Confirm your features</h2>
				<p>We've pre-selected the best features for your business. Tick or untick to customise.</p>

				<div class="kyc-module-list" id="module-list">
					<?php foreach ( $registry as $key => $module ) : ?>
					<label class="kyc-module-item" id="module-item-<?php echo esc_attr( $key ); ?>">
						<input type="checkbox" name="kyc_modules[]" value="<?php echo esc_attr( $key ); ?>"
							id="mod_<?php echo esc_attr( $key ); ?>">
						<span class="micon"><?php echo esc_html( $module['icon'] ); ?></span>
						<span class="mtxt">
							<strong><?php echo esc_html( $module['label'] ); ?></strong>
							<small><?php echo esc_html( $module['description'] ); ?></small>
						</span>
					</label>
					<?php endforeach; ?>
				</div>

				<div class="kyc-actions">
					<button type="button" class="kyc-btn kyc-btn-ghost" onclick="goToStep1()">← Back</button>
					<button type="submit" class="kyc-btn kyc-btn-primary">Finish Setup 🎉</button>
				</div>
			</div>
		</div>

	</form>
</div>

<script>
const presets = <?php echo wp_json_encode( array_map( fn($p) => $p['modules'], $presets ) ); ?>;

// Business type card selection
document.querySelectorAll('.kyc-biz-card').forEach(function(card) {
	card.addEventListener('click', function() {
		document.querySelectorAll('.kyc-biz-card').forEach(c => c.classList.remove('selected'));
		card.classList.add('selected');
		const type = card.dataset.type;
		document.getElementById('kyc_business_type_hidden').value = type;
	});
});

// Sync checkbox styling
document.querySelectorAll('.kyc-module-item input[type=checkbox]').forEach(function(cb) {
	cb.addEventListener('change', function() {
		cb.closest('.kyc-module-item').classList.toggle('checked', cb.checked);
	});
});

function goToStep2() {
	const type = document.getElementById('kyc_business_type_hidden').value;
	const active = presets[type] || [];
	// Update checkboxes to match preset
	document.querySelectorAll('.kyc-module-item input[type=checkbox]').forEach(function(cb) {
		const checked = active.includes(cb.value);
		cb.checked = checked;
		cb.closest('.kyc-module-item').classList.toggle('checked', checked);
	});
	document.getElementById('step-1').classList.remove('active');
	document.getElementById('step-2').classList.add('active');
	document.getElementById('dot-1').classList.remove('active');
	document.getElementById('dot-2').classList.add('active');
}

function goToStep1() {
	document.getElementById('step-2').classList.remove('active');
	document.getElementById('step-1').classList.add('active');
	document.getElementById('dot-2').classList.remove('active');
	document.getElementById('dot-1').classList.add('active');
}
</script>
</body>
</html>
