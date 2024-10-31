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
 * NS8CSP handle inactive sites.
 */
class NS8CSP_Setup_Inactive {

	/**
	 * Add actions for an inactive site.
	 */
	public static function init() {
		add_action( 'admin_menu', array( 'NS8CSP_Setup_Inactive', 'add_menus' ) );
		add_action( 'wp_ajax_ns8csp_activate', array( 'NS8CSP_Setup', 'activate_ajax' ) );
	}

	/**
	 * Add the menus for an inactive site.
	 */
	static function add_menus() {

		if ( current_user_can( 'manage_options' ) ) {

			add_menu_page(
				'NS8 Protect',
				'NS8 Protect',
				'manage_options',
				'ns8csp_activate',
				null,
				'dashicons-shield'
			);

			add_submenu_page(
				'ns8csp_activate',
				'NS8 Protect',
				'Activate',
				'manage_options',
				'ns8csp_activate',
				array( 'NS8CSP_Setup_Inactive', 'activate_page' )
			);
		}
	}

	/**
	 * Display the page to activate the plugin.
	 */
	static function activate_page() {
	    $url = NS8CSP_Config::container_link('/status');

		?>
		<div class="wrap">
			<h2>NS8 Protect™</h2>
		</div>
		<h2>Plugin Activation</h2>
		NS8 Protect is currently not activated for this site.  Do you wish to activate it?
        <script>
            function NS8CSP_Activate() {
                jQuery.post(ajaxurl, { action: 'ns8csp_activate' }, function(response) {
                    location.href = '<?php echo $url ?>';
                });
            }
        </script>
		<?php
		echo '<br><br><input type="button" class="button button-primary" value="Activate" onclick="NS8CSP_Activate()" />';
	}
}
