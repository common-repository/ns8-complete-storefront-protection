<?php
/**
 * Author: NS8.com
 * Author URI: https://ns8.com
 * Copyright 2017 NS8.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined( 'ABSPATH' ))
	exit();

/**
 * Class for REST calls to the NS8 API
 */
class NS8CSP_API {

	/**
	 * Return an array of options for a REST call.
	 *
	 * @param string $verb              HTTP verb (GET, POST, etc)
	 * @param $data                     The call's payload
	 * @param array $options            Call options (timeout, etc)
	 *
	 * @return array
	 */
	private static function get_options( $verb, $data, $options = array() ) {

		return array(
			'method' => $verb,
			'timeout' => isset( $options['timeout'] ) ? $options['timeout'] : 20,
			'redirection' => 2,
			'httpversion' => '1.0',
			'blocking' => isset( $options['blocking'] ) ? $options['blocking'] : true,
			'headers' => isset( $options['headers'] ) ? $options['headers'] : array(),
			'body' => $data,
			'cookies' => array()
		);
	}

	/**
	 * Execute a POST call.
	 *
	 * @param string $path          The path on the API
	 * @param null $data            The payload
	 * @param array null $options   Options to override the default options
	 *
	 * @return array                The data returned from the call
	 */
	static function post( $path, $data = null, $options = null ) {
		return self::get_result( wp_remote_post( NS8CSP_Config::get_api_base_url().$path, self::get_options( 'POST', $data, $options ) ) );
	}

	/**
	 * Execute a GET call.
	 *
	 * @param string $path          The path on the API
	 * @param null $data            The payload
	 * @param array null $options   Options to override the default options
	 *
	 * @return array                The data returned from the call
	 */
	static function get( $path, $data = null, $options = null ) {
		return self::get_result( wp_remote_post( NS8CSP_Config::get_api_base_url().$path, self::get_options( 'GET', $data, $options ) ) );
	}

	/**
	 * Return a normalized http response, which decodes the JSON payload.
	 *
	 * @param array $response       The raw response from the REST call
	 *
	 * @return array                The normalized response (code, message, data)
	 */
	static function get_result( $response ) {
		if ( ! isset( $response ) ) {
			return array(
				"code"    => 500,
				"message" => 'No response'
			);
		}

		if ( is_wp_error( $response ) ) {
			return array(
				"code"    => 500,
				"message" => $response->get_error_message()
			);
		}

		if ( isset( $response->errors ) ) {
			return array(
				"code"    => 500,
				"message" => 'An error occurred'
			);
		}

		if ( !isset( $response['response'] ) || !isset( $response['response']['code'] ) ) {
			return array(
				"code"    => 500,
				"message" => 'No response or no response code returned'
			);
		}

		$result = array(
			"code"    => $response['response']['code'],
			"message" => $response['response']['message']
		);

		if ( isset( $response['body'] ) ) {
			$result['body'] = $response['body'];

			if ( NS8CSP_Config::isJSON( $response['body'] ) ) {
				$json = json_decode( $response['body'] );

				if ( isset( $json->data ) ) {
					$result['data'] = $json->data;
				}
			}
		}
		return $result;
	}
}
