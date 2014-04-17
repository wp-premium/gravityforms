<?php

if(!class_exists('GFForms')){
    die();
}

require_once("class-gf-locking.php");

class GFFormLocking extends GFLocking {
    public function __construct() {
        $capabilities = array("gravityforms_edit_forms");
        $redirect_url = admin_url("admin.php?page=gf_edit_forms");
        $form_id      = $this->get_object_id();
        $edit_url     = admin_url(sprintf("admin.php?page=gf_edit_forms&id=%d", $form_id));
        parent::__construct("form", $redirect_url, $edit_url, $capabilities);
    }

    public function get_strings() {
        $strings = array(
            "currently_locked"  => __('This form is currently locked. Click on the "Request Control" button to let %s know you\'d like to take over.', "gravityforms"),
            "accept"     => "Accept",
            "currently_editing" => "%s is currently editing this form",
            "taken_over"        => "%s has taken over and is currently editing this form.",
            "lock_requested"    => __("%s has requested permission to take over control of this form.", "gravityforms")
        );

        return array_merge(parent::get_strings(), $strings);
    }

    protected function is_edit_page() {
        return $this->is_page("form_editor");
    }

    protected function is_list_page() {
        return $this->is_page("form_list");
    }

    protected function get_object_id() {
        return rgget("id");
    }

}

new GFFormLocking();


class GFEntryLocking extends GFLocking {
    public function __construct() {
        $capabilities = array("gravityforms_edit_entries");
        $redirect_url = admin_url("admin.php?page=gf_entries");
        $entry_id     = $this->get_object_id();
        $form_id = rgget("id");
        $edit_url     = admin_url(sprintf("admin.php?page=gf_entries&view=entry&id=%d&lid=%d", $form_id, $entry_id));
        parent::__construct("entry", $redirect_url, $edit_url, $capabilities);
    }

    public function get_strings() {
        $strings = array(
            "currently_locked"  => __('This entry is currently locked. Click on the "Request Control" button to let %s know you\'d like to take over.', "gravityforms"),
            "currently_editing" => "%s is currently editing this entry",
            "taken_over"        => "%s has taken over and is currently editing this entry.",
            "lock_requested"    => __("%s has requested permission to take over control of this entry.", "gravityforms")
        );

        return array_merge(parent::get_strings(), $strings);
    }

    protected function is_edit_page() {
        return $this->is_page("entry_detail_edit");
    }

    protected function is_list_page() {
        return $this->is_page("entry_list");
    }

    protected function is_view_page() {
        return $this->is_page("entry_detail");
    }

    protected function get_object_id() {
        return rgget("lid");
    }

}

new GFEntryLocking();

class GFFormSettingsLocking extends GFLocking {
    public function __construct() {
        $capabilities = array("gravityforms_edit_forms");
        $redirect_url = admin_url("admin.php?page=gf_edit_forms");
        $form_id      = rgget("id");
        $subview = rgget("subview");
        if(empty($subview))
            $subview = "settings";
        $edit_url     = admin_url(sprintf("admin.php?page=gf_edit_forms&view=settings&subview=%s&id=%d", esc_html($subview), $form_id));
        parent::__construct("form_settings", $redirect_url, $edit_url, $capabilities);
    }

    public function get_strings() {
        $strings = array(
            "currently_locked"  => __('These form settings are currently locked. Click on the "Request Control" button to let %s know you\'d like to take over.', "gravityforms"),
            "currently_editing" => "%s is currently editing these settings",
            "taken_over"        => "%s has taken over and is currently editing these settings.",
            "lock_requested"    => __("%s has requested permission to take over control of these settings.", "gravityforms")
        );

        return array_merge(parent::get_strings(), $strings);
    }

    protected function is_edit_page() {
        $is_edit_page = rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings';
        return  $is_edit_page ;
    }

    protected function get_object_id() {
        $subview = rgget("subview");
        if(empty($subview))
            $subview = "settings";

        $form_id = rgget("id");
        return $subview . "-" . $form_id;
    }

}

new GFFormSettingsLocking();

class GFPluginSettingsLocking extends GFLocking {
    public function __construct() {
        $capabilities = array("gravityforms_edit_settings");
        $redirect_url = admin_url("admin.php?page=gf_edit_forms");
        $edit_url     = admin_url("admin.php?page=gf_settings");
        parent::__construct("plugin_settings", $redirect_url, $edit_url, $capabilities);
    }

    public function get_strings() {
        $strings = array(
            "currently_locked"  => __('These settings are currently locked. Click on the "Request Control" button to let %s know you\'d like to take over.', "gravityforms"),
            "currently_editing" => "%s is currently editing these settings",
            "taken_over"        => "%s has taken over and is currently editing these settings.",
            "lock_requested"    => __("%s has requested permission to take over control of these settings.", "gravityforms")
        );

        return array_merge(parent::get_strings(), $strings);
    }

    protected function is_edit_page() {
        return $this->is_page("settings");
    }

    protected function get_object_id() {
        $view = rgget("subview");
        if(empty($view))
            $view = "settings";
        return $view;
    }

}

new GFPluginSettingsLocking();