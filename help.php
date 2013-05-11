<?php
class GFHelp{
    public static function help_page(){
        if(!GFCommon::ensure_wp_version())
                return;

            echo GFCommon::get_remote_message();

            ?>
            <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css" />
            <div class="wrap">
                <div class="icon32" id="gravity-help-icon"><br></div>
                <h2><?php _e("Gravity Forms Help", "gravityforms"); ?></h2>

                <div style="margin-top:10px;">

                <div class="gforms_help_alert alert_yellow"><?php _e("<strong>IMPORTANT NOTICE:</strong> We do not provide support via e-mail. Please post any support queries in our <a href='http://forum.gravityhelp.com/'>support forums</a>.", "gravityforms") ?></div>

                <div><?php _e("Please review the plugin documentation and <a href='http://www.gravityhelp.com/frequently-asked-questions/'>frequently asked questions (FAQ)</a> first. If you still can't find the answer you need visit our <a href='http://forum.gravityhelp.com/'>support forums</a> where we will be happy to answer your questions and assist you with any problems. <strong>Please note:</strong> If you have not <a href='http://www.gravityforms.com/purchase-gravity-forms/'>purchased a license</a> from us, you won't have access to these help resources.", "gravityforms"); ?></div>


                <div class="hr-divider"></div>

                <h3><?php _e("Gravity Forms Documentation", "gravityforms"); ?></h3>
                <?php _e("<strong>Note:</strong> Only licensed Gravity Forms customers are granted access to the documentation section.", "gravityforms"); ?>
                <ul style="margin-top:15px;">
                    <li>
                    <div class="gforms_helpbox">
                    <form name="jump">
                    <select name="menu">

                        <!-- begin documentation listing -->
                        <option selected><?php _e("Documentation (please select a topic)", "gravityforms"); ?></option>
                        <option value="http://gravityhelp.com/documentation/page/Getting_Started"><?php _e("Getting Started", "gravityforms"); ?></option>
                        
                        <option value="http://gravityhelp.com/documentation/page/Using_Gravity_Forms"><?php _e("Using Gravity Forms", "gravityforms"); ?></option>
                        <option value="http://gravityhelp.com/documentation/page/Design_and_Layout"><?php _e("Design and Layout", "gravityforms"); ?></option>
                        <option value="http://gravityhelp.com/documentation/page/Developer_Docs"><?php _e("Developer Docs", "gravityforms"); ?></option>
                        <option value="http://gravityhelp.com/documentation/page/Add-ons"><?php _e("Add-Ons", "gravityforms"); ?></option>
                        <option value="http://gravityhelp.com/documentation/page/How_To"><?php _e("How To", "gravityforms"); ?></option>
                        
                    <!-- end documentation listing -->
                    </select>
                    <input type="button" class="button" onClick="location=document.jump.menu.options[document.jump.menu.selectedIndex].value;" value="<?php _e("GO", "gravityforms"); ?>">
                </form>
                </div>

                    </li>
                   </ul>

                <div class="hr-divider"></div>

                <h3><?php _e("Gravity Forms FAQ", "gravityforms"); ?></h3>
                <?php _e("<strong>Please Note:</strong> Only licensed Gravity Forms customers are granted access to the FAQ section.", "gravityforms"); ?>
                <ul style="margin-top:15px;">
                    <li>
                    <div class="gforms_helpbox">
                    <form name="jump1">
                    <select name="menu1">

                        <!-- begin faq listing -->
                        <option selected><?php _e("FAQ (please select a topic)", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/frequently-asked-questions/#faq_installation"><?php _e("Installation Questions", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/frequently-asked-questions/#faq_styling"><?php _e("Formatting/Styling Questions", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/frequently-asked-questions/#faq_notifications"><?php _e("Notification Questions", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/frequently-asked-questions/#faq_general"><?php _e("General Questions", "gravityforms"); ?></option>

                        <!-- end faq listing -->
                    </select>
                    <input type="button" class="button" onClick="location=document.jump1.menu1.options[document.jump1.menu1.selectedIndex].value;" value="<?php _e("GO", "gravityforms"); ?>">
                </form>
                    </div>

                    </li>

                </ul>

                <div class="hr-divider"></div>

                <h3><?php _e("Gravity Forms Support Forums", "gravityforms"); ?></h3>
                <?php _e("<strong>Please Note:</strong> Only licensed Gravity Forms customers are granted access to the support forums.", "gravityforms"); ?>
                <ul style="margin-top:15px;">
                    <li>
                    <div class="gforms_helpbox">
                    <form name="jump2">
                    <select name="menu2">

                        <!-- begin forums listing -->
                        <option selected><?php _e("Forums (please select a topic)", "gravityforms"); ?></option>
                        <option value="http://www.gravityhelp.com/forums/forum/general"><?php _e("General", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/forums/forum/news-and-announcements">&nbsp;&nbsp;&nbsp;<?php _e("News &amp; Announcements", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/forums/forum/pre-sale-questions">&nbsp;&nbsp;&nbsp;<?php _e("Pre-Sale Questions", "gravityforms"); ?></option>

                            <option value="http://www.gravityhelp.com/forums/forum/feature-requests">&nbsp;&nbsp;&nbsp;<?php _e("Feature Requests", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/forums/forum/testimonials">&nbsp;&nbsp;&nbsp;<?php _e("Testimonials", "gravityforms"); ?></option>

                        <option value="http://www.gravityhelp.com/forums/forum/plugin-support"><?php _e("Plugin Support", "gravityforms"); ?></option>
                        <option value="http://www.gravityhelp.com/forums/forum/gravity-forms">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms", "gravityforms"); ?></option>
                            
                        <optgroup label="&nbsp;&nbsp;&nbsp;Basic Add-Ons">
                        
                            <option value="http://www.gravityhelp.com/forums/forum/gravity-forms-mailchimp-add-on">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms MailChimp Add-On", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/forums/forum/gravity-forms-campaign-monitor-add-on">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms Campaign Monitor Add-On", "gravityforms"); ?></option>
                            
                        </optgroup>
                        <optgroup label="&nbsp;&nbsp;&nbsp;Advanced Add-Ons">
                        
                        <option value="http://www.gravityhelp.com/forums/forum/gravity-forms-freshbooks-add-on">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms FreshBooks Add-On", "gravityforms"); ?></option>
                        <option value="http://www.gravityhelp.com/forums/forum/gravity-forms-paypal-add-on">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms PayPal Add-On", "gravityforms"); ?></option>
                        <option value="http://www.gravityhelp.com/forums/forum/gravity-forms-user-registration-add-on">&nbsp;&nbsp;&nbsp;<?php _e("Gravity Forms User Registration Add-On", "gravityforms"); ?></option>
                        
                        
                        </optgroup>

                            
                            

                    <!-- end forums listing -->
                    </select>
                    <input type="button" class="button" onClick="location=document.jump2.menu2.options[document.jump2.menu2.selectedIndex].value;" value="<?php _e("GO", "gravityforms"); ?>">
                </form>
                    </div>

                    </li>

                </ul>

                <div class="hr-divider"></div>

                <h3><?php _e("Gravity Forms Downloads", "gravityforms"); ?></h3>
                <?php _e("<strong>Please Note:</strong> Only licensed Gravity Forms customers are granted access to the downloads section.", "gravityforms"); ?>
                <ul style="margin-top:15px;">
                    <li>
                    <div class="gforms_helpbox">
                    <form name="jump3">
                    <select name="menu3">

                        <!-- begin downloads listing -->
                        <option selected><?php _e("Downloads (please select a product)", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/downloads/"><?php _e("Gravity Forms", "gravityforms"); ?></option>
                            <option value="http://www.gravityhelp.com/downloads/add-ons/"><?php _e("Gravity Forms Add-Ons", "gravityforms"); ?></option>

                        <!-- end downloads listing -->
                    </select>
                    <input type="button" class="button" onClick="location=document.jump3.menu3.options[document.jump3.menu3.selectedIndex].value;" value="<?php _e("GO", "gravityforms"); ?>">
                </form>
                    </div>

                    </li>

                </ul>


                <div class="hr-divider"></div>

                <h3><?php _e("Gravity Forms Tutorials &amp; Resources", "gravityforms"); ?></h3>
                <?php _e("<strong>Please note:</strong> The Gravity Forms support team does not provide support for third party scripts, widgets, etc.", "gravityforms"); ?>

                <div class="gforms_helpbox" style="margin:15px 0;">
                <ul class="resource_list">
                <li><a href="http://www.gravityhelp.com/">Gravity Forms Blog</a></li>
                    <li><a href="http://www.gravityhelp.com/gravity-forms-css-visual-guide/">Gravity Forms Visual CSS Guide</a></li>
                    <li><a href="http://www.rocketgenius.com/gravity-forms-css-targeting-specific-elements/">Gravity Forms CSS: Targeting Specific Elements</a></li>
                    <li><a href="http://www.gravityhelp.com/creating-a-modal-form-with-gravity-forms-and-fancybox/">Creating a Modal Form with Gravity Forms and FancyBox</a></li>
                    <li><a href="http://yoast.com/gravity-forms-widget-update/">Gravity Forms Widget (Third Party Release)</a></li>
                    <li><a href="http://wordpress.org/extend/plugins/wp-mail-smtp/">WP Mail SMTP Plugin</a></li>
                    <li><a href="http://wordpress.org/extend/plugins/members/">Members Plugin (Role Management - Integrates with Gravity Forms)</a></li>
                    <li><a href="http://wordpress.org/extend/plugins/really-simple-captcha/">Really Simple Captcha Plugin (Integrates with Gravity Forms)</a></li>
                </ul>

                </div>



                </div>
            </div>


            <?php
    }
}
?>