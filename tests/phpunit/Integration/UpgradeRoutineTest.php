<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Coupon_Restrictions_Table;

class UpgradeRoutineTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The test framework rewrites CREATE/DROP TABLE to TEMPORARY table
		// queries, but dbDelta decides between CREATE and ALTER by checking
		// SHOW TABLES, which cannot see temporary tables. Use real DDL for
		// these tests so the upgrade path behaves like production.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// The schema changes only run for admin and WP CLI requests.
		set_current_screen( 'dashboard' );

		WC_Coupon_Restrictions_Table::delete_table();
		delete_option( 'woocommerce-coupon-restrictions' );
		delete_transient( 'woocommerce-coupon-restrictions-updating' );
	}

	/**
	 * The verification table should be created on install/upgrade so enhanced
	 * usage limits work for coupons created via REST API, CLI or import
	 * (not just the admin coupon save screen).
	 */
	public function test_upgrade_routine_creates_verification_table() {
		\WC_Coupon_Restrictions()->upgrade_routine();

		$this->assertTrue( WC_Coupon_Restrictions_Table::table_exists() );
	}

	/**
	 * The upgrade routine should also update the schema for existing installs
	 * when the plugin version changes.
	 */
	public function test_upgrade_routine_updates_schema_on_version_change() {
		global $wpdb;

		$table_name = WC_Coupon_Restrictions_Table::get_table_name();
		$this->create_legacy_table();
		update_option( 'woocommerce-coupon-restrictions', array( 'version' => '2.0.0' ) );

		\WC_Coupon_Restrictions()->upgrade_routine();

		$indexes   = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );
		$key_names = wp_list_pluck( $indexes, 'Key_name' );
		$this->assertContains( 'coupon_ip', $key_names );

		// The ip column should now accommodate IPv6 addresses.
		$column = $wpdb->get_row( "SHOW COLUMNS FROM {$table_name} LIKE 'ip'" );
		$this->assertSame( 'varchar(45)', strtolower( $column->Type ) );
	}

	/**
	 * The schema check is what the upgrade routine relies on to decide whether
	 * the table changes actually applied, since dbDelta does not report failed
	 * queries.
	 */
	public function test_schema_is_current_detects_the_legacy_schema() {
		$this->create_legacy_table();

		$this->assertFalse( WC_Coupon_Restrictions_Table::schema_is_current() );

		WC_Coupon_Restrictions_Table::create_or_update_table();

		$this->assertTrue( WC_Coupon_Restrictions_Table::schema_is_current() );
	}

	/**
	 * A missing table is not a current schema.
	 */
	public function test_schema_is_current_is_false_without_a_table() {
		$this->assertFalse( WC_Coupon_Restrictions_Table::schema_is_current() );
	}

	/**
	 * The version is only recorded once the schema changes have applied, so a
	 * store that cannot run them retries instead of being marked as upgraded.
	 */
	public function test_upgrade_routine_records_version_after_successful_upgrade() {
		$this->create_legacy_table();
		update_option( 'woocommerce-coupon-restrictions', array( 'version' => '2.0.0' ) );

		\WC_Coupon_Restrictions()->upgrade_routine();

		$option = get_option( 'woocommerce-coupon-restrictions' );
		$this->assertSame( \WC_Coupon_Restrictions()->version, $option['version'] );
	}

	/**
	 * The option can exist without a version key if an earlier write did not
	 * finish. That should run the upgrade rather than raise a PHP warning.
	 */
	public function test_upgrade_routine_handles_option_without_version_key() {
		update_option( 'woocommerce-coupon-restrictions', array() );

		\WC_Coupon_Restrictions()->upgrade_routine();

		$option = get_option( 'woocommerce-coupon-restrictions' );
		$this->assertSame( \WC_Coupon_Restrictions()->version, $option['version'] );
	}

	/**
	 * Creates the table with the schema used before 2.4.1.
	 */
	protected function create_legacy_table() {
		global $wpdb;

		$table_name      = WC_Coupon_Restrictions_Table::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$wpdb->query(
			"CREATE TABLE {$table_name} (
			record_id mediumint(9) NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			coupon_code varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			ip varchar(15) NOT NULL,
			shipping_address varchar(255) NOT NULL,
			UNIQUE KEY record_id (record_id)
			) {$charset_collate};"
		);
	}

	public function tear_down() {
		WC_Coupon_Restrictions_Table::delete_table();
		delete_option( 'woocommerce-coupon-restrictions' );
		delete_transient( 'woocommerce-coupon-restrictions-updating' );

		set_current_screen( 'front' );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		parent::tear_down();
	}
}
