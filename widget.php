<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

add_action( 'widgets_init', 'gf_register_widget' );

if ( ! function_exists( 'gf_register_widget' ) ) {
	function gf_register_widget() {
		register_widget( 'GFWidget' );
	}
}

if ( ! class_exists( 'GFWidget' ) ) {
	/**
	 * Class GFWidget
	 *
	 * Facilitates the creation of the Gravity Forms widget
	 *
	 * @see WP_Widget
	 */
	class GFWidget extends WP_Widget {

		/**
		 * GFWidget constructor.
		 * @see WP_Widget::__construct
		 */
		function __construct() {

			//load text domains
			GFCommon::load_gf_text_domain();

			$description = esc_html__( 'Gravity Forms Widget', 'gravityforms' );

			WP_Widget::__construct( 
				'gform_widget',
				__( 'Form', 'gravityforms' ),
				array( 'classname' => 'gform_widget', 'description' => $description ),
				array( 'width' => 200, 'height' => 250, 'id_base' => 'gform_widget' )
			);

		}

		/**
		 * Handles outputting of the widget content
		 *
		 * @see WP_Widget::widget
		 * @see RGFormsModel::get_form_meta
		 * @see RGForms::print_form_scripts
		 * @see RGForms::get_form
		 *
		 * @param array $args     Arguments provided to the widget
		 * @param array $instance Saved database values for the widget
		 */
		function widget( $args, $instance ) {

			extract( $args );
			echo $before_widget;
			/**
			 * Filters the widget title
			 * @param string $instance['title'] The title
			 */
			$title = apply_filters( 'widget_title', $instance['title'] );

			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			$tabindex = is_numeric( $instance['tabindex'] ) ? $instance['tabindex'] : 1;

			// Creating form
			$form = RGFormsModel::get_form_meta( $instance['form_id'] );

			if ( empty( $instance['disable_scripts'] ) && ! is_admin() ) {
				RGForms::print_form_scripts( $form, $instance['ajax'] );
			}

			$form_markup = RGForms::get_form( $instance['form_id'], $instance['showtitle'], $instance['showdescription'], false, null, $instance['ajax'], $tabindex );

			// Display form
			echo $form_markup;
			echo $after_widget;
		}

		/**
		 * Handles updates to the widget content
		 *
		 * @param array $new_instance The new instance of the widget
		 * @param array $old_instance The old instance of the widget
		 *
		 * @return array The widget instance, after changes have occurred
		 */
		function update( $new_instance, $old_instance ) {
			$instance                    = $old_instance;
			$instance['title']           = strip_tags( $new_instance['title'] );
			$instance['form_id']         = rgar( $new_instance, 'form_id' );
			$instance['showtitle']       = rgar( $new_instance, 'showtitle' );
			$instance['ajax']            = rgar( $new_instance, 'ajax' );
			$instance['disable_scripts'] = rgar( $new_instance, 'disable_scripts' );
			$instance['showdescription'] = rgar( $new_instance, 'showdescription' );
			$instance['tabindex']        = rgar( $new_instance, 'tabindex' );

			return $instance;
		}

		/**
		 * Outputs the form options for the widget
		 *
		 * @param array $instance The widget instance
		 *
		 * @return void
		 */
		function form( $instance ) {

			$instance = wp_parse_args( (array) $instance, array( 'title' => __( 'Contact Us', 'gravityforms' ), 'tabindex' => '1' ) );
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'gravityforms' ); ?>:</label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" style="width:90%;" />
			</p>
			<p>
				<label for="<?php echo absint( $this->get_field_id( 'form_id' ) ); ?>"><?php esc_html_e( 'Select a Form', 'gravityforms' ); ?>:</label>
				<select id="<?php echo esc_attr( $this->get_field_id( 'form_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'form_id' ) ); ?>" style="width:90%;">
					<?php
					$forms = RGFormsModel::get_forms( 1, 'title' );
					foreach ( $forms as $form ) {
						$selected = '';
						if ( $form->id == rgar( $instance, 'form_id' ) ) {
							$selected = ' selected="selected"';
						}
						echo '<option value="' . absint( $form->id ) . '" ' . $selected . '>' . esc_html( $form->title ) . '</option>';
					}
					?>
				</select>
			</p>
			<p>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'showtitle' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showtitle' ) ); ?>" <?php checked( rgar( $instance, 'showtitle' ) ); ?> value="1" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'showtitle' ) ); ?>"><?php esc_html_e( 'Display form title', 'gravityforms' ); ?></label><br />
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'showdescription' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showdescription' ) ); ?>" <?php checked( rgar( $instance, 'showdescription' ) ); ?> value="1" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'showdescription' ) ); ?>"><?php esc_html_e( 'Display form description', 'gravityforms' ); ?></label><br />
			</p>
			<p>
				<a href="javascript: var obj = jQuery('#<?php echo esc_attr( $this->get_field_id( 'advanced' ) ); ?>'); if(!obj.is(':visible')) {var a = obj.show('slow');} else {var a = obj.hide('slow');}"><?php esc_html_e( 'Advanced Options', 'gravityforms' ); ?></a>
			</p>
			<p id="<?php echo esc_attr( $this->get_field_id( 'advanced' ) ); ?>" class="gf_widget_advanced" style="display:none;">
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'ajax' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'ajax' ) ); ?>" <?php checked( rgar( $instance, 'ajax' ) ); ?> value="1" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'ajax' ) ); ?>"><?php esc_html_e( 'Enable AJAX', 'gravityforms' ); ?></label><br />
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'disable_scripts' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'disable_scripts' ) ); ?>" <?php checked( rgar( $instance, 'disable_scripts' ) ); ?> value="1" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'disable_scripts' ) ); ?>"><?php esc_html_e( 'Disable script output', 'gravityforms' ); ?></label><br />
				<label for="<?php echo esc_attr( $this->get_field_id( 'tabindex' ) ); ?>"><?php esc_html_e( 'Tab Index Start', 'gravityforms' ); ?>: </label>
				<input id="<?php echo esc_attr( $this->get_field_id( 'tabindex' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tabindex' ) ); ?>" value="<?php echo esc_attr( rgar( $instance, 'tabindex' ) ); ?>" style="width:15%;" /><br />
				<small><?php esc_html_e( 'If you have other forms on the page (i.e. Comments Form), specify a higher tabindex start value so that your Gravity Form does not end up with the same tabindices as your other forms. To disable the tabindex, enter 0 (zero).', 'gravityforms' ); ?></small>
			</p>

		<?php
		}
	}
}
