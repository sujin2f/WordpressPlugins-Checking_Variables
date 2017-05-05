<?php
/**
 * Initialize
 *
 * project	Checking Variables (Dev. Tool)
 * version	4.0.0
 * Author: Sujin 수진 Choi
 * Author URI: http://www.sujinc.com/
 *
*/

if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

class QMCV {
	private static $__instance;
	private $version;

	private $style_path;
	private $script_path;

	public $_AdminPage;
	public $_IO;

	public function __construct() {
		$this->include_files();
		$this->set_vars();
		$this->trigger_hooks();
	}

	private function include_files() {
		include_once( QMCV_CLASS_DIR . 'class.admin_page.php');
		include_once( QMCV_CLASS_DIR . 'class.helper.php');
		include_once( QMCV_CLASS_DIR . 'class.io.php');

		include_once( QMCV_CLASS_DIR . 'function.console.php');
	}

	private function set_vars() {
		$this->style_path = QMCV_ASSETS_URL . 'css/checking-vars.css';
		$this->script_path = QMCV_ASSETS_URL . 'script/debugger-min.js';
	}

	private function trigger_hooks() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		// Style and Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
	}

	// <-- Hooks
	public function wp_enqueue_scripts() {
		wp_enqueue_style( 'QMCV_Debugger', $this->style_path, false, '1.0' );
		wp_enqueue_script( 'QMCV_Debugger', $this->script_path, array( 'jquery' ), '1.0' );
	}

	public function plugins_loaded() {
		$this->set_admin_page();
		if ( $this->_AdminPage->updated ) {
			$this->update_plugin();
		}

		$this->execute_query_monitor();
	}
	// Hooks -->

	private function execute_query_monitor() {
		$this->_IO = QMCV_IO::getInstance();
		$setting = $this->_AdminPage->settings;

		if ( class_exists( 'QueryMonitor' ) && $setting['enable-query-monitor'] === 'on' ) {
			// Set Query Monitor Collector
			include_once( QMCV_CLASS_DIR . 'query_monitor/collector.php');
 			QMCV_Collector_Variable_Checking::initialize();
		}
	}

	private function update_plugin() {
		$options = get_option( 'QMCV_options' );

		$options_new = array(
			'show-on-footer' => $options[ 'footer' ],
			'hide-footer' => $options[ 'hide' ],
			'javascript-console' => $options[ 'console' ],
			'allowed-users' => $options[ 'users' ],
			'enable-query-monitor' => $options[ 'query_monitor' ]
		);

		if ( !empty( $options[ 'capability' ] ) ) {
			foreach( $options[ 'capability' ] as $capability ) {
				$options_new[ 'capability-' . $capability ] = 'on';
			}
		}

		$this->_AdminPage->update_option( $options_new );
	}

	private function set_admin_page() {
		$capabilities = array();
		foreach( wp_roles()->roles as $key => $capability ) {
			$capabilities[] = array(
				'name' => $capability['name'],
				'type' => 'checkbox',
			);
		}
		$capabilities[] = array(
			'name' => 'Guest',
			'type' => 'checkbox',
		);

		$donation = '
		<div id="donation_div">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----">
			<input type="image" id="donation_submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</div>
		';

		$donation.= "
		<script>
		jQuery( document ).ready( function($) {
			$( '#donation_submit' ).click( function( e ) {
				e.preventDefault();

				$( '<form/>', {
					'action':'https://www.paypal.com/cgi-bin/webscr',
					'method':'post',
					'target':'_blank',
					'class':'hidden',
					'id':'donation_form',
				}).appendTo('body');

				$( '#donation_div' ).clone().appendTo( 'form#donation_form' );
				$( '#donation_form' ).submit();
				$( '#donation_form' ).remove();
			});
		});
		</script>
		";

		$this->_AdminPage = new WP_Admin_Page( array(
			'name' => 'Check Variables',
			'settings' => array(
				array(
					'name' => '',
					'type' => 'html',
					'value' => $donation
				),
				array(
					'name' => 'Show on Footer',
					'type' => 'checkbox',
				),
				array(
					'name' => 'Hide Footer',
					'type' => 'checkbox',
					'default' => true,
				),
				array(
					'name' => 'Javascript Console',
					'type' => 'checkbox',
					'default' => true,
				),
				array(
					'name' => 'Capability',
					'type' => $capabilities
				),
				array(
					'name' => 'Allowed Users',
					'type' => 'text',
					'description' => 'Input User ID (number) separated by commas(,).'
				),
				array(
					'name' => 'Enable Query Monitor',
					'type' => 'checkbox'
				)
			)
		));

		$this->_AdminPage->version = '4.0.0';
	}

	/**
	 * Return Instance
	 *
	 * @since 3.0
	 * @access public
	 */
	public static function getInstance() {
		// check if instance is avaible
		if ( self::$__instance==null ) {
			// create new instance if not
			self::$__instance = new self();
		}
		return self::$__instance;
	}
}
