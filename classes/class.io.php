<?php
/**
 * Input and Output
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

class QMCV_IO {
	private static $__instance;

	public $message = array();

	private $setting;
	private $message_sandbox = array();
	private $message_stack = array();
	private $json_sandbox = array();
	private $message_key = 2;

	private $prev_temp_key = 0;

	public function __construct() {
		$this->setting = get_option( '_check-variables_' );

		if ( $this->is_user_allowed() && ( !empty( $this->setting['show-on-footer'] ) || !empty( $this->setting['javascript-console'] ) ) ) {
			add_action( 'wp_footer', array( $this, 'echo_debug' ) );
			add_action( 'admin_footer', array( $this, 'echo_debug' ) );
		}
	}

	private function is_user_allowed() {
		$WP_User = wp_get_current_user();

		if ( empty( $WP_User->ID ) && !empty( $this->setting['guest'] ) ) return true;
		if ( empty( $WP_User->ID ) ) return false;

		// Allowed User
		$allowed_users = str_replace( ' ', '', $this->setting['allowed-users'] );
		$allowed_users = explode( ',', $allowed_users );

		if ( in_array( $WP_User->ID, $allowed_users ) ) return true;

		if ( !empty( $this->setting ) ) {
			foreach( $WP_User->roles as $role ) {
				if ( array_key_exists( 'capability-' . $role, $this->setting ) ) return true;
			}
		}
	}

	public function echo_debug( $query_monitor = false ) {
		$hide = ( !empty( $this->setting['hide-footer'] ) && !$query_monitor ) ? ' hidden' : '';

		foreach( $this->message as $key => $message ) {
			$title = $message['type'];

			switch( $message['type'] ) {
				case 'object' :
				case 'array' :
					$this->message_sandbox = array();
					$this->message_stack = explode( PHP_EOL, $message['message'] );
					$title = $this->message_stack[0];
					unset( $this->message_stack[0] );
					unset( $this->message_stack[1] );

					$this->txt2structure( $this->message_stack, $this->message_sandbox );

					$message_key = $message['key'];
					$html_value = sprintf( '<dl data-id="%s">', $key );

					ob_start();
					$this->structure2html( $this->message_sandbox );
					$html_value.= ob_get_contents();
					ob_end_clean();

					$html_value.= '</dl>';

					$this->structure2json( $this->message_sandbox, $this->json_sandbox );
					$json = str_replace( "'", "\'", json_encode( $this->json_sandbox ) );
				break;

				case 'boolean' :
				case 'NULL' :
					$message['message'] = ( $message['type'] == "boolean" ) ? ( $message['message'] ) ? 'true' : 'false' : "NULL";

				default :
					$html_value = sprintf( '<div class="QMCV_value" data-id="%s">%s</div>', $key, $message['message'] );

					$jskey = str_replace( "\"", "'", $message['key'] );
					$jskey = str_replace( "'", "\\'", $jskey );

					$json = sprintf( '{"%s":"%s"}', $jskey, $message['message'] );
				break;
			}

			# HTML
			if ( !empty( $this->setting['show-on-footer'] ) || $query_monitor ) {
				printf( '<div class="QMCV_IO %s">', $hide );

				printf( '<h3 data-id="%s"><span class="dashicons dashicons-arrow-down"></span> %s <span>%s</span></h3>', $key, $message['key'], $title );
				printf( '<div class="file">%s <span>( Line %s )</span></div>', $message['file'], $message['line'] );

				echo $html_value;

				echo '</div>';
			}

			# JS Console
			if ( !empty( $this->setting['javascript-console'] ) && !$query_monitor ) {
				?>
				<script type="text/javascript">
					QMCV_Data = '<?php echo $json; ?>';
					QMCV_Data = JSON.parse( QMCV_Data );
					console.log( QMCV_Data );
				</script>
				<?php
			}
		}
	}

	private function txt2structure( &$message_stack, &$message_sandbox ) {
		foreach ( $message_stack as $msg_key => $message ) {
			$trimed = trim( $message );
			if ( !$trimed ) continue;

			if ( $trimed == ')' ) {
				unset($message_stack[$msg_key]);
				return true;
			}

			$trimed_next = trim( $message_stack[$msg_key+1] );
			$explode = explode(  '=>', $trimed, 2 );

			if ( !isset( $explode[1] ) && $trimed ) {
				$message_sandbox[ $this->prev_temp_key ].= htmlspecialchars( $trimed );
				unset($message_stack[$msg_key]);
			} else {
				$key = substr( trim( $explode[0] ), 1, -1 ) ;
				$val = trim( $explode[1] );

				unset($message_stack[$msg_key]);

				if ( $trimed_next == '(' ) {
					unset($message_stack[$msg_key + 1]);

					$message_sandbox[$key] = array(
						'type' => $val,
						'value' => array()
					);
					$this->txt2structure( $message_stack, $message_sandbox[$key]['value'] );
				} else {
					$message_sandbox[$key] = htmlspecialchars( $val );
					$this->prev_temp_key = $key;
				}
			}
		}
	}

	private function structure2json( $message_sandbox, &$json_sandbox ) {
		foreach( $message_sandbox as $key => $message ) {
			$visibility = 'public';

			if ( $protected_position = strstr( $key, ':protected' ) !== false ) {
				$key = substr( $key, 0, $protected_position );
				$visibility = 'protected';
			}

			if ( $private_position = strstr( $key, ':private' ) !== false ) {
				$key = substr( $key, 0, $private_position );
				$visibility = 'private';
			}

			if ( is_array( $message ) ) {
				$json_sandbox[$key] = array(
					'visibility' => $visibility,
					'type' => strtolower( $message['type'] )
				);
				if ( !$message['value'] ) {
					$json_sandbox[$key]['value'] = array();
				} else {
					$json_sandbox[$key]['value'] = false;
					$this->structure2json( $message['value'], $json_sandbox[$key]['value'] );
				}
			} else {
				$json_sandbox[$key] = str_replace( array( "\"" ), array( "\\\"" ), $message );

			}
		}
	}

	private function structure2html( $message_sandbox, $parentKey = array() ) {
		if ( $parentKey ) echo '<dl>';

		foreach( $message_sandbox as $key => $message ) {
			$protected = $private = '';

			$protected_position = strpos( $key, ':protected' );
			if ( $protected_position !== false ) {
				$key = substr( $key, 0, $protected_position );
				$protected = '<span class="class_keyword">:protected</span>';
			}
			$private_position = strpos( $key, ':private' );
			if ( $private_position !== false ) {
				$key = substr( $key, 0, $private_position );
				$private = '<span class="class_keyword">:private</span>';
			}

			if ( is_array( $message ) ) {
				$parentKey[] = str_replace( ' ', '', $key );
				$itemKey = implode( '-', $parentKey );

				printf( '<dt class="foldable" data-id="QMCV_IO-%s"><span class="dashicons dashicons-arrow-down"></span> [%s%s%s] <span class="type">( %s )</span></dt>', $itemKey, $key, $protected, $private, strtolower( $message['type'] ) );
				printf( '<dd data-id="QMCV_IO-%s">', $itemKey );
				$this->structure2html( $message['value'], $parentKey );
				echo '</dd>';
			} else {
				printf( '<dt class="single"><span class="dashicons"></span> [%s%s%s] <span class="value">%s</span></dt>', $key, $protected, $private, $message );
			}
		}

		if ( $parentKey ) echo '</dl>';
	}

	public static function getInstance() {
		// check if instance is avaible
		if ( self::$__instance==null ) {
			// create new instance if not
			self::$__instance = new self();
		}
		return self::$__instance;
	}
}

