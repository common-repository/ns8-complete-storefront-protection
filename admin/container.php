<?php
/**
 * Author: NS8.com
 * Author URI: https://ns8.com
 * Copyright 2017 NS8.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

/**
 * Page manager for the plugin's container
 */
class NS8CSP_Pages {

	/**
	 * Renders the container frame for the dashboard
	 */
	static function dashboard() {
        self::render_container('/status');
    }

	/**
	 * Renders the container frame for the suspicious order report
	 */
	static function orders() {
		self::render_container('/report?id=suspiciousOrders');
	}

	/**
	 * Renders the container frame for the order rules page
	 */
	static function rules() {
		self::render_container('/rules');
	}

	/**
	 * Renders the container frame for the site monitors page
	 */
	static function monitors() {
		self::render_container('/monitors');
	}

	/**
	 * Renders the container frame for the campaign activity page
	 */
	static function campaigns() {
		self::render_container('/report?id=campaignActivity');
	}

	/**
     * Renders the container frame that hosts the app
     *
	 * @param string $path      The path to load into the container
	 */
	static function render_container($path) {

		if ( isset( $_GET["setApi"] ) ) {
	        $url = esc_url_raw( $_GET["setApi"] );

	        if ( filter_var($url, FILTER_VALIDATE_URL ) ) {
		        NS8CSP_Config::set_api_base_url( $url );
	        }
        }

	    if ( isset( $_GET["setWebsite"] ) ) {
		    $url = esc_url_raw( $_GET["setWebsite"] );

		    if ( filter_var($url, FILTER_VALIDATE_URL ) ) {
			    NS8CSP_Config::set_website_base_url( $url );
		    }
	    }

	    if ( isset( $_GET["resetEndpoints"] ) ) {
		    NS8CSP_Config::reset_endpoints();
	    }

	    if ( isset($_GET["path"] ) ) {
	        $path = esc_url_raw( $_GET["path"] );
	    }

        if (!isset($path) || $path === '') {
            $path = '/status';
        }

        if (!strrpos($path, 'accessToken')) {

            if (strrpos($path, '?') > -1) {
                $path .= '&';
            } else {
                $path .= '?';
            }
            $path .= 'accessToken='.NS8CSP_Config::get_access_token();
        }

        $url = NS8CSP_Config::get_website_base_url().$path.'&wc-active='.( NS8CSP_Config::wc_active() ? 1 : 0 );

        ?>

        <div class="wrap">
            <h2>NS8 Protect™</h2>
        </div>

        <div id="ns8csp-notice-info" class="notice notice-info" style="display: none"><p></p></div>
        <div id="ns8csp-notice-error" class="notice notice-error" style="display: none"><p></p></div>
        <div id="ns8csp-notice-warning" class="notice notice-warning" style="display: none"><p></p></div>
        <div id="ns8csp-notice-success" class="notice notice-success" style="display: none"><p></p></div>

        <div id="ns8-modal" class="hidden" style="max-width:800px"></div>

        <div id="ns8-modal-prompt" class="hidden" style="max-width:800px">
            <div id="ns8-modal-prompt-content"></div>
            <input id="ns8-modal-prompt-value" style="width:100%" />
        </div>

        <div id="ns8-modal-confirm" class="hidden" style="max-width:800px">
            <div id="ns8-modal-confirm-content"></div>
        </div>

        <!--  The IFRAME that hosts the app -->
        <br>
        <div style="padding-right:20px">
            <iframe id="ns8-app-iframe" width="100%" name="ns8-app-iframe" frameborder="0" scrolling="no" src="<?= $url ?>"></iframe>
        </div>

        <script>
            var NS8CSPConfig = {
                shopId: <?php echo get_current_blog_id() ?>,
                adminUrl: '<?php echo admin_url(); ?>',
                homeUrl: '<?php echo home_url(); ?>',
                wcActive: <?php echo NS8CSP_Config::wc_active() ? 1 : 0; ?>,
                apiUrl: '<?php echo NS8CSP_Config::get_api_base_url(); ?>',
                websiteUrl: '<?php echo NS8CSP_Config::get_website_base_url(); ?>',
                paymentUrl: '<?php echo admin_url(); ?>' + '?page=ns8csp_dashboard&path=/billing/paymentmethods',
                monitorsUrl: '<?php echo admin_url(); ?>' + '?page=ns8csp_dashboard&path=/monitors'
            };

            jQuery(document).ready(function () {
                initializeAdmin();
            });
        </script>
	    <?php
    }
}