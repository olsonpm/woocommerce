<?php
/**
 * WooCommerce Admin Helper - React admin interface
 *
 * @package WooCommerce\Admin\Helper
 */

use Automattic\WooCommerce\Internal\Admin\Marketplace;
use Automattic\WooCommerce\Internal\Admin\WCAdminAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Helper Class
 *
 * The main entry-point for all things related to the Helper.
 * The Helper manages the connection between the store and
 * an account on WooCommerce.com.
 */
class WC_Helper_Admin {
	const CHECK_SUBSCRIPTION_DISMISSED_COUNT_META_PREFIX = '_woocommerce_helper_check_subscription_dismissed_count';

	const CHECK_SUBSCRIPTION_DISMISSED_TIMESTAMP_META_PREFIX = '_woocommerce_helper_check_subscription_dismissed_timestamp';
	const CHECK_SUBSCRIPTION_REMIND_LATER_TIMESTAMP_META_PREFIX = '_woocommerce_helper_check_subscription_remind_later_timestamp';

	private static $checked_products = array();

	private static $checked_screen_param = array();

	/**
	 * Loads the class, runs on init
	 *
	 * @return void
	 */
	public static function load() {
		add_filter( 'woocommerce_admin_shared_settings', array( __CLASS__, 'add_marketplace_settings' ) );
		add_filter( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		add_action( 'current_screen', array( __CLASS__, 'check_subscriptions' ) );

		add_action( 'wp_ajax_woocommerce_helper_check_subscription_dismissed', array( __CLASS__, 'check_subscription_dismissed' ) );
		add_action( 'wp_ajax_woocommerce_helper_check_subscription_remind_later', array( __CLASS__, 'check_subscription_remind_later' ) );
	}

	/**
	 * Pushes settings onto the WooCommerce Admin global settings object (wcSettings).
	 *
	 * @param mixed $settings The settings object we're amending.
	 *
	 * @return mixed $settings
	 */
	public static function add_marketplace_settings( $settings ) {
		$auth_user_data  = WC_Helper_Options::get( 'auth_user_data', array() );
		$auth_user_email = isset( $auth_user_data['email'] ) ? $auth_user_data['email'] : '';

		// Get the all installed themes and plugins. Knowing this will help us decide to show Add to Store button on product cards.
		$installed_products = array_merge( WC_Helper::get_local_plugins(), WC_Helper::get_local_themes() );
		$installed_products = array_map(
			function ( $product ) {
				return $product['slug'];
			},
			$installed_products
		);

		$woo_connect_notice_type = WC_Helper_Updater::get_woo_connect_notice_type();

		$settings['wccomHelper'] = array(
			'isConnected'                => WC_Helper::is_site_connected(),
			'connectURL'                 => self::get_connection_url(),
			'userEmail'                  => $auth_user_email,
			'userAvatar'                 => get_avatar_url( $auth_user_email, array( 'size' => '48' ) ),
			'storeCountry'               => wc_get_base_location()['country'],
			'inAppPurchaseURLParams'     => WC_Admin_Addons::get_in_app_purchase_url_params(),
			'installedProducts'          => $installed_products,
			'wooUpdateManagerInstalled'  => WC_Woo_Update_Manager_Plugin::is_plugin_installed(),
			'wooUpdateManagerActive'     => WC_Woo_Update_Manager_Plugin::is_plugin_active(),
			'wooUpdateManagerInstallUrl' => WC_Woo_Update_Manager_Plugin::generate_install_url(),
			'wooUpdateManagerPluginSlug' => WC_Woo_Update_Manager_Plugin::WOO_UPDATE_MANAGER_SLUG,
			'wooUpdateCount'             => WC_Helper_Updater::get_updates_count_based_on_site_status(),
			'woocomConnectNoticeType'    => $woo_connect_notice_type,
		);

		return $settings;
	}

	/**
	 * Generates the URL for connecting or disconnecting the store to/from WooCommerce.com.
	 * Approach taken from existing helper code that isn't exposed.
	 *
	 * @return string
	 */
	public static function get_connection_url() {
		global $current_screen;

		$connect_url_args = array(
			'page'    => 'wc-addons',
			'section' => 'helper',
		);

		// No active connection.
		if ( WC_Helper::is_site_connected() ) {
			$connect_url_args['wc-helper-disconnect'] = 1;
			$connect_url_args['wc-helper-nonce']      = wp_create_nonce( 'disconnect' );
		} else {
			$connect_url_args['wc-helper-connect'] = 1;
			$connect_url_args['wc-helper-nonce']   = wp_create_nonce( 'connect' );
		}

		return add_query_arg(
			$connect_url_args,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Registers the REST routes for the featured products endpoint.
	 * This endpoint is used by the WooCommerce > Extensions > Discover
	 * page.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'wc/v3',
			'/marketplace/featured',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_featured' ),
				'permission_callback' => array( __CLASS__, 'get_permission' ),
			)
		);
	}

	/**
	 * The Extensions page can only be accessed by users with the manage_woocommerce
	 * capability. So the API mimics that behavior.
	 */
	public static function get_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Fetch featured products from WooCommerce.com and serve them
	 * as JSON.
	 */
	public static function get_featured() {
		$featured = WC_Admin_Addons::fetch_featured();

		if ( is_wp_error( $featured ) ) {
			wp_send_json_error( array( 'message' => $featured->get_error_message() ) );
		}

		wp_send_json( $featured );
	}

	public static function check_subscriptions( $screen ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		self::$checked_products = WC_Helper::get_checked_products();
		if ( empty( self::$checked_products ) ) {
			return;
		}

		self::$checked_screen_param = self::get_checked_screen_param( $screen );
		if ( empty( self::$checked_screen_param ) ) {
			return;
		}

		$product_id = self::$checked_screen_param['id'];

		// Check when the last time user clicked "remind later". If it's still
		// in the wait period, don't show the nudge.
		$remind_later_ts_meta    = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_REMIND_LATER_TIMESTAMP_META_PREFIX, $product_id );
		$last_remind_later       = absint( get_user_meta( $user_id, $remind_later_ts_meta, true ) );
		$wait_after_remind_later = self::$checked_screen_param['wait_in_seconds_after_remind_later'];
		if ( $last_remind_later > 0 && ( time() - $last_remind_later ) < $wait_after_remind_later ) {
			return;
		}

		// Don't show the nudge after dismissed max_dismissals times.
		$count_meta     = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_DISMISSED_COUNT_META_PREFIX, $product_id );
		$dismiss_count  = absint( get_user_meta( $user_id, $count_meta, true ) );
		$max_dismissals = self::$checked_screen_param['max_dismissals'];
		if ( $dismiss_count >= $max_dismissals ) {
			return;
		}

		// Check when the last time user dismissed the nudge. If it's still in
		// the wait period, don't show the nudge
		$dismiss_ts_meta    = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_DISMISSED_TIMESTAMP_META_PREFIX, $product_id );
		$last_dismissed     = absint( get_user_meta( $user_id, $dismiss_ts_meta, true) );
		$wait_after_dismiss = self::$checked_screen_param['wait_in_seconds_after_dismiss'];
		if ( $last_dismissed > 0 && ( time() - $last_dismissed ) < $wait_after_dismiss ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_check_subscription_modal_scripts' ) );
	}

	public static function enqueue_check_subscription_modal_scripts() {
		WCAdminAssets::register_style( 'woo-check-subscription', 'style', array( 'wp-components' ) );
		WCAdminAssets::register_script( 'wp-admin-scripts', 'woo-check-subscription', true );

		wp_localize_script(
			'wc-admin-woo-check-subscription',
			'wooCheckSubscriptionData',
			array(
				'manageSubscriptionsUrl' => 'https://woocommerce.com/my-account/my-subscriptions/',
				'dismissAction'          => 'woocommerce_helper_check_subscription_dismissed',
				'remindLaterAction'      => 'woocommerce_helper_check_subscription_remind_later',
				'productId'              => self::$checked_screen_param['id'],
				'productName'            => self::$checked_screen_param['name'],
				'productRegularPrice'    => self::$checked_screen_param['regular_price'],
				'dismissNonce'           => wp_create_nonce( 'check_subscription_dismissed' ),
				'remindLaterNonce'       => wp_create_nonce( 'check_subscription_remind_later' ),
				'showAs'                 => self::$checked_screen_param['show_as'],
				'colorScheme'            => self::$checked_screen_param['color_scheme'],
			)
		);
	}

	private static function get_checked_screen_param( $screen ) {
		foreach ( self::$checked_products as $product_id => $param ) {
			if ( ! isset( $param['screens'][ $screen->id ] ) ) {
				continue;
			}

			// Check query strings.
			if ( ! self::query_string_matches( $screen, $param ) ) {
				continue;
			}

			$product_id = absint( $product_id );
			if ( ! WC_Helper::has_product_subscription( $product_id ) ) {
				$param['id'] = $product_id;
				return $param;
			}
		}

		return array();
	}

	private static function query_string_matches( $screen, $param ) {
		if ( empty( $param['screens'][ $screen->id ]['qs'] ) ) {
			return true;
		}

		$qs = $param['screens'][ $screen->id ]['qs'];
		foreach ( $qs as $key => $val ) {
			if ( empty( $_GET[ $key ] ) || $_GET[ $key ] !== $val ) {
				return false;
			}
		}
		return true;
	}

	public static function check_subscription_dismissed() {
		if ( ! check_ajax_referer( 'check_subscription_dismissed' ) ) {
			wp_die( -1 );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( -1 );
		}

		$product_id = absint( $_GET['product_id'] );
		if ( ! $product_id ) {
			wp_die( -1 );
		}

		$count_meta = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_DISMISSED_COUNT_META_PREFIX, $product_id );
		$dismiss_count = (int) get_user_meta( $user_id, $count_meta, true );
		update_user_meta( $user_id, $count_meta, $dismiss_count + 1 );

		$timestamp_meta = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_DISMISSED_TIMESTAMP_META_PREFIX, $product_id );
		update_user_meta( $user_id, $timestamp_meta, time() );

		wp_die( 1 );
	}

	public static function check_subscription_remind_later() {
		if ( ! check_ajax_referer( 'check_subscription_remind_later' ) ) {
			wp_die( -1 );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( -1 );
		}

		$product_id = absint( $_GET['product_id'] );
		if ( ! $product_id ) {
			wp_die( -1 );
		}

		$timestamp_meta = sprintf( '%s_%d', self::CHECK_SUBSCRIPTION_REMIND_LATER_TIMESTAMP_META_PREFIX , $product_id );
		update_user_meta( $user_id, $timestamp_meta, time() );

		wp_die( 1 );
	}
}

WC_Helper_Admin::load();
