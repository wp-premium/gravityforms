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
        }
        else if($action == "duplicate"){
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_id = RGForms::post("action_argument");
            RGFormsModel::duplicate_form($form_id);
        }
        else if($bulk_action == "delete"){
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_ids = rgpost("form");
            RGFormsModel::delete_forms($form_ids);
        }
        else if($bulk_action == "reset_views"){
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_ids = rgpost("form");
            if(is_array($form_ids)){
                foreach($form_ids as $form_id){
                    RGFormsModel::delete_views($form_id);
                }
            }
        }
        else if($bulk_action == "delete_entries"){
            check_admin_referer('gforms_update_forms', 'gforms_update_forms');
            $form_ids = RGForms::post("form");
            if(is_array($form_ids)){
                foreach($form_ids as $form_id){
                    RGFormsModel::delete_leads_by_form($form_id);
                }
            }
        }

        $active = RGForms::get("active") == "" ? null : RGForms::get("active");
        $forms = RGFormsModel::get_forms($active, "title");
        $form_count = RGFormsModel::get_form_count();

        ?>
        <script>
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
                <a class="button add-new-h2" href="admin.php?page=gf_new_form"><?php _e("Add New", "gravityforms") ?></a>
            </h2>
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

											$form_actions = array();
											$form_actions['edit'] = '<a title="Edit this form" href="admin.php?page=gf_edit_forms&id=' . $form->id . '">' . __("Edit", "gravityforms") . '</a>';

											$form_actions['notifications'] = '<a title="' . __("Edit notifications sent by this form", "gravityforms") . '" href="admin.php?page=gf_edit_forms&view=notification&id=' . $form->id . '">' . __("Notifications", "gravityforms") . '</a>';

											if(GFCommon::current_user_can_any("gravityforms_view_entries"))
												$form_actions['entries'] = '<a title="' . __("View entries generated by this form", "gravityforms") . '" href="admin.php?page=gf_entries&view=entries&id=' . $form->id . '">' . __("Entries", "gravityforms") . '</a>';

											$form_actions['preview'] = '<a title="' . __("Preview this form", "gravityforms") . '" href="' . site_url() . '/?gf_page=preview&id=' . $form->id . '" target="_blank">' . __("Preview", "gravityforms") . '</a>';

											if(GFCommon::current_user_can_any("gravityforms_create_form"))
												$form_actions['duplicate'] = '<a title="' . __("Duplicate this form", "gravityforms") . '" href="javascript:DuplicateForm(' . $form->id . ');">' . __("Duplicate", "gravityforms") . '</a>';

											if(GFCommon::current_user_can_any("gravityforms_delete_forms")) {
												$delete_link = '<a title="Delete" href="javascript: if(confirm(\'' . __("WARNING: You are about to delete this form and ALL entries associated with it. ", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") . '\')){ DeleteForm(' . $form->id . ');}">' . __("Delete", "gravityforms"). '</a>';
												$form_actions['delete'] = apply_filters("gform_form_delete_link", $delete_link);
											}

											$form_actions = apply_filters("gform_form_actions", $form_actions, $form->id);

											if(is_array($form_actions) && !empty($form_actions)) {
												$last_key = array_pop(array_keys($form_actions));
												foreach($form_actions as $action_key => $action_link) {
													$divider = $action_key == $last_key ? '' : " | ";
													?>

                                                    <span class="edit">
                                                        <?php echo $action_link . $divider; ?>
                                                    </span>

													<?php }
											}

											?>

                                        </div>
                                    </td>
                                    <td class="column-date"><strong><?php echo $form->view_count ?></strong></td>
                                    <td class="column-date"><strong><?php echo $form->lead_count ?></strong></td>
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


}

?>