<?php

//For backwards compatibility, load wordpress if it hasn't been loaded yet
//Will be used if this file is being called directly
if(!class_exists("RGForms")){
    for ( $i = 0; $i < $depth = 10; $i++ ) {
        $wp_root_path = str_repeat( '../', $i );

        if ( file_exists("{$wp_root_path}wp-load.php" ) ) {
            require_once("{$wp_root_path}wp-load.php");
            require_once("{$wp_root_path}wp-admin/includes/admin.php");
            break;
        }
    }

    //redirect to the login page if user is not authenticated
    auth_redirect();
}

if(!GFCommon::current_user_can_any("gravityforms_view_entries"))
    die(__("You don't have adequate permission to view entries.", "gravityforms"));

$form_id = absint(rgget("fid"));
$lead_ids = explode(',', rgget("lid"));
$page_break = rgget("page_break") ? 'print-page-break' : false;

// sort lead IDs numerically
sort($lead_ids);

if(empty($form_id) || empty($lead_ids))
    die(__("Form Id and Lead Id are required parameters.", "gravityforms"));

$form = RGFormsModel::get_form_meta($form_id);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="MSSmartTagsPreventParsing" content="true" />
    <meta name="Robots" content="noindex, nofollow" />
    <meta http-equiv="Imagetoolbar" content="No" />
    <title>
        Print Preview :
        <?php echo $form["title"] ?> :
        <?php echo count($lead_ids) > 1 ? __("Entry # ", "gravityforms") . $lead_ids[0] : 'Bulk Print' ?>
    </title>
    <link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/print.css' type='text/css' />
	<?php 
		$styles = apply_filters("gform_print_styles", false, $form);
		if(!empty($styles)){
			wp_print_styles($styles);
		}
	?> 
    </head>
	<body onload="window.print();">

	<div id="print_preview_hdr" style="display:none">
		    <div><span class="actionlinks"><a href="javascript:;" onclick="window.print();" class="header-print-link">print this page</a> | <a href="javascript:window.close()" class="close_window"><?php _e("close window", "gravityforms") ?></a></span><?php _e("Print Preview", "gravityforms") ?></div>
	    </div>
		<div id="view-container">
        <?php

        require_once(GFCommon::get_base_path() . "/entry_detail.php");

        foreach($lead_ids as $lead_id){

            $lead = RGFormsModel::get_lead($lead_id);

            do_action("gform_print_entry_header", $form, $lead);

            GFEntryDetail::lead_detail_grid($form, $lead);

            if(rgget('notes')){
                $notes = RGFormsModel::get_lead_notes($lead["id"]);
                if(!empty($notes))
                    GFEntryDetail::notes_grid($notes, false);
            }

            // output entry divider/page break
            if(array_search($lead_id, $lead_ids) < count($lead_ids) - 1)
                echo '<div class="print-hr ' . $page_break . '"></div>';

            do_action("gform_print_entry_footer", $form, $lead);
        }

        ?>
		</div>
	</body>
</html>