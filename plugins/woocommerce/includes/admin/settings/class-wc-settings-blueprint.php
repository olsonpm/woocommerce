<?php
/**
 * WooCommerce blueprint settings
 *
 * @package  WooCommerce\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings for API.
 */
if ( class_exists( 'WC_Settings_Blueprint', false ) ) {
	return new WC_Settings_Blueprint();
}

/**
 * WC_Settings_Advanced.
 */
class WC_Settings_Blueprint extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'blueprint';
		$this->label = __( 'Blueprint', 'woocommerce' );

		parent::__construct();
	}


	/**
	 * Get settings for the default section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		$settings =
			array(
				array(
					'id'   => 'wc_settings_blueprint_slotfill',
					'type' => 'slotfill_placeholder',
				),
			);

		return $settings;
	}
}


return new WC_Settings_Blueprint();
