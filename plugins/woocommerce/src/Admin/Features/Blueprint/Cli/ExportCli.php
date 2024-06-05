<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint\Cli;

use Automattic\WooCommerce\Admin\Features\Blueprint\ExportSchema;

class ExportCli {
	private string $save_to;
	public function __construct($save_to) {
		$this->save_to = $save_to;
	}

	public function run($steps = array()) {
		$schema = (new ExportSchema())->export($steps);
		file_put_contents($this->save_to, json_encode($schema, JSON_PRETTY_PRINT));
		\WP_CLI::success("Exported to {$this->save_to}");
	}
}
