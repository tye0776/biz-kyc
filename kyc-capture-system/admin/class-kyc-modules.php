<?php
/**
 * KYC Module Registry, Loader & Business Presets.
 * This is the heart of the modular system — nothing else loads unless listed here.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class KYC_Modules {

	/** @var array|null Cached active module keys for this request. */
	private static $active = null;

	/** @var array|null Cached registry (built once per request). */
	private static $registry = null;

	/** @var array|null Cached presets (built once per request). */
	private static $presets = null;

	/** @var array Cached file_exists() results — avoids repeated filesystem hits. */
	private static $file_exists_cache = array();

	// -------------------------------------------------------------------------
	// Registry: every available module defined in one place
	// -------------------------------------------------------------------------

	public static function get_registry() {
		if ( self::$registry !== null ) {
			return self::$registry;
		}
		self::$registry = array(
			'reminders' => array(
				'label'       => 'Birthday & Anniversary Reminders',
				'description' => 'Daily email reminders for upcoming birthdays and anniversaries.',
				'icon'        => '🎂',
				'file'        => KYC_PLUGIN_DIR . 'modules/reminders/class-kyc-reminders.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array(),
				'admin_menus' => array( 'kyc-upcoming-dates' ),
			),
			'family_graph' => array(
				'label'       => 'Family Social Graph',
				'description' => 'Link family members by phone; confirm mutual relationships.',
				'icon'        => '👨‍👩‍👧',
				'file'        => KYC_PLUGIN_DIR . 'modules/family-graph/class-kyc-family-graph.php',
				'ajax_file'   => KYC_PLUGIN_DIR . 'modules/family-graph/class-kyc-contacts-ajax.php',
				'db_tables'   => array( 'kyc_linked_contacts' ),
				'db_columns'  => array(),
				'admin_menus' => array( 'kyc-linked-contacts' ),
			),
			'tags' => array(
				'label'       => 'Customer Tags',
				'description' => 'Tag customers: VIP, Wedding Client, Bulk Order, etc.',
				'icon'        => '🏷️',
				'file'        => KYC_PLUGIN_DIR . 'modules/tags/class-kyc-tags.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array( 'tags varchar(500) DEFAULT NULL' ),
				'admin_menus' => array(),
			),
			'order_notes' => array(
				'label'       => 'Customer Preferences & Notes',
				'description' => 'Store allergies, favourite flavours, and order preferences.',
				'icon'        => '📝',
				'file'        => KYC_PLUGIN_DIR . 'modules/order-notes/class-kyc-order-notes.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array( 'preferences text DEFAULT NULL' ),
				'admin_menus' => array(),
			),
			'loyalty' => array(
				'label'       => 'Loyalty Points / Visit Counter',
				'description' => 'Track repeat visits and reward loyal customers.',
				'icon'        => '⭐',
				'file'        => KYC_PLUGIN_DIR . 'modules/loyalty/class-kyc-loyalty.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array( 'loyalty_points mediumint(9) DEFAULT 0' ),
				'admin_menus' => array(),
			),
			'referrals' => array(
				'label'       => 'Referral Tracking',
				'description' => 'Track who referred new customers and how they heard of you.',
				'icon'        => '🤝',
				'file'        => KYC_PLUGIN_DIR . 'modules/referrals/class-kyc-referrals.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array( 'referred_by varchar(100) DEFAULT NULL', 'referral_source varchar(100) DEFAULT NULL' ),
				'admin_menus' => array(),
			),
			'integrations' => array(
				'label'       => 'WooCommerce & Webhook Integration',
				'description' => 'Auto-sync WooCommerce orders and fire webhooks to Zapier/Make.',
				'icon'        => '🔌',
				'file'        => KYC_PLUGIN_DIR . 'modules/integrations/class-kyc-integrations.php',
				'ajax_file'   => '',
				'db_tables'   => array(),
				'db_columns'  => array(),
				'admin_menus' => array(),
			),
		);
		return self::$registry;
	}

	// -------------------------------------------------------------------------
	// Business presets: map a business type to a set of modules
	// -------------------------------------------------------------------------

	public static function get_presets() {
		if ( self::$presets !== null ) {
			return self::$presets;
		}
		self::$presets = array(
			'food_bakery'  => array( 'label' => '🎂 Food & Bakery',          'description' => 'Cake shops, pastry shops, restaurants, caterers',   'modules' => array( 'reminders', 'family_graph', 'tags', 'order_notes', 'integrations' ) ),
			'retail'       => array( 'label' => '🛍️ Retail Shop',             'description' => 'Fashion, electronics, general store, supermarket',   'modules' => array( 'tags', 'loyalty', 'referrals', 'integrations' ) ),
			'service'      => array( 'label' => '✂️ Service Business',         'description' => 'Salon, spa, clinic, fitness studio, laundry',         'modules' => array( 'tags', 'loyalty', 'reminders', 'integrations' ) ),
			'professional' => array( 'label' => '🏢 Professional Services',    'description' => 'Law firm, consultancy, agency, school',               'modules' => array( 'tags', 'order_notes', 'referrals', 'integrations' ) ),
			'custom'       => array( 'label' => '⚙️ Custom',                   'description' => 'Pick exactly the features you need',                 'modules' => array() ),
		);
		return self::$presets;
	}

	// -------------------------------------------------------------------------
	// Runtime helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the list of active module keys (cached per-request in static property).
	 */
	public static function get_active() {
		if ( self::$active !== null ) {
			return self::$active;
		}
		$stored       = get_option( 'kyc_active_modules', null );
		// Legacy installs (before modular system): activate everything
		if ( $stored === null ) {
			$stored = array_keys( self::get_registry() );
		}
		self::$active = is_array( $stored ) ? $stored : array();
		return self::$active;
	}

	/**
	 * Check whether a single module is active.
	 */
	public static function is_active( $key ) {
		return in_array( $key, self::get_active(), true );
	}

	/**
	 * Require module PHP files for all active modules (called once on bootstrap).
	 * AJAX-only files are only required when DOING_AJAX.
	 */
	public static function load() {
		$registry = self::get_registry(); // cached
		$is_ajax  = defined( 'DOING_AJAX' ) && DOING_AJAX;

		foreach ( self::get_active() as $key ) {
			if ( ! isset( $registry[ $key ] ) ) continue;
			$m = $registry[ $key ];

			if ( ! empty( $m['file'] ) ) {
				// Cache file_exists() result — filesystem hit only once per file per request
				$fk = 'f_' . $key;
				if ( ! isset( self::$file_exists_cache[ $fk ] ) ) {
					self::$file_exists_cache[ $fk ] = file_exists( $m['file'] );
				}
				if ( self::$file_exists_cache[ $fk ] ) {
					require_once $m['file'];
				}
			}
			if ( $is_ajax && ! empty( $m['ajax_file'] ) ) {
				$ak = 'a_' . $key;
				if ( ! isset( self::$file_exists_cache[ $ak ] ) ) {
					self::$file_exists_cache[ $ak ] = file_exists( $m['ajax_file'] );
				}
				if ( self::$file_exists_cache[ $ak ] ) {
					require_once $m['ajax_file'];
				}
			}
		}
	}

	/**
	 * Initialise every active module that exposes a static init() method.
	 * Convention: class name follows KYC_<StudlyCaps> pattern per module key.
	 */
	public static function init_all() {
		$class_map = array(
			'reminders'    => 'KYC_Reminders',
			'family_graph' => 'KYC_Family_Graph',
			'tags'         => 'KYC_Tags',
			'order_notes'  => 'KYC_Order_Notes',
			'loyalty'      => 'KYC_Loyalty',
			'referrals'    => 'KYC_Referrals',
			'integrations' => 'KYC_Integrations',
		);

		foreach ( self::get_active() as $key ) {
			if ( isset( $class_map[ $key ] ) && class_exists( $class_map[ $key ] ) ) {
				call_user_func( array( $class_map[ $key ], 'init' ) );
			}
		}

		// AJAX: init contact handlers for family_graph
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( self::is_active( 'family_graph' ) && class_exists( 'KYC_Contacts_Ajax' ) ) {
				( new KYC_Contacts_Ajax() )->init();
			}
		}
	}

	/**
	 * Persist a new set of active modules.
	 */
	public static function save( array $keys ) {
		$valid        = array_keys( self::get_registry() );
		$clean        = array_values( array_filter( $keys, fn( $k ) => in_array( $k, $valid, true ) ) );
		// Reset all static caches so next request picks up new state cleanly
		self::$active   = null;
		self::$registry = null;
		self::$presets  = null;
		self::$file_exists_cache = array();
		update_option( 'kyc_active_modules', $clean );
		delete_transient( 'kyc_db_version_check' );
	}

	/**
	 * Return all db_columns entries for currently active modules.
	 * Used by the activator to build a dynamic CREATE TABLE statement.
	 */
	public static function get_active_columns() {
		$registry = self::get_registry();
		$columns  = array();
		foreach ( self::get_active() as $key ) {
			if ( isset( $registry[ $key ]['db_columns'] ) ) {
				$columns = array_merge( $columns, $registry[ $key ]['db_columns'] );
			}
		}
		return array_unique( $columns );
	}

	/**
	 * Return all db_table names needed by active modules.
	 */
	public static function get_active_tables() {
		$registry = self::get_registry();
		$tables   = array();
		foreach ( self::get_active() as $key ) {
			if ( isset( $registry[ $key ]['db_tables'] ) ) {
				$tables = array_merge( $tables, $registry[ $key ]['db_tables'] );
			}
		}
		return array_unique( $tables );
	}
}
