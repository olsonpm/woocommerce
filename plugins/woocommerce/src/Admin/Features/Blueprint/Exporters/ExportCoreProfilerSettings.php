<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint\Exporters;

class ExportCoreProfilerSettings implements ExportsStepSchema {
	public function export() {
		$onboarding_profile = get_option('woocommerce_onboarding_profile', array());
	    return array(
			'blogname' => get_option('blogname'),
		    "woocommerce_allow_tracking"=> true,
			"industry"=> $onboarding_profile['industry'] ?? array(),
			"business_choice"=> $onboarding_profile['business_choice'] ?? '',
			"store_email"=> $onboarding_profile['store_email'] ?? '',
		    'completed' => $onboarding_profile['completed'] ?? false,
		    'skipped' => $onboarding_profile['skipped'] ?? false,
	    );
	}
	public function export_step_schema() {
		return array(
			'step' => $this->get_step_name(),
			'values' => $this->export()
		);
	}

	public function get_step_name() {
		return 'configureCoreProfiler';
	}
}
