<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint;

/**
 * @todo interface should be good enough?
 */
abstract class Schema {
	protected object $schema;
	abstract public function validate();
	public function get_steps() {
		return $this->schema->steps;
	}
}
