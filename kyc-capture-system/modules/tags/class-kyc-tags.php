<?php
/**
 * Module: Tags — Customer tagging system.
 * Adds a `tags` column to wp_kyc_customers (provisioned by activator).
 * Admin: inline tag editor in the customer list.
 * No frontend changes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Tags {

	public static function init() {
		add_action( 'wp_ajax_kyc_update_tags', array( __CLASS__, 'handle_update' ) );
	}

	/**
	 * AJAX: save tags for a customer. Comma-separated, sanitized.
	 */
	public static function handle_update() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$raw_tags    = sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) );

		// Sanitize each tag individually
		$tags = implode( ',', array_filter( array_map( 'sanitize_text_field',
			explode( ',', $raw_tags )
		) ) );

		if ( ! $customer_id ) wp_send_json_error( array( 'message' => 'Invalid customer.' ) );

		// Security: verify customer actually exists before updating
		if ( ! KYC_DB::get_customer( $customer_id ) ) {
			wp_send_json_error( array( 'message' => 'Customer not found.' ) );
		}

		KYC_DB::update_customer( $customer_id, array( 'tags' => $tags ) );
		wp_send_json_success( array( 'tags' => $tags ) );
	}

	/**
	 * Render tags as coloured badges for the admin list table.
	 *
	 * @param string $tags_value Raw comma-separated tags string.
	 * @param int    $customer_id
	 * @return string HTML
	 */
	public static function render_tags_cell( $tags_value, $customer_id ) {
		$colours = array(
			'VIP'           => '#8b5cf6',
			'Wedding Client'=> '#ec4899',
			'Bulk Order'    => '#f59e0b',
			'Regular'       => '#10b981',
			'Corporate'     => '#3b82f6',
		);

		$out = '<div class="kyc-tags-wrap" data-id="' . esc_attr( $customer_id ) . '">';
		if ( ! empty( $tags_value ) ) {
			foreach ( explode( ',', $tags_value ) as $tag ) {
				$tag   = trim( $tag );
				$color = $colours[ $tag ] ?? '#6b7280';
				$out  .= '<span class="kyc-tag" style="background:' . esc_attr( $color ) . '">'
				       . esc_html( $tag ) . '</span>';
			}
		}
		$out .= '<button class="kyc-tag-edit" title="Edit tags" style="background:none;border:1px dashed #ccc;border-radius:3px;cursor:pointer;padding:1px 5px;font-size:11px;color:#888;">＋</button>';
		$out .= '</div>';
		return $out;
	}
}
