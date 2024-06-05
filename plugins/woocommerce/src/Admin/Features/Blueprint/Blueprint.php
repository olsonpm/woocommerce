<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint;

class Blueprint {
	private $schemaPath;
	public function __construct( $schema ) {
		$this->schema = $schema;
	}

	/**
	 * @return StepProcessorResult[]
	 */
	public function process() {
		if ( ! $this->validate() ) {
			// @todo Implement JSON Schema validation here.
			return false;
		}

		/**
		 * @var StepProcessorResult[]
		 */
		$results = array();
		foreach ( $this->schema->steps as $stepSchema ) {
			$stepProcessor = __NAMESPACE__ . '\\StepProcessors\\' . ucfirst( $stepSchema->step );
			if ( class_exists( $stepProcessor ) ) {
				/**
				 * @var $stepProcessor StepProcessor
				 * @todo Use container.
				 */
				$stepProcessor = new $stepProcessor();
				$results[] = $stepProcessor->process($stepSchema);
			}
		}

		return $results;
	}

	private function validate() {
		return true;
	}
}
