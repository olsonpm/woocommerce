<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint\Exporters;

class ExportPluginList implements ExportsStepSchema {
	public function export() {
		if (!function_exists('is_plugin_active') || !function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin-install.php';
		}
		$export = array();
		$plugins = get_plugins();
		foreach ($plugins as $path => $plugin) {
			$slug = dirname($path);
			$info = \plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'sections' => false,
					),
				)
			);
			if (isset($info->download_link)) {
				$export[] = array(
					'slug' => $slug,
					'resource' => 'wordpress.org/plugins',
					'activate' => \is_plugin_active($path)
				);
			}
		}
	    return $export;
	}

	public function export_step_schema() {
		return array(
			'step' => $this->get_step_name(),
			'plugins' => $this->export()
		);
	}

	public function get_step_name() {
		return 'installPlugins';
	}
}
