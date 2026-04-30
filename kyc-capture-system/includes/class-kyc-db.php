<?php
/**
 * Database operations wrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KYC_DB {

	/**
	 * Get the table name.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'kyc_customers';
	}

	/**
	 * Insert a new customer.
	 */
	public static function insert_customer( $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Update an existing customer.
	 */
	public static function update_customer( $id, $data ) {
		global $wpdb;
		$table = self::get_table_name();

		// Security whitelist — only known columns can be updated via this method.
		// Prevents any AJAX caller from injecting arbitrary column names.
		$allowed = array(
			'first_name', 'last_name', 'phone', 'email', 'dob', 'gender',
			// Module columns (only writable when the column exists)
			'tags', 'preferences', 'loyalty_points', 'referred_by', 'referral_source',
			'updated_at',
		);
		$data = array_intersect_key( $data, array_flip( $allowed ) );
		if ( empty( $data ) ) {
			return 0; // nothing to update
		}

		$data['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	/**
	 * Get a customer by ID.
	 */
	public static function get_customer( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Get a customer by Phone.
	 */
	public static function get_customer_by_phone( $phone ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", $phone ), ARRAY_A );
	}

	/**
	 * Delete a customer.
	 */
	public static function delete_customer( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->delete( $table, array( 'id' => $id ) );
	}

	// -------------------------------------------------------------------------
	// Linked Contacts (Family Social Graph)
	// -------------------------------------------------------------------------

	/**
	 * Get the linked contacts table name.
	 */
	public static function get_contacts_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'kyc_linked_contacts';
	}

	/**
	 * Insert a new linked contact.
	 * After insert, try to resolve the linked_customer_id automatically.
	 *
	 * @param array $data  Keys: customer_id, linked_name, linked_phone, relationship, dob, anniversary.
	 * @return int|false  Inserted row ID or false on failure.
	 */
	public static function insert_linked_contact( array $data ) {
		global $wpdb;
		$table = self::get_contacts_table_name();

		$data['created_at'] = current_time( 'mysql' );

		// Auto-resolve linked_customer_id if the linked phone belongs to a known customer
		if ( ! isset( $data['linked_customer_id'] ) ) {
			$matched = self::get_customer_by_phone( $data['linked_phone'] );
			$data['linked_customer_id'] = $matched ? $matched['id'] : null;
		}

		$result = $wpdb->insert( $table, $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing linked contact.
	 *
	 * @param int   $id    Row ID.
	 * @param array $data  Fields to update.
	 */
	public static function update_linked_contact( $id, array $data ) {
		global $wpdb;
		$table = self::get_contacts_table_name();

		// Re-resolve linked_customer_id if the phone changed
		if ( isset( $data['linked_phone'] ) ) {
			$matched = self::get_customer_by_phone( $data['linked_phone'] );
			$data['linked_customer_id'] = $matched ? $matched['id'] : null;
		}

		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	/**
	 * Delete a linked contact. Only the owning customer can delete it.
	 *
	 * @param int $id          Row ID.
	 * @param int $customer_id Owner customer ID (security guard).
	 */
	public static function delete_linked_contact( $id, $customer_id ) {
		global $wpdb;
		$table = self::get_contacts_table_name();
		return $wpdb->delete( $table, array( 'id' => $id, 'customer_id' => $customer_id ) );
	}

	/**
	 * Get all linked contacts for a given customer.
	 *
	 * @param int $customer_id
	 * @return array
	 */
	public static function get_linked_contacts_by_customer( $customer_id ) {
		global $wpdb;
		$table = self::get_contacts_table_name();

		// Guard: if the table doesn't exist (family_graph module inactive) return empty array
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE customer_id = %d ORDER BY linked_name ASC", $customer_id ),
			ARRAY_A
		);
	}

	/**
	 * Backfill linked_customer_id on all existing rows whose linked_phone
	 * matches the newly registered customer's phone.
	 * Called whenever a new customer is created.
	 *
	 * @param string $phone       The new customer's phone number.
	 * @param int    $customer_id The new customer's ID.
	 */
	public static function backfill_linked_customer_id( $phone, $customer_id ) {
		global $wpdb;
		$table = self::get_contacts_table_name();

		// Guard: table may not exist if family_graph module is inactive
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return;
		}

		// Fix: $wpdb->update() format array must not contain null.
		// Use a raw prepared query to safely match NULL in the WHERE clause.
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET linked_customer_id = %d
			 WHERE linked_phone = %s AND linked_customer_id IS NULL",
			$customer_id,
			$phone
		) );
	}

	/**
	 * Get all linked contacts across all customers, joined with customer names.
	 * Used by the admin Linked Contacts page.
	 *
	 * @return array
	 */
	public static function get_all_linked_contacts( $limit = 500, $offset = 0 ) {
		global $wpdb;
		$contacts_table  = self::get_contacts_table_name();
		$customers_table = self::get_table_name();

		// Guard: table may not exist
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$contacts_table'" ) !== $contacts_table ) {
			return array();
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT lc.*,
			        c.first_name AS owner_first, c.last_name AS owner_last, c.phone AS owner_phone,
			        CASE WHEN lc.linked_customer_id IS NOT NULL THEN 1 ELSE 0 END AS is_mutual
			 FROM $contacts_table AS lc
			 INNER JOIN $customers_table AS c ON c.id = lc.customer_id
			 ORDER BY c.first_name ASC, lc.linked_name ASC
			 LIMIT %d OFFSET %d",
			$limit, $offset
		), ARRAY_A );
	}

	/**
	 * Get upcoming dates within the next N days across all linked contacts.
	 *
	 * @param int $days  Look-ahead window.
	 * @return array
	 */
	public static function get_upcoming_dates( $days = 30 ) {
		global $wpdb;
		$contacts_table  = self::get_contacts_table_name();
		$customers_table = self::get_table_name();

		// Pull all contacts with at least one date, then filter in PHP (year-agnostic)
		$contacts = $wpdb->get_results(
			"SELECT lc.*, c.first_name AS owner_first, c.last_name AS owner_last,
			        c.phone AS owner_phone, c.email AS owner_email
			 FROM $contacts_table AS lc
			 INNER JOIN $customers_table AS c ON c.id = lc.customer_id
			 WHERE lc.dob IS NOT NULL OR lc.anniversary IS NOT NULL",
			ARRAY_A
		);

		$upcoming = array();
		$today    = new DateTime( 'today', wp_timezone() );

		foreach ( $contacts as $contact ) {
			foreach ( array( 'dob' => 'Birthday', 'anniversary' => 'Anniversary' ) as $field => $label ) {
				if ( empty( $contact[ $field ] ) ) {
					continue;
				}
				try {
					$date = new DateTime( $contact[ $field ], wp_timezone() );
					$year = (int) $today->format( 'Y' );
					$date->setDate( $year, (int) $date->format( 'm' ), (int) $date->format( 'd' ) );
					if ( $date < $today ) {
						$date->setDate( $year + 1, (int) $date->format( 'm' ), (int) $date->format( 'd' ) );
					}
					$diff = (int) $today->diff( $date )->days;
					if ( $diff <= $days ) {
						$upcoming[] = array_merge( $contact, array(
							'event_type'   => $label,
							'event_date'   => $date->format( 'Y-m-d' ),
							'days_until'   => $diff,
						) );
					}
				} catch ( Exception $e ) {
					// skip invalid dates
				}
			}
		}

		usort( $upcoming, function( $a, $b ) {
			return $a['days_until'] - $b['days_until'];
		} );

		return $upcoming;
	}
}
