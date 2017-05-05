<?php
/**
 *
 * WP_Admin_Page Class
 *
 * @author	Sujin 수진 Choi
 * @package	wp-hacks
 * @version	3.0.0
 * @website	http://sujinc.com
 *
 * @require	WP_HacksHelper
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 */

if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( !class_exists( 'WP_Admin_Page' ) ) {
	class WP_Admin_Page {
		private $key;
		private $name;

		private $cb_save;
		private $cb_template;

		private $position;
		private $icon;
		private $capability;
		private $version;

		private $plugin;
		private $url;

		private $settings;
		private $options;

		private $page_now;
		private $updated = false;

		private $scripts;
		private $styles;

		const plugin_pattern = '/wp-content\/plugins\/([0-9a-zA-Z\-_\. ]+)/';
		private $temp_section_key = false;

		public function __construct( $options = array() ) {
			if ( empty( $options ) ) return false;
			if ( !is_array( $options ) ) $options = array( 'name' => $options );

			extract( shortcode_atts( array(
				'key' => false,
				'name' => 'My Option',
				'save' => false,
				'template' => false,
				'position' => 'settings',
				'icon' => 'dashicons-admin-generic',
				'cap' =>'activate_plugins',
				'version' => 'developer',
				'settings' => array(),
			), $options, 'WP_Admin_Page' ) );

			$this->name = __( $name );
			$this->key = ( $key ) ? $key : sanitize_title( $name );

			$this->cb_save = $save;
			$this->cb_template = $template;

			$this->position = $position;
			$this->icon = $icon;
			$this->capability = $cap;
			$this->version = $version;

			$this->settings = $settings;

			$this->page_now = ( isset( $_GET[ 'page' ] ) ) ? $_GET[ 'page' ] : '';

			$get_version = get_option( 'WP_Admin_Page_Version-' . $this->key );

			if ( $this->version === 'developer' ) {
				$this->initSettings();
				$this->detectPlugin();

			} else if ( version_compare( $get_version, $this->version, '<' ) ) {
				$this->initSettings();
				$this->detectPlugin();

				add_action( 'shutdown', array( $this, 'saveVersion' ) );
				update_option( 'WP_Admin_Page_Settings-' . $this->key, $this->settings );
				update_option( 'WP_Admin_Page_Plugin-' . $this->key, $this->plugin );

				$this->updated = true;

			} else {
				$this->settings = get_option( 'WP_Admin_Page_Settings-' . $this->key );
				$this->plugin = get_option( 'WP_Admin_Page_Plugin' . $this->key );
			}

			$this->initOptions();

			if ( $this->plugin ) add_action( 'plugin_action_links', array( $this, 'setPluginActionLinks' ), 15, 2 );

			add_action( 'admin_menu', array( $this, 'setAdminMenu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		}

		public function enqueueScripts() {
			if ( strpos( $this->url, '/wp-admin/options-general.php?page=' . $this->page_now ) !== false ) {
				if ( $this->scripts ) {
					foreach( $this->scripts as $key => $script ) {
						wp_enqueue_script( $this->key . '$key', $script , array( 'jquery' ), $this->version );
					}
				}

				if ( $this->styles ) {
					foreach( $this->styles as $key => $styles ) {
						wp_enqueue_style( $this->key . '$key', $styles , false, $this->version );
					}
				}
			}
		}

		public function saveVersion() {
			update_option( 'WP_Admin_Page_Version-' . $this->key, $this->version );
		}

		// ! Callbacks : for setting section and field
		public function __call( $name, $arguments ) {
			# Settings Section
			if ( strpos( $name, 'settings_section_' ) === 0 ) {
				return true;
			}

			# Settings Section
			if ( strpos( $name, 'settings_field_' ) === 0 ) {
				return $this->printSettingsField( $arguments[0] );
			}
		}

		public function __get( $name ) {
			if ( $name == 'settings' || $name == 'options' ) return $this->options;

			if ( $name == 'updated' ) return $this->updated;
		}

		public function __set( $name, $value ) {
			if ( $name === 'position' ) $this->position = $value;

			if ( $name === 'save' ) $this->cb_save = $value;

			if ( $name === 'template' ) $this->cb_template = $value;

			if ( $name === 'version' ) $this->version = $value;

			if ( $name === 'settings' ) {
				if ( !is_array( $value ) ) {
					$value = array(
						'name' => $value,
						'type' => 'text'
					);
				} else {
					$count_all = count( $value, COUNT_RECURSIVE );
					$count = count( $value );

					if ( $count === $count_all && $count == 2 && !array_key_exists( 'name', $value ) && !array_key_exists( 'type', $value ) && !array_key_exists( 'fields', $value ) ) {
						$value = array(
							'name' => $value[0],
							'type' => $value[1]
						);
					}
				}

				$this->alignSettings( $value );
				$this->initOptions();

				$get_version = get_option( 'WP_Admin_Page_Version-' . $this->key );
				if ( $this->version !== 'developer' && version_compare( $get_version, $this->version, '<' ) ) {
					update_option( 'WP_Admin_Page_Settings-' . $this->key, $this->settings );
					$this->updated = true;
				}
			}

			if ( $name === 'script' || $name === 'js' ) $this->scripts[] = $value;

			if ( $name === 'styles' || $name === 'css' ) $this->styles[] = $value;
		}

		private function initSettings() {
			$settings = $this->settings;
			$this->settings = array();
			$this->alignSettings( $settings );
		}

		// Load Setting from WP Option / Fill with the Default Vaule if not Exists
		private function initOptions() {
			$this->options = get_option( '_' . $this->key . '_', false );

			// If setting is Empty, Set Default
			if ( !$this->options && $this->settings ) {
				$this->setOptionsDefault();
			}
		}

		private function alignSettings( $settings ) {
			if ( !is_array( $settings ) ) return false;

			$field_keys = array();

			if ( !empty( $settings[ 'fields' ] ) ) { // Not Multiple Value && Group
				$this->alignSettingsSection( $settings );
				$this->alignSettingsOrganize();

			} else if ( !empty( $settings[ 'name' ] ) || !empty( $settings[ 'type' ] ) ) { // Not Multiple Value
				$this->alignSettingsSingle( $settings );
				$this->alignSettingsOrganize();

			} else {
				foreach( $settings as $setting ) {
					if ( is_array( $setting ) ) {
						$count_all = count( $setting, COUNT_RECURSIVE );
						$count = count( $setting );

						if ( $count === $count_all && $count == 2 && !array_key_exists( 'name', $setting ) && !array_key_exists( 'type', $setting ) ) {
							$setting = array(
								'name' => $setting[0],
								'type' => $setting[1]
							);
						}

						if ( !empty( $setting[ 'fields' ] ) ) { // Section Exists
							$this->alignSettingsSection( $setting );

						} else { // Section doesn't Exist
							$this->alignSettingsSingle( $setting );

						}

						$this->alignSettingsOrganize();
					}
				}
			}
		}

		private function alignSettingsSection( $setting ) {
			if ( empty( $setting[ 'name' ] ) ) { // Doesn't have Name
				$setting[ 'name' ] = '';
				$setting[ 'key' ] = uniqid();
				$this->settings[] = $setting;
			} else {
				$setting[ 'key' ] = sanitize_title( $setting[ 'name' ] );
				$this->settings[] = $setting;
			}

			$this->temp_section_key = false;
		}

		private function alignSettingsSingle( $setting ) {
			if ( $this->temp_section_key === false ) {
				$this->settings[] = array(
					'name' => '',
					'key' => uniqid(),
					'fields' => array()
				);

				end( $this->settings );
				$this->temp_section_key = key( $this->settings );
			}

			$this->settings[ $this->temp_section_key ][ 'fields' ][] = $setting;
		}

		private function alignSettingsOrganize() {
			// Set Fields Keys
			end( $this->settings );
			$section_key = key( $this->settings );

			if ( !empty( $this->settings[ $section_key ][ 'name' ] ) ) $this->settings[ $section_key ][ 'name' ] = __( $this->settings[ $section_key ][ 'name' ] );

			foreach( $this->settings[ $section_key ][ 'fields' ] as $key => &$field ) {
				if ( !is_array( $field ) ) continue;

				if ( $field[ 'type' ] !== 'html' && empty( $field[ 'key' ] ) ) $field[ 'key' ] = sanitize_title( $field[ 'name' ] );

				if ( is_array( $field[ 'type' ] ) ) { // Multiple Types

					foreach( $field[ 'type' ] as $key => &$multi_field ) {
						$html_field = $this->alignSettingsOrganizeSingleType( $multi_field, $field[ 'key' ] . '-' );

						if ( !$html_field ) {
							unset( $field[ 'type' ][ $key ] );
							continue;
						}

						$multi_field = $html_field;
					}
				} else { // Single Type
					$html_field = $this->alignSettingsOrganizeSingleType( $field );

					if ( !$html_field ) {
						unset( $this->settings[ $section_key ][ 'fields' ][ $key ] );
						continue;
					}

					$field = $html_field;
				}
			}
		}

		private function alignSettingsOrganizeSingleType( $field, $prefix = '' ) {
			if ( !empty( $field[ 'type' ] ) && $field[ 'type' ] == 'html' ) { // HTML Type
				if ( empty( $field[ 'value' ] ) && empty( $field[ 'name' ] ) ) { // Empty
					return false;

				} else if ( !empty( $field[ 'value' ] ) && empty( $field[ 'name' ] ) ) { // Empty Name
					$field[ 'name' ] = '';

				} else if ( empty( $field[ 'value' ] ) && !empty( $field[ 'name' ] ) ) { // Empty Value
					$field[ 'value' ] = $field[ 'name' ];
					$field[ 'name' ] = '';
				}

			} else if ( empty( $field[ 'name' ] ) ) {
				return false;

			} else if ( empty( $field[ 'type' ] ) ) {
				$field[ 'type' ] = 'text';
			}


			if ( empty( $field[ 'key' ] ) ) {
				$field[ 'key' ] = $prefix . sanitize_title( $field[ 'name' ] );
			}

			$field[ 'name' ] = __( $field[ 'name' ] );

			return $field;
		}

		// Set Default Values to Options
		private function setOptionsDefault() {
			// Extract $settings_query's default value
			foreach( $this->settings as $section ) {
				foreach( $section[ 'fields' ] as $fields ) {
					// Multiple values
					if ( is_array( $fields[ 'type' ] ) ) {
						foreach( $fields[ 'type' ] as $type ) {
							if ( !empty( $type[ 'key' ] ) ) {
								$this->options[ $type[ 'key' ] ] = ( !empty( $type[ 'default' ] ) ) ? $type[ 'default' ] : false;
							}
						}
					} else {
						if ( !empty( $fields[ 'key' ] ) ) {
							$this->options[ $fields[ 'key' ] ] = ( !empty( $fields['default'] ) ) ? $fields['default'] : false;
						}
					}
				}
			}
		}

		private function detectPlugin() {
			$debug_backtrace = (array) debug_backtrace();

			foreach( $debug_backtrace as $backtrace ) {
				if ( !empty( $backtrace[ 'file' ] ) && strpos( $backtrace[ 'file' ], 'wp-content/plugins' ) !== false ) {
					preg_match( self::plugin_pattern, $backtrace[ 'file' ], $matches );

					if( !empty( $matches[1] ) ) {
						$this->plugin = $matches[1];
						break;
					}
				}
			}
		}

		public function setPluginActionLinks( $actions, $plugin_file ) {
			$plugin_file = explode( '/', $plugin_file );

			if( $plugin_file[0] == $this->plugin ) {
				$actions[ 'setting' ] = sprintf( '<a href="%s"><span class="dashicons dashicons-admin-settings"></span> Setting</a>', $this->url );
			}

			return $actions;
		}

		public function setAdminMenu() {
			switch ( $this->position ) {
				case 'option' :
				case 'settings' :
				case 'Settings' :
					add_options_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'options-general.php?page=' . $this->key );
				break;

				case 'tools' :
				case 'Tools' :
					add_management_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'tools.php?page=' . $this->key );
				break;

				case 'users' :
				case 'Users' :
					add_users_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'users.php?page=' . $this->key );
				break;

				case 'plugins' :
				case 'Plugins' :
					add_plugins_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'plugins.php?page=' . $this->key );
				break;

				case 'comments' :
				case 'Comments' :
					add_comments_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'comments.php?page=' . $this->key );
				break;

				case 'pages' :
				case 'Pages' :
					add_pages_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'edit.php?post_type=page&page=' . $this->key );
				break;

				case 'posts' :
				case 'Posts' :
					add_posts_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'edit.php?page=' . $this->key );
				break;

				case 'media' :
				case 'Media' :
					add_media_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'upload.php?page=' . $this->key );
				break;

				case 'dashboard' :
				case 'Dashboard' :
					add_dashboard_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'index.php?page=' . $this->key );
				break;

				case 'appearance' :
				case 'Appearance' :
					add_theme_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
					$this->url = admin_url( 'themes.php?page=' . $this->key );
				break;

				default :
 					global $menu;
 					$position_key = $this->key;

					if ( is_numeric( $this->position ) ) {
						if ( isset( $menu[ $this->position ] ) ) {
							$position_key = $menu[ $this->position ][2];
							add_submenu_page( $position_key, $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
						} else {
							add_menu_page( $this->name, $this->name, $this->capability, $position_key, array( $this, 'printTemplate' ), $this->menu_icon, $this->position );
						}
					} else {
						$detected = false;

						foreach( $menu as $menu_ ) {
							if ( $this->position == $menu_[0] ) {
								$position_key = $menu_[2];
								$detected = true;
								add_submenu_page( $position_key, $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ) );
								break;
							}
						}

						if ( !$detected ) {
							add_menu_page( $this->name, $this->name, $this->capability, $this->key, array( $this, 'printTemplate' ), $this->menu_icon );
						}
					}

					$this->url = admin_url( 'admin.php?page=' . $this->key );
				break;
			}
		}

		// Save
		private function saveSettings() {
			if( !$_POST || !wp_verify_nonce( $_POST['_wpnonce'], $this->key . '-options' ) ) return false;
			unset( $_POST['option_page'], $_POST['action'], $_POST['_wpnonce'], $_POST['_wp_http_referer'], $_POST['submit'] );

			if ( $this->cb_save ) {
				call_user_func( $this->cb_save );

			} else {
				$this->options = $_POST;

				$this->options = apply_filters( 'WP_Admin_Page_update_option_' . $this->key , $this->options );
				update_option( '_' . $this->key . '_', $this->options );
				if ( class_exists( 'WP_HacksHelper' ) ) WP_HacksHelper::showMessage( 'Option Saved!' );

				do_action( 'WP_Admin_Page_' . $this->key . '_after_update_option' , $this->options );
			}
		}

		// Admin Page
		public function printTemplate() {
 			$this->saveSettings();
			$this->setSettingsSection();

			if ( $this->cb_template ) {
				call_user_func( $this->cb_template );

			} else {
				?>
				<div class="wrap" id="admin-<?php echo $this->key ?>">
					<h2 class="page-title"><?php echo $this->name ?></h2>

					<?php if ( $this->version === 'developer' ) { ?>
					<div class="description">The setting will be stored in <code>_<?php echo $this->key ?>_</code> option value. This message will be disappeared when you set <code>version</code> value. ( ig. 1.0.0 )</div>
					<?php } ?>

					<div class="clear"></div>

					<form id="form-<?php echo $this->key ?>" method="POST" enctype="multipart/form-data">
						<?php settings_fields( $this->key ); ?>
						<?php do_settings_sections( $this->key ); ?>
						<?php submit_button( 'Submit', 'primary' ); ?>
					</form>
				</div>
				<?php
			}
		}

		// ! Register Sections and Fields
		private function setSettingsSection() {
			foreach ( $this->settings as $setting ) {
				add_settings_section( $setting[ 'key' ], $setting['name'], array( $this, 'settings_section_' . $setting[ 'key' ] ), $this->key );

				# Set Fields
				foreach ( $setting['fields'] as $field ) {
					add_settings_field( $field[ 'key' ], $field[ 'name' ], array( $this, 'settings_field_' . $field[ 'key' ] ), $this->key, $setting[ 'key' ], $field );
					register_setting( $setting[ 'key' ], $field[ 'key' ] );

					if ( $field['type'] === 'file' && class_exists( 'WP_HacksHelper' ) ) {
						add_action( 'admin_footer', function() { WP_HacksHelper::printMediaUploadScript( 'form-' . $this->key ); } );
						add_action( 'admin_enqueue_scripts', 'wp_enqueue_media' );
					}
				}
			}
		}

		# Setting API Fields
		private function printSettingsField( $arg ) {
			if ( !class_exists( 'WP_HacksHelper' ) ) return false;

			if ( $arg[ 'type' ] != 'html' )
				$arg[ 'value' ] = ( !empty( $this->options[ $arg[ 'key' ] ] ) ) ? $this->options[ $arg[ 'key' ] ] : false;

			if ( is_array( $arg[ 'type' ] ) ) {
				foreach ( $arg[ 'type' ] as $multiple_type )
					$this->printSettingsField( $multiple_type );

			} else {
				WP_HacksHelper::printSettingsField( $arg );

				if ( !empty( $arg[ 'description' ] ) ) {
					echo '<p class="description">' . $arg[ 'description' ] . '</p>';
				}
			}
		}

		public function update_options( $options ) {
			$this->options = $options;
			update_option(  '_' . $this->key . '_', $options );
		}
	}
}

