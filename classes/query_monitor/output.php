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

class QMCV_Output_Variable_Checking extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 60 );
	}

	public function output() {
		$data = $this->collector->get_data();

		?>
		<div class="qm" id="<?php echo esc_attr( $this->collector->id() ) ?>">
			<table cellspacing="0" class="QMCV_Table">
				<thead>
					<tr>
						<th colspan="2"><?php echo esc_html( $this->collector->name() ) ?></th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td colspan="2">
							<?php $GLOBALS['QMCVar']->_IO->echo_debug( true ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}

