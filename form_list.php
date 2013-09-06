<?php
class GFFormList{

    public static function form_list_page(){
        global $wpdb;

        if(!GFCommon::ensure_wp_version())
            return;

        echo GFCommon::get_remote_message();

        $action = RGForms::post("action");
        $bulk_action = RGForms::post("bulk_action");
        $bulk_action = !empty($bulk_action) ? $bulk_action : RGForms::post("bulk_action2");

        if($action == "delete")
        {
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_id = RGForms::post("action_argument");
            RGFormsModel::delete_form($form_id);
            $message = __('Form deleted.', 'gravityforms');
        }
        else if($action == "duplicate"){
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_id = RGForms::post("action_argument");
            RGFormsModel::duplicate_form($form_id);
            $message = __('Form duplicated.', 'gravityforms');
        }

        if($bulk_action) {

            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_ids = is_array(rgpost('form')) ? rgpost('form') : array();
            $form_count = count($form_ids);

            switch($bulk_action) {
            case 'delete':
                RGFormsModel::delete_forms($form_ids);
                $message = _n('%s form deleted.', '%s forms deleted.', $form_count, 'gravityforms');
                break;
            case 'reset_views':
                foreach($form_ids as $form_id){
                    RGFormsModel::delete_views($form_id);
                }
                $message = _n('Views for %s form have been reset.', 'Views for %s forms have been reset.', $form_count, 'gravityforms');
                break;
            case 'delete_entries':
                foreach($form_ids as $form_id){
                    RGFormsModel::delete_leads_by_form($form_id);
                }
                $message = _n('Entries for %s form have been deleted.', 'Entries for %s forms have been deleted.', $form_count, 'gravityforms');
                break;
            case 'activate':
                foreach($form_ids as $form_id){
                    RGFormsModel::update_form_active($form_id, 1);
                }
                $message = _n('%s form has been marked as active.', '%s forms have been marked as active.', $form_count, 'gravityforms');
                break;
            case 'deactivate':
                foreach($form_ids as $form_id){
                    RGFormsModel::update_form_active($form_id, 0);
                }
                $message = _n('%s form has been marked as inactive.', '%s forms have been marked as inactive.', $form_count, 'gravityforms');
                break;
            }

            if(isset($message))
                $message = sprintf($message, $form_count);

        }

        $active = RGForms::get("active") == "" ? null : RGForms::get("active");
        $forms = RGFormsModel::get_forms($active, "title");
        $form_count = RGFormsModel::get_form_count();

        // - new form modal - //

        wp_print_styles(array('thickbox'));

        ?>

        <script type="text/javascript" src="<?php echo GFCommon::get_base_url() . '/js/form_admin.js' ?>"></script>
        <script type="text/javascript">

            jQuery(document).ready(function($) {

                <?php if(rgget('page') == 'gf_new_form'): ?>
                loadNewFormModal();
                <?php endif; ?>

                $('.gf_form_action_has_submenu').hover(function(){
                    var l = jQuery(this).offset().left;
                    jQuery(this).find('.gf_submenu')
                        .toggle()
                        .offset({ left: l });
                }, function(){
                    jQuery(this).find('.gf_submenu').hide();
                });


            });

            function loadNewFormModal() {
                resetNewFormModal();
                tb_show('<?php _e('Create a New Form', 'gravityforms'); ?>', '#TB_inline?width=375&amp;inlineId=gf_new_form_modal');
                jQuery('#new_form_title').focus();
                return false;
            }

            function saveNewForm() {

                var createButton = jQuery('#save_new_form');
                var spinner = new gfAjaxSpinner(createButton, gf_vars.baseUrl + '/images/spinner.gif');

                // clear error message
                jQuery('#gf_new_form_error_message').html('');

                var origVal = createButton.val();
                createButton.val('<?php _e('Creating Form...', 'gravityforms'); ?>');

                var form = {
                    title: jQuery('#new_form_title').val(),
                    description: jQuery('#new_form_description').val()
                }

                jQuery.post(ajaxurl, {
                    form: form,
                    action: 'gf_save_new_form',
                    gf_save_new_form: '<?php echo wp_create_nonce('gf_save_new_form'); ?>'
                }, function(response){

                    spinner.destroy();

                    var respData = jQuery.parseJSON(response);

                    if(respData['error']) {
                        // adding class later otherwise WP moves box up to the top of the page
                        jQuery('#gf_new_form_error_message').html( respData.error );
                        addInputErrorIcon( '#new_form_title' );
                        createButton.val(origVal);
                    } else {
                        location.href = respData.redirect;
                        createButton.val('<?php _e('Saved! Redirecting...', 'gravityforms'); ?>');
                    }

                });

            }

            function resetNewFormModal() {
                jQuery('#new_form_title').val('');
                jQuery('#new_form_description').val('');
                jQuery('#gf_new_form_error_message').html('');
                removeInputErrorIcons( '.gf_new_form_modal_container' );
            }

            function addInputErrorIcon( elem ) {
                var elem = jQuery(elem);
                elem.before( '<span class="gf_input_error_icon"></span>');
            }

            function removeInputErrorIcons( elem ) {
                var elem = jQuery(elem);
                elem.find('span.gf_input_error_icon').remove();
            }

        </script>

        <style type="text/css">
       body div#TB_window[style] { width: 405px !important; height:340px !important; margin-left: -202px !important; }
        body #TB_ajaxContent { height: 290px !important; overflow: hidden; }
        .gf_new_form_modal_container { padding: 30px; }
        .gf_new_form_modal_container .setting-row { margin: 0 0 10px; }
        .gf_new_form_modal_container .setting-row label { line-height: 24px; }
        .gf_new_form_modal_container .setting-row input,
        .gf_new_form_modal_container .setting-row textarea { display: block; width: 100%; }
        .gf_new_form_modal_container .setting-row textarea { height: 110px; }
        .gf_new_form_modal_container .submit-row { margin-top: 18px; }
        .gf_new_form_modal_container #gf_new_form_error_message { margin: 0 0 18px 5px !important; color: #BC0B0B; }
        .gf_new_form_modal_container img.gfspinner { position: relative; top: 5px; left: 5px; }
        </style>

        <div id="gf_new_form_modal" style="display:none;">
            <div class="gf_new_form_modal_container">

                <div class="setting-row">
                    <label for="new_form_title"><?php _e('Form Title', 'gravityforms'); ?><span class="gfield_required">*</span></label><br />
                    <input type="text" class="regular-text" value="" id="new_form_title" tabindex="9000">
                </div>

                <div class="setting-row">
                    <label for="new_form_description"><?php _e('Form Description', 'gravityforms'); ?></label><br />
                    <textarea class="regular-text" id="new_form_description" tabindex="9001"></textarea>
                </div>

                <div class="submit-row">
                    <?php echo apply_filters("gform_new_form_button", '<input id="save_new_form" type="button" class="button button-large button-primary" value="' . __('Create Form', 'gravityforms'). '" onclick="saveNewForm();" tabindex="9002" />'); ?>
                    <div id="gf_new_form_error_message" style="display:inline-block;"></div>
                </div>

            </div>
        </div>

        <?php // - end of new form modal - // ?>

        <script text="text/javascript">
            function DeleteForm(form_id){
                jQuery("#action_argument").val(form_id);
                jQuery("#action").val("delete");
                jQuery("#forms_form")[0].submit();
            }

            function DuplicateForm(form_id){
                jQuery("#action_argument").val(form_id);
                jQuery("#action").val("duplicate");
                jQuery("#forms_form")[0].submit();
            }

            function ToggleActive(img, form_id){
                var is_active = img.src.indexOf("active1.png") >=0
                if(is_active){
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title','<?php _e("Inactive", "gravityforms") ?>').attr('alt', '<?php _e("Inactive", "gravityforms") ?>');
                }
                else{
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title','<?php _e("Active", "gravityforms") ?>').attr('alt', '<?php _e("Active", "gravityforms") ?>');
                }

                UpdateCount("active_count", is_active ? -1 : 1);
                UpdateCount("inactive_count", is_active ? 1 : -1);

                var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "rg_update_form_active" );
                mysack.setVar( "rg_update_form_active", "<?php echo wp_create_nonce("rg_update_form_active") ?>" );
                mysack.setVar( "form_id", form_id);
                mysack.setVar( "is_active", is_active ? 0 : 1);
                mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while update form", "gravityforms")) ?>' )};
                mysack.runAJAX();

                return true;
            }
            function UpdateCount(element_id, change){
                var element = jQuery("#" + element_id);
                var count = parseInt(element.html()) + change
                element.html(count + "");
            }

