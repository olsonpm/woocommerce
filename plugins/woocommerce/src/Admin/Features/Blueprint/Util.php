<?php

namespace Automattic\WooCommerce\Admin\Features\Blueprint;

class Util {
	public static function snake_to_camel($string) {
		// Split the string by underscores
		$words = explode('_', $string);

		// Capitalize the first letter of each word
		$words = array_map('ucfirst', $words);

		// Join the words back together
		return implode('', $words);
	}

	public static function camel_to_snake($input) {
		// Replace all uppercase letters with an underscore followed by the lowercase version of the letter
		$pattern = '/([a-z])([A-Z])/';
		$replacement = '$1_$2';
		$snake = preg_replace($pattern, $replacement, $input);

		// Replace spaces with underscores
		$snake = str_replace(' ', '_', $snake);

		// Convert the entire string to lowercase
		return strtolower($snake);
	}

	public static function array_filter_by_field($array, $field_name, $force_convert = false) {
		if (!is_array($array) && $force_convert) {
			$array = json_decode(json_encode($array), true);
		}
		$result = [];
		foreach ($array as $item) {
			if (is_array($item)) {
				if (isset($item[$field_name])) {
					$result[] = $item;
				}
				// Recursively search in nested arrays
				$nestedResult = static::array_filter_by_field($item, $field_name);
				if (!empty($nestedResult)) {
					$result = array_merge($result, $nestedResult);
				}
			}
		}
		return $result;
	}
}
