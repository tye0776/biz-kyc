<?php
/**
 * Customer Data Page View.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once KYC_PLUGIN_DIR . 'admin/class-kyc-list-table.php';

$list_table = new KYC_List_Table();
$list_table->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline">Customer Data</h1>

	<form method="post" action="" style="display:inline-block; margin-left:10px;">
		<?php wp_nonce_field( 'kyc_export_action', 'kyc_export_nonce' ); ?>
		<input type="hidden" name="kyc_export" value="1">
		<?php submit_button( 'Export to CSV', 'primary', 'submit', false ); ?>
	</form>

	<hr class="wp-header-end">

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? '' ); ?>" />
		<?php
		$list_table->search_box( 'Search Customers', 'search_id' );
		$list_table->display();
		?>
	</form>
</div>
