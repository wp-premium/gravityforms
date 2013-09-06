<?php
class GFUpdate{
    public static function update_page(){
        if(!GFCommon::current_user_can_any("gravityforms_view_updates"))
           wp_die(__("You don't have permissions to view this page", "gravityforms"));

        if(!GFCommon::ensure_wp_version())
            return;

        GFCommon::cache_remote_message();
        echo GFCommon::get_remote_message();
        ?>

        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url() . "/css/admin.css"?>" />

        <div class="wrap">

        	<div class="icon32" id="gravity-update-icon"><br></div>
          <h2><?php echo _e("Gravity Forms Updates", "gravityforms") ?></h2>
            <?php

            $version_info = GFCommon::get_version_info(false);
            do_action("gform_after_check_update");

            if(version_compare(GFCommon::$version, $version_info["version"], '<')) {
                $plugin_file = "gravityforms/gravityforms.php";
                $upgrade_url = wp_nonce_url('update.php?action=upgrade-plugin&amp;plugin=' . urlencode($plugin_file), 'upgrade-plugin_' . $plugin_file);


                $message = __("There is a new version of Gravity Forms available.", "gravityforms");
                if( $version_info["is_valid_key"] ){
                    ?>
                    <div class="gf_update_outdated alert_yellow">
                        <?php echo $message . " " . sprintf(__("<p>You can update to the latest version automatically or download the update and install it manually. %sUpdate Automatically%s %sDownload Update%s", "gravityforms"), "</p><a class='button-primary' href='{$upgrade_url}'>", "</a>", "&nbsp;<a class='button' href='{$version_info["url"]}'>", "</a>"); ?>
                    </div>
                    <?php
                }
                else
                {
                    ?>
                    <div class="gf_update_expired alert_red">
                        <?php echo $message . " " . __('<a href="admin.php?page=gf_settings">Register</a> your copy of Gravity Forms to receive access to automatic updates and support. Need a license key? <a href="http://www.gravityforms.com">Purchase one now</a>.', 'gravityforms');?>
                    </div>
                    <?php
                }

                echo "<br/><br/>";
                $changelog = RGForms::get_changelog();
                echo $changelog;
            }
            else{

                ?>
                <div class="gf_update_current alert_green">
                    <?php _e("Your version of Gravity Forms is up to date.", "gravityforms"); ?>
                </div>
                <?php
            }
            ?>

            <div id='gform_upgrade_license' style="display:none;"></div>
              <script type="text/javascript">
                jQuery(document).ready(function(){
                    jQuery.post(ajaxurl,{
                            action:"gf_upgrade_license",
                            gf_upgrade_license: "<?php echo wp_create_nonce("gf_upgrade_license") ?>"},

                            function(data){
                                if(data.trim().length > 0)
                                    jQuery("#gform_upgrade_license").replaceWith(data);
                            }
                    );
                });
              </script>
            </div>
        <?php
    }


}
?>