<?php
/**
 * Admin logic — module-aware menus, settings, onboarding redirect.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Admin {

	public function init() {
		add_action( 'admin_menu',       array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init',       array( $this, 'register_settings' ) );
		add_action( 'admin_init',       array( $this, 'handle_csv_export' ) );
		add_action( 'admin_init',       array( $this, 'maybe_redirect_to_onboarding' ) );
		add_action( 'admin_init',       array( $this, 'handle_manage_features' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_notices',    array( $this, 'welcome_notice' ) );
		add_action( 'wp_ajax_kyc_dismiss_widget', array( $this, 'ajax_dismiss_widget' ) );
		// Enqueue admin JS for Tags + Loyalty inline interactions
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function maybe_redirect_to_onboarding() {
		if ( get_transient( 'kyc_redirect_to_onboarding' ) && get_option( 'kyc_onboarding_complete' ) !== '1' ) {
			delete_transient( 'kyc_redirect_to_onboarding' );
			wp_redirect( admin_url( 'admin.php?page=kyc-onboarding' ) );
			exit;
		}
	}

	public function ajax_dismiss_widget() {
		check_ajax_referer( 'kyc_action_nonce', 'security' );
		update_user_meta( get_current_user_id(), 'kyc_dismiss_dashboard_widget', 1 );
		wp_send_json_success();
	}

	public function welcome_notice() {
		if ( isset( $_GET['kyc_welcome'] ) && '1' === $_GET['kyc_welcome'] ) {
			$count = count( KYC_Modules::get_active() );
			echo '<div class="notice notice-success is-dismissible"><p>🎉 <strong>KYC Capture System is ready!</strong> '
				. esc_html( $count ) . ' modules activated. <a href="' . esc_url( admin_url( 'admin.php?page=kyc-customers' ) ) . '">View Customers</a></p></div>';
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'kyc' ) === false ) return;
		wp_enqueue_style( 'dashicons' );

		$has_tags    = KYC_Modules::is_active( 'tags' );
		$has_loyalty = KYC_Modules::is_active( 'loyalty' );

		if ( $has_tags || $has_loyalty || strpos( $hook, 'dashboard' ) !== false || strpos( $hook, 'index.php' ) !== false ) {
			wp_localize_script( 'jquery', 'kycAdminObj', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'kyc_action_nonce' ),
			) );

			$inline_parts = array();
			
			$inline_parts[] = "
$(document).on('click','#kyc-dismiss-widget',function(e){
  e.preventDefault();
  var wrap = $(this).closest('.postbox');
  $.post(kycAdminObj.ajaxurl,{action:'kyc_dismiss_widget',security:kycAdminObj.nonce},function(r){if(r.success) wrap.remove();});
});";

			if ( $has_tags ) {
				$inline_parts[] = "
$(document).on('click','.kyc-tag-edit',function(){
  var wrap=$(this).closest('.kyc-tags-wrap'),id=wrap.data('id');
  var cur=wrap.find('.kyc-tag').map(function(){return $(this).text();}).get().join(', ');
  var val=prompt('Edit tags (comma-separated):',cur);
  if(val===null)return;
  $.post(kycAdminObj.ajaxurl,{action:'kyc_update_tags',security:kycAdminObj.nonce,customer_id:id,tags:val},function(r){if(r.success)location.reload();});
});";
			}
			if ( $has_loyalty ) {
				$inline_parts[] = "
$(document).on('click','.kyc-loyalty-add',function(){
  var id=$(this).data('id'),$pts=$(this).prev('.kyc-loyalty-pts');
  $.post(kycAdminObj.ajaxurl,{action:'kyc_increment_loyalty',security:kycAdminObj.nonce,customer_id:id},function(r){if(r.success)$pts.find('small').text('('+r.data.points+')');});
});";
			}

			wp_add_inline_script( 'jquery', 'jQuery(function($){' . implode( '', $inline_parts ) . '});' );
		}
	}

	public function add_plugin_admin_menu() {
		// Core pages — always shown
		add_menu_page( 'Customer Data', 'Customer Data', 'manage_options', 'kyc-customers',
			array( $this, 'display_data_page' ), 'dashicons-groups', 30 );
		add_submenu_page( 'kyc-customers', 'Customers',   'Customers',   'manage_options', 'kyc-customers',  array( $this, 'display_data_page' ) );
		add_submenu_page( 'kyc-customers', 'KYC Settings','Settings',    'manage_options', 'kyc-settings',   array( $this, 'display_settings_page' ) );
		add_submenu_page( 'kyc-customers', 'Manage Features', '⚙️ Features', 'manage_options', 'kyc-features', array( $this, 'display_features_page' ) );

		// Hidden onboarding page (no parent = not in menu)
		add_submenu_page( null, 'KYC Setup', 'KYC Setup', 'manage_options', 'kyc-onboarding', array( $this, 'display_onboarding' ) );

		// Module-conditional submenus
		if ( KYC_Modules::is_active( 'family_graph' ) ) {
			add_submenu_page( 'kyc-customers', 'Linked Contacts', 'Linked Contacts', 'manage_options', 'kyc-linked-contacts', array( $this, 'display_linked_contacts_page' ) );
		}
		if ( KYC_Modules::is_active( 'reminders' ) ) {
			add_submenu_page( 'kyc-customers', 'Upcoming Dates', '🎂 Upcoming Dates', 'manage_options', 'kyc-upcoming-dates', array( $this, 'display_upcoming_dates_page' ) );
		}
	}

	public function register_settings() {
		// Core settings
		foreach ( array(
			'kyc_enable_popup'      => 'absint',
			'kyc_popup_delay'       => 'absint',
			'kyc_main_color'        => 'sanitize_hex_color',
			'kyc_accent_color'      => 'sanitize_hex_color',
			'kyc_btn_position'      => 'sanitize_text_field',
			'kyc_btn_text'          => 'sanitize_text_field',
			'kyc_btn_icon'          => 'sanitize_text_field',
			'kyc_additional_fields' => array( $this, 'sanitize_array' ),
		) as $key => $cb ) {
			register_setting( 'kyc_settings_group', $key, $cb );
		}
		// Module-conditional settings
		if ( KYC_Modules::is_active( 'integrations' ) ) {
			register_setting( 'kyc_settings_group', 'kyc_webhook_url', 'esc_url_raw' );
		}
		if ( KYC_Modules::is_active( 'reminders' ) ) {
			register_setting( 'kyc_settings_group', 'kyc_notification_email',  'sanitize_email' );
			register_setting( 'kyc_settings_group', 'kyc_reminder_lead_days',  'absint' );
			register_setting( 'kyc_settings_group', 'kyc_reminder_look_ahead', 'absint' );
		}
	}

	public function sanitize_array( $input ) {
		return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : array();
	}

	public function handle_manage_features() {
		if ( ! isset( $_POST['kyc_save_features'] ) ) return;
		check_admin_referer( 'kyc_save_features_action' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		$raw     = $_POST['kyc_modules'] ?? array();
		$modules = array_map( 'sanitize_text_field', (array) $raw );
		KYC_Modules::save( $modules );
		update_option( 'kyc_business_type', sanitize_text_field( $_POST['kyc_business_type'] ?? 'custom' ) );

		wp_redirect( add_query_arg( array( 'page' => 'kyc-features', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ---- Page renderers ----

	public function display_onboarding() {
		require_once KYC_PLUGIN_DIR . 'admin/views/onboarding-wizard.php';
	}

	public function display_data_page() {
		require_once KYC_PLUGIN_DIR . 'admin/views/data-page.php';
	}

	public function display_settings_page() {
		require_once KYC_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public function display_features_page() {
		require_once KYC_PLUGIN_DIR . 'admin/views/features-page.php';
	}

	public function display_linked_contacts_page() {
		require_once KYC_PLUGIN_DIR . 'admin/views/linked-contacts-page.php';
	}

	public function display_upcoming_dates_page() {
		require_once KYC_PLUGIN_DIR . 'admin/views/upcoming-dates-page.php';
	}

	// ---- Dashboard widget ----

	public function add_dashboard_widgets() {
		if ( get_user_meta( get_current_user_id(), 'kyc_dismiss_dashboard_widget', true ) ) return;
		wp_add_dashboard_widget( 'kyc_capture_dashboard_widget', 'KYC Capture Stats', array( $this, 'dashboard_widget_content' ) );
	}

	public function dashboard_widget_content() {
		global $wpdb;
		$table = KYC_DB::get_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) { echo '<p>KYC Database not initialized yet.</p>'; return; }
		$total = $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
		$week  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE created_at >= %s", date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );
		echo '<div style="display:flex;gap:20px;text-align:center">'
			. '<div style="flex:1;padding:15px;background:#f0f0f1;border-radius:5px"><h3 style="margin:0;font-size:24px;color:#0073aa">' . esc_html( $total ) . '</h3><p style="margin:5px 0 0">Total Captures</p></div>'
			. '<div style="flex:1;padding:15px;background:#f0f0f1;border-radius:5px"><h3 style="margin:0;font-size:24px;color:#2271b1">' . esc_html( $week ) . '</h3><p style="margin:5px 0 0">Last 7 Days</p></div>'
			. '</div>'
			. '<p style="text-align:right;margin-top:15px"><a href="' . esc_url( admin_url( 'admin.php?page=kyc-customers' ) ) . '">View All Customers &rarr;</a> | <a href="#" id="kyc-dismiss-widget" style="color:#d63638">Dismiss Widget</a></p>';
	}

	// ---- CSV Export ----

	public function handle_csv_export() {
		if ( ! isset( $_POST['kyc_export'] ) || ! isset( $_POST['kyc_export_nonce'] ) ) return;
		if ( ! wp_verify_nonce( $_POST['kyc_export_nonce'], 'kyc_export_action' ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		global $wpdb;
		$table = KYC_DB::get_table_name();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="kyc_customers_' . date( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' ); header( 'Expires: 0' );
		$out = fopen( 'php://output', 'w' );
		fputs( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		fputcsv( $out, array( 'ID', 'First Name', 'Last Name', 'Phone', 'Email', 'DOB', 'Gender', 'Tags', 'Preferences', 'Loyalty', 'Referral Source', 'Created At' ) );
		$offset = 0;
		while ( true ) {
			$rows = $wpdb->get_results( "SELECT * FROM $table LIMIT 500 OFFSET $offset", ARRAY_A );
			if ( empty( $rows ) ) break;
			foreach ( $rows as $row ) {
				fputcsv( $out, array(
					$row['id'], $row['first_name'], $row['last_name'] ?? '', $row['phone'], $row['email'] ?? '',
					$row['dob'] ?? '', $row['gender'] ?? '', $row['tags'] ?? '', $row['preferences'] ?? '',
					$row['loyalty_points'] ?? 0, $row['referral_source'] ?? '', $row['created_at'],
				) );
			}
			$offset += 500;
		}
		fclose( $out ); exit;
	}
}
