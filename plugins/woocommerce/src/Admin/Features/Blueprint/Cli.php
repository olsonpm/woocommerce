<?php
namespace Automattic\WooCommerce\Admin\Features\Blueprint;

use Automattic\WooCommerce\Admin\Features\Blueprint\Cli\ExportCli;
use Automattic\WooCommerce\Admin\Features\Blueprint\Cli\ImportCli;

/**
 * Class Cli.
 *
 * This class is included and execute from WC_CLI(class-wc-cli.php) to register
 * WP CLI commands.
 *
 */
class Cli {
	public static function register_commands() {
		\WP_CLI::add_command( 'wc blueprint import', function($args, $assoc_args) {
			$import = new ImportCli($args[0]);
			$import->run($assoc_args);
		}, array(
			'synopsis' => [
				[
					'type' => 'positional',
					'name' => 'schema-path',
					'optional' => false,
				],
				[
					'type' => 'assoc',
					'name' => 'show-messages',
					'optional' => true,
					'options' => ['all', 'error', 'info', 'debug'],
				],
			],
			'when' => 'after_wp_load',
		));

		\WP_CLI::add_command( 'wc blueprint export', function($args, $assoc_args) {
			$import = new ExportCli($args[0]);
			$steps = array();
			if (isset($assoc_args['steps'])) {
				$steps = array_map(function($step) {
					return trim($step);
				}, explode(',', $assoc_args['steps']));
			}
			$import->run($steps);
		}, array(
			'synopsis' => [
				[
					'type' => 'positional',
					'name' => 'save-to',
					'optional' => false,
				],
				[
					'type' => 'assoc',
					'name' => 'steps',
					'optional' => true,
				],
			],
			'when' => 'after_wp_load',
		));
	}
}