            function gfConfirmBulkAction(element_id){
                var element = "#" + element_id;
                if(jQuery(element).val() == 'delete')
                    return confirm('<?php echo __("WARNING: You are about to delete this form and ALL entries associated with it. ", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") ?>');
                else if(jQuery(element).val() == 'reset_views')
                    return confirm('<?php echo __("Are you sure you would like to reset the Views for the selected forms? ", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to reset.", "gravityforms") ?>');
                else if(jQuery(element).val() == 'delete_entries')
                    return confirm('<?php echo __("WARNING: You are about to delete ALL entries associated with the selected forms. ", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") ?>');

                return true;
            }
        </script>

        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css" />
        <div class="wrap">

            <div class="icon32" id="gravity-edit-icon"><br></div>
            <h2>
                <?php _e("Forms", "gravityforms"); ?>
                <a class="button add-new-h2" href="" onclick="return loadNewFormModal();"><?php _e("Add New", "gravityforms") ?></a>
            </h2>

            <?php if(isset($message)) { ?>
            <div class="updated below-h2" id="message"><p><?php echo $message; ?></p></div>
            <?php } ?>

            <form id="forms_form" method="post">
                <?php wp_nonce_field('gforms_update_forms', 'gforms_update_forms') ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>

                <ul class="subsubsub">
                    <li><a class="<?php echo ($active === null) ? "current" : "" ?>" href="?page=gf_edit_forms"><?php _e("All", "gravityforms"); ?> <span class="count">(<span id="all_count"><?php echo $form_count["total"] ?></span>)</span></a> | </li>
                    <li><a class="<?php echo $active == "1" ? "current" : ""?>" href="?page=gf_edit_forms&active=1"><?php _e("Active", "gravityforms"); ?> <span class="count">(<span id="active_count"><?php echo $form_count["active"] ?></span>)</span></a> | </li>
                    <li><a class="<?php echo $active == "0" ? "current" : ""?>" href="?page=gf_edit_forms&active=0"><?php _e("Inactive", "gravityforms"); ?> <span class="count">(<span id="inactive_count"><?php echo $form_count["inactive"] ?></span>)</span></a></li>
                </ul>

