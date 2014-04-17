<?php

if(!class_exists('GFForms')){
    die();
}

class GFAddonLocking extends GFLocking {
    protected $_strings;
    protected $_addon;
    public function __construct($config, $addon) {
        $this->_addon = $addon;
        $capabilities = isset($config["capabilities"]) ? $config["capabilities"] : array();
        $redirect_url = isset($config["redirect_url"]) ? $config["redirect_url"] : "";
        $edit_url     = isset($config["edit_url"]) ? $config["edit_url"] : "";
        $object_type = isset($config["object_type"]) ? $config["object_type"] : "";
        $this->_strings = isset($config["strings"]) ? $config["strings"] : array();
        parent::__construct($object_type, $redirect_url, $edit_url, $capabilities);
    }

    public function get_strings() {
        return array_merge(parent::get_strings(), $this->_strings);
    }

    protected function is_edit_page() {
        return $this->_addon->is_locking_edit_page();
    }

    protected function is_list_page() {
        return $this->_addon->is_locking_list_page();
    }

    protected function is_view_page() {
        return $this->_addon->is_locking_view_page();
    }

    protected function get_object_id() {
        return $this->_addon->get_locking_object_id();
    }

    protected function is_object_locked($object_id){
        return $this->is_object_locked($object_id);
    }
}