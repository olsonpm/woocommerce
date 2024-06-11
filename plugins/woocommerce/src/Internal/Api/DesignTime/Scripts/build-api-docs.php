<?php

namespace Automattic\WooCommerce\Internal\Api\DesignTime\Scripts;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionMethod;
use ReflectionProperty;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.YodaConditions.NotYoda

/**
 * The WooCommerce API builder class.
 * The entry point is the "run" method.
 */
class ApiBuilder {
	private static $curl;

	private static $url_argument_regexes = [
		'\w+' => 'x',
		'\d+' => '0',
		'[a-z]{3}_[a-zA-Z0-9]{24}' => 'aaa_aaaaaaaaaaaaaaaaaaaaaaa',
		'[a-z0-9_]+' => 'x',
		'[A-Za-z]{3}' => 'xxx',
		'[\d]+' => '0',
		'\w[\w\s\-]*' => 'x',
		'[\w-]+' => 'x',
		'[\w-]{3}' => 'xxx'
	];

	public static function run(): void {
		if (version_compare(phpversion(), '8', '<')) {
			echo "*** This script requires PHP 8.0 or newer.\n";
			exit(1);
		}

		if(!function_exists('curl_version')) {
			echo "*** This script requires the PHP curl extension.\n";
			exit(1);
		}

		self::$curl = curl_init();
		if(false === self::$curl) {
			echo "*** CURL initialization failed.\n";
			exit(1);
		}

		$server_url = getenv('SERVER_URL');
		if(!$server_url) {
			$server_url = "http://localhost";
		}
		$server_url = trim($server_url, '/');

		echo "Server URL: $server_url\n";

		$endpoint_url = getenv('ENDPOINT_URL');
		if($endpoint_url) {
			if(str_starts_with($endpoint_url, $server_url)) {
				$endpoint_url = str_replace($server_url . '/wp-json', '', $endpoint_url);
			}
			if(!str_starts_with($endpoint_url, '/wc/v3')) {
				$endpoint_url = '/wc/v3/' . $endpoint_url;
			}

			echo "Endpoint URL: $endpoint_url\n";
		}

		echo "\n";

		//* Get list of routes

		if($endpoint_url) {
			$routes = [$endpoint_url];
		}
		else {
			$full_schema_url = $server_url . '/wp-json/wc/v3';
			$full_schema_info = self::query_server($full_schema_url, 'GET');
			if (is_string($full_schema_info)) {
				echo "*** Failed to retrieve the full API schema: $full_schema_info\n";
				echo "Queried URL: $full_schema_url\n";
				exit(1);
			}

			$routes = array_keys($full_schema_info['routes']);
			$routes = array_diff($routes, ['/wc/v3']);
		}

		//* Transform URL argument regexes

		$transformed_routes = [];
		foreach($routes as $route) {
			$matches = [];
			preg_match_all('/(?<=\(\?P)[^)]*/', $route, $matches);
			foreach($matches[0] as $match) {
				$unnamed_match = preg_replace('/\<[^>]*\>/', '', $match);
				$match_replacement = self::$url_argument_regexes[$unnamed_match] ?? null;
				if($match_replacement === null) {
					echo "*** Route \"$route\": regex \"\{$unnamed_match}\" is not in the replacements array.\n";
					exit(1);
				}
				$route = str_replace("(?P$match)", self::$url_argument_regexes[$unnamed_match], $route);
			}
			$transformed_routes[] = $route;
		}

		//* Get the route details for each route

		$route_info = [];
		$i = 1;
		$count = count($routes);
		foreach($transformed_routes as $route) {
			echo "$i/$count - $route\n";
			$route_data = self::query_server( $server_url . '/wp-json' . $route, 'OPTIONS');
			if(is_string($route_data)) {
				echo "*** Failed to get info for API endpoint \"$route\": $route_data";
				exit(1);
			}
			if(empty($route_data)) {
				echo "*** No information obtained for \"$route\", does that endpoint exist?\n";
				exit(1);
			}
			$route_info[$route] = $route_data;
			$i++;
		}

		file_put_contents(__DIR__ . "/data.json", json_encode($route_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		//var_dump($route_info);
	}

	private static function query_server(string $url, string $http_verb) {
		curl_setopt(self::$curl, CURLOPT_URL, $url);
		curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(self::$curl, CURLOPT_CUSTOMREQUEST, $http_verb);

		$result = curl_exec(self::$curl);

		curl_close(self::$curl);

		if($result === false) {
			$message = "CURL failed with code " . curl_errno(self::$curl);
			$error_message = curl_error(self::$curl);
			if($error_message) {
				$message .= ' - ' . $error_message;
			}
			return $message . ".\n";
		}

		$http_return_code = (string)curl_getinfo(self::$curl, CURLINFO_HTTP_CODE);
		if($http_return_code[0] !== '2') {
			return "HTTP request failed with status code {$http_return_code}.\n";
		}

		$decoded_response = json_decode($result, true);
		if($decoded_response === null) {
			return "Failed to JSON decode response.\n";
		}

		return $decoded_response;
	}
}

ApiBuilder::run();