                <?php
                if(GFCommon::current_user_can_any("gravityforms_delete_forms")){
                ?>
                    <div class="tablenav">
                        <div class="alignleft actions" style="padding:8px 0 7px 0;">

                            <label class="hidden" for="bulk_action"><?php _e("Bulk action", "gravityforms") ?></label>
                            <select name="bulk_action" id="bulk_action">
                                <option value=''> <?php _e("Bulk action", "gravityforms") ?> </option>
                                <option value='delete'><?php _e("Delete", "gravityforms") ?></option>
                                <option value='activate'><?php _e("Mark as Active", "gravityforms") ?></option>
                                <option value='deactivate'><?php _e("Mark as Inactive", "gravityforms") ?></option>
                                <option value='reset_views'><?php _e("Reset Views", "gravityforms") ?></option>
                                <option value='delete_entries'><?php _e("Delete Entries", "gravityforms") ?></option>
                            </select>
                            <?php
                            $apply_button = '<input type="submit" class="button" value="' . __("Apply", "gravityforms") . '" onclick="return gfConfirmBulkAction(\'bulk_action\');"/>';
                            echo apply_filters("gform_form_apply_button", $apply_button);
                            ?>

                            <br class="clear" />

                        </div>
                    </div>
                <?php
                }
                ?>

                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <?php
                            if(GFCommon::current_user_can_any("gravityforms_delete_forms")){
                            ?>
                                <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" name="form_bulk_check_all" onclick="jQuery('.gform_list_checkbox').attr('checked', this.checked);" /></th>
                            <?php
                            }
                            ?>
                            <th scope="col" id="active" class="manage-column column-cb check-column"></th>
                            <th scope="col" id="id" class="manage-column" style="width:50px;"><?php _e("Id", "gravityforms") ?></th>
                            <th width="360" scope="col" id="title" class="manage-column column-title"><?php _e("Title", "gravityforms") ?></th>
                            <th scope="col" id="author" class="manage-column column-author" style=""><?php _e("Views", "gravityforms") ?></th>
                            <th scope="col" id="template" class="manage-column" style=""><?php _e("Entries", "gravityforms") ?></th>
                            <th scope="col" id="template" class="manage-column" style=""><?php _e("Conversion", "gravityforms") ?> <?php gform_tooltip("entries_conversion", "tooltip_left") ?> </th>
                        </tr>
                    </thead>

                    <tfoot>
                        <tr>
                            <?php
                            if(GFCommon::current_user_can_any("gravityforms_delete_forms")){
                            ?>
                                <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" name="form_bulk_check_all" onclick="jQuery('.gform_list_checkbox').attr('checked', this.checked);" /></th>
                            <?php
                            }
                            ?>
                            <th scope="col" id="active" class="manage-column column-cb check-column"></th>
                            <th scope="col" id="id" class="manage-column"><?php _e("Id", "gravityforms") ?></th>
                            <th width="350" scope="col" id="title" class="manage-column column-title"><?php _e("Title", "gravityforms") ?></th>
                            <th scope="col" id="author" class="manage-column column-author" style=""><?php _e("Views", "gravityforms") ?></th>
                            <th scope="col" id="template" class="manage-column" style=""><?php _e("Entries", "gravityforms") ?></th>
                            <th scope="col" id="template" class="manage-column" style=""><?php _e("Conversion", "gravityforms") ?></th>
                        </tr>
                    </tfoot>

                    <tbody class="list:user user-list">
                        <?php
                        if(sizeof($forms) > 0){
                            foreach($forms as $form){
                                $conversion = "0%";
                                if($form->view_count > 0){
                                    $conversion = (number_format($form->lead_count / $form->view_count, 3) * 100) . "%";
                                }
                                ?>
                                <tr class='author-self status-inherit' valign="top">
                                    <?php
                                    if(GFCommon::current_user_can_any("gravityforms_delete_forms")){
                                    ?>
                                        <th scope="row" class="check-column"><input type="checkbox" name="form[]" value="<?php echo $form->id ?>" class="gform_list_checkbox"/></th>
                                    <?php
                                    }
                                    ?>

                                    <td><img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval($form->is_active) ?>.png" style="cursor: pointer;" alt="<?php echo $form->is_active ? __("Active", "gravityforms") : __("Inactive", "gravityforms");?>" title="<?php echo $form->is_active ? __("Active", "gravityforms") : __("Inactive", "gravityforms");?>" onclick="ToggleActive(this, <?php echo $form->id ?>); " /></td>
                                    <td class="column-id"><?php echo $form->id ?></td>
                                    <td class="column-title">
                                        <strong><a class="row-title" href="admin.php?page=gf_edit_forms&id=<?php echo $form->id ?>" title="<?php _e("Edit", "gravityforms") ?>"><?php echo $form->title ?></a></strong>
                                        <div class="row-actions">

                                            <?php

                                            require_once(GFCommon::get_base_path() . '/form_settings.php');

											$form_actions = GFForms::get_toolbar_menu_items($form->id, true);

											$form_actions['duplicate'] = array(
												'label' 		=> __("Duplicate", "gravityforms"),
												'title' 		=> __("Duplicate this form", "gravityforms"),
												'url' 			=> 'javascript:DuplicateForm(' . $form->id . ');',
												'capabilities' 	=> "gravityforms_create_form",
												'priority'		=> 600
											);

											$form_actions['delete'] = array(
												'label' 		=> __("Delete", "gravityforms"),
												'title' 		=> __("Delete", "gravityforms"),
												'url' 			=> 'javascript: if(confirm("' . __("WARNING: You are about to delete this form and ALL entries associated with it. ", "gravityforms") . __('\"Cancel\" to stop, \"OK\" to delete.', "gravityforms") . '")){ DeleteForm(' . $form->id . ');}',
												'capabilities' 	=> "gravityforms_delete_forms",
												'priority'		=> 500
											);

                                            $form_actions = apply_filters("gform_form_actions", $form_actions, $form->id);
											echo GFForms::format_toolbar_menu_items($form_actions, true);

                                                    ?>

                                        </div>
                                    </td>
                                    <td class="column-date"><strong><?php echo $form->view_count ?></strong></td>
                                    <td class="column-date">
                                        <strong>
                                            <?php if($form->lead_count > 0) { ?>
                                                <a href="<?php echo admin_url("admin.php?page=gf_entries&view=entries&id={$form->id}"); ?>"><?php echo $form->lead_count; ?></a>
                                            <?php } else {
                                                echo $form->lead_count;
                                            } ?>
                                        </strong>
                                    </td>
                                    <td class="column-date"><?php echo $conversion?></td>
                                </tr>
                                <?php
                            }
                        }
                        else{
                            ?>
                            <tr>
                                <td colspan="6" style="padding:20px;">
                                    <?php echo sprintf(__("You don't have any forms. Let's go %screate one%s!", "gravityforms"), '<a href="admin.php?page=gf_new_form">', "</a>"); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px 0;">
                        <?php
                        if(GFCommon::current_user_can_any("gravityforms_delete_forms")){
                            ?>
                            <label class="hidden" for="bulk_action2"><?php _e("Bulk action", "gravityforms") ?></label>
                            <select name="bulk_action2" id="bulk_action2">
                                <option value=''> <?php _e("Bulk action", "gravityforms") ?> </option>
                                <option value='delete'><?php _e("Delete", "gravityforms") ?></option>
                                <option value='activate'><?php _e("Mark as Active", "gravityforms") ?></option>
                                <option value='deactivate'><?php _e("Mark as Inactive", "gravityforms") ?></option>
                                <option value='reset_views'><?php _e("Reset Views", "gravityforms") ?></option>
                                <option value='delete_entries'><?php _e("Delete Entries", "gravityforms") ?></option>
                            </select>
                            <?php
                            $apply_button = '<input type="submit" class="button" value="' . __("Apply", "gravityforms") . '" onclick="return gfConfirmBulkAction(\'bulk_action2\');"/>';
                            echo apply_filters("gform_form_apply_button", $apply_button);
                        }
                        ?>
                        <br class="clear" />
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public static function save_new_form() {

        if(!check_admin_referer('gf_save_new_form', 'gf_save_new_form')) {
            die( json_encode( array( 'error' => __('There was an issue creating your form.', 'gravityforms') ) ) );
        }

        GFFormsModel::ensure_tables_exist();

        require_once(GFCommon::get_base_path() . '/form_detail.php');

        $form = rgpost('form');

        if( empty( $form['title'] ) ) {
            $result = array( 'error' => __( 'Please enter a form title.', 'gravityforms' ) );
            die( json_encode( $result ) );
        }

        $form['labelPlacement'] = 'top_label';
        $form['descriptionPlacement'] = 'below';
        $form['button'] = array(
            'type' => 'text',
            'text' => __("Submit", "gravityforms"),
            'imageUrl' => ''
            );
        $form['fields'] = array();

        $result = GFFormDetail::save_form_info( 0, json_encode($form) );

        switch(rgar($result, 'status')){
            case 'invalid_json':
                $result['error'] = __('There was an issue creating your form.', 'gravityforms');
                die(json_encode($result));

            case 'duplicate_title':
                $result['error'] = __('Please enter an unique form title.', 'gravityforms');
                die(json_encode($result));

            default:
                $form_id = abs($result['status']);
                die( json_encode( array('redirect' => admin_url("admin.php?page=gf_edit_forms&id={$form_id}")) ) );
        }

    }

}

?>