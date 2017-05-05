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

if( !function_exists( 'console' ) ) {
	/**
		* print debug message
		*
		* @return void
		* @since 1.0
	*/

	function console() {
		$arguments = func_get_args();
		$debug_backtrace = (array) debug_backtrace();

		if ( empty( $arguments ) ) return;

		foreach( $arguments as $message_key => $message ) {
			if ( empty( $debug_backtrace[$message_key]['file'] ) ) {
				$idx = 0;
			} else {
				$idx = strpos($debug_backtrace[$message_key]['file'], 'id.php') ? 1 : 0;
			}
			$src = (object)$debug_backtrace[$idx];
			$file = file( $src->file );

			$i = 1;
			do {
				$line = $file[$src->line - $i++];
			} while ( strpos( $line, 'console' ) === false );

			$line = str_replace( ' ', '', $line );
			preg_match( '/console\((.+?)\)?(?:$|;|\?>)/', $line, $m );

			$key = $m[1];
			$key = explode( ',', $key );
			$key = trim( $key[$message_key] );

			$GLOBALS['QMCVar']->_IO->message[] = array(
				'message' => print_r( $message, true ),
				'file' => $src->file,
				'line' => $src->line,
				'key' => $key,
				'type' => gettype( $message )
			);
		}
	}
}
