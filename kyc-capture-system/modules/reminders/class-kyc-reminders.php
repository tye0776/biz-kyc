<?php
/**
 * Module: Reminders — Birthday & Anniversary Engine.
 * Moved to modules/reminders/ from includes/class-kyc-reminders.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Reminders {

	public static function init() {
		self::schedule_cron();
	}

	public static function schedule_cron() {
		add_action( 'kyc_daily_reminder_check', array( __CLASS__, 'run_daily_check' ) );

		if ( get_transient( 'kyc_cron_scheduled' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( 'kyc_daily_reminder_check' ) ) {
			$start = strtotime( 'today 08:00:00' );
			if ( $start < time() ) {
				$start = strtotime( 'tomorrow 08:00:00' );
			}
			wp_schedule_event( $start, 'daily', 'kyc_daily_reminder_check' );
		}
		set_transient( 'kyc_cron_scheduled', 1, HOUR_IN_SECONDS );
	}

	public static function clear_cron() {
		$ts = wp_next_scheduled( 'kyc_daily_reminder_check' );
		if ( $ts ) wp_unschedule_event( $ts, 'kyc_daily_reminder_check' );
		delete_transient( 'kyc_cron_scheduled' );
	}

	public static function run_daily_check() {
		global $wpdb;
		$lead_days       = absint( get_option( 'kyc_reminder_lead_days', 3 ) );
		$customers_table = KYC_DB::get_table_name();
		$today           = new DateTime( 'today', wp_timezone() );

		// 1. Remind about customers' own birthdays (always, just needs dob column)
		$customers = $wpdb->get_results(
			"SELECT id, first_name, last_name, phone, email, dob FROM $customers_table
			 WHERE dob IS NOT NULL AND dob != '0000-00-00'",
			ARRAY_A
		);
		foreach ( (array) $customers as $c ) {
			$days = self::days_until( $c['dob'], $today );
			if ( $days !== false && $days <= $lead_days ) {
				self::send_self_reminder( $c, $days );
			}
		}

		// 2. Remind about linked contacts — only if family_graph module is active & table exists
		if ( KYC_Modules::is_active( 'family_graph' ) ) {
			$contacts_table = KYC_DB::get_contacts_table_name();
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$contacts_table'" ) !== $contacts_table ) {
				return;
			}
			$contacts = $wpdb->get_results(
				"SELECT lc.*, c.first_name AS owner_first, c.last_name AS owner_last,
				        c.phone AS owner_phone, c.email AS owner_email
				 FROM $contacts_table AS lc
				 INNER JOIN $customers_table AS c ON c.id = lc.customer_id
				 WHERE lc.dob IS NOT NULL OR lc.anniversary IS NOT NULL",
				ARRAY_A
			);
			foreach ( (array) $contacts as $contact ) {
				foreach ( array( 'dob' => 'Birthday', 'anniversary' => 'Anniversary' ) as $field => $label ) {
					if ( empty( $contact[ $field ] ) ) continue;
					$days = self::days_until( $contact[ $field ], $today );
					if ( $days !== false && $days <= $lead_days ) {
						self::send_linked_reminder( $contact, $label, $contact[ $field ], $days );
					}
				}
			}
		}
	}

	private static function days_until( $date_string, DateTime $today ) {
		try {
			$date = new DateTime( $date_string, wp_timezone() );
		} catch ( Exception $e ) { return false; }
		$year = (int) $today->format( 'Y' );
		$date->setDate( $year, (int) $date->format( 'm' ), (int) $date->format( 'd' ) );
		if ( $date < $today ) $date->setDate( $year + 1, (int) $date->format( 'm' ), (int) $date->format( 'd' ) );
		return (int) $today->diff( $date )->days;
	}

	private static function send_self_reminder( array $c, $days ) {
		$to   = get_option( 'kyc_notification_email', get_option( 'admin_email' ) );
		$site = get_bloginfo( 'name' );
		$name = trim( $c['first_name'] . ' ' . ( $c['last_name'] ?? '' ) );
		$wa   = 'https://wa.me/' . preg_replace( '/\D/', '', $c['phone'] );
		$lbl  = $days === 0 ? 'TODAY' : "in {$days} day" . ( $days > 1 ? 's' : '' );

		$subject = "[{$site}] 🎂 Birthday Reminder: {$name}";
		$body  = "Hi,\n\n{$name}'s birthday is {$lbl}!\n\nPhone: {$c['phone']}\nWhatsApp: {$wa}\n\n— {$site}";
		wp_mail( $to, $subject, $body );

		if ( ! empty( $c['email'] ) ) {
			wp_mail( $c['email'], "🎂 Your birthday is coming up — treat yourself!", "Hi {$name},\n\nWe noticed your birthday is {$lbl}! Come visit us for something special. 🎉\n\n— {$site}" );
		}
	}

	private static function send_linked_reminder( array $contact, $event_type, $event_date, $days ) {
		$to         = get_option( 'kyc_notification_email', get_option( 'admin_email' ) );
		$site       = get_bloginfo( 'name' );
		$owner_name = trim( $contact['owner_first'] . ' ' . $contact['owner_last'] );
		$wa         = 'https://wa.me/' . preg_replace( '/\D/', '', $contact['owner_phone'] );
		$lbl        = $days === 0 ? 'TODAY' : "in {$days} day" . ( $days > 1 ? 's' : '' );
		$formatted  = date_i18n( get_option( 'date_format' ), strtotime( $event_date ) );

		wp_mail( $to,
			"[{$site}] 🎉 {$event_type} Reminder: {$contact['linked_name']} ({$contact['relationship']} of {$owner_name})",
			"Hello,\n\n{$contact['linked_name']}'s {$event_type} is {$lbl} on {$formatted}.\n\nCustomer: {$owner_name} | Phone: {$contact['owner_phone']} | WhatsApp: {$wa}\n\n— {$site}"
		);

		if ( ! empty( $contact['owner_email'] ) ) {
			wp_mail( $contact['owner_email'],
				"🎉 {$contact['linked_name']}'s {$event_type} is {$lbl}!",
				"Hi {$owner_name},\n\nJust a reminder that {$contact['linked_name']}'s {$event_type} is {$lbl} on {$formatted}. Why not order something special? 🎂\n\n— {$site}"
			);
		}
	}
}
