<?php
/**
 *
 * WP_HacksHelper Class
 *
 * @author	Sujin 수진 Choi
 * @package	wp-hacks
 * @version	1.0.0
 * @website	http://sujinc.com
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

if ( !class_exists( 'WP_HacksHelper' ) ) {
	class WP_HacksHelper {
		static private $script_encluded = array();

		public static function showMessage( $text, $class = 'updated' ) {
			printf( '<div id="message" class="%s"><p>%s</a></p></div>', $class, $text );
		}

		/*
		$field = array(
			'name' => string
			'key' => string
			'type' => string
			'value' => mixed
			'class' => text
		);
		*/
		public static function printSettingsField( $field ) {
			$class = ( !empty( $field[ 'class' ] ) ) ? $field[ 'class' ] : 'regular-text';

			switch ( $field[ 'type' ] ) {
				case 'file' :
					$upload_link = get_upload_iframe_src();
					$media_arr = wp_get_attachment_image_src( $field[ 'value' ] );

					$img = $field[ 'value' ] ? '<img src="' . $media_arr[0] . '" />' : '';

					?>
					<div class="custom-img-container" data-key="<?php echo $field[ 'key' ] ?>"><?php echo $img ?></div>
					<a class="upload-custom-img <?php if ( $img ) { echo 'hidden'; } ?>" href="<?php echo $upload_link ?>" data-key="<?php echo $field[ 'key' ] ?>"><?php _e( 'Set custom image' ) ?></a>
					<a class="delete-custom-img <?php if ( !$img ) { echo 'hidden'; } ?>" href="#" data-key="<?php echo $field[ 'key' ] ?>"><?php _e( 'Remove this image' ) ?></a>

					<input class="custom-img-id" name="<?php echo $field[ 'key' ] ?>" type="hidden" value="<?php echo esc_attr( $field[ 'value' ] ); ?>" data-key="<?php echo $field[ 'key' ] ?>" />
					<?php
				break;

				case 'text' :
					?>
					<input type="text" name="<?php echo $field[ 'key' ] ?>" id="<?php echo $field[ 'key' ] ?>" value="<?php echo $field[ 'value' ] ?>" class="<?php echo $class ?>" />
					<?php
				break;

				case 'number' :
					?>
					<input type="number" name="<?php echo $field[ 'key' ] ?>" id="<?php echo $field[ 'key' ] ?>" value="<?php echo $field[ 'value' ] ?>" class="<?php echo $class ?>" />
					<?php
				break;

				case 'checkbox' :
					$class = ( !empty( $field[ 'class' ] ) ) ? $field[ 'class' ] : '';
					?>
					<label for="<?php echo $field[ 'key' ] ?>">
						<input type="checkbox" name="<?php echo $field[ 'key' ] ?>" id="<?php echo $field[ 'key' ] ?>" class="<?php echo $class ?>" <?php if ( $field[ 'value' ] ) echo 'checked="checked"'; ?> />
						<?php echo $field[ 'name' ] ?>
					</label>
					<?php
				break;

				case 'select' :
					$class = ( !empty( $field[ 'class' ] ) ) ? $field[ 'class' ] : '';
					?>
					<select name="<?php echo $field[ 'key' ] ?>" id="<?php echo $field[ 'key' ] ?>" class="<?php echo $class ?>">
						<?php
						if ( !empty( $field['options'] ) ) {
							foreach( $field['options'] as $options ) {
								if ( is_array( $options ) && ( !array_key_exists( 'value', $options ) || !array_key_exists( 'name', $options ) ) ) {
									$options["value"] = $options["name"] = array_shift( $options );

								} else if ( !is_array( $options ) ) {
									$options = array( 'value' => $options, 'name' => $options );

								}
								?>
								<option value="<?php echo $options["value"] ?>" <?php if ( $options["value"] === $field[ 'value' ] ) echo 'selected="selected"'; ?>><?php echo $options["name"] ?></option>
							<?php
							}
						}
						?>
					</select>
					<?php
				break;

				case 'html' :
					echo $field['value'];
				break;

				case 'textarea' :
					$class = ( !empty( $field[ 'class' ] ) ) ? $field[ 'class' ] : 'large-text';
					?>
					<textarea  name="<?php echo $field[ 'key' ] ?>" id="<?php echo $field[ 'key' ] ?>" class="<?php echo $class ?>" rows="8"><?php echo $field[ 'value' ] ?></textarea>
					<?php
				break;

			}
		}

		public static function printMediaUploadScript( $key ) {
			if ( !empty( self::$script_encluded[ $key ] ) ) return false;

			self::$script_encluded[ $key ] = true;
			?>
			<script type="text/javascript">
				jQuery( document ).ready( function($) {
					var frame, selected_key;
					$( '#<?php echo $key ?> .upload-custom-img' ).click( function( e ) {
						e.preventDefault();
						selected_key = $(this).attr( 'data-key' );

						if( !frame ) {
							frame = wp.media({
								title: 'Select or Upload Media Of Your Chosen Persuasion',
								button: { text: 'Use this media' },
								multiple: false
							});

							frame.on( 'select', function() {
								var attachment = frame.state().get('selection').first().toJSON();
								$( '.custom-img-container[data-key="' + selected_key + '"]' ).append( '<img src="' + attachment.url + '" />' );
								$( '.custom-img-id[data-key="' + selected_key + '"]' ).val( attachment.id );

								$( '.upload-custom-img[data-key="' + selected_key + '"]' ).addClass( 'hidden' );
								$( '.delete-custom-img[data-key="' + selected_key + '"]' ).removeClass( 'hidden' );
							});
						}

						frame.open();
					});

					$( '#term-<?php echo $key ?> .delete-custom-img' ).on( 'click', function( e ){
						e.preventDefault();
						selected_key = $(this).attr( 'data-key' );

						$( '.custom-img-container[data-key="' + selected_key + '"]' ).html( '' );
						$( '.custom-img-id[data-key="' + selected_key + '"]' ).val( '' );

						$( '.upload-custom-img[data-key="' + selected_key + '"]' ).removeClass( 'hidden' );
						$( '.delete-custom-img[data-key="' + selected_key + '"]' ).addClass( 'hidden' );
					});
				});
			</script>
			<?php
		}
	}
}










