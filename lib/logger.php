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
 * NS8CSP remote logging
 */
class NS8CSP_Logger
{

	/**
	 * Log an error.
	 *
	 * @param string $function  The function name
	 * @param $log              A description of the log entry
	 * @param mixed null $data  Any data associated with the log entry
	 */
	static function error($function, $log, $data = null) {
		self::log($function, $log, $data, 1);
	}

	/**
	 * Log a warning.
	 *
	 * @param string $function  The function name
	 * @param $log              A description of the log entry
	 * @param mixed null $data  Any data associated with the log entry
	 */
	static function warn($function, $log, $data = null)	{
		self::log($function, $log, $data, 2);
	}

	/**
	 * Log info.
	 *
	 * @param string $function  The function name
	 * @param $log              A description of the log entry
	 * @param mixed null $data  Any data associated with the log entry
	 */
	static function info($function, $log, $data = null)	{
		self::log($function, $log, $data, 3);
	}

	/**
	 * Create a remote log entry.
	 *
	 * @param string $function  The function name
	 * @param $log              A description of the log entry
	 * @param mixed null $data  Any data associated with the log entry
	 * @param int $level        The level of the entry
	 */
	static function log($function, $log, $data = null, $level = 1)
	{
		try {
			//  log to the cloud
			$params = [
				'level' => $level,
				'category' => 'woo ns8csp',
				'data' => [
					'platform' => 'woo',
					'projectId' => NS8CSP_Config::get_project_id(),
					'function' => $function,
					'message' => $log,
					'data' => $data,
					"wooVersion" => NS8CSP_Config::wc_version(),
					"moduleVersion" => NS8CSP_VERSION,
					"phpVersion" => phpversion(),
					"phpOS" => PHP_OS
				]
			];

			$options = array(
				'blocking' => false,
				'timeout' => 5
			);

			NS8CSP_API::post( '/ops/logs', $params, $options );
		} finally {
			return;
		}
	}
}