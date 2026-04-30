<?php
/**
 * WP_List_Table for Customer Data — module-aware columns.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class KYC_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'customer', 'plural' => 'customers', 'ajax' => false ) );
	}

	public function get_columns() {
		$cols = array(
			'cb'         => '<input type="checkbox" />',
			'first_name' => 'First Name',
			'last_name'  => 'Last Name',
			'phone'      => 'Phone',
			'email'      => 'Email',
		);
		if ( KYC_Modules::is_active( 'tags' ) )        $cols['tags']           = 'Tags';
		if ( KYC_Modules::is_active( 'order_notes' ) ) $cols['preferences']    = 'Preferences';
		if ( KYC_Modules::is_active( 'loyalty' ) )     $cols['loyalty_points'] = '⭐ Loyalty';
		if ( KYC_Modules::is_active( 'referrals' ) )   $cols['referral_source']= 'Referral';
		$cols['created_at'] = 'Date Created';
		return $cols;
	}

	public function get_sortable_columns() {
		return array(
			'first_name' => array( 'first_name', false ),
			'created_at' => array( 'created_at', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'first_name':
			case 'last_name':
			case 'email':
			case 'created_at':
				return esc_html( $item[ $column_name ] ?? '' );
			case 'phone':
				$clean  = preg_replace( '/[^0-9+]/', '', $item['phone'] );
				$wa     = 'https://wa.me/' . ltrim( $clean, '+' );
				return esc_html( $item['phone'] )
					. ' <a href="' . esc_url( $wa ) . '" target="_blank" title="Chat on WhatsApp" style="text-decoration:none">'
					. '<span class="dashicons dashicons-whatsapp" style="color:#25D366;vertical-align:middle"></span></a>';
			case 'tags':
				return KYC_Tags::render_tags_cell( $item['tags'] ?? '', $item['id'] );
			case 'preferences':
				return KYC_Order_Notes::render_cell( $item['preferences'] ?? '' );
			case 'loyalty_points':
				return KYC_Loyalty::render_cell( $item['loyalty_points'] ?? 0, $item['id'] );
			case 'referral_source':
				return KYC_Referrals::render_cell( $item['referral_source'] ?? '' );
			default:
				return print_r( $item, true );
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="customer[]" value="%s" />', $item['id'] );
	}

	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'kyc_customers';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 20, 'total_pages' => 0 ) );
			return;
		}

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$where        = '';

		if ( ! empty( $search ) ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( ' WHERE first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR email LIKE %s', $like, $like, $like, $like );
		}

		$allowed_orderby = array( 'first_name', 'created_at' );
		$orderby = in_array( $_REQUEST['orderby'] ?? '', $allowed_orderby, true ) ? $_REQUEST['orderby'] : 'created_at';
		$order   = strtoupper( $_REQUEST['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC';

		$total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table $where" );
		$items = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY $orderby $order LIMIT $per_page OFFSET " . ( ( $current_page - 1 ) * $per_page ), ARRAY_A );

		$this->items = $items;
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page, 'total_pages' => ceil( $total / $per_page ) ) );
	}
}
