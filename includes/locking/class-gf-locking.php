<?php

/**
 * Handles all tasks related to locking.
 *
 * - Loads the WordPress Heartbeat API and scripts & styles for GF Locking
 * - Provides standardized UX
 *
 * @package GFLocking
 * @author  Rocketgenius
 */
abstract class GFLocking {
    private $_object_type;
    private $_object_id;
    private $_edit_url;
    private $_redirect_url;
    private $_capabilities;
    const PREFIX_EDIT_LOCK         = "lock_";
    const PREFIX_EDIT_LOCK_REQUEST = "lock_request_";


    public function __construct($object_type, $redirect_url, $edit_url = "", $capabilities = array()) {
        $this->_object_type  = $object_type;
        $this->_redirect_url = $redirect_url;
        $this->_capabilities = $capabilities;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            $this->init_ajax();
        } else {
            $this->register_scripts();
            $is_locking_page = false;
            $is_edit_page = false;
            if ($this->is_edit_page()) {
                $this->init_edit_lock();
                $is_locking_page = true;
                $is_edit_page = true;
            } else if ($this->is_list_page()) {
                $this->init_list_page();
                $is_locking_page = true;
            } else if ($this->is_view_page()) {
                $this->init_view_page();
                $is_locking_page = true;
            }
            if($is_locking_page){
                $this->_object_id = $this->get_object_id();
                $this->_edit_url  = $edit_url;
                $this->maybe_lock_object($is_edit_page);
            }
        }
    }

    /**
     * Override this method to check the condition for the edit page.
     *
     * @return bool
     */
    protected function is_edit_page() {
        return false;
    }

    /**
     * Override this method to check the condition for the list page.
     *
     * @return bool
     */
    protected function is_list_page() {
        return false;
    }

    /**
     * Override this method to check the condition for the view page.
     *
     * @return bool
     */
    protected function is_view_page() {
        return false;
    }

    /**
     * Override this method to provide the class with the correct object id.
     *
     * @return bool
     */
    protected function get_object_id() {
        return rgget("id"); // example in the case of form id
    }

    public function init_edit_lock() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function init_ajax() {
        add_filter('heartbeat_received', array($this, 'heartbeat_refresh_nonces'), 10, 3);
        add_filter('heartbeat_received', array($this, 'heartbeat_check_locked_objects'), 10, 3);
        add_filter('heartbeat_received', array($this, 'heartbeat_refresh_lock'), 10, 3);
        add_filter('heartbeat_received', array($this, 'heartbeat_request_lock'), 10, 3);
        add_filter('wp_ajax_gf_lock_request_' . $this->_object_type, array($this, 'ajax_lock_request'));
        add_filter('wp_ajax_gf_reject_lock_request_' . $this->_object_type, array($this, 'ajax_reject_lock_request'));
    }

    public function init_list_page() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_list_scripts'));
    }

    public function init_view_page() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_view_page_scripts'));
    }

    public function register_scripts() {
        $locking_path = GFCommon::get_base_url() . "/includes/locking/";
        wp_register_script("gforms_locking", $locking_path . "js/locking.js", array("jquery", "heartbeat"), GFCommon::$version);
        wp_register_script("gforms_locking_view", $locking_path . "js/locking-view.js", array("jquery", "heartbeat"), GFCommon::$version);
        wp_register_script("gforms_locking_list", $locking_path . "js/locking-list.js", array("jquery", "heartbeat"), GFCommon::$version);
        wp_register_style("gforms_locking_css", $locking_path . "css/locking.css", array(), GFCommon::$version);
        wp_register_style("gforms_locking_list_css", $locking_path . "css/locking-list.css", array(), GFCommon::$version);

        // No conflict scripts
        add_filter('gform_noconflict_scripts', array($this, 'register_noconflict_scripts'));
        add_filter('gform_noconflict_styles', array($this, 'register_noconflict_styles'));
    }

    public function register_noconflict_scripts($scripts) {
        $locking_scripts = array("gforms_locking", "gforms_locking_list", "gforms_locking_view");

        return array_merge($scripts, $locking_scripts);
    }

    public function register_noconflict_styles($styles) {
        $locking_styles = array("gforms_locking_css", "gforms_locking_list_css");

        return array_merge($styles, $locking_styles);
    }

    public function enqueue_scripts() {


        wp_enqueue_script("gforms_locking");
        wp_enqueue_style("gforms_locking_css");
        $lock_user_id = $this->check_lock($this->get_object_id());

        $strings = array(
            'noResponse'    => $this->get_string("no_response"),
            'requestAgain'  => $this->get_string("request_again"),
            'requestError'  => $this->get_string("request_error"),
            'gainedControl' => $this->get_string("gained_control"),
            'rejected'      => $this->get_string("request_rejected"),
            'pending'       => $this->get_string("request_pending")
        );


        $vars = array(
            "hasLock"    => !$lock_user_id ? 1 : 0,
            "lockUI"     => $this->get_lock_ui($lock_user_id),
            "objectID"   => $this->_object_id,
            "objectType" => $this->_object_type,
            "strings"    => $strings
        );

        wp_localize_script("gforms_locking", "gflockingVars", $vars);
    }

    public function enqueue_list_scripts() {

        wp_enqueue_script("gforms_locking_list");
        wp_enqueue_style("gforms_locking_list_css");

        $vars = array(
            "objectType" => $this->_object_type,
        );

        wp_localize_script("gforms_locking_list", "gflockingVars", $vars);

    }

    public function enqueue_view_page_scripts() {

        wp_enqueue_script("gforms_locking_view");
        wp_enqueue_style("gforms_locking_view_css");

        $lock_user_id = $this->check_lock($this->get_object_id());
        $vars         = array(
            "hasLock"    => !$lock_user_id ? 1 : 0,
            "objectID"   => $this->_object_id,
            "objectType" => $this->_object_type,
        );

        wp_localize_script("gforms_locking_view", "gflockingVars", $vars);
    }


    protected function get_strings() {
        $strings = array(
            "currently_locked"  => __('This page is currently locked. Click on the "Request Control" button to let %s know you\'d like to take over.', "gravityforms"),
            "accept"            => __("Accept", "gravityforms"),
            "cancel"            => __("Cancel", "gravityforms"),
            "currently_editing" => __("%s is currently editing", "gravityforms"),
            "taken_over"        => __("%s has taken over and is currently editing.", "gravityforms"),
            "lock_requested"    => __("%s has requested permission to take over control.", "gravityforms"),
            "gained_control"    => __("You now have control", "gravityforms"),
            "request_pending"   => __("Pending", "gravityforms"),
            "no_response"       => __("No response", "gravityforms"),
            "request_again"     => __("Request again", "gravityforms"),
            "request_error"     => __("Error", "gravityforms"),
            "request_rejected"  => __("Your request was rejected", "gravityforms")
        );

        return $strings;
    }

    public function ajax_lock_request() {
        $object_id = rgget("object_id");
        $response  = $this->request_lock($object_id);
        echo json_encode($response);
        die();
    }

    public function ajax_reject_lock_request() {
        $object_id = rgget("object_id");
        $response  = $this->delete_lock_request_meta($object_id);
        echo json_encode($response);
        die();
    }

    protected function has_lock() {
        return $this->check_lock($this->get_object_id()) ? true : false;
    }


    protected function check_lock($object_id) {

        if (!$user_id = $this->get_lock_meta($object_id))
            return false;

        if ($user_id != get_current_user_id())
            return $user_id;

        return false;
    }

    protected function check_lock_request($object_id) {

        if (!$user_id = $this->get_lock_request_meta($object_id))
            return false;

        if ($user_id != get_current_user_id())
            return $user_id;

        return false;
    }

    protected function set_lock($object_id) {
        if (!GFCommon::current_user_can_any($this->_capabilities))
            return false;

        if (0 == ($user_id = get_current_user_id()))
            return false;

        $this->update_lock_meta($object_id, $user_id);

        return $user_id;
    }

    protected function request_lock($object_id) {
        if (0 == ($user_id = get_current_user_id()))
            return false;

        $lock_holder_user_id = $this->check_lock($object_id);

        $result = array();
        if (!$lock_holder_user_id) {
            $this->set_lock($object_id);
            $result["html"]   = __("You now have control", "gravityforms");
            $result["status"] = "lock_obtained";
        } else {
            $user = get_userdata($lock_holder_user_id);
            $this->update_lock_request_meta($object_id, $user_id);
            $result["html"]   = sprintf(__("%s has been notified of your request", "gravityforms"), $user->display_name);
            $result["status"] = "lock_requested";
        }

        return $result;
    }

    protected function get_lock_request_meta($object_id) {
        return GFCache::get(self::PREFIX_EDIT_LOCK_REQUEST . $this->_object_type . "_" . $object_id);
    }

    protected function get_lock_meta($object_id) {
        return GFCache::get(self::PREFIX_EDIT_LOCK . $this->_object_type . "_" . $object_id);
    }

    protected function update_lock_meta($object_id, $lock_value) {
        GFCache::set(self::PREFIX_EDIT_LOCK . $this->_object_type . "_" . $object_id, $lock_value, true, 90);
    }

    protected function update_lock_request_meta($object_id, $lock_request_value) {
        GFCache::set(self::PREFIX_EDIT_LOCK_REQUEST . $this->_object_type . "_" . $object_id, $lock_request_value, true, 120);
    }

    protected function delete_lock_request_meta($object_id) {
        GFCache::delete(self::PREFIX_EDIT_LOCK_REQUEST . $this->_object_type . "_" . $object_id);

        return true;
    }

    protected function delete_lock_meta($object_id) {
        GFCache::delete(self::PREFIX_EDIT_LOCK . $this->_object_type . "_" . $object_id);

        return true;
    }

    public function maybe_lock_object($is_edit_page) {
        if (isset($_GET['get-edit-lock'])) {
            $this->set_lock($this->_object_id);
            wp_redirect($this->_edit_url);
            exit();
        } else if (isset($_GET['release-edit-lock'])) {
            $this->delete_lock_meta($this->_object_id);
            wp_redirect($this->_redirect_url);
            exit();
        } else {
            if ($is_edit_page && !$user_id = $this->check_lock($this->_object_id))
                $this->set_lock($this->_object_id);
        }
    }



    public function heartbeat_check_locked_objects($response, $data, $screen_id) {
        $checked       = array();
        $heartbeat_key = 'gform-check-locked-objects-' . $this->_object_type;
        if (array_key_exists($heartbeat_key, $data) && is_array($data[$heartbeat_key])) {
            foreach ($data[$heartbeat_key] as $object_id) {
                if (($user_id = $this->check_lock($object_id)) && ($user = get_userdata($user_id))) {
                    $send = array('text' => sprintf(__($this->get_string("currently_editing")), $user->display_name));

                    if (($avatar = get_avatar($user->ID, 18)) && preg_match("|src='([^']+)'|", $avatar, $matches))
                        $send['avatar_src'] = $matches[1];

                    $checked[$object_id] = $send;
                }
            }
        }

        if (!empty($checked))
            $response[$heartbeat_key] = $checked;

        return $response;
    }

    public function heartbeat_refresh_lock($response, $data, $screen_id) {
        $heartbeat_key = 'gform-refresh-lock-' . $this->_object_type;
        if (array_key_exists($heartbeat_key, $data)) {
            $received = $data[$heartbeat_key];
            $send     = array();

            if (!isset($received['objectID']))
                return $response;

            $object_id = $received['objectID'];

            if (($user_id = $this->check_lock($object_id)) && ($user = get_userdata($user_id))) {
                $error = array(
                    'text' => sprintf(__($this->get_string("taken_over")), $user->display_name)
                );

                if ($avatar = get_avatar($user->ID, 64)) {
                    if (preg_match("|src='([^']+)'|", $avatar, $matches))
                        $error['avatar_src'] = $matches[1];
                }

                $send['lock_error'] = $error;
            } else {
                if ($new_lock = $this->set_lock($object_id)) {
                    $send['new_lock'] = $new_lock;

                    if (($lock_requester = $this->check_lock_request($object_id)) && ($user = get_userdata($lock_requester))) {
                        $lock_request = array(
                            'text' => sprintf(__($this->get_string("lock_requested")), $user->display_name)
                        );

                        if ($avatar = get_avatar($user->ID, 64)) {
                            if (preg_match("|src='([^']+)'|", $avatar, $matches))
                                $lock_request['avatar_src'] = $matches[1];
                        }
                        $send['lock_request'] = $lock_request;
                    }

                }

            }

            $response[$heartbeat_key] = $send;
        }

        return $response;
    }

    public function heartbeat_request_lock($response, $data, $screen_id) {
        $heartbeat_key = 'gform-request-lock-' . $this->_object_type;
        if (array_key_exists($heartbeat_key, $data)) {
            $received = $data[$heartbeat_key];
            $send     = array();

            if (!isset($received['objectID']))
                return $response;

            $object_id = $received['objectID'];

            if (($user_id = $this->check_lock($object_id)) && ($user = get_userdata($user_id))) {
                if ($this->get_lock_request_meta($object_id)) {
                    $send["status"] = "pending";
                } else {
                    $send["status"] = "deleted";
                }
            } else {
                if ($new_lock = $this->set_lock($object_id)) {
                    $send['status'] = "granted";
                }

            }

            $response[$heartbeat_key] = $send;
        }

        return $response;
    }


    public function heartbeat_refresh_nonces($response, $data, $screen_id) {
        if (array_key_exists('gform-refresh-nonces', $data)) {
            $received                         = $data['gform-refresh-nonces'];
            $response['gform-refresh-nonces'] = array('check' => 1);

            if (!isset($received['objectID']))
                return $response;

            $object_id = $received['objectID'];

            if (!GFCommon::current_user_can_any($this->_capabilities) || empty($received['post_nonce']))
                return $response;

            if (2 === wp_verify_nonce($received['object_nonce'], 'update-contact_' . $object_id)) {
                $response['gform-refresh-nonces'] = array(
                    'replace'        => array(
                        '_wpnonce' => wp_create_nonce('update-object_' . $object_id),
                    ),
                    'heartbeatNonce' => wp_create_nonce('heartbeat-nonce'),
                );
            }
        }

        return $response;
    }

    public function get_lock_ui($user_id) {

        $user = get_userdata($user_id);

        $locked = $user_id && $user;

        $edit_url = $this->_edit_url;

        $hidden = $locked ? '' : ' hidden';
        if ($locked) {

            $message = '<div class="gform-locked-message">
                            <div class="gform-locked-avatar">' . get_avatar($user->ID, 64) . '</div>
                            <p class="currently-editing" tabindex="0">' . __(sprintf($this->get_string("currently_locked"), $user->display_name)) . '</p>
                            <p>

                                <a id="gform-take-over-button" style="display:none" class="button button-primary wp-tab-first" href="' . esc_url(add_query_arg('get-edit-lock', '1', $edit_url)) . '">' . __('Take Over', "gravityforms") . '</a>
                                <button id="gform-lock-request-button" class="button button-primary wp-tab-last">' . __('Request Control', "gravityforms") . '</button>
                                <a class="button" href="' . esc_url($this->_redirect_url) . '">' . $this->get_string("cancel") . '</a>
                            </p>
                            <div id="gform-lock-request-status">
                                <!-- placeholder -->
                            </div>
                        </div>';

        } else {

            $message = '<div class="gform-taken-over">
                            <div class="gform-locked-avatar"></div>
                            <p class="wp-tab-first" tabindex="0">
                                <span class="currently-editing"></span><br>
                            </p>
                            <p>
                                <a id="gform-release-lock-button" class="button button-primary wp-tab-last"  href="' . esc_url(add_query_arg('release-edit-lock', '1', $edit_url)) . '">' . $this->get_string("accept") . '</a>
                                <button id="gform-reject-lock-request-button" style="display:none"  class="button button-primary wp-tab-last">' . __('Reject Request', "gravityforms") . '</button>
                            </p>
                        </div>';

        }
        $html = '<div id="gform-lock-dialog" class="notification-dialog-wrap' . $hidden . '">
                    <div class="notification-dialog-background"></div>
                    <div class="notification-dialog">';
        $html .= $message;

        $html .= '   </div>
                 </div>';

        return $html;
    }

    public function get_string($string_key) {
        $strings = $this->get_strings();

        return rgar($strings, $string_key);
    }

    // helper functions for the list page

    public function list_row_class($object_id, $echo = true) {
        $locked_class = $this->is_locked($object_id) ? "wp-locked" : "";
        $classes      = " gf-locking " . $locked_class;
        if ($echo)
            echo $classes;

        return $classes;
    }

    public function is_locked($object_id) {
        if (!$user_id = GFCache::get(self::PREFIX_EDIT_LOCK . $this->_object_type . "_" . $object_id))
            return false;

        if ($user_id != get_current_user_id())
            return true;

        return false;
    }

    public function lock_indicator($echo = true) {

        $lock_indicator = '<div class="locked-indicator"></div>';

        if ($echo)
            echo $lock_indicator;

        return $lock_indicator;
    }

    public function lock_info($object_id, $echo = true) {
        $user_id = $this->check_lock($object_id);

        if (!$user_id)
            return "";

        if ($user_id && $user = get_userdata($user_id)) {
            $locked_avatar = get_avatar($user->ID, 18);
            $locked_text   = esc_html(sprintf($this->get_string("currently_editing"), $user->display_name));
        } else {
            $locked_avatar = $locked_text = '';
        }

        $locked_info = '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";

        if ($echo)
            echo $locked_info;

        return $locked_info;
    }

    protected function is_page($page_name) {

        return $page_name == GFForms::get_page();
    }

}
