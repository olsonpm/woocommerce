<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint;

class JsonSchema extends Schema {
	public function __construct($json_path) {
		$this->schema = json_decode(file_get_contents($json_path));
		if (!$this->validate()) {
			// throw exception;
		}


//			if ( json_last_error() !== JSON_ERROR_NONE ) {
//				return new \WP_REST_Response(array(
//					'status' => 'error',
//					'message' => 'Invalid JSON data',
//				), 400);
//			}
	}

	public function validate() {
	    return true;
	}
}
