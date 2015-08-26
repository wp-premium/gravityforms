<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Installation_Wizard {
	private $_step_class_names = array();

	function __construct(){
		$path = GFCOmmon::get_base_path()  . '/includes/wizard/steps/';
		require_once( $path . 'class-gf-installation-wizard-step.php' );
		$classes = array();
		foreach ( glob( $path . 'class-gf-installation-wizard-step-*.php' ) as $filename ) {
			require_once( $filename );
			$regex = '/class-gf-installation-wizard-step-(.*?).php/';
			preg_match( $regex, $filename, $matches );
			$class_name = 'GF_Installation_Wizard_Step_' . str_replace( '-', '_', $matches[1] );
			$step = new $class_name;
			$step_name = $step->get_name();
			$classes[ $step_name ] = $class_name;
		}
		$sorted = array();
		foreach ( $this->get_sorted_step_names() as $sorted_step_name ){
			$sorted[ $sorted_step_name ] = $classes[ $sorted_step_name ];
		}
		$this->_step_class_names = $sorted;
	}

	public function get_sorted_step_names(){
		return array(
			'license_key',
			'background_updates',
			'settings',
			'complete',
		);
	}

	public function display(){

		$name = rgpost( '_step_name' );

		$current_step = $this->get_step( $name );

		$nonce_key = '_gform_installation_wizard_step_' . $current_step->get_name();

		if ( isset( $_POST[ $nonce_key ] ) && check_admin_referer( $nonce_key, $nonce_key ) ) {

			if ( rgpost( '_previous' ) ) {
				$posted_values = $current_step->get_posted_values();
				$current_step->update( $posted_values );
				$previous_step = $this->get_previous_step( $current_step );
				if ( $previous_step ) {
					$current_step = $previous_step;
				}
			} elseif ( rgpost( '_next' ) ) {
				$posted_values = $current_step->get_posted_values();
				$current_step->update( $posted_values );
				$validation_result = $current_step->validate();
				$current_step->update();
				if ( $validation_result === true ) {
					$next_step = $this->get_next_step( $current_step );
					if ( $next_step ) {
						$current_step = $next_step;
					}
				}
			} elseif ( rgpost( '_install' ) ) {
				$posted_values = $current_step->get_posted_values();
				$current_step->update( $posted_values );
				$validation_result = $current_step->validate();
				$current_step->update();
				if ( $validation_result === true ) {
					$this->complete_installation();
					$next_step = $this->get_next_step( $current_step );
					if ( $next_step ) {
						$current_step = $next_step;
					}
				}
			}

			$nonce_key = '_gform_installation_wizard_step_' . $current_step->get_name();

		}

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		// register admin styles
		wp_register_style( 'gform_admin', GFCommon::get_base_url() . "/css/admin{$min}.css" );
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );



		?>

		<div class="wrap about-wrap gform_installation_progress_step_wrap">

		<h1><?php esc_html_e( 'Welcome to Gravity Forms', 'gravityforms' ) ?></h1>

		<div id="gform_installation_progress">
			<?php $this->progress( $current_step ); ?>
		</div>

		<hr/>

		<br />
		<h2>
			<?php echo $current_step->get_title(); ?>
		</h2>

			<form action="" method="POST">
				<input type="hidden" name="_step_name" value="<?php echo esc_attr( $current_step->get_name() ); ?>"/>
				<?php
				wp_nonce_field( $nonce_key, $nonce_key );

				$validation_summary = $current_step->get_validation_summary();
				if ( $validation_summary ) {
					printf( '<div class="delete-alert alert_red">%s</div>', $validation_summary );
				}

				?>
				<div class="about-text">
					<?php

					$current_step->display( $current_step );
					?>
					</div>
					<?php
					if ( $current_step->is( 'settings' ) ) {
						$next_button = sprintf( '<input class="button button-primary" type="submit" value="%s" name="_install"/>', esc_attr( $current_step->get_next_button_text() ) );
					} elseif ( $current_step->is( 'complete' ) ) {

						$next_button = sprintf( '<a class="button button-primary" href="%s">%s</a>', esc_url( admin_url('admin.php?page=gf_new_form') ), esc_attr( $current_step->get_next_button_text() ) );
					} else {
						$next_button = sprintf( '<input class="button button-primary" type="submit" value="%s" name="_next"/>', esc_attr( $current_step->get_next_button_text() ) );
					}
					?>
				<div>
					<?php
					$previous_button_text = $current_step->get_previous_button_text();
					if ( $previous_button_text ) {
						$previous_button = $this->get_step_index( $current_step ) > 0 ? '<input name="_previous" class="button button-primary" type="submit" value="' . esc_attr( $previous_button_text ) . '" style="margin-right:30px;" />' : '';
						echo $previous_button;
					}
					echo $next_button;
					?>
				</div>
			</form>
		</div>

	<?php

		return true;
	}

	/**
	 * @param bool $name
	 *
	 * @return GF_Installation_Wizard_Step
	 */
	public function get_step( $name = false ){

		if ( empty( $name ) ) {
			$class_names = array_keys( $this->_step_class_names );
			$name = $class_names[0];
		}

		$current_step_values = get_option( 'gform_installation_wizard_' . $name );

		$step = new $this->_step_class_names[ $name ]( $current_step_values );

		return $step;
	}

	/**
	 * @param $current_step
	 *
	 * @return bool|GF_Installation_Wizard_Step
	 */
	public function get_previous_step( $current_step ){
		$current_step_name = $current_step->get_name();

		$step_names = array_keys( $this->_step_class_names );
		$i = array_search( $current_step_name, $step_names );

		if ( $i == 0 ) {
			return false;
		}

		$previous_step_name = $step_names[ $i - 1 ];

		return $this->get_step( $previous_step_name );
	}

	/**
	 * @param GF_Installation_Wizard_Step $current_step
	 *
	 * @return bool|GF_Installation_Wizard_Step
	 */
	public function get_next_step( $current_step ){
		$current_step_name = $current_step->get_name();

		$step_names = array_keys( $this->_step_class_names );
		$i = array_search( $current_step_name, $step_names );

		if ( $i == count( $step_names ) - 1 ) {
			return false;
		}

		$next_step_name = $step_names[ $i + 1 ];

		return $this->get_step( $next_step_name );
	}

	public function complete_installation(){
		foreach ( array_keys( $this->_step_class_names ) as $step_name ) {
			$step = $this->get_step( $step_name );
			$step->install();
			$step->flush_values();
		}
		delete_option( 'gform_pending_installation' );
	}



	/**
	 * @param GF_Installation_Wizard_Step $current_step
	 * @param bool $echo
	 *
	 * @return string
	 */
	public function progress( $current_step, $echo = true ){
		$html = '<ul id="gform_installation_progress">';
		$done = true;
		$current_step_name = $current_step->get_name();
		foreach ( array_keys( $this->_step_class_names ) as $step_name ) {
			$class = '';
			$step = $this->get_step( $step_name );
			if ( $current_step_name == $step_name ) {
				$class .= 'gform_installation_progress_current_step ';
				$done = $step->is('complete') ? true : false;
			} else {
				$class .= $done  ?  'gform_installation_progress_step_complete' : 'gform_installation_progress_step_pending';
			}
			$check = $done ? '<i class="fa fa-check" style="color:green"></i>'  : '<i class="fa fa-check" style="visibility:hidden"></i>';

			$html .= sprintf( '<li id="gform_installation_progress_%s" class="%s">%s&nbsp;%s</li>', esc_attr( $step->get_name() ), esc_attr( $class ), esc_html( $step->get_title() ), $check );
		}
		$html .= '</ul>';

		if ( $echo ) {
			echo $html;
		}
		return $html;
	}

	public function get_step_index( $step ){
		$i = array_search( $step->get_name(), array_keys( $this->_step_class_names ) );
		return $i;
	}

	public function summary(){
		?>

		<h3>Summary</h3>
		<?php
		echo '<table class="form-table"><tbody>';
		$steps = $this->get_steps();
		foreach ( $steps as $step ) {
			$step_summary = $step->summary( false );
			if ( $step_summary ) {
				printf( '<tr valign="top"><th scope="row"><label>%s</label></th><td>%s</td></tr>', esc_html( $step->get_title() ), $step_summary );
			}
		}
		echo '</tbody></table>';

	}

	/**
	 * @return GF_Installation_Wizard_Step[]
	 */
	public function get_steps() {
		$steps = array();
		foreach ( array_keys( $this->_step_class_names ) as $step_name ) {
			$steps[] = $this->get_step( $step_name );
		}

		return $steps;
	}

}