<?php

if(!class_exists('GFForms')){
    die();
}

add_action( 'widgets_init', 'gf_register_widget' );

if(!function_exists("gf_register_widget")){
function gf_register_widget() {
    register_widget( 'GFWidget' );
}
}

if(!class_exists("GFWidget")){
class GFWidget extends WP_Widget {

    function __construct() {

        load_plugin_textdomain( 'gravityforms', false, '/gravityforms/languages' );

        $description = __('Gravity Forms Widget', "gravityforms");
        $this->WP_Widget( 'gform_widget', __('Form', 'gravityforms'),
                            array( 'classname' => 'gform_widget', 'description' => $description ),
                            array( 'width' => 200, 'height' => 250, 'id_base' => 'gform_widget' )
                            );
    }

    function widget( $args, $instance ) {

        extract( $args );
        echo $before_widget;
        $title = apply_filters('widget_title', $instance['title'] );

        if ( $title )
            echo $before_title . $title . $after_title;

        //setting tabindex based on configured value
        if(is_numeric($instance['tabindex'])){
            add_filter("gform_tabindex_{$instance['form_id']}", create_function("", "return {$instance['tabindex']};"));
        }

        //creating form
        $form = RGFormsModel::get_form_meta($instance['form_id']);

        if(empty($instance["disable_scripts"]) && !is_admin()){
            RGForms::print_form_scripts($form, $instance["ajax"]);
        }

        $form_markup = RGForms::get_form($instance['form_id'], $instance['showtitle'], $instance['showdescription'], false, null, $instance["ajax"]);

        //display form
        echo $form_markup;
        echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance["title"] = strip_tags( $new_instance["title"] );
        $instance["form_id"] = $new_instance["form_id"];
        $instance["showtitle"] = $new_instance["showtitle"];
        $instance["ajax"] = $new_instance["ajax"];
        $instance["disable_scripts"] = $new_instance["disable_scripts"];
        $instance["showdescription"] = $new_instance["showdescription"];
        $instance["tabindex"] = $new_instance["tabindex"];

        return $instance;
    }

    function form( $instance ) {

        $instance = wp_parse_args( (array) $instance, array('title' => __("Contact Us", "gravityforms"), 'tabindex' => '1') );
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e("Title", "gravityforms"); ?>:</label>
            <input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:90%;" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'form_id' ); ?>"><?php _e("Select a Form", "gravityforms"); ?>:</label>
            <select id="<?php echo $this->get_field_id( 'form_id' ); ?>" name="<?php echo $this->get_field_name( 'form_id' ); ?>" style="width:90%;">
                <?php
                    $forms = RGFormsModel::get_forms(1, "title");
                    foreach ($forms as $form) {
                        $selected = '';
                        if ($form->id == rgar($instance, 'form_id'))
                            $selected = ' selected="selected"';
                        echo '<option value="'.$form->id.'" '.$selected.'>'.$form->title.'</option>';
                    }
                ?>
            </select>
        </p>
        <p>
            <input type="checkbox" name="<?php echo $this->get_field_name( 'showtitle' ); ?>" id="<?php echo $this->get_field_id( 'showtitle' ); ?>" <?php checked(rgar($instance, 'showtitle')); ?> value="1" /> <label for="<?php echo $this->get_field_id( 'showtitle' ); ?>"><?php _e("Display form title", "gravityforms"); ?></label><br/>
            <input type="checkbox" name="<?php echo $this->get_field_name( 'showdescription' ); ?>" id="<?php echo $this->get_field_id( 'showdescription' ); ?>" <?php checked(rgar($instance, 'showdescription')); ?> value="1"/> <label for="<?php echo $this->get_field_id( 'showdescription' ); ?>"><?php _e("Display form description", "gravityforms"); ?></label><br/>
        </p>
        <p>
            <a href="javascript: var obj = jQuery('.gf_widget_advanced'); if(!obj.is(':visible')) {var a = obj.show('slow');} else {var a = obj.hide('slow');}"><?php _e("advanced options", "gravityforms"); ?></a>
        </p>
        <p class="gf_widget_advanced" style="display:none;">
            <input type="checkbox" name="<?php echo $this->get_field_name( 'ajax' ); ?>" id="<?php echo $this->get_field_id( 'ajax' ); ?>" <?php checked(rgar($instance, 'ajax')); ?> value="1"/> <label for="<?php echo $this->get_field_id( 'ajax' ); ?>"><?php _e("Enable AJAX", "gravityforms"); ?></label><br/>
            <input type="checkbox" name="<?php echo $this->get_field_name( 'disable_scripts' ); ?>" id="<?php echo $this->get_field_id( 'disable_scripts' ); ?>" <?php checked(rgar($instance, 'disable_scripts')); ?> value="1"/> <label for="<?php echo $this->get_field_id( 'disable_scripts' ); ?>"><?php _e("Disable script output", "gravityforms"); ?></label><br/>
            <label for="<?php echo $this->get_field_id( 'tabindex' ); ?>"><?php _e("Tab Index Start", "gravityforms"); ?>: </label>
            <input id="<?php echo $this->get_field_id( 'tabindex' ); ?>" name="<?php echo $this->get_field_name( 'tabindex' ); ?>" value="<?php echo rgar($instance, 'tabindex'); ?>" style="width:15%;" /><br/>
            <small><?php _e("If you have other forms on the page (i.e. Comments Form), specify a higher tabindex start value so that your Gravity Form does not end up with the same tabindices as your other forms. To disable the tabindex, enter 0 (zero).", "gravityforms"); ?></small>
        </p>

    <?php
    }
}
}

?>