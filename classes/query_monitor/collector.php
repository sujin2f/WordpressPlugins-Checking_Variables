<?php
/**
 * Collector for Query Monitor
 *
 * project	Checking Variables (Dev. Tool)
 * version	4.0.0
 * Author: Sujin
 * Author URI: http://www.sujinc.com/
 *
*/

if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

class QMCV_Collector_Variable_Checking extends QM_Collector {
	public $id = 'variable_checking';
	function name() {
		return __( 'Variable Checking', 'query-monitor' );
	}

	static public function initialize() {
		add_filter( 'qm/outputter/html', array( 'QMCV_Collector_Variable_Checking', 'register_output' ), 10, 2 );
		QM_Collectors::add( new QMCV_Collector_Variable_Checking );
	}

	static function register_output( array $output, QM_Collectors $collectors ) {
		if ( $collector = QM_Collectors::get( 'variable_checking' ) ) {
			require_once( QMCV_CLASS_DIR . 'query_monitor/output.php' );
			$output['variable_checking'] = new QMCV_Output_Variable_Checking( $collector );
		}
		return $output;
	}
}


