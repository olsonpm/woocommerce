<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint\Exporters;

class ExportPaymentGateways implements ExportsStepSchema {

	public function export() {
		$payment_gateways = array();
		foreach (WC()->payment_gateways->payment_gateways() as $id => $payment_gateway) {
			$payment_gateways[$id] = array(
				'title'	=> $payment_gateway->get_title(),
				'description' => $payment_gateway->get_description(),
				'enabled' => $payment_gateway->is_available() ? 'yes' : 'no',
			);
		}

		return $payment_gateways;
	}

	public function export_step_schema() {
		return array(
			'step' => $this->get_step_name(),
			'payment_gateways' => $this->export()
		);
	}

	public function get_step_name() {
		return 'configurePaymentGateways';
	}
}
