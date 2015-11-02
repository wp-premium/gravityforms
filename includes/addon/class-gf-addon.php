<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Handles all tasks mostly common to any Gravity Forms Add-On, including third party ones
 *
 *
 * @package GFAddOn
 * @author  Rocketgenius
 */
abstract class GFAddOn {

	/**
	 * @var string Version number of the Add-On
	 */
	protected $_version;
	/**
	 * @var string Gravity Forms minimum version requirement
	 */
	protected $_min_gravityforms_version;
	/**
	 * @var string URL-friendly identifier used for form settings, add-on settings, text domain localization...
	 */
	protected $_slug;
	/**
	 * @var string Relative path to the plugin from the plugins folder. Example "gravityforms/gravityforms.php"
	 */
	protected $_path;
	/**
	 * @var string Full path the the plugin. Example: __FILE__
	 */
	protected $_full_path;
	/**
	 * @var string URL to the Gravity Forms website. Example: 'http://www.gravityforms.com' OR affiliate link.
	 */
	protected $_url;
	/**
	 * @var string Title of the plugin to be used on the settings page, form settings and plugins page. Example: 'Gravity Forms MailChimp Add-On'
	 */
	protected $_title;
	/**
	 * @var string Short version of the plugin title to be used on menus and other places where a less verbose string is useful. Example: 'MailChimp'
	 */
	protected $_short_title;
	/**
	 * @var array Members plugin integration. List of capabilities to add to roles.
	 */
	protected $_capabilities = array();
	/**
	 * @var string The hook suffix for the app menu
	 */
	public $app_hook_suffix;

	private $_saved_settings = array();
	private $_previous_settings = array();

	/**
	 * @var array Stores a copy of setting fields that failed validation; only populated after validate_settings() has been called.
	 */
	private $_setting_field_errors = array();

	// ------------ Permissions -----------
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the settings page
	 */
	protected $_capabilities_settings_page = array();
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the form settings
	 */
	protected $_capabilities_form_settings = array();
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the plugin page
	 */
	protected $_capabilities_plugin_page = array();
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the app menu
	 */
	protected $_capabilities_app_menu = array();
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the app settings page
	 */
	protected $_capabilities_app_settings = array();
	/**
	 * @var string|array A string or an array of capabilities or roles that can uninstall the plugin
	 */
	protected $_capabilities_uninstall = array();

	// ------------ RG Autoupgrade -----------

	/**
	 * @var bool Used by Rocketgenius plugins to activate auto-upgrade.
	 * @ignore
	 */
	protected $_enable_rg_autoupgrade = false;

	// ------------ Private -----------

	private $_no_conflict_scripts = array();
	private $_no_conflict_styles = array();
	private $_preview_styles = array();
	private $_print_styles = array();
	private static $_registered_addons = array( 'active' => array(), 'inactive' => array() );

	/**
	 * Class constructor which hooks the instance into the WordPress init action
	 */
	function __construct() {

		add_action( 'init', array( $this, 'init' ) );

		if ( $this->_enable_rg_autoupgrade ) {
			require_once( 'class-gf-auto-upgrade.php' );
			$is_gravityforms_supported = $this->is_gravityforms_supported( $this->_min_gravityforms_version );
			new GFAutoUpgrade( $this->_slug, $this->_version, $this->_min_gravityforms_version, $this->_title, $this->_full_path, $this->_path, $this->_url, $is_gravityforms_supported );
		}

		$this->pre_init();
	}

	/**
	 * Registers an addon so that it gets initialized appropriately
	 *
	 * @param string $class - The class name
	 * @param string $overrides - Specify the class to replace/override
	 */
	public static function register( $class, $overrides = null ) {

		//Ignore classes that have been marked as inactive
		if ( in_array( $class, self::$_registered_addons['inactive'] ) ) {
			return;
		}

		//Mark classes as active. Override existing active classes if they are supposed to be overridden
		$index = array_search( $overrides, self::$_registered_addons['active'] );
		if ( $index !== false ) {
			self::$_registered_addons['active'][ $index ] = $class;
		} else {
			self::$_registered_addons['active'][] = $class;
		}

		//Mark overridden classes as inactive.
		if ( ! empty( $overrides ) ) {
			self::$_registered_addons['inactive'][] = $overrides;
		}

	}

	/**
	 * Gets all active, registered Add-Ons.
	 * 
	 * @static
	 * @return array - Active, registered Add-Ons
	 */
	public static function get_registered_addons() {
		return self::$_registered_addons['active'];
	}

	/**
	 * Initializes all addons.
	 */
	public static function init_addons() {

		//Removing duplicate add-ons
		$active_addons = array_unique( self::$_registered_addons['active'] );

		foreach ( $active_addons as $addon ) {

			call_user_func( array( $addon, 'get_instance' ) );

		}
	}

	/**
	 * Gets executed before all init functions. Override this function to perform initialization tasks that must be done prior to init
	 */
	public function pre_init() {

		if ( $this->is_gravityforms_supported() ) {

			//Entry meta
			if ( $this->method_is_overridden( 'get_entry_meta' ) ) {
				add_filter( 'gform_entry_meta', array( $this, 'get_entry_meta' ), 10, 2 );
			}
		}
	}

	/**
	 * Plugin starting point. Handles hooks and loading of language files.
	 */
	public function init() {

		// Initializing translations. Translation files in the WP_LANG_DIR folder have a higher priority.
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->_slug );
		load_textdomain( $this->_slug, WP_LANG_DIR . '/gravityforms/' . $this->_slug . '-' . $locale . '.mo' );
		load_plugin_textdomain( $this->_slug, false, $this->_slug . '/languages' );

		add_filter( 'gform_logging_supported', array( $this, 'set_logging_supported' ) );

		if ( RG_CURRENT_PAGE == 'admin-ajax.php' ) {

			//If gravity forms is supported, initialize AJAX
			if ( $this->is_gravityforms_supported() ) {
				$this->init_ajax();
			}
		} elseif ( is_admin() ) {

			$this->init_admin();

		} else {

			if ( $this->is_gravityforms_supported() ) {
				$this->init_frontend();
			}
		}


	}

	/**
	 * Override this function to add initialization code (i.e. hooks) for the admin site (WP dashboard)
	 */
	protected function init_admin() {

		// enqueues admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 0 );

		// message enforcing min version of Gravity Forms
		if ( isset( $this->_min_gravityforms_version ) && RG_CURRENT_PAGE == 'plugins.php' && false === $this->_enable_rg_autoupgrade ) {
			add_action( 'after_plugin_row_' . $this->_path, array( $this, 'plugin_row' ) );
		}

		// STOP HERE IF GRAVITY FORMS IS NOT SUPPORTED
		if ( isset( $this->_min_gravityforms_version ) && ! $this->is_gravityforms_supported( $this->_min_gravityforms_version ) ) {
			return;
		}

		$this->setup();

		// Add form settings only when there are form settings fields configured or form_settings() method is implemented
		if ( self::has_form_settings_page() ) {
			$this->form_settings_init();
		}

		// Add plugin page when there is a plugin page configured or plugin_page() method is implemented
		if ( self::has_plugin_page() ) {
			$this->plugin_page_init();
		}

		// Add addon settings page only when there are addon settings fields configured or settings_page() method is implemented
		if ( self::has_plugin_settings_page() ) {
			if ( $this->current_user_can_any( $this->_capabilities_settings_page ) ) {
				$this->plugin_settings_init();
			}
		}

		// creates the top level app left menu
		if ( self::has_app_menu() ) {
			if ( $this->current_user_can_any( $this->_capabilities_app_menu ) ) {
				add_action( 'admin_menu', array( $this, 'create_app_menu' ) );
			}
		}


		// Members plugin integration
		if ( self::has_members_plugin() ) {
			add_filter( 'members_get_capabilities', array( $this, 'members_get_capabilities' ) );
		}

		// Results page
		if ( $this->method_is_overridden( 'get_results_page_config' ) ) {
			$results_page_config  = $this->get_results_page_config();
			$results_capabilities = rgar( $results_page_config, 'capabilities' );
			if ( $results_page_config && $this->current_user_can_any( $results_capabilities ) ) {
				$this->results_page_init( $results_page_config );
			}
		}

		// Locking
		if ( $this->method_is_overridden( 'get_locking_config' ) ) {
			require_once( GFCommon::get_base_path() . '/includes/locking/class-gf-locking.php' );
			require_once( 'class-gf-addon-locking.php' );
			$config = $this->get_locking_config();
			new GFAddonLocking( $config, $this );
		}

		// No conflict scripts
		add_filter( 'gform_noconflict_scripts', array( $this, 'register_noconflict_scripts' ) );
		add_filter( 'gform_noconflict_styles', array( $this, 'register_noconflict_styles' ) );

	}

	/**
	 * Override this function to add initialization code (i.e. hooks) for the public (customer facing) site
	 */
	protected function init_frontend() {

		$this->setup();

		add_filter( 'gform_preview_styles', array( $this, 'enqueue_preview_styles' ), 10, 2 );
		add_filter( 'gform_print_styles', array( $this, 'enqueue_print_styles' ), 10, 2 );
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 2 );

	}

	/**
	 * Override this function to add AJAX hooks or to add initialization code when an AJAX request is being performed
	 */
	protected function init_ajax() {
		if ( rgpost( 'view' ) == 'gf_results_' . $this->_slug ) {
			require_once( GFCommon::get_base_path() . '/tooltips.php' );
			require_once( 'class-gf-results.php' );
			$gf_results = new GFResults( $this->_slug, $this->get_results_page_config() );
			add_action( 'wp_ajax_gresults_get_results_gf_results_' . $this->_slug, array( $gf_results, 'ajax_get_results' ) );
			add_action( 'wp_ajax_gresults_get_more_results_gf_results_' . $this->_slug, array( $gf_results, 'ajax_get_more_results' ) );
		} elseif ( $this->method_is_overridden( 'get_locking_config' ) ) {
			require_once( GFCommon::get_base_path() . '/includes/locking/class-gf-locking.php' );
			require_once( 'class-gf-addon-locking.php' );
			$config = $this->get_locking_config();
			new GFAddonLocking( $config, $this );
		}
	}


	//--------------  Setup  ---------------

	/**
	 * Performs upgrade tasks when the version of the Add-On changes. To add additional upgrade tasks, override the upgrade() function, which will only get executed when the plugin version has changed.
	 */
	protected function setup() {

		//Upgrading add-on
		$installed_version = get_option( 'gravityformsaddon_' . $this->_slug . '_version' );

		//Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
		if ( $installed_version != $this->_version ) {
			$installed_version = GFForms::get_wp_option( 'gravityformsaddon_' . $this->_slug . '_version' );
		}

		//Upgrade if version has changed
		if ( $installed_version != $this->_version ) {

			$this->upgrade( $installed_version );
			update_option( 'gravityformsaddon_' . $this->_slug . '_version', $this->_version );
		}
	}

	/**
	 * Override this function to add to add database update scripts or any other code to be executed when the Add-On version changes
	 */
	protected function upgrade( $previous_version ) {
		return;
	}


	//--------------  Script enqueuing  ---------------

	/**
	 * Override this function to provide a list of styles to be enqueued.
	 * When overriding this function, be sure to call parent::styles() to ensure the base class scripts are enqueued.
	 * See scripts() for an example of the format expected to be returned.
	 */
	protected function styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		return array(
			array(
				'handle'  => 'gaddon_form_settings_css',
				'src'     => GFAddOn::get_gfaddon_base_url() . "/css/gaddon_settings{$min}.css",
				'version' => GFCommon::$version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings', 'plugin_settings', 'plugin_page', 'app_settings' ) ),
				)
			),
			array(
				'handle'  => 'gaddon_results_css',
				'src'     => GFAddOn::get_gfaddon_base_url() . "/css/gaddon_results{$min}.css",
				'version' => GFCommon::$version,
				'enqueue' => array(
					array( 'admin_page' => array( 'results' ) ),
				)
			),
		);
	}


	/**
	 * Override this function to provide a list of scripts to be enqueued.
	 * When overriding this function, be sure to call parent::scripts() to ensure the base class scripts are enqueued.
	 * Following is an example of the array that is expected to be returned by this function:
	 *<pre>
	 * <code>
	 *
	 *    array(
	 *        array(  "handle" => 'maskedinput',
	 *                "src" => GFCommon::get_base_url() . '/js/jquery.maskedinput-1.3.min.js',
	 *                "version" => GFCommon::$version,
	 *                "deps" => array("jquery"),
	 *                "in_footer" => false,
	 *
	 *                //Determines where the script will be enqueued. The script will be enqueued if any of the conditions match
	 *                "enqueue" => array(
	 *                                    //admin_page - Specified one or more pages (known pages) where the script is supposed to be enqueued.
	 *                                    //To enqueue scripts in the front end (public website), simply don't define this setting
	 *                                    array("admin_page" => array("form_settings", 'plugin_settings') ),
	 *
	 *                                    //tab - Specifies a form settings or plugin settings tab in which the script is supposed to be enqueued. If none is specified, the script will be enqueued in any of the form settings or plugin_settings page
	 *                                    array("tab" => 'signature'),
	 *
	 *                                    //query - Specifies a set of query string ($_GET) values. If all specified query string values match the current requested page, the script will be enqueued
	 *                                    array("query" => 'page=gf_edit_forms&view=settings&id=_notempty_')
	 *
	 *                                    //post - Specifies a set of post ($_POST) values. If all specified posted values match the current request, the script will be enqueued
	 *                                    array("post" => 'posted_field=val')
	 *
	 *                                    )
	 *            ),
	 *        array(
	 *            "handle" => 'super_signature_script',
	 *            "src" => $this->get_base_url() . '/super_signature/ss.js',
	 *            "version" => $this->_version,
	 *            "deps" => array("jquery"),
	 *            "callback" => array($this, 'localize_scripts'),
	 *            "strings" => array(
	 *                               // Accessible in JavaScript using the global variable "[script handle]_strings"
	 *                               "stringKey1" => __("The string", 'gravityforms'),
	 *                               "stringKey2" => __("Another string.", 'gravityforms')
	 *                               )
	 *            "enqueue" => array(
	 *                                //field_types - Specifies one or more field types that requires this script. The script will only be enqueued if the current form has a field of any of the specified field types. Only applies when a current form is available.
	 *                                array("field_types" => array("signature"))
	 *                                )
	 *        )
	 *  );
	 *
	 * </code>
	 * </pre>
	 */
	protected function scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		return array(
			array(
				'handle'  => 'gform_form_admin',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) )
			),
			array(
				'handle'  => 'gform_gravityforms',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) )
			),
			array(
				'handle'  => 'google_charts',
				'src'     => 'https://www.google.com/jsapi',
				'version' => GFCommon::$version,
				'enqueue' => array(
					array( 'admin_page' => array( 'results' ) ),
				)
			),
			array(
				'handle'   => 'gaddon_results_js',
				'src'      => GFAddOn::get_gfaddon_base_url() . "/js/gaddon_results{$min}.js",
				'version'  => GFCommon::$version,
				'deps'     => array( 'jquery', 'sack', 'jquery-ui-resizable', 'gform_datepicker_init', 'google_charts', 'gform_field_filter' ),
				'callback' => array( 'GFResults', 'localize_results_scripts' ),
				'enqueue'  => array(
					array( 'admin_page' => array( 'results' ) ),
				)
			),
			array(
				'handle'  => 'gaddon_repeater',
				'src'     => GFAddOn::get_gfaddon_base_url() . "/js/repeater{$min}.js",
				'version' => GFCommon::$version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
					),
				),
			),
			array(
				'handle'   => 'gaddon_fieldmap_js',
				'src'      => GFAddOn::get_gfaddon_base_url() . "/js/gaddon_fieldmap{$min}.js",
				'version'  => GFCommon::$version,
				'deps'     => array( 'jquery', 'gaddon_repeater' ),
				'enqueue'  => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				)
			),
			array(
				'handle'   => 'gaddon_selectcustom_js',
				'src'      => GFAddOn::get_gfaddon_base_url() . "/js/gaddon_selectcustom{$min}.js",
				'version'  => GFCommon::$version,
				'deps'     => array( 'jquery' ),
				'enqueue'  => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				)
			),
		);
	}


	/**
	 * Target of admin_enqueue_scripts and gform_enqueue_scripts hooks.
	 * Not intended to be overridden by child classes.
	 * In order to enqueue scripts and styles, override the scripts() and styles() functions
	 *
	 * @ignore
	 */
	public function enqueue_scripts( $form = '', $is_ajax = false ) {

		if ( empty( $form ) ) {
			$form = $this->get_current_form();
		}

		//Enqueueing scripts
		$scripts = $this->scripts();
		foreach ( $scripts as $script ) {
			$src       = isset( $script['src'] ) ? $script['src'] : false;
			$deps      = isset( $script['deps'] ) ? $script['deps'] : array();
			$version   = isset( $script['version'] ) ? $script['version'] : false;
			$in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : false;
			wp_register_script( $script['handle'], $src, $deps, $version, $in_footer );
			if ( isset( $script['enqueue'] ) && $this->_can_enqueue_script( $script['enqueue'], $form, $is_ajax ) ) {
				$this->add_no_conflict_scripts( array( $script['handle'] ) );
				wp_enqueue_script( $script['handle'] );
				if ( isset( $script['strings'] ) ) {
					wp_localize_script( $script['handle'], $script['handle'] . '_strings', $script['strings'] );
				}
				if ( isset( $script['callback'] ) && is_callable( $script['callback'] ) ) {
					$args = compact( 'form', 'is_ajax' );
					call_user_func_array( $script['callback'], $args );
				}
			}
		}

		//Enqueueing styles
		$styles = $this->styles();
		foreach ( $styles as $style ) {
			$src     = isset( $style['src'] ) ? $style['src'] : false;
			$deps    = isset( $style['deps'] ) ? $style['deps'] : array();
			$version = isset( $style['version'] ) ? $style['version'] : false;
			$media   = isset( $style['media'] ) ? $style['media'] : 'all';
			wp_register_style( $style['handle'], $src, $deps, $version, $media );
			if ( $this->_can_enqueue_script( $style['enqueue'], $form, $is_ajax ) ) {
				$this->add_no_conflict_styles( array( $style['handle'] ) );
				if ( $this->is_preview() ) {
					$this->_preview_styles[] = $style['handle'];
				} elseif ( $this->is_print() ) {
					$this->_print_styles[] = $style['handle'];
				} else {
					wp_enqueue_style( $style['handle'] );
				}
			}
		}
	}

	/**
	 * Target of gform_preview_styles. Enqueue styles to the preview page.
	 * Not intended to be overridden by child classes
	 *
	 * @ignore
	 */
	public function enqueue_preview_styles( $preview_styles, $form ) {
		return array_merge( $preview_styles, $this->_preview_styles );
	}


	/**
	 * Target of gform_print_styles. Enqueue styles to the print entry page.
	 * Not intended to be overridden by child classes
	 *
	 * @ignore
	 */
	public function enqueue_print_styles( $print_styles, $form ) {
		if ( false === $print_styles ) {
			$print_styles = array();
		}

		$styles = $this->styles();
		foreach ( $styles as $style ) {
			if ( $this->_can_enqueue_script( $style['enqueue'], $form, false ) ) {
				$this->add_no_conflict_styles( array( $style['handle'] ) );
				$src     = isset( $style['src'] ) ? $style['src'] : false;
				$deps    = isset( $style['deps'] ) ? $style['deps'] : array();
				$version = isset( $style['version'] ) ? $style['version'] : false;
				$media   = isset( $style['media'] ) ? $style['media'] : 'all';
				wp_register_style( $style['handle'], $src, $deps, $version, $media );
				$print_styles[] = $style['handle'];
			}
		}

		return array_merge( $print_styles, $this->_print_styles );
	}


	/**
	 * Adds scripts to the list of white-listed no conflict scripts.
	 *
	 * @param $scripts
	 */
	private function add_no_conflict_scripts( $scripts ) {
		$this->_no_conflict_scripts = array_merge( $scripts, $this->_no_conflict_scripts );

	}

	/**
	 * Adds styles to the list of white-listed no conflict styles.
	 *
	 * @param $styles
	 */
	private function add_no_conflict_styles( $styles ) {
		$this->_no_conflict_styles = array_merge( $styles, $this->_no_conflict_styles );
	}

	private function _can_enqueue_script( $enqueue_conditions, $form, $is_ajax ) {
		if ( empty( $enqueue_conditions ) ) {
			return false;
		}

		foreach ( $enqueue_conditions as $condition ) {
			if ( is_callable( $condition ) ) {
				return call_user_func( $condition, $form, $is_ajax );
			} else {
				$query_matches      = isset( $condition['query'] ) ? $this->_request_condition_matches( $_GET, $condition['query'] ) : true;
				$post_matches       = isset( $condition['post'] ) ? $this->_request_condition_matches( $_POST, $condition['query'] ) : true;
				$admin_page_matches = isset( $condition['admin_page'] ) ? $this->_page_condition_matches( $condition['admin_page'], rgar( $condition, 'tab' ) ) : true;
				$field_type_matches = isset( $condition['field_types'] ) ? $this->_field_condition_matches( $condition['field_types'], $form ) : true;

				if ( $query_matches && $post_matches && $admin_page_matches && $field_type_matches ) {
					return true;
				}
			}
		}

		return false;
	}

	private function _request_condition_matches( $request, $query ) {
		parse_str( $query, $query_array );
		foreach ( $query_array as $key => $value ) {

			switch ( $value ) {
				case '_notempty_' :
					if ( rgempty( $key, $request ) ) {
						return false;
					}
					break;
				case '_empty_' :
					if ( ! rgempty( $key, $request ) ) {
						return false;
					}
					break;
				default :
					if ( rgar( $request, $key ) != $value ) {
						return false;
					}
					break;
			}
		}

		return true;
	}

	private function _page_condition_matches( $pages, $tab ) {
		if ( ! is_array( $pages ) ) {
			$pages = array( $pages );
		}

		foreach ( $pages as $page ) {
			switch ( $page ) {
				case 'form_editor' :
					if ( $this->is_form_editor() ) {
						return true;
					}

					break;

				case 'form_settings' :
					if ( $this->is_form_settings( $tab ) ) {
						return true;
					}

					break;

				case 'plugin_settings' :
					if ( $this->is_plugin_settings( $tab ) ) {
						return true;
					}

					break;

				case 'app_settings' :
					if ( $this->is_app_settings( $tab ) ) {
						return true;
					}

					break;

				case 'plugin_page' :
					if ( $this->is_plugin_page() ) {
						return true;
					}

					break;

				case 'entry_list' :
					if ( $this->is_entry_list() ) {
						return true;
					}

					break;

				case 'entry_view' :
					if ( $this->is_entry_view() ) {
						return true;
					}

					break;

				case 'entry_edit' :
					if ( $this->is_entry_edit() ) {
						return true;
					}

					break;

				case 'results' :
					if ( $this->is_results() ) {
						return true;
					}

					break;

			}
		}

		return false;

	}

	private function _field_condition_matches( $field_types, $form ) {
		if ( ! is_array( $field_types ) ) {
			$field_types = array( $field_types );
		}

		$fields = GFAPI::get_fields_by_type( $form, $field_types );
		if ( count( $fields ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Target for the gform_noconflict_scripts filter. Adds scripts to the list of white-listed no conflict scripts.
	 *
	 * Not intended to be overridden or called directed by Add-Ons.
	 *
	 * @ignore
	 *
	 * @param array $scripts Array of scripts to be white-listed
	 *
	 * @return array
	 */
	public function register_noconflict_scripts( $scripts ) {
		//registering scripts with Gravity Forms so that they get enqueued when running in no-conflict mode
		return array_merge( $scripts, $this->_no_conflict_scripts );
	}

	/**
	 * Target for the gform_noconflict_styles filter. Adds styles to the list of white-listed no conflict scripts.
	 *
	 * Not intended to be overridden or called directed by Add-Ons.
	 *
	 * @ignore
	 *
	 * @param array $styles Array of styles to be white-listed
	 *
	 * @return array
	 */
	public function register_noconflict_styles( $styles ) {
		//registering styles with Gravity Forms so that they get enqueued when running in no-conflict mode
		return array_merge( $styles, $this->_no_conflict_styles );
	}



	//--------------  Entry meta  --------------------------------------

	/**
	 * Override this method to activate and configure entry meta.
	 *
	 *
	 * @param array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
	 * @param int   $form_id    The form id
	 *
	 * @return array The filtered entry meta array.
	 */
	protected function get_entry_meta( $entry_meta, $form_id ) {
		return $entry_meta;
	}


	//--------------  Results page  --------------------------------------
	/**
	 * Returns the configuration for the results page. By default this is not activated.
	 * To activate the results page override this function and return an array with the configuration data.
	 *
	 * Example:
	 * public function get_results_page_config() {
	 *      return array(
	 *       "title" => 'Quiz Results',
	 *       "capabilities" => array("gravityforms_quiz_results"),
	 *       "callbacks" => array(
	 *          "fields" => array($this, 'results_fields'),
	 *          "calculation" => array($this, 'results_calculation'),
	 *          "markup" => array($this, 'results_markup'),
	 *              )
	 *       );
	 * }
	 *
	 */
	public function get_results_page_config() {
		return false;
	}

	/**
	 * Initializes the result page functionality. To activate result page functionality, override the get_results_page_config() function.
	 *
	 * @param $results_page_config - configuration returned by get_results_page_config()
	 */
	protected function results_page_init( $results_page_config ) {
		require_once( 'class-gf-results.php' );

		if ( isset( $results_page_config['callbacks']['filters'] ) ) {
			add_filter( 'gform_filters_pre_results', $results_page_config['callbacks']['filters'], 10, 2 );
		}

		if ( isset( $results_page_config['callbacks']['filter_ui'] ) ) {
			add_filter( 'gform_filter_ui', $results_page_config['callbacks']['filter_ui'], 10, 5 );
		}

		$gf_results = new GFResults( $this->_slug, $results_page_config );
		$gf_results->init();
	}

	//--------------  Logging integration  --------------------------------------

	public function set_logging_supported( $plugins ) {
		$plugins[ $this->_slug ] = $this->_title;

		return $plugins;
	}


	//--------------  Members plugin integration  --------------------------------------

	/**
	 * Checks whether the Members plugin is installed and activated.
	 *
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 *
	 * @return bool
	 */
	protected function has_members_plugin() {
		return function_exists( 'members_get_capabilities' );
	}

	/**
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 *
	 * @param $caps
	 *
	 * @return array
	 */
	public function members_get_capabilities( $caps ) {
		return array_merge( $caps, $this->_capabilities );
	}

	//--------------  Permissions: Capabilities and Roles  ----------------------------

	/**
	 *  Checks whether the current user is assigned to a capability or role.
	 *
	 * @param string|array $caps An string or array of capabilities to check
	 *
	 * @return bool Returns true if the current user is assigned to any of the capabilities.
	 */
	protected function current_user_can_any( $caps ) {
		return GFCommon::current_user_can_any( $caps );
	}


	//------- Settings Helper Methods (Common to all settings pages) -------------------

	/***
	 * Renders the UI of all settings page based on the specified configuration array $sections
	 *
	 * @param array $sections - Configuration array containing all fields to be rendered grouped into sections
	 */
	protected function render_settings( $sections ) {

		if ( ! $this->has_setting_field_type( 'save', $sections ) ) {
			$sections = $this->add_default_save_button( $sections );
		}

		?>

		<form id="gform-settings" action="" method="post">

			<?php $this->settings( $sections ); ?>

		</form>

	<?php
	}

	/***
	 * Renders settings fields based on the specified configuration array $sections
	 *
	 * @param array $sections - Configuration array containing all fields to be rendered grouped into sections
	 */
	protected function settings( $sections ) {
		$is_first = true;
		foreach ( $sections as $section ) {
			if ( $this->setting_dependency_met( rgar( $section, 'dependency' ) ) ) {
				$this->single_section( $section, $is_first );
			}

			$is_first = false;
		}
	}

	/***
	 * Displays the UI for a field section
	 *
	 * @param array $section  - The section to be displayed
	 * @param bool  $is_first - true for the first section in the list, false for all others
	 */
	protected function single_section( $section, $is_first = false ) {

		extract(
			wp_parse_args(
				$section, array(
					'title'       => false,
					'description' => false,
					'id'          => '',
					'class'       => false,
					'style'       => '',
					'tooltip'     => false,
					'tooltip_class' => ''
				)
			)
		);

		$classes = array( 'gaddon-section' );

		if ( $is_first ) {
			$classes[] = 'gaddon-first-section';
		}

		if ( $class )
			$classes[] = $class;

		?>

		<div
			id="<?php echo $id; ?>"
			class="<?php echo implode( ' ', $classes ); ?>"
			style="<?php echo $style; ?>"
			>

			<?php if ( $title ): ?>
				<h4 class="gaddon-section-title gf_settings_subgroup_title">
					<?php echo $title; ?>
					<?php if( $tooltip ): ?>
						<?php gform_tooltip( $tooltip, $tooltip_class ); ?>
					<?php endif; ?>
				</h4>
			<?php endif; ?>

			<?php if ( $description ): ?>
				<div class="gaddon-section-description"><?php echo $description; ?></div>
			<?php endif; ?>

			<table class="form-table gforms_form_settings">

				<?php
				foreach ( $section['fields'] as $field ) {

					if ( ! $this->setting_dependency_met( rgar( $field, 'dependency' ) ) )
						continue;

					if ( is_callable( array( $this, "single_setting_row_{$field['type']}" ) ) ) {
						call_user_func( array( $this, "single_setting_row_{$field['type']}" ), $field );
					} else {
						$this->single_setting_row( $field );
					}
				}
				?>

			</table>

		</div>

	<?php
	}

	/***
	 * Displays the UI for the field container row
	 *
	 * @param array $field - The field to be displayed
	 */
	protected function single_setting_row( $field ) {

		$display = rgar( $field, 'hidden' ) || rgar( $field, 'type' ) == 'hidden' ? 'style="display:none;"' : '';

		?>

		<tr id="gaddon-setting-row-<?php echo $field['name'] ?>" <?php echo $display; ?>>
			<th>
				<?php $this->single_setting_label( $field ); ?>
			</th>
			<td>
				<?php $this->single_setting( $field ); ?>
			</td>
		</tr>

	<?php
	}

	/**
	 * Displays the label for a field, including the tooltip and requirement indicator.
	 */
	protected function single_setting_label( $field ) {

		echo $field['label'];

		if ( isset( $field['tooltip'] ) ) {
			echo ' ' . gform_tooltip( $field['tooltip'], rgar( $field, 'tooltip_class' ), true );
		}

		if ( rgar( $field, 'required' ) ) {
			echo ' ' . $this->get_required_indicator( $field );
		}

	}

	protected function single_setting_row_save( $field ) {
		?>

		<tr>
			<td colspan="2">
				<?php $this->single_setting( $field ); ?>
			</td>
		</tr>

	<?php
	}

	/***
	 * Calls the appropriate field function to handle rendering of each specific field type
	 *
	 * @param array $field - The field to be rendered
	 */
	protected function single_setting( $field ) {
		if ( is_callable( rgar( $field, 'callback' ) ) ) {
			call_user_func( $field['callback'], $field );
		} elseif ( is_callable( array( $this, "settings_{$field['type']}" ) ) ) {
			call_user_func( array( $this, "settings_{$field['type']}" ), $field );
		} else {
			printf( esc_html__( "Field type '%s' has not been implemented", 'gravityforms' ), esc_html( $field['type'] ) );
		}
	}

	/***
	 * Sets the current saved settings to a class variable so that it can be accessed by lower level functions in order to initialize inputs with the appropriate values
	 *
	 * @param array $settings : Settings to be saved
	 */
	protected function set_settings( $settings ) {
		$this->_saved_settings = $settings;
	}

	/***
	 * Sets the previous settings to a class variable so that it can be accessed by lower level functions providing support for
	 * verifying whether a value was changed before executing an action
	 *
	 * @param array $settings : Settings to be stored
	 */
	protected function set_previous_settings( $settings ) {
		$this->_previous_settings = $settings;
	}

	protected function get_previous_settings() {
		return $this->_previous_settings;
	}


	/***
	 * Gets settings from $_POST variable, returning a name/value collection of setting name and setting value
	 */
	protected function get_posted_settings() {
		global $_gaddon_posted_settings;

		if ( isset( $_gaddon_posted_settings ) ) {
			return $_gaddon_posted_settings;
		}

		$_gaddon_posted_settings = array();
		if ( count( $_POST ) > 0 ) {
			foreach ( $_POST as $key => $value ) {
				if ( preg_match( '|_gaddon_setting_(.*)|', $key, $matches ) ) {
					$_gaddon_posted_settings[ $matches[1] ] = self::maybe_decode_json( stripslashes_deep( $value ) );
				}
			}
		}

		return $_gaddon_posted_settings;
	}

	protected static function maybe_decode_json( $value ) {
		if ( self::is_json( $value ) ) {
			return json_decode( $value, ARRAY_A );
		}

		return $value;
	}

	protected static function is_json( $value ) {
		if ( is_string( $value ) && in_array( substr( $value, 0, 1 ), array( '{', '[' ) ) && is_array( json_decode( $value, ARRAY_A ) ) ) {
			return true;
		}

		return false;
	}

	/***
	 * Gets the "current" settings, which are settings from $_POST variables if this is a postback request, or the current saved settings for a get request.
	 */
	protected function get_current_settings() {
		//try getting settings from post
		$settings = $this->get_posted_settings();

		//if nothing has been posted, get current saved settings
		if ( empty( $settings ) ) {
			$settings = $this->_saved_settings;
		}

		return $settings;
	}

	/***
	 * Retrieves the setting for a specific field/input
	 *
	 * @param string $setting_name  The field or input name
	 * @param string $default_value Optional. The default value
	 * @param bool|array $settings Optional. THe settings array
	 *
	 * @return string|array
	 */
	protected function get_setting( $setting_name, $default_value = '', $settings = false ) {

		if ( ! $settings ) {
			$settings = $this->get_current_settings();
		}

		if ( false === $settings ) {
			return $default_value;
		}

		if ( strpos( $setting_name, '[' ) !== false ) {
			$path_parts = explode( '[', $setting_name );
			foreach ( $path_parts as $part ) {
				$part = trim( $part, ']' );
				if ( $part != '0'){
					if ( empty( $part ) ) {
						return $settings;
					}
				}
				if ( false === isset( $settings[ $part ] ) ) {
					return $default_value;
				}

				$settings = rgar( $settings, $part );
			}
			$setting = $settings;
		} else {
			if ( false === isset( $settings[ $setting_name ] ) ) {
				return $default_value;
			}
			$setting = $settings[ $setting_name ];
		}

		return $setting;
	}

	/***
	 * Determines if a dependent field has been populated.
	 *
	 * @param string $dependency - Field or input name of the "parent" field.
	 *
	 * @return bool - true if the "parent" field has been filled out and false if it has not.
	 *
	 */
	protected function setting_dependency_met( $dependency ) {

		// if no dependency, always return true
		if ( ! $dependency ) {
			return true;
		}

		//use a callback if one is specified in the configuration
		if ( is_callable( $dependency ) ) {
			return call_user_func( $dependency );
		}

		if ( is_array( $dependency ) ) {
			//supports: 'dependency' => array("field" => 'myfield', 'values' => array("val1", 'val2'))
			$dependency_field = $dependency['field'];
			$dependency_value = $dependency['values'];
		} else {
			//supports: 'dependency' => 'myfield'
			$dependency_field = $dependency;
			$dependency_value = '_notempty_';
		}

		if ( ! is_array( $dependency_value ) ) {
			$dependency_value = array( $dependency_value );
		}

		$current_value = $this->get_setting( $dependency_field );

		foreach ( $dependency_value as $val ) {
			if ( $current_value == $val ) {
				return true;
			}

			if ( $val == '_notempty_' && ! rgblank( $current_value ) ) {
				return true;
			}
		}

		return false;
	}

	protected function has_setting_field_type( $type, $fields ) {
        if ( ! empty( $fields ) ) {
			foreach ( $fields as &$section ) {
				foreach ( $section['fields'] as $field ) {
					if ( rgar( $field, 'type' ) == $type ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	protected function add_default_save_button( $sections ) {
		$sections[ count( $sections ) - 1 ]['fields'][] = array( 'type' => 'save' );

		return $sections;
	}

	protected function get_save_success_message( $sections ) {
		$save_button = $this->get_save_button( $sections );

		return isset( $save_button['messages']['success'] ) ? $save_button['messages']['success'] : esc_html__( 'Settings updated', 'gravityforms' );
	}

	protected function get_save_error_message( $sections ) {
		$save_button = $this->get_save_button( $sections );

		return isset( $save_button['messages']['error'] ) ? $save_button['messages']['error'] : esc_html__( 'There was an error while saving your settings', 'gravityforms' );
	}

	protected function get_save_button( $sections ) {
		$fields = $sections[ count( $sections ) - 1 ]['fields'];

		foreach ( $fields as $field ) {
			if ( $field['type'] == 'save' )
				return $field;
		}

		return false;
	}



	//------------- Field Types ------------------------------------------------------

	/***
	 * Renders and initializes a text field based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	protected function settings_text( $field, $echo = true ) {

		$field['type']       = 'text'; //making sure type is set to text
		$field['input_type'] = rgar( $field, 'input_type' ) ? rgar( $field, 'input_type' ) : 'text';
		$attributes          = $this->get_field_attributes( $field );
		$default_value       = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
		$value               = $this->get_setting( $field['name'], $default_value );


		$name    = esc_attr( $field['name'] );
		$tooltip = isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class' ), true ) : '';
		$html    = '';

		$html .= '<input
                    type="' . esc_attr( $field['input_type'] ) . '"
                    name="_gaddon_setting_' . esc_attr( $field['name'] ) . '"
                    value="' . esc_attr( $value ) . '" ' .
		         implode( ' ', $attributes ) .
		         ' />';
		         
		$html .= rgar( $field, 'after_input' );

		$feedback_callback = rgar( $field, 'feedback_callback' );
		if ( is_callable( $feedback_callback ) ) {
			$is_valid = call_user_func_array( $feedback_callback, array( $value, $field ) );
			$icon     = '';
			if ( $is_valid === true ) {
				$icon = 'icon-check fa-check gf_valid'; // check icon
			} elseif ( $is_valid === false ) {
				$icon = 'icon-remove fa-times gf_invalid'; // x icon
			}

			if ( ! empty( $icon ) ) {
				$html .= "&nbsp;&nbsp;<i class=\"fa {$icon}\"></i>";
			}
		}

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/***
	 * Renders and initializes a textarea field based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	protected function settings_textarea( $field, $echo = true ) {
		$field['type'] = 'textarea'; //making sure type is set to textarea
		$attributes    = $this->get_field_attributes( $field );
		$default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
		$value         = $this->get_setting( $field['name'], $default_value );

		$name    = '' . esc_attr( $field['name'] );
		$tooltip = isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class' ), true ) : '';
		$html    = '';

		if ( rgar( $field, 'use_editor' ) && GFCommon::is_wp_version( '3.3' ) ) {
			
			$html .= '<span class="mt-gaddon-editor mt-_gaddon_setting_'. $field['name'] .'"></span>';
			
			ob_start();
			
			wp_editor( $value, '_gaddon_setting_'. $field['name'], array( 'autop' => false, 'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right' ) );
			
			$html .= ob_get_contents();
			ob_end_clean();
			
		} else {
			
			$html .= '<textarea
                    name="_gaddon_setting_' . $name . '" ' .
		         implode( ' ', $attributes ) .
		         '>' .
		         esc_html( $value ) .
		         '</textarea>';
			
		}

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}


	/***
	 * Renders and initializes a hidden field based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	protected function settings_hidden( $field, $echo = true ) {
		$field['type'] = 'hidden'; //making sure type is set to hidden
		$attributes    = $this->get_field_attributes( $field );

		$default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
		$value         = $this->get_setting( $field['name'], $default_value );

		if ( is_array( $value ) ) {
			$value = json_encode( $value );
		}

		$html = '<input
                    type="hidden"
                    name="_gaddon_setting_' . esc_attr( $field['name'] ) . '"
                    value=\'' . esc_attr( $value ) . '\' ' .
		        implode( ' ', $attributes ) .
		        ' />';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/***
	 * Renders and initializes a checkbox field or a collection of checkbox fields based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	protected function settings_checkbox( $field, $echo = true ) {

		$field['type'] = 'checkbox'; //making sure type is set to checkbox

		$field_attributes   = $this->get_field_attributes( $field, array() );
		$horizontal         = rgar( $field, 'horizontal' ) ? ' gaddon-setting-inline' : '';



		$html = '';
		$default_choice_attributes = array( 'onclick' => 'jQuery(this).siblings("input[type=hidden]").val(jQuery(this).prop("checked") ? 1 : 0);' );
		$is_first_choice = true;
		if ( is_array( $field['choices'] ) ) {
			foreach ( $field['choices'] as $choice ) {
				$choice['id']      = sanitize_title( $choice['name'] );
				$choice_attributes = $this->get_choice_attributes( $choice, $field_attributes, $default_choice_attributes );
				$value             = $this->get_setting( $choice['name'], rgar( $choice, 'default_value' ) );
				$tooltip           = isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class' ), true ) : '';

				//displaying error message after first checkbox item
				$error_icon = '';
				if ( $is_first_choice ){
					$error_icon = $this->field_failed_validation( $field ) ? $this->get_error_icon( $field ) : '';
				}

				$html .= $this->checkbox_item( $choice, $horizontal, $choice_attributes, $value, $tooltip, $error_icon );

				$is_first_choice = false;
			}
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}


	/**
	 * Returns the markup for an individual checkbox item give the parameters
	 *
	 * @param $choice           - Choice array with all configured properties
	 * @param $horizontal_class - CSS class to style checkbox items horizontally
	 * @param $attributes       - String containing all the attributes for the input tag.
	 * @param $value            - Currently selection (1 if field has been checked. 0 or null otherwise)
	 * @param $tooltip          - String containing a tooltip for this checkbox item.
	 *
	 * @return string - The markup of an individual checkbox item
	 */
	protected function checkbox_item( $choice, $horizontal_class, $attributes, $value, $tooltip, $error_icon='' ) {
		$hidden_field_value = $value == '1' ? '1' : '0';
		$checkbox_item = '
                    <div id="gaddon-setting-checkbox-choice-' . $choice['id'] . '" class="gaddon-setting-checkbox' . $horizontal_class . '">
                        <input type=hidden name="_gaddon_setting_' . esc_attr( $choice['name'] ) . '" value="' . $hidden_field_value . '" />';

		if ( is_callable( array( $this, "checkbox_input_{$choice['name']}" ) ) ) {
			$markup = call_user_func( array( $this, "checkbox_input_{$choice['name']}" ), $choice, $attributes, $value, $tooltip );
		} else {
			$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );
		}

		$checkbox_item .= $markup . $error_icon . '</div>';

		return $checkbox_item;
	}

	/**
	 * Returns the markup for an individual checkbox input and its associated label
	 *
	 * @param $choice     - Choice array with all configured properties
	 * @param $attributes - String containing all the attributes for the input tag.
	 * @param $value      - Currently selection (1 if field has been checked. 0 or null otherwise)
	 * @param $tooltip    - String containing a tooltip for this checkbox item.
	 *
	 * @return string - The markup of an individual checkbox input and its associated label
	 */
	protected function checkbox_input( $choice, $attributes, $value, $tooltip ) {
		$markup = '<input type = "checkbox" ' .
		          implode( ' ', $attributes ) . ' ' .
		          checked( $value, '1', false ) .
		          ' />
            <label for="' . esc_attr( $choice['id'] ) . '">' . esc_html( $choice['label'] ) . ' ' . $tooltip . '</label>';

		return $markup;
	}


	/***
	 * Renders and initializes a radio field or a collection of radio fields based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string Returns the markup for the radio buttons
	 *
	 */
	protected function settings_radio( $field, $echo = true ) {

		$field['type'] = 'radio'; //making sure type is set to radio

		$selected_value   = $this->get_setting( $field['name'], rgar( $field, 'default_value' ) );
		$field_attributes = $this->get_field_attributes( $field );
		$horizontal       = rgar( $field, 'horizontal' ) ? ' gaddon-setting-inline' : '';
		$html             = '';
		if ( is_array( $field['choices'] ) ) {
			foreach ( $field['choices'] as $i => $choice ) {
				$choice['id']      = $field['name'] . $i;
				$choice_attributes = $this->get_choice_attributes( $choice, $field_attributes );

				$tooltip = isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class' ), true ) : '';

				$radio_value = isset( $choice['value'] ) ? $choice['value'] : $choice['label'];
				$checked     = checked( $selected_value, $radio_value, false );
				$html .= '
                        <div id="gaddon-setting-radio-choice-' . $choice['id'] . '" class="gaddon-setting-radio' . $horizontal . '">
                        <label for="' . esc_attr( $choice['id'] ) . '">
                            <input
                                id = "' . esc_attr( $choice['id'] ) . '"
                                type = "radio" ' .
				         'name="_gaddon_setting_' . esc_attr( $field['name'] ) . '" ' .
				         'value="' . $radio_value . '" ' .
				         implode( ' ', $choice_attributes ) . ' ' .
				         $checked .
				         ' /><span>' . esc_html( $choice['label'] ) . ' ' . $tooltip . '</span>
                        </label>
                        </div>
                    ';
			}
		}

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/***
	 * Renders and initializes a drop down field based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	protected function settings_select( $field, $echo = true ) {

		$field['type'] = 'select'; // making sure type is set to select
		$attributes    = $this->get_field_attributes( $field );
		$value         = $this->get_setting( $field['name'], rgar( $field, 'default_value' ) );
		$name          = '' . esc_attr( $field['name'] );

		$html = sprintf(
			'<select name="%1$s" %2$s>%3$s</select>',
			'_gaddon_setting_' . $name, implode( ' ', $attributes ), $this->get_select_options( $field['choices'], $value )
		);

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Renders and initializes a drop down field with a input field for custom input based on the $field array.
	 * 
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 * 
	 * @return string The HTML for the field
	 */
	protected function settings_select_custom( $field, $echo = true ) {
		
		/* Prepare select field */
		$select_field             = $field;
		$select_field_value       = $this->get_setting( $select_field['name'], rgar( $select_field, 'default_value' ) );
		$select_field['onchange'] = '';
		$select_field['class']    = ( isset( $select_field['class'] ) ) ? $select_field['class'] . 'gaddon-setting-select-custom' : 'gaddon-setting-select-custom';

		/* Prepare input field */
		$input_field          = $field;
		$input_field['name'] .= '_custom';
		$input_field['style'] = 'width:200px;max-width:90%;';
		$input_field_display  = '';

		/* Loop through select choices and make sure option for custom exists */
		$has_gf_custom = false;
		foreach ( $select_field['choices'] as $choice ) {
			if ( rgar( $choice, 'name' ) == 'gf_custom' || rgar( $choice, 'value' ) == 'gf_custom' ) {
				$has_gf_custom = true;
			}
		}
		if ( ! $has_gf_custom ) {
			$select_field['choices'][] = array(
				'label' => esc_html__( 'Add Custom', 'gravityforms' ) .' ' . $select_field['label'],
				'value' => 'gf_custom'
			);
		}
		
		/* If select value is "gf_custom", hide the select field and display the input field. */
		if ( $select_field_value == 'gf_custom' || ( count( $select_field['choices'] ) == 1 && $select_field['choices'][0]['value'] == 'gf_custom' ) ) {
			$select_field['style'] = 'display:none;';
		} else {
			$input_field_display   = ' style="display:none;"';
		}
								
		/* Add select field */
		$html = $this->settings_select( $select_field, false );
		
		/* Add input field */
		$html .= '<div class="gaddon-setting-select-custom-container"'. $input_field_display .'>';
		$html .= count( $select_field['choices'] ) > 1 ? '<a href="#" class="select-custom-reset">Reset</a>' : '';
		$html .= $this->settings_text( $input_field, false );
		$html .= '</div>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
		
	}

	/**
	 * Prepares an HTML string of options for a drop down field.
	 * 
	 * @param array  $choices - Array containing all the options for the drop down field
	 * @param string $selected_value - The value currently selected for the field
	 * 
	 * @return string The HTML for the select options
	 */
	public function get_select_options( $choices, $selected_value ) {

		$options = '';

		foreach ( $choices as $choice ) {

			if ( isset( $choice['choices'] ) ) {

				$options .= sprintf( '<optgroup label="%1$s">%2$s</optgroup>', esc_attr( $choice['label'] ), $this->get_select_options( $choice['choices'], $selected_value ) );

			} else {

				if ( ! isset( $choice['value'] ) ) {
					$choice['value'] = $choice['label'];
				}

				$options .= $this->get_select_option( $choice, $selected_value );

			}
		}

		return $options;
	}

	/**
	 * Prepares an HTML string for a single drop down field option.
	 * 
	 * @access protected
	 * @param array  $choice - Array containing the settings for the drop down option
	 * @param string $selected_value - The value currently selected for the field
	 * 
	 * @return string The HTML for the select choice
	 */
	protected function get_select_option( $choice, $selected_value ) {
		if ( is_array( $selected_value ) ) {
			$selected = in_array( $choice['value'], $selected_value ) ? "selected='selected'" : '';
		} else {
			$selected = selected( $selected_value, $choice['value'], false );
		}

		return sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $choice['value'] ), $selected, $choice['label'] );
	}


	//------------- Field Map Field Type --------------------------

	public function settings_field_map( $field, $echo = true ) {

		$html      = '';
		$field_map = rgar( $field, 'field_map' );

		if ( empty( $field_map ) ) {
			return $html;
		}

		$form_id = rgget( 'id' );


		$html .= '<table class="settings-field-map-table" cellspacing="0" cellpadding="0">' .
						$this->field_map_table_header() .
                	'<tbody>';

		foreach ( $field['field_map'] as $child_field ) {

			if ( ! $this->setting_dependency_met( rgar( $child_field, 'dependency' ) ) ) {
				continue;
			}

			$child_field['name'] = $this->get_mapped_field_name( $field, $child_field['name'] );
			$required            = rgar( $child_field, 'required' ) ? $this->get_required_indicator( $child_field ) : '';

			$html .= '
                <tr>
                    <td>
                        <label for="' . $child_field['name'] . '">' . $child_field['label'] . ' ' . $required . '<label>
                    </td>
                    <td>' .
			         $this->settings_field_map_select( $child_field, $form_id ) .
			         '</td>
            </tr>';
		}

		$html .= '
                </tbody>
            </table>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function field_map_table_header() {
		return '<thead>
					<tr>
						<th>' . $this->field_map_title() . '</th>
						<th>' . esc_html__( 'Form Field', 'gravityforms' ) . '</th>
					</tr>
				</thead>';
	}

	public function settings_field_map_select( $field, $form_id ) {

		$field_type          = ( isset( $field['field_type'] ) ) ? $field['field_type'] : null;
		$exclude_field_types = ( isset( $field['exclude_field_types'] ) ? $field['exclude_field_types'] : null );

		$field['choices'] = $this->get_field_map_choices( $form_id, $field_type, $exclude_field_types );

		if ( empty( $field['choices'] ) || ( count( $field['choices'] ) == 1 && rgblank( $field['choices'][0]['value'] ) ) ) {
			
			if ( ( ! is_array( $field_type ) && ! rgblank( $field_type ) ) || ( is_array( $field_type ) && count( $field_type ) == 1 ) ) {
			
				$type = is_array( $field_type ) ? $field_type[0] : $field_type;
				$type = ucfirst( GF_Fields::get( $type )->get_form_editor_field_title() );
				
				return sprintf( __( 'Please add a %s field to your form.', 'gravityforms' ), $type );
				
			}

		}
		
		return $this->settings_select( $field, false );

	}

	protected function field_map_title() {
		return esc_html__( 'Field', 'gravityforms' );
	}

	public static function get_field_map_choices( $form_id, $field_type = null, $exclude_field_types = null ) {

		$form = RGFormsModel::get_form_meta( $form_id );

		$fields = array();

		// Setup first choice 
		if ( rgblank( $field_type ) || ( is_array( $field_type ) && count( $field_type ) > 1 ) ) {
			
			$first_choice_label = __( 'Select a Field', 'gravityforms' );
			
		} else {
			
			$type = is_array( $field_type ) ? $field_type[0] : $field_type;
			$type = ucfirst( GF_Fields::get( $type )->get_form_editor_field_title() );
			
			$first_choice_label = sprintf( __( 'Select a %s Field', 'gravityforms' ), $type );
			
		}

		$fields[] = array( 'value' => '', 'label' => $first_choice_label );

		// Adding default fields
		if ( is_null( $field_type ) ) {
			$fields[] = array( 'value' => 'id', 'label' => esc_html__( 'Entry ID', 'gravityforms' ) );
			$fields[] = array( 'value' => 'date_created', 'label' => esc_html__( 'Entry Date', 'gravityforms' ) );
			$fields[] = array( 'value' => 'ip', 'label' => esc_html__( 'User IP', 'gravityforms' ) );
			$fields[] = array( 'value' => 'source_url', 'label' => esc_html__( 'Source Url', 'gravityforms' ) );
			$fields[] = array( 'value' => 'form_title', 'label' => esc_html__( 'Form Title', 'gravityforms' ) );
		}

		// Populate entry meta
		$entry_meta = GFFormsModel::get_entry_meta( $form['id'] );
		foreach ( $entry_meta as $meta_key => $meta ) {
			$fields[] = array( 'value' => $meta_key, 'label' => rgars( $entry_meta, "{$meta_key}/label" ) );
		}

		// Populate form fields
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$input_type = $field->get_input_type();
				$inputs     = $field->get_entry_inputs();
				$field_is_valid_type = ( empty( $field_type ) || ( is_array( $field_type ) && in_array( $input_type, $field_type ) ) || ( ! empty( $field_type ) && $input_type == $field_type ) );

				if ( is_null( $exclude_field_types ) ) {
					$exclude_field = false;
				} elseif ( is_array( $exclude_field_types ) ) {
					if ( in_array( $input_type, $exclude_field_types ) ) {
						$exclude_field = true;
					} else {
						$exclude_field = false;
					}
				} else {
					//not array, so should be single string
					if ( $input_type == $exclude_field_types ) {
						$exclude_field = true;
					} else {
						$exclude_field = false;
					}
				}

				if ( is_array( $inputs ) && $field_is_valid_type && ! $exclude_field ) {
					//If this is an address field, add full name to the list
					if ( $input_type == 'address' ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
						);
					}
					//If this is a name field, add full name to the list
					if ( $input_type == 'name' ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
						);
					}
					//If this is a checkbox field, add to the list
					if ( $input_type == 'checkbox' ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Selected', 'gravityforms' ) . ')'
						);
					}

					foreach ( $inputs as $input ) {
						$fields[] = array(
							'value' => $input['id'],
							'label' => GFCommon::get_label( $field, $input['id'] )
						);
					}
				} elseif ( $input_type == 'list' && $field->enableColumns && $field_is_valid_type && ! $exclude_field ) {
					$fields[] = array(
						'value' => $field->id,
						'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
					);
					$col_index = 0;
					foreach ( $field->choices as $column ) {
						$fields[] = array(
							'value' => $field->id . '.' . $col_index,
							'label' => GFCommon::get_label( $field ) . ' (' . esc_html( rgar( $column, 'text' ) ) . ')',
						);
						$col_index ++;
					}
				} elseif ( ! rgar( $field, 'displayOnly' ) && $field_is_valid_type && ! $exclude_field ) {
					$fields[] = array( 'value' => $field->id, 'label' => GFCommon::get_label( $field ) );
				}
			}
		}

 		return $fields;
	}

	public function get_mapped_field_name( $parent_field, $field_name ) {
		return "{$parent_field['name']}_{$field_name}";
	}

	public static function get_field_map_fields( $feed, $field_name ) {

		$fields = array();
		$prefix = "{$field_name}_";

		foreach ( $feed['meta'] as $name => $value ) {
			if ( strpos( $name, $prefix ) === 0 ) {
				$name          = str_replace( $prefix, '', $name );
				$fields[ $name ] = $value;
			}
		}

		return $fields;
	}

	public static function get_dynamic_field_map_fields( $feed, $field_name ) {

		$fields = array();
		$dynamic_fields = $feed['meta'][$field_name];

		if ( ! empty( $dynamic_fields ) ) {
			
			foreach ( $dynamic_fields as $dynamic_field ) {
			
				$field_key = ( $dynamic_field['key'] == 'gf_custom' ) ? $dynamic_field['custom_key'] : $dynamic_field['key'];
				$fields[$field_key] = $dynamic_field['value'];
				
			}
			
		}

		return $fields;
	}


	//----------------------------------------------------------------


	public function settings_dynamic_field_map( $field, $echo = true ) {

		$html = '';
		$value_field = $key_field = $custom_key_field = $field;
		$form = $this->get_current_form();

		/* Setup key field drop down */
		$key_field['choices']  = ( isset( $field['field_map'] ) ) ? $field['field_map'] : null;
		$key_field['name']    .= '_key';
		$key_field['class']    = 'key key_{i}';
		$key_field['style']    = 'width:200px;';

		/* Setup custom key text field */
		$custom_key_field['name']  .= '_custom_key_{i}';
		$custom_key_field['class']  = 'custom_key custom_key_{i}';
		$custom_key_field['style']  = 'width:200px;max-width:90%;';
		$custom_key_field['value']  = '{custom_key}';

		/* Setup value drop down */
		$value_field['name']  .= '_custom_value';
		$value_field['class']  = 'value value_{i}';
		
		/* Remove unneeded values */
		unset( $field['field_map'] );
		unset( $value_field['field_map'] );
		unset( $key_field['field_map'] );
		unset( $custom_key_field['field_map'] );

		//add on errors set when validation fails
		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		/* Build key cell based on available field map choices */
		if ( empty( $key_field['choices'] ) ) {
			
			/* Set key field value to "gf_custom" so custom key is used. */
			$key_field['value'] = 'gf_custom';
			
			/* Build HTML string */
			$key_field_html = '<td>' .
                $this->settings_hidden( $key_field, false ) . '
                <div class="custom-key-container">
                    ' . $this->settings_text( $custom_key_field, false ) . '
				</div>
            </td>';			
			
		} else {
			
			/* Ensure field map array has a custom key option. */
			$has_gf_custom = false;
			foreach ( $key_field['choices'] as $choice ) {
				if ( rgar( $choice, 'name' ) == 'gf_custom' || rgar( $choice, 'value' ) == 'gf_custom' ) {
					$has_gf_custom = true;
				}
				if ( rgar( $choice, 'choices' ) ) {
					foreach ( $choice['choices'] as $subchoice ) {
						if ( rgar( $subchoice, 'name' ) == 'gf_custom' || rgar( $subchoice, 'value' ) == 'gf_custom' ) {
							$has_gf_custom = true;
						}
					}					
				}
			}
			if ( ! $has_gf_custom && ! rgar( $field, 'disable_custom' ) ) {
				$key_field['choices'][] = array(
					'label' => esc_html__( 'Add Custom Key', 'gravityforms' ),
					'value' => 'gf_custom'
				);
			}
			
			/* Build HTML string */
			$key_field_html = '<th>' .
                $this->settings_select( $key_field, false ) . '
                <div class="custom-key-container">
                    <a href="#" class="custom-key-reset">Reset</a>' .
                    $this->settings_text( $custom_key_field, false ) . '
				</div>
            </th>';
			
		}

		$html .= '
            <table class="settings-field-map-table" cellspacing="0" cellpadding="0">
                <tbody class="repeater">
	                <tr>
	                    '. $key_field_html .'
	                    <td>' .
			                $this->settings_field_map_select( $value_field, $form['id'] ) . '
						</td>
						<td>
							{buttons}
						</td>
	                </tr>
                </tbody>
            </table>';

		$html .= $this->settings_hidden( $field, false );

		$limit = empty( $field['limit'] ) ? 0 : $field['limit'];

		$html .= "
			<script type=\"text/javascript\">
			
				var dynamicFieldMap". esc_attr( $field['name'] ) ." = new gfieldmap({
					
					'baseURL':      '". GFCommon::get_base_url() ."',
					'fieldId':      '". esc_attr( $field['name'] ) ."',
					'fieldName':    '". $field['name'] ."',
					'keyFieldName': '". $key_field['name'] ."',
					'limit':        '". $limit . "'
										
				});
			
			</script>";

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Renders and initializes a drop down field based on the $field array whose choices are populated by the form's fields.
	 * 
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	public function settings_field_select( $field, $echo = true ) {

		$args = is_array( rgar( $field, 'args' ) ) ? rgar( $field, 'args' ) : array( rgar( $field, 'args' ) );

		$args = wp_parse_args(
			$args, array(
				'append_choices'       => array(),
				'disable_first_choice' => false,
			)
		);

		$field['choices'] = array();

		if ( ! $args['disable_first_choice'] ) {

			// Setup first choice 
			if ( empty( $args['input_types'] ) || ( is_array( $args['input_types'] ) && count( $args['input_types'] ) > 1 ) ) {
				
				$first_choice_label = __( 'Select a Field', 'gravityforms' );
				
			} else {
				
				$type = is_array( $args['input_types'] ) ? $args['input_types'][0] : $args['input_types'];
				$type = ucfirst( GF_Fields::get( $type )->get_form_editor_field_title() );
				
				$first_choice_label = sprintf( __( 'Select a %s Field', 'gravityforms' ), $type );
				
			}

			$field['choices'][] = array( 'value' => '', 'label' => $first_choice_label );
			
		}

		$field['choices'] = array_merge( $field['choices'], $this->get_form_fields_as_choices( $this->get_current_form(), $args ) );

		if ( ! empty( $args['append_choices'] ) ) {
			$field['choices'] = array_merge( $field['choices'], $args['append_choices'] );
		}

		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Retrieve an array of form fields formatted for select, radio and checkbox settings fields.
	 * 
	 * @access public
	 * @param array $form - The form object
	 * @param array $args - Additional settings to check for (field and input types to include, callback for applicable input type)
	 *
	 * @return array The array of formatted form fields
	 */
	public function get_form_fields_as_choices( $form, $args = array() ) {

		$fields = array();

		if ( ! is_array( $form['fields'] ) ) {
			return $fields;
		}

		$args = wp_parse_args(
			$args, array(
				'field_types'    => array(),
				'input_types'    => array(),
			    'callback'       => false
			)
		);

		foreach ( $form['fields'] as $field ) {

			$input_type               = GFFormsModel::get_input_type( $field );
			$is_applicable_input_type = empty( $args['input_types'] ) || in_array( $input_type, $args['input_types'] );

			if ( is_callable( $args['callback'] ) ) {
				$is_applicable_input_type = call_user_func( $args['callback'], $is_applicable_input_type, $field, $form );
			}

			if ( ! $is_applicable_input_type ) {
				continue;
			}

			if ( ! empty( $args['property'] ) && ( ! isset( $field->$args['property'] ) || $field->$args['property'] != $args['property_value'] ) ) {
				continue;
			}

			$inputs = $field->get_entry_inputs();
			if ( is_array( $inputs ) ) {
				// if this is an address field, add full name to the list
				if ( $input_type == 'address' ) {
					$fields[] = array(
						'value' => $field->id,
						'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
					);
				}
				// if this is a name field, add full name to the list
				if ( $input_type == 'name' ) {
					$fields[] = array(
						'value' => $field->id,
						'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
					);
				}
				// if this is a checkbox field, add to the list
				if ( $input_type == 'checkbox' ) {
					$fields[] = array(
						'value' => $field->id,
						'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Selected', 'gravityforms' ) . ')'
					);
				}

				foreach ( $inputs as $input ) {
					$fields[] = array(
						'value' => $input['id'],
						'label' => GFCommon::get_label( $field, $input['id'] )
					);
				}
			} elseif ( $input_type == 'list' && $field->enableColumns ) {
				$fields[] = array(
					'value' => $field->id,
					'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
				);
				$col_index = 0;
				foreach ( $field->choices as $column ) {
					$fields[] = array(
						'value' => $field->id . '.' . $col_index,
						'label' => GFCommon::get_label( $field ) . ' (' . rgar( $column, 'text' ) . ')',
					);
					$col_index ++;
				}
			} elseif ( ! rgar( $field, 'displayOnly' ) ) {
				$fields[] = array( 'value' => $field->id, 'label' => GFCommon::get_label( $field ) );
			} else {
				$fields[] = array(
					'value' => $field->id,
					'label' => GFCommon::get_label( $field )
				);
			}
		}

		return $fields;
	}

	/**
	 * Renders and initializes a checkbox field that displays a select field when checked based on the $field array.
	 * 
	 * @access public
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	public function settings_checkbox_and_select( $field, $echo = true ) {

		// prepare checkbox

		$checkbox_input = rgars( $field, 'checkbox' );

		$checkbox_field = array(
			'type'       => 'checkbox',
			'name'       => $field['name'] . 'Enable',
			'label'      => esc_html__( 'Enable', 'gravityforms' ),
			'horizontal' => true,
			'value'      => '1',
			'choices'    => false,
			'tooltip'    => false
		);

		$checkbox_field = wp_parse_args( $checkbox_input, $checkbox_field );

		// prepare select

		$select_input = rgars( $field, 'select' );
		$is_enabled   = $this->get_setting( $checkbox_field['name'] );

		$select_field = array(
			'name'    => $field['name'] . 'Value',
			'type'    => 'select',
			'class'   => '',
			'tooltip' => false
		);

		$select_field['class'] .= ' ' . $select_field['name'];

		$select_field = wp_parse_args( $select_input, $select_field );

		// a little more with the checkbox
		if( empty( $checkbox_field['choices'] ) ) {
			$checkbox_field['choices'] = array(
				array(
					'name'          => $checkbox_field['name'],
					'label'         => $checkbox_field['label'],
					'onchange'      => sprintf( "( function( $, elem ) {
						$( elem ).parents( 'td' ).css( 'position', 'relative' );
						if( $( elem ).prop( 'checked' ) ) {
							$( '%1\$s' ).fadeIn();
						} else {
							$( '%1\$s' ).fadeOut();
						}
					} )( jQuery, this );",
					"#{$select_field['name']}Span" )
				)
			);
		}

		// get markup

		$html = sprintf(
			'%s <span id="%s" class="%s">%s %s</span>',
			$this->settings_checkbox( $checkbox_field, false ),
			$select_field['name'] . 'Span',
			$is_enabled ? '' : 'hidden',
			$this->settings_select( $select_field, false ),
			$select_field['tooltip'] ? gform_tooltip( $select_field['tooltip'], rgar( $select_field, 'tooltip_class' ) . ' tooltip ' . $select_field['name'], true ) : ''
		);

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/***
	 * Renders the save button for settings pages
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML
	 */
	protected function settings_save( $field, $echo = true ) {

		$field['type']  = 'submit';
		$field['name']  = 'gform-settings-save';
		$field['class'] = 'button-primary gfbutton';

		if ( ! rgar( $field, 'value' ) ) {
			$field['value'] = esc_html__( 'Update Settings', 'gravityforms' );
		}

		$attributes = $this->get_field_attributes( $field );

		$html = '<input
					type="' . esc_attr( $field['type'] ) . '"
					name="' . esc_attr( $field['name'] ) . '"
					value="' . esc_attr( $field['value'] ) . '" ' . implode( ' ', $attributes ) . ' />';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Helper to create a simple conditional logic set of fields. It creates one row of conditional logic with Field/Operator/Value inputs.
	 *
	 * @param mixed $setting_name_root - The root name to be used for inputs. It will be used as a prefix to the inputs that make up the conditional logic fields.
	 *
	 * @return string The HTML
	 */
	protected function simple_condition( $setting_name_root ) {

		$conditional_fields = $this->get_conditional_logic_fields();

		$value_input = esc_js( '_gaddon_setting_' . esc_attr( $setting_name_root ) . '_value' );
		$object_type = esc_js( "simple_condition_{$setting_name_root}" );

		$str = $this->settings_select( array(
			'name' => "{$setting_name_root}_field_id",
			'type' => 'select',
			'choices' => $conditional_fields,
			'class' => 'optin_select',
			'onchange' => "jQuery('#" . esc_js( $setting_name_root ) . "_container').html(GetRuleValues('{$object_type}', 0, jQuery(this).val(), '', '{$value_input}'));"
		), false );

		$str .= $this->settings_select( array(
			'name' => "{$setting_name_root}_operator",
			'type' => 'select',
			'onchange' => "SetRuleProperty('{$object_type}', 0, 'operator', jQuery(this).val()); jQuery('#" . esc_js( $setting_name_root ) . "_container').html(GetRuleValues('{$object_type}', 0, jQuery('#{$setting_name_root}_field_id').val(), '', '{$value_input}'));",
			'choices' => array(
				array(
					'value' => 'is',
					'label' => esc_html__( 'is', 'gravityforms' ),
				),
				array(
					'value' => 'isnot',
					'label' => esc_html__( 'is not', 'gravityforms' ),
				),
				array(
					'value' => '>',
					'label' => esc_html__( 'greater than', 'gravityforms' ),
				),
				array(
					'value' => '<',
					'label' => esc_html__( 'less than', 'gravityforms' ),
				),
				array(
					'value' => 'contains',
					'label' => esc_html__( 'contains', 'gravityforms' ),
				),
				array(
					'value' => 'starts_with',
					'label' => esc_html__( 'starts with', 'gravityforms' ),
				),
				array(
					'value' => 'ends_with',
					'label' => esc_html__( 'ends with', 'gravityforms' ),
				),
			),

		), false );

		$str .= sprintf( "<span id='%s_container'></span>", esc_attr( $setting_name_root ) );

		$field_id = $this->get_setting( "{$setting_name_root}_field_id" );

		$value    = $this->get_setting( "{$setting_name_root}_value" );
		$operator = $this->get_setting( "{$setting_name_root}_operator" );
		if ( empty( $operator ) ){
			$operator = 'is';
		}

		$field_id_attribute = ! empty( $field_id ) ? $field_id : 'jQuery("#' . esc_attr( $setting_name_root ) . '_field_id").val()';

		$str .= "<script type='text/javascript'>
			var " . esc_attr( $setting_name_root ) . "_object = {'conditionalLogic':{'rules':[{'fieldId':'{$field_id}','operator':'{$operator}','value':'" . esc_attr( $value ) . "'}]}};

			jQuery(document).ready(
				function(){
					gform.addFilter( 'gform_conditional_object', 'SimpleConditionObject' );

					jQuery('#" . esc_attr( $setting_name_root ) . "_container').html(
											GetRuleValues('{$object_type}', 0, {$field_id_attribute}, '" . esc_attr( $value ) . "', '_gaddon_setting_" . esc_attr( $setting_name_root ) . "_value'));

					}
			);
			</script>";

		return $str;
	}

	/**
	 * Parses the properties of the $field meta array and returns a set of HTML attributes to be added to the HTML element.
	 *
	 * @param array $field   - current field meta to be parsed.
	 * @param array $default - default set of properties. Will be appended to the properties specified in the $field array
	 *
	 * @return array - resulting HTML attributes ready to be included in the HTML element.
	 */
	protected function get_field_attributes( $field, $default = array() ) {

		/**
		 * Each nonstandard property will be extracted from the $props array so it is not auto-output in the field HTML
		 *
		 * @param array $field The current field meta to be parsed
		 */
		$no_output_props = apply_filters(
			'gaddon_no_output_field_properties',
			array(
				'default_value', 'label', 'choices', 'feedback_callback', 'checked', 'checkbox_label', 'value', 'type',
				'validation_callback', 'required', 'hidden', 'tooltip', 'dependency', 'messages', 'name', 'args', 'exclude_field_types',
				'field_type', 'after_input', 'input_type'
			), $field
		);

		$default_props = array(
			'class'         => '', // will default to gaddon-setting
			'default_value' => '', // default value that should be selected or entered for the field
		);

		// Property switch case
		switch ( $field['type'] ) {
			case 'select':
				$default_props['choices'] = array();
				break;
			case 'checkbox':
				$default_props['checked']        = false;
				$default_props['checkbox_label'] = '';
				$default_props['choices']        = array();
				break;
			case 'text':
			default:
				break;
		}

		$props          = wp_parse_args( $field, $default_props );
		$props['id']    = rgempty( 'id', $props ) ? rgar( $props, 'name' ) : rgar( $props, 'id' );
		$props['class'] = trim( "{$props['class']} gaddon-setting gaddon-{$props['type']}" );

		// extract no-output properties from $props array so they are not auto-output in the field HTML
		foreach ( $no_output_props as $prop ) {
			if ( isset( $props[ $prop ] ) ) {
				${$prop} = $props[ $prop ];
				unset( $props[ $prop ] );
			}
		}

		//adding default attributes
		foreach ( $default as $key => $value ) {
			if ( isset( $props[ $key ] ) ) {
				$props[ $key ] = $value . $props[ $key ];
			} else {
				$props[ $key ] = $value;
			}
		}

		// get an array of property strings, example: name='myFieldName'
		$prop_strings = array();
		foreach ( $props as $prop => $value ) {
			$prop_strings[ $prop ] = "{$prop}='" . esc_attr( $value ) . "'";
		}

		return $prop_strings;
	}

	/**
	 * Parses the properties of the $choice meta array and returns a set of HTML attributes to be added to the HTML element.
	 *
	 * @param array $choice           - current choice meta to be parsed.
	 * @param array $field_attributes - current field's attributes.
	 *
	 * @return array - resulting HTML attributes ready to be included in the HTML element.
	 */
	protected function get_choice_attributes( $choice, $field_attributes, $default_choice_attributes = array() ) {
		$choice_attributes = $field_attributes;
		foreach ( $choice as $prop => $val ) {
			$no_output_choice_attributes = array(
				'default_value', 'label', 'checked', 'value', 'type',
				'validation_callback', 'required', 'tooltip',
			);
			if ( in_array( $prop, $no_output_choice_attributes ) || is_array( $val ) ) {
				unset( $choice_attributes[ $prop ] );
			} else {
				$choice_attributes[ $prop ] = "{$prop}='" . esc_attr( $val ) . "'";
			}
		}

		//Adding default attributes. Either creating a new attribute or pre-pending to an existing one.
		foreach ( $default_choice_attributes as $default_attr_name => $default_attr_value ) {

			if ( isset( $choice_attributes[ $default_attr_name ] ) ) {
				$choice_attributes[ $default_attr_name ] = $this->prepend_attribute( $default_attr_name, $default_attr_value, $choice_attributes[ $default_attr_name ] );
			}
			else {
				$choice_attributes[ $default_attr_name ] = "{$default_attr_name}='" . esc_attr( $default_attr_value ) . "'";
			}
		}

		return $choice_attributes;
	}

	/***
	 * @param $name - The name of the attribute to be added
	 * @param $attribute - The attribute value to be added
	 * @param $current_attribute - The full string containing the current attribute value
	 * @return mixed - The new attribute string with the new value added to the beginning of the list
	 */
	protected function prepend_attribute( $name, $attribute, $current_attribute ) {
		return str_replace( "{$name}='", "{$name}='{$attribute}", $current_attribute );
	}

	/**
	 * Validates settings fields.
	 * Validates that all fields are valid. Fields can be invalid when they are blank and marked as required or if it fails a custom validation check.
	 * To specify a custom validation, use the 'validation_callback' field meta property and implement the validation function with the custom logic.
	 *
	 * @param $fields   - A list of all fields from the field meta configuration
	 * @param $settings - A list of submitted settings values
	 *
	 * @return bool - Returns true if all fields have passed validation, and false otherwise.
	 */
	protected function validate_settings( $fields, $settings ) {

		foreach ( $fields as $section ) {

			if ( ! $this->setting_dependency_met( rgar( $section, 'dependency' ) ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {

				if ( ! $this->setting_dependency_met( rgar( $field, 'dependency' ) ) ) {
					continue;
				}

				$field_setting = rgar( $settings, rgar( $field, 'name' ) );

				if ( is_callable( rgar( $field, 'validation_callback' ) ) ) {
					call_user_func( rgar( $field, 'validation_callback' ), $field, $field_setting );
					continue;
				}

				switch ( $field['type'] ) {
					case 'field_map' :

						$this->validate_field_map_settings( $field, $settings );

						break;

					case 'checkbox' :

						$this->validate_checkbox_settings( $field, $settings );

						break;

					case 'select_custom' :

						$this->validate_select_custom_settings( $field, $settings );

						break;


					default :

						if ( rgar( $field, 'required' ) && rgblank( $field_setting ) ) {
							$this->set_field_error( $field, rgar( $field, 'error_message' ) );
						}

						break;
				}
			}
		}

		$field_errors = $this->get_field_errors();
		$is_valid     = empty( $field_errors );

		return $is_valid;
	}

	protected function validate_checkbox_settings( $field, $settings ) {

		if ( ! rgar( $field, 'required' ) ) {
			return;
		}

		if ( ! is_array( rgar( $field, 'choices' ) ) ) {
			return;
		}

		foreach ( $field['choices'] as $choice ) {
			$choice_setting = rgar( $settings, rgar( $choice, 'name' ) );
			if ( ! empty( $choice_setting ) ) {
				return;
			}
		}

		$this->set_field_error( $field, rgar( $field, 'error_message' ) );
	}

	protected function validate_select_custom_settings( $field, $settings ) {

		if ( ! rgar( $field, 'required' ) ) {
			return;
		}

		if ( ! is_array( rgar( $field, 'choices' ) ) ) {
			return;
		}
		
		$select_value = rgar( $settings, $field['name'] );
		$custom_value = rgar( $settings, $field['name'] . '_custom' );

		if ( rgar( $field, 'required' ) && rgblank( $select_value ) ) {
			$this->set_field_error( $field );
		} else if ( rgar( $field, 'required' ) && $select_value == 'gf_custom' && rgblank( $custom_value ) ) {
			$custom_field          = $field;
			$custom_field['name'] .= '_custom';
			$this->set_field_error( $custom_field );
		}
		
	}

	protected function validate_field_map_settings( $field, $settings ) {

		$field_map = rgar( $field, 'field_map' );

		if ( empty( $field_map ) ) {
			return;
		}

		foreach ( $field_map as $child_field ) {

			if ( ! $this->setting_dependency_met( rgar( $child_field, 'dependency' ) ) ) {
				continue;
			}

			$child_field['name'] = $this->get_mapped_field_name( $field, $child_field['name'] );
			$setting_value       = rgar( $settings, $child_field['name'] );

			if ( rgar( $child_field, 'required' ) && rgblank( $setting_value ) ) {
				$this->set_field_error( $child_field );
			} elseif ( rgar( $child_field, 'validation_callback' ) && is_callable( rgar( $child_field, 'validation_callback' ) ) ) {
				call_user_func( rgar( $child_field, 'validation_callback' ), $child_field, $field );
			}
		}

	}

	/**
	 * Sets the validation error message
	 * Sets the error message to be displayed when a field fails validation.
	 * When implementing a custom validation callback function, use this function to specify the error message to be displayed.
	 *
	 * @param array  $field         - The current field meta
	 * @param string $error_message - The error message to be displayed
	 */
	protected function set_field_error( $field, $error_message = '' ) {

		// set default error message if none passed
		if ( ! $error_message ) {
			$error_message = esc_html__( 'This field is required.', 'gravityforms' );
		}

		$this->_setting_field_errors[ $field['name'] ] = $error_message;
	}

	/**
	 * Gets the validation errors for a field.
	 * Returns validation errors associated with the specified field or a list of all validation messages (if a field isn't specified)
	 *
	 * @param array|boolean $field - Optional. The field meta. When specified, errors for this field will be returned
	 *
	 * @return mixed - If a field is specified, a string containing the error message will be returned. Otherwise, an array of all errors will be returned
	 */
	protected function get_field_errors( $field = false ) {

		if ( ! $field ) {
			return $this->_setting_field_errors;
		}

		return isset( $this->_setting_field_errors[ $field['name'] ] ) ? $this->_setting_field_errors[ $field['name'] ] : array();
	}

	/**
	 * Gets the invalid field icon
	 * Returns the markup for an alert icon to indicate and highlight invalid fields.
	 *
	 * @param array $field - The field meta.
	 *
	 * @return string - The full markup for the icon
	 */
	protected function get_error_icon( $field ) {

		$error = $this->get_field_errors( $field );

		return '<span
            class="gf_tooltip tooltip"
            title="<h6>' . esc_html__( 'Validation Error', 'gravityforms' ) . '</h6>' . $error . '"
            style="display:inline-block;position:relative;right:-3px;top:1px;font-size:14px;">
                <i class="fa fa-exclamation-circle icon-exclamation-sign gf_invalid"></i>
            </span>';
	}

	/**
	 * Gets the required indicator
	 * Gets the markup of the required indicator symbol to highlight fields that are required
	 *
	 * @param $field - The field meta.
	 *
	 * @return string - Returns markup of the required indicator symbol
	 */
	protected function get_required_indicator( $field ) {
		return '<span class="required">*</span>';
	}

	/**
	 * Checks if the specified field failed validation
	 *
	 * @param $field - The field meta to be checked
	 *
	 * @return bool|mixed - Returns a validation error string if the field has failed validation. Otherwise returns false
	 */
	protected function field_failed_validation( $field ) {
		$field_error = $this->get_field_errors( $field );

		return ! empty( $field_error ) ? $field_error : false;
	}

	/**
	 * Filter settings fields.
	 * Runs through each field and applies the 'save_callback', if set, before saving the settings.
	 * To specify a custom save filter, use the 'save_callback' field meta property and implement the save filter function with the custom logic.
	 *
	 * @param $fields   - A list of all fields from the field meta configuration
	 * @param $settings - A list of submitted settings values
	 *
	 * @return $settings - The updated settings values.
	 */
	public function filter_settings( $fields, $settings ) {

		foreach ( $fields as $section ) {

			if ( ! $this->setting_dependency_met( rgar( $section, 'dependency' ) ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {

				if ( ! $this->setting_dependency_met( rgar( $field, 'dependency' ) ) ) {
					continue;
				}

				$field_setting = rgar( $settings, rgar( $field, 'name' ) );

				if ( is_callable( rgar( $field, 'save_callback' ) ) ) {
					$settings[ $field['name'] ] = call_user_func( rgar( $field, 'save_callback' ), $field, $field_setting );
					continue;
				}

			}
		}

		return $settings;
	}

	protected function add_field_before( $name, $fields, $settings ) {
		return $this->add_field( $name, $fields, $settings, 'before' );
	}

	protected function add_field_after( $name, $fields, $settings ) {
		return $this->add_field( $name, $fields, $settings, 'after' );
	}

	protected function add_field( $name, $fields, $settings, $pos ) {

		if ( rgar( $fields, 'name' ) ) {
			$fields = array( $fields );
		}

		$pos_mod = $pos == 'before' ? 0 : 1;

		foreach ( $settings as &$section ) {
			for ( $i = 0; $i < count( $section['fields'] ); $i ++ ) {
				if ( $section['fields'][ $i ]['name'] == $name ) {
					array_splice( $section['fields'], $i + $pos_mod, 0, $fields );
					break 2;
				}
			}
		}

		return $settings;
	}

	protected function remove_field( $name, $settings ) {

		foreach ( $settings as &$section ) {
			for ( $i = 0; $i < count( $section['fields'] ); $i ++ ) {
				if ( $section['fields'][ $i ]['name'] == $name ) {
					array_splice( $section['fields'], $i, 1 );
					break 2;
				}
			}
		}

		return $settings;
	}

	protected function replace_field( $name, $fields, $settings ) {

		if ( rgar( $fields, 'name' ) ) {
			$fields = array( $fields );
		}

		foreach ( $settings as &$section ) {
			for ( $i = 0; $i < count( $section['fields'] ); $i ++ ) {
				if ( $section['fields'][ $i ]['name'] == $name ) {
					array_splice( $section['fields'], $i, 1, $fields );
					break 2;
				}
			}
		}

		return $settings;

	}

	protected function get_field( $name, $settings ) {
		foreach ( $settings as $section ) {
			for ( $i = 0; $i < count( $section['fields'] ); $i ++ ) {
				if ( $section['fields'][ $i ]['name'] == $name ) {
					return $section['fields'][ $i ];
				}
			}
		}

		return false;
	}

	public function build_choices( $key_value_pairs ) {

		$choices = array();

		if ( ! is_array( $key_value_pairs ) ) {
			return $choices;
		}

		$first_key  = key( $key_value_pairs );
		$is_numeric = is_int( $first_key ) && $first_key === 0;

		foreach ( $key_value_pairs as $value => $label ) {
			if ( $is_numeric ) {
				$value = $label;
			}
			$choices[] = array( 'value' => $value, 'label' => $label );
		}

		return $choices;
	}

	//--------------  Form settings  ---------------------------------------------------

	/**
	 * Initializes form settings page
	 * Hooks up the required scripts and actions for the Form Settings page
	 */
	protected function form_settings_init() {
		$view    = rgget( 'view' );
		$subview = rgget( 'subview' );
		if ( $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			add_action( 'gform_form_settings_menu', array( $this, 'add_form_settings_menu' ), 10, 2 );
		}

		if ( rgget( 'page' ) == 'gf_edit_forms' && $view == 'settings' && $subview == $this->_slug && $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			require_once( GFCommon::get_base_path() . '/tooltips.php' );
			add_action( 'gform_form_settings_page_' . $this->_slug, array( $this, 'form_settings_page' ) );
		}
	}

	/**
	 * Initializes plugin settings page
	 * Hooks up the required scripts and actions for the Plugin Settings page
	 */
	protected function plugin_page_init() {

		if ( $this->current_user_can_any( $this->_capabilities_plugin_page ) ) {
			//creates the subnav left menu
			add_filter( 'gform_addon_navigation', array( $this, 'create_plugin_page_menu' ) );
		}

	}

	/**
	 * Creates plugin page menu item
	 * Target of gform_addon_navigation filter. Creates a menu item in the left nav, linking to the plugin page
	 *
	 * @param $menus - Current list of menu items
	 *
	 * @return array - Returns a new list of menu items
	 */
	public function create_plugin_page_menu( $menus ) {

		$menus[] = array( 'name' => $this->_slug, 'label' => $this->get_short_title(), 'callback' => array( $this, 'plugin_page_container' ), 'permission' => $this->_capabilities_plugin_page );

		return $menus;
	}

	/**
	 * Renders the form settings page.
	 *
	 * Not intended to be overridden or called directly by Add-Ons.
	 * Sets up the form settings page.
	 *
	 * @ignore
	 */
	public function form_settings_page() {

		GFFormSettings::page_header( $this->_title );
		?>
		<div class="gform_panel gform_panel_form_settings" id="form_settings">

			<?php
			$form = $this->get_current_form();

			$form_id = $form['id'];
			$form    = gf_apply_filters( 'gform_admin_pre_render', $form_id, $form );

			if ( $this->method_is_overridden( 'form_settings' ) ) {

				//enables plugins to override settings page by implementing a form_settings() function
				$this->form_settings( $form );
			} else {

				//saves form settings if save button was pressed
				$this->maybe_save_form_settings( $form );

				//reads current form settings
				$settings = $this->get_form_settings( $form );
				$this->set_settings( $settings );

				//reading addon fields
				$sections = $this->form_settings_fields( $form );

				GFCommon::display_admin_message();

				$page_title = $this->form_settings_page_title();
				if ( empty( $page_title ) ) {
					$page_title = rgar( $sections[0], 'title' );

					//using first section title as page title, so disable section title
					$sections[0]['title'] = false;
				}
				$icon = $this->form_settings_icon();
				if ( empty( $icon ) ) {
					$icon = '<i class="fa fa-cogs"></i>';
				}

				?>
				<h3><span><?php echo $icon ?> <?php echo $page_title ?></span></h3>
				<?php

				//rendering settings based on fields and current settings
				$this->render_settings( $sections );
			}
			?>

			<script type="text/javascript">
				var form = <?php echo json_encode( $this->get_current_form() ) ?>;
			</script>
		</div>
		<?php
		GFFormSettings::page_footer();
	}

	/***
	 * Saves form settings if the submit button was pressed
	 *
	 * @param array $form The form object
	 *
	 * @return null|true|false True on success, false on error, null on no action
	 */
	public function maybe_save_form_settings( $form ) {

		if ( $this->is_save_postback() ) {

			// store a copy of the previous settings for cases where action would only happen if value has changed
			$this->set_previous_settings( $this->get_form_settings( $form ) );

			$settings = $this->get_posted_settings();
			$sections = $this->form_settings_fields( $form );

			$is_valid = $this->validate_settings( $sections, $settings );
			$result   = false;

			if ( $is_valid ) {
				$settings = $this->filter_settings( $sections, $settings );
				$result = $this->save_form_settings( $form, $settings );
			}

			if ( $result ) {
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}

			return $result;
		}

	}

	/***
	 * Saves form settings to form object
	 *
	 * @param array $form
	 * @param array $settings
	 *
	 * @return true|false True on success or false on error
	 */
	public function save_form_settings( $form, $settings ) {
		$form[ $this->_slug ] = $settings;
		$result               = GFFormsModel::update_form_meta( $form['id'], $form );

		return ! ( false === $result );
	}

	/**
	 * Checks whether the current Add-On has a form settings page.
	 *
	 * @return bool
	 */
	private function has_form_settings_page() {
		return $this->method_is_overridden( 'form_settings_fields' ) || $this->method_is_overridden( 'form_settings' );
	}

	/**
	 * Custom form settings page
	 * Override this function to implement a complete custom form settings page.
	 * Before overriding this function, consider using the form_settings_fields() and specifying your field meta.
	 */
	protected function form_settings( $form ) {
	}

	/**
	 * Custom form settings title
	 * Override this function to display a custom title on the Form Settings Page.
	 * By default, the first section in the configuration done in form_settings_fields() will be used as the page title.
	 * Use this function to override that behavior and add a custom page title.
	 */
	protected function form_settings_page_title() {
		return '';
	}

	/**
	 * Override this function to customize the form settings icon
	 */
	protected function form_settings_icon() {
		return '';
	}

	/**
	 * Checks whether the current Add-On has a plugin page.
	 *
	 * @return bool
	 */
	private function has_plugin_page() {
		return $this->method_is_overridden( 'plugin_page' );
	}

	/**
	 * Override this function to create a custom plugin page
	 */
	protected function plugin_page() {
	}

	/**
	 * Override this function to customize the plugin page icon
	 */
	protected function plugin_page_icon() {
		return '';
	}

	/**
	 * Override this function to customize the plugin page title
	 */
	protected function plugin_page_title() {
		return $this->_title;
	}

	/**
	 * Plugin page container
	 * Target of the plugin menu left nav icon. Displays the outer plugin page markup and calls plugin_page() to render the actual page.
	 * Override plugin_page() in order to provide a custom plugin page
	 */
	public function plugin_page_container() {
		?>
		<div class="wrap">
			<?php
			$icon = $this->plugin_page_icon();
			if ( ! empty( $icon ) ) {
				?>
				<img alt="<?php echo $this->get_short_title() ?>" style="margin: 15px 7px 0pt 0pt; float: left;" src="<?php echo $icon ?>" />
			<?php
			}
			?>

			<h2 class="gf_admin_page_title"><?php echo $this->plugin_page_title() ?></h2>
			<?php

			$this->plugin_page();
			?>
		</div>
	<?php
	}

	/**
	 * Checks whether the current Add-On has a top level app menu.
	 *
	 * @return bool
	 */
	public function has_app_menu() {
		return $this->has_app_settings() || $this->method_is_overridden( 'get_app_menu_items' );
	}

	/**
	 * Creates a top level app menu. Adds the app settings page automatically if it's configured.
	 * Target of the WordPress admin_menu action.
	 * Not intended to be overridden or called directly by add-ons.
	 */
	public function create_app_menu() {

		$has_full_access = current_user_can( 'gform_full_access' );
		$min_cap         = GFCommon::current_user_can_which( $this->_capabilities_app_menu );
		if ( empty( $min_cap ) ) {
			$min_cap = 'gform_full_access';
		}

		$menu_items = $this->get_app_menu_items();

		$addon_menus = array();

		/**
		 * Filters through addon menus (filter by addon slugs)
		 *
		 * @param array $addon_menus A modifiable array of admin addon menus
		 */
		$addon_menus = apply_filters( 'gform_addon_app_navigation_' . $this->_slug, $addon_menus );

		$parent_menu = self::get_parent_menu( $menu_items, $addon_menus );

		if ( empty( $parent_menu ) ) {
			return;
		}

		// Add a top-level left nav
		$callback = isset( $parent_menu['callback'] ) ? $parent_menu['callback'] : array( $this, 'app_tab_page' );

		global $menu;
		$number = 10;
		$menu_position = '16.' . $number;
		while ( isset( $menu[$menu_position] ) ) {
			$number += 10;
			$menu_position = '16.' . $number;
		}

		/**
		 * Modify the menu position of an add-on menu
		 *
		 * @param int $menu_position The Menu position of the add-on menu
		 */
		$menu_position = apply_filters( 'gform_app_menu_position_' . $this->_slug, $menu_position );
		$this->app_hook_suffix = add_menu_page( $this->get_short_title(), $this->get_short_title(), $has_full_access ? 'gform_full_access' : $min_cap, $parent_menu['name'], $callback, $this->get_app_menu_icon(), $menu_position );

		if ( method_exists( $this, 'load_screen_options' ) ) {
			add_action( "load-$this->app_hook_suffix", array( $this, 'load_screen_options' ) );
		}

		// Adding submenu pages
		foreach ( $menu_items as $menu_item ) {
			$callback = isset( $menu_item['callback'] ) ? $menu_item['callback'] : array( $this, 'app_tab_page' );
			add_submenu_page( $parent_menu['name'], $menu_item['label'], $menu_item['label'], $has_full_access || empty( $menu_item['permission'] ) ? 'gform_full_access' : $menu_item['permission'], $menu_item['name'], $callback );
		}

		if ( is_array( $addon_menus ) ) {
			foreach ( $addon_menus as $addon_menu ) {
				add_submenu_page( $parent_menu['name'], $addon_menu['label'], $addon_menu['label'], $has_full_access ? 'gform_full_access' : $addon_menu['permission'], $addon_menu['name'], $addon_menu['callback'] );
			}
		}

		if ( $this->has_app_settings() ) {
			add_submenu_page( $parent_menu['name'], esc_html__( 'Settings', 'gravityforms' ), esc_html__( 'Settings', 'gravityforms' ), $has_full_access ? 'gform_full_access' : $this->_capabilities_app_settings, $this->_slug . '_settings', array( $this, 'app_tab_page' ) );
		}

	}

	/**
	 * Returns the parent menu item
	 *
	 * @param $menu_items
	 * @param $addon_menus
	 *
	 * @return array|bool The parent menu araray or false if none
	 */
	private function get_parent_menu( $menu_items, $addon_menus ) {
		$parent = false;
		if ( GFCommon::current_user_can_any( $this->_capabilities_app_menu ) ) {
			foreach ( $menu_items as $menu_item ) {
				if ( $this->current_user_can_any( $menu_item['permission'] ) ) {
					$parent = $menu_item;
					break;
				}
			}
		} elseif ( is_array( $addon_menus ) && sizeof( $addon_menus ) > 0 ) {
			foreach ( $addon_menus as $addon_menu ) {
				if ( $this->current_user_can_any( $addon_menu['permission'] ) ) {
					$parent = array( 'name' => $addon_menu['name'], 'callback' => $addon_menu['callback'] );
					break;
				}
			}
		} elseif ( $this->has_app_settings() && $this->current_user_can_any( $this->_capabilities_app_settings ) ) {
			$parent = array( 'name' => $this->_slug . '_settings', 'callback' => array( $this, 'app_settings' ) );
		}

		return $parent;
	}

	/**
	 * Override this function to create a top level app menu.
	 *
	 * e.g.
	 * $menu_item['name'] = 'gravitycontacts';
	 * $menu_item['label'] = __("Contacts", 'gravitycontacts');
	 * $menu_item['permission'] = 'gravitycontacts_view_contacts';
	 * $menu_item['callback'] = array($this, 'app_menu');
	 *
	 * @return array The array of menu items
	 */
	protected function get_app_menu_items() {
		return array();
	}

	/**
	 * Override this function to specify a custom icon for the top level app menu.
	 * Accepts a dashicon class or a URL.
	 *
	 * @return string
	 */
	protected function get_app_menu_icon() {
		return '';
	}

	/**
	 * Override this function to load custom screen options.
	 *
	 * e.g.
	 * $screen = get_current_screen();
	 * if(!is_object($screen) || $screen->id != $this->app_hook_suffix)
	 *     return;
	 *
	 * if($this->is_contact_list_page()){
	 *     $args = array(
	 *         'label' => __('Contacts per page', 'gravitycontacts'),
	 *         'default' => 20,
	 *         'option' => 'gcontacts_per_page'
	 *     );
	 * add_screen_option( 'per_page', $args );
	 */
	public function load_screen_options() {
	}

	/**
	 * Handles the rendering of app menu items that implement the tabs UI.
	 *
	 * Not intended to be overridden or called directly by add-ons.
	 */
	public function app_tab_page() {
		$page        = rgget( 'page' );
		$current_tab = rgget( 'view' );

		if ( $page == $this->_slug . '_settings' ) {

			$tabs = $this->get_app_settings_tabs();

		} else {

			$menu_items = $this->get_app_menu_items();

			$current_menu_item = false;
			foreach ( $menu_items as $menu_item ) {
				if ( $menu_item['name'] == $page ) {
					$current_menu_item = $menu_item;
					break;
				}
			}

			if ( empty( $current_menu_item ) ) {
				return;
			}

			if ( empty( $current_menu_item['tabs'] ) ) {
				return;
			}

			$tabs = $current_menu_item['tabs'];
		}

		if ( empty( $current_tab ) ) {
			foreach ( $tabs as $tab ) {
				if ( ! isset( $tab['permission'] ) || $this->current_user_can_any( $tab['permission'] ) ) {
					$current_tab = $tab['name'];
					break;
				}
			}
		}

		if ( empty( $current_tab ) ) {
			wp_die( esc_html__( "You don't have adequate permission to view this page", 'gravityforms' ) );
		}

		foreach ( $tabs as $tab ) {
			if ( $tab['name'] == $current_tab && isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
				if ( isset( $tab['permission'] ) && ! $this->current_user_can_any( $tab['permission'] ) ) {
					wp_die( esc_html__( "You don't have adequate permission to view this page", 'gravityforms' ) );
				}

				$title = rgar( $tab,'title' );

				if ( empty( $title ) ) {
					$title = isset( $tab['label'] ) ? $tab['label'] : $tab['name'];
				}

				$this->app_tab_page_header( $tabs, $current_tab, $title, '' );
				call_user_func( $tab['callback'] );
				$this->app_tab_page_footer();

				return;
			}
		}

		$this->app_tab_page_header( $tabs, $current_tab, $current_tab, '' );
		$action_hook = 'gform_addon_app_' . $page . '_' . str_replace( ' ', '_', $current_tab );
		do_action( $action_hook );
		$this->app_tab_page_footer();

	}

	/**
	 * Returns the form settings for the Add-On
	 *
	 * @param $form
	 *
	 * @return string
	 */
	protected function get_form_settings( $form ) {
		return rgar( $form, $this->_slug );
	}

	/**
	 * Add the form settings tab.
	 *
	 * Override this function to add the tab conditionally.
	 *
	 *
	 * @param $tabs
	 * @param $form_id
	 *
	 * @return array
	 */
	public function add_form_settings_menu( $tabs, $form_id ) {

		$tabs[] = array( 'name' => $this->_slug, 'label' => $this->get_short_title(), 'query' => array( 'fid' => null ) );

		return $tabs;
	}

	/**
	 * Override this function to specify the settings fields to be rendered on the form settings page
	 */
	protected function form_settings_fields( $form ) {
		// should return an array of sections, each section contains a title, description and an array of fields
		return array();
	}

	//--------------  Plugin Settings  ---------------------------------------------------

	protected function plugin_settings_init() {
		$subview = rgget( 'subview' );
		RGForms::add_settings_page(
			array(
				'name'      => $this->_slug,
				'tab_label' => $this->get_short_title(),
				'title'     => $this->plugin_settings_title(),
				'handler'   => array( $this, 'plugin_settings_page' ),
			)
		);
		if ( rgget( 'page' ) == 'gf_settings' && $subview == $this->_slug && $this->current_user_can_any( $this->_capabilities_settings_page ) ) {
			require_once( GFCommon::get_base_path() . '/tooltips.php' );
		}

		add_filter( 'plugin_action_links', array( $this, 'plugin_settings_link' ), 10, 2 );

	}

	public function plugin_settings_link( $links, $file ) {
		if ( $file != $this->_path ) {
			return $links;
		}

		array_unshift( $links, '<a href="' . admin_url( 'admin.php' ) . '?page=gf_settings&subview=' . $this->_slug . '">' . esc_html__( 'Settings', 'gravityforms' ) . '</a>' );

		return $links;
	}

	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		$icon = $this->plugin_settings_icon();
		if ( empty( $icon ) ) {
			$icon = '<i class="fa fa-cogs"></i>';
		}
		?>

		<h3><span><?php echo $icon ?> <?php echo $this->plugin_settings_title() ?></span></h3>

		<?php if ( $this->has_deprecated_elements() ) : ?>
		<div class="push-alert-red" style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
			<?php esc_html_e( 'This add-on needs to be updated. Please contact the developer.', 'gravityforms' ); ?>
		</div>
		<?php endif; ?>

		<?php

		if ( $this->method_is_overridden( 'plugin_settings' ) ) {
			//enables plugins to override settings page by implementing a plugin_settings() function
			$this->plugin_settings();
		} elseif ( $this->maybe_uninstall() ) {
			?>
			<div class="push-alert-gold" style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
				<?php printf( esc_html__( '%s has been successfully uninstalled. It can be re-activated from the %splugins page%s.', 'gravityforms'), $this->_title, "<a href='plugins.php'>", '</a>' ); ?>
			</div>
		<?php
		} else {
			//saves settings page if save button was pressed
			$this->maybe_save_plugin_settings();

			//reads main addon settings
			$settings = $this->get_plugin_settings();
			$this->set_settings( $settings );

			//reading addon fields
			$sections = $this->plugin_settings_fields();

			GFCommon::display_admin_message();

			//rendering settings based on fields and current settings
			$this->render_settings( $sections, $settings );

			//renders uninstall section
			$this->render_uninstall();

		}

	}

	public function plugin_settings_title() {
		return sprintf( esc_html__( "%s Settings", "gravityforms" ), $this->get_short_title() );
	}

	protected function plugin_settings_icon() {
		return '';
	}

	/**
	 * Override this function to add a custom settings page.
	 */
	protected function plugin_settings() {
	}

	/**
	 * Checks whether the current Add-On has a settings page.
	 *
	 * @return bool
	 */
	public function has_plugin_settings_page() {
		return $this->method_is_overridden( 'plugin_settings_fields' ) || $this->method_is_overridden( 'plugin_settings_page' ) || $this->method_is_overridden( 'plugin_settings' );
	}

	/**
	 * Returns the currently saved plugin settings
	 * @return mixed
	 */
	public function get_plugin_settings() {
		return get_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
	}

	/**
	 * Get plugin setting
	 * Returns the plugin setting specified by the $setting_name parameter
	 *
	 * @param string $setting_name - Plugin setting to be returned
	 *
	 * @return mixed  - Returns the specified plugin setting or null if the setting doesn't exist
	 */
	public function get_plugin_setting( $setting_name ) {
		$settings = $this->get_plugin_settings();

		return isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : null;
	}

	/**
	 * Updates plugin settings with the provided settings
	 *
	 * @param array $settings - Plugin settings to be saved
	 */
	protected function update_plugin_settings( $settings ) {
		update_option( 'gravityformsaddon_' . $this->_slug . '_settings', $settings );
	}

	/**
	 * Saves the plugin settings if the submit button was pressed
	 *
	 */
	protected function maybe_save_plugin_settings() {

		if ( $this->is_save_postback() ) {

			// store a copy of the previous settings for cases where action whould only happen if value has changed
			$this->set_previous_settings( $this->get_plugin_settings() );

			$settings = $this->get_posted_settings();
			$sections = $this->plugin_settings_fields();
			$is_valid = $this->validate_settings( $sections, $settings );

			if ( $is_valid ) {
				$settings = $this->filter_settings( $sections, $settings );
				$this->update_plugin_settings( $settings );
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		}

	}

	/**
	 * Override this function to specify the settings fields to be rendered on the plugin settings page
	 * @return array
	 */
	public function plugin_settings_fields() {
		// should return an array of sections, each section contains a title, description and an array of fields
		return array();
	}

	//--------------  App Settings  ---------------------------------------------------

	/**
	 * Returns the tabs for the settings app menu item
	 *
	 * Not intended to be overridden or called directly by add-ons.
	 *
	 * @return array|mixed|void
	 */
	public function get_app_settings_tabs() {

		//build left side options, always have app Settings first and Uninstall last, put add-ons in the middle

		$setting_tabs = array( array( 'name' => 'settings', 'label' => esc_html__( 'Settings', 'gravityforms' ), 'callback' => array( $this, 'app_settings_tab' ) ) );

		$setting_tabs = apply_filters( 'gform_addon_app_settings_menu_' . $this->_slug, $setting_tabs );

		if ( $this->current_user_can_any( $this->_capabilities_uninstall ) ) {
			$setting_tabs[] = array( 'name' => 'uninstall', 'label' => esc_html__( 'Uninstall', 'gravityforms' ), 'callback' => array( $this, 'app_settings_uninstall_tab' ) );
		}

		ksort( $setting_tabs, SORT_NUMERIC );

		return $setting_tabs;
	}

	/**
	 * Renders the app settings uninstall tab.
	 *
	 * Not intended to be overridden or called directly by add-ons.
	 */
	protected function app_settings_uninstall_tab() {

		if ( $this->maybe_uninstall() ) {
			?>
			<div class="push-alert-gold" style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
				<?php printf( esc_html__( '%s has been successfully uninstalled. It can be re-activated from the %splugins page%s.', 'gravityforms' ), esc_html( $this->_title ), "<a href='plugins.php'>", '</a>' ); ?>
			</div>
		<?php

		} else {
			if ( $this->current_user_can_any( $this->_capabilities_uninstall ) && ( ! function_exists( 'is_multisite' ) || ! is_multisite() || is_super_admin() ) ) {
			?>
			<form action="" method="post">
				<?php wp_nonce_field( 'uninstall', 'gf_addon_uninstall' ) ?>
				<?php  ?>
					<h3>
						<span><i class="fa fa-times"></i> <?php printf( esc_html__( 'Uninstall %s', 'gravityforms' ), $this->get_short_title() ); ?></span>
					</h3>

					<div class="delete-alert alert_red">

						<h3>
							<i class="fa fa-exclamation-triangle gf_invalid"></i> <?php esc_html_e( 'Warning', 'gravityforms' ); ?>
						</h3>

						<div class="gf_delete_notice">
							<?php echo $this->uninstall_warning_message() ?>
						</div>

						<?php
						$uninstall_button = '<input type="submit" name="uninstall" value="' . sprintf( esc_attr__( 'Uninstall %s', 'gravityforms' ), $this->get_short_title() ) . '" class="button" onclick="return confirm(\'' . esc_js( $this->uninstall_confirm_message() ) . '\');"/>';
						echo $uninstall_button;
						?>

					</div>
			</form>
			<?php
			}
		}
	}

	/**
	 * Renders the header for the tabs UI.
	 *
	 * @param        $tabs
	 * @param        $current_tab
	 * @param        $title
	 * @param string $message
	 */
	protected function app_tab_page_header( $tabs, $current_tab, $title, $message = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		// register admin styles
		wp_register_style( 'gform_admin', GFCommon::get_base_url() . "/css/admin{$min}.css" );
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );

		?>

		<div class="wrap <?php echo GFCommon::get_browser_class() ?>">

		<?php if ( $message ) { ?>
			<div id="message" class="updated"><p><?php echo $message; ?></p></div>
		<?php } ?>

		<h2><?php echo esc_html( $title ) ?></h2>

		<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
		<ul id="gform_tabs" class="gform_tabs">
			<?php
			foreach ( $tabs as $tab ) {
				if ( isset( $tab['permission'] ) && ! $this->current_user_can_any( $tab['permission'] ) ) {
					continue;
				}
				$label = isset( $tab['label'] ) ? $tab['label'] : $tab['name'];
				?>
				<li <?php echo urlencode( $current_tab ) == $tab['name'] ? "class='active'" : '' ?>>
					<a href="<?php echo esc_url( add_query_arg( array( 'view' => $tab['name'] ) ) ); ?>"><?php echo esc_html( $label ) ?></a>
				</li>
			<?php
			}
			?>
		</ul>

		<div id="gform_tab_container" class="gform_tab_container">
		<div class="gform_tab_content" id="tab_<?php echo $current_tab ?>">

	<?php
	}

	/**
	 * Renders the footer for the tabs UI.
	 *
	 */
	protected function app_tab_page_footer() {
		?>
		</div> <!-- / gform_tab_content -->
		</div> <!-- / gform_tab_container -->
		</div> <!-- / gform_tab_group -->

		<br class="clear" style="clear: both;" />

		</div> <!-- / wrap -->

		<script type="text/javascript">
			// JS fix for keep content contained on tabs with less content
			jQuery(document).ready(function ($) {
				$('#gform_tab_container').css('minHeight', jQuery('#gform_tabs').height() + 100);
			});
		</script>

	<?php
	}

	public function app_settings_tab() {

		require_once( GFCommon::get_base_path() . '/tooltips.php' );

		$icon = $this->app_settings_icon();
		if ( empty( $icon ) ) {
			$icon = '<i class="fa fa-cogs"></i>';
		}
		?>

		<h3><span><?php echo $icon ?> <?php echo $this->app_settings_title() ?></span></h3>

		<?php

		if ( $this->method_is_overridden( 'app_settings' ) ) {
			//enables plugins to override settings page by implementing a plugin_settings() function
			$this->app_settings();
		} elseif ( $this->maybe_uninstall() ) {
			?>
			<div class="push-alert-gold" style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
				<?php printf( esc_html__( '%s has been successfully uninstalled. It can be re-activated from the %splugins page%s.', 'gravityforms' ), esc_html( $this->_title ), "<a href='plugins.php'>", '</a>' ); ?>
			</div>
		<?php
		} else {
			//saves settings page if save button was pressed
			$this->maybe_save_app_settings();

			//reads main addon settings
			$settings = $this->get_app_settings();
			$this->set_settings( $settings );

			//reading addon fields
			$sections = $this->app_settings_fields();

			GFCommon::display_admin_message();

			//rendering settings based on fields and current settings
			$this->render_settings( $sections, $settings );

		}

	}

	/**
	 * Override this function to specific a custom app settings title
	 *
	 * @return string
	 */
	protected function app_settings_title() {
		return sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
	}

	/**
	 * Override this function to specific a custom app settings icon
	 *
	 * @return string
	 */
	protected function app_settings_icon() {
		return '';
	}

	/**
	 * Checks whether the current Add-On has a settings page.
	 *
	 * @return bool
	 */
	public function has_app_settings() {
		return $this->method_is_overridden( 'app_settings_fields' ) || $this->method_is_overridden( 'app_settings' );
	}

	/**
	 * Override this function to add a custom app settings page.
	 */
	protected function app_settings() {
	}

	/**
	 * Returns the currently saved plugin settings
	 * @return mixed
	 */
	protected function get_app_settings() {
		return get_option( 'gravityformsaddon_' . $this->_slug . '_app_settings' );
	}

	/**
	 * Get app setting
	 * Returns the app setting specified by the $setting_name parameter
	 *
	 * @param string $setting_name - Plugin setting to be returned
	 *
	 * @return mixed  - Returns the specified plugin setting or null if the setting doesn't exist
	 */
	protected function get_app_setting( $setting_name ) {
		$settings = $this->get_app_settings();

		return isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : null;
	}

	/**
	 * Updates app settings with the provided settings
	 *
	 * @param array $settings - App settings to be saved
	 */
	protected function update_app_settings( $settings ) {
		update_option( 'gravityformsaddon_' . $this->_slug . '_app_settings', $settings );
	}

	/**
	 * Saves the plugin settings if the submit button was pressed
	 *
	 */
	protected function maybe_save_app_settings() {

		if ( $this->is_save_postback() ) {

			// store a copy of the previous settings for cases where action would only happen if value has changed
			$this->set_previous_settings( $this->get_app_settings() );

			$settings = $this->get_posted_settings();
			$sections = $this->app_settings_fields();
			$is_valid = $this->validate_settings( $sections, $settings );

			if ( $is_valid ) {
				$settings = $this->filter_settings( $sections, $settings );
				$this->update_app_settings( $settings );
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		}

	}

	/**
	 * Override this function to specify the settings fields to be rendered on the plugin settings page
	 * @return array
	 */
	public function app_settings_fields() {
		// should return an array of sections, each section contains a title, description and an array of fields
		return array();
	}

	/**
	 * Returns an flattened array of field settings for the specified settings type ignoring sections.
	 *
	 * @param string $settings_type The settings type. e.g. 'plugin'
	 *
	 * @return array
	 */
	protected function settings_fields_only( $settings_type = 'plugin' ) {

		$fields = array();

		if ( ! is_callable( array( $this, "{$settings_type}_settings_fields" ) ) ) {
			return $fields;
		}

		$sections = call_user_func( array( $this, "{$settings_type}_settings_fields" ) );

		foreach ( $sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	//--------------  Uninstall  ---------------

	/**
	 * Override this function to customize the markup for the uninstall section on the plugin settings page
	 */
	public function render_uninstall() {

		?>
		<form action="" method="post">
			<?php wp_nonce_field( 'uninstall', 'gf_addon_uninstall' ) ?>
			<?php if ( $this->current_user_can_any( $this->_capabilities_uninstall ) ) { ?>

				<div class="hr-divider"></div>

				<h3><span><i class="fa fa-times"></i> <?php printf( esc_html__( 'Uninstall %s Add-On', 'gravityforms' ), $this->get_short_title() ) ?></span></h3>
				<div class="delete-alert alert_red">
					<h3><i class="fa fa-exclamation-triangle gf_invalid"></i> Warning</h3>
					<div class="gf_delete_notice">
						<?php echo $this->uninstall_warning_message() ?>
					</div>
					<input type="submit" name="uninstall" value="<?php esc_attr_e( 'Uninstall  Add-On', 'gravityforms' ) ?>" class="button" onclick="return confirm('<?php echo esc_js( $this->uninstall_confirm_message() ); ?>');">
				</div>

			<?php
			}
			?>
		</form>
	<?php
	}

	protected function uninstall_warning_message() {
		return sprintf( esc_html__( '%sThis operation deletes ALL %s settings%s. If you continue, you will NOT be able to retrieve these settings.', 'gravityforms' ), '<strong>', esc_html( $this->get_short_title() ), '</strong>' );
	}

	protected function uninstall_confirm_message() {
		return sprintf( __( "Warning! ALL %s settings will be deleted. This cannot be undone. 'OK' to delete, 'Cancel' to stop", 'gravityforms' ), __( $this->get_short_title() ) );
	}
	/**
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 */
	public function maybe_uninstall() {
		if ( rgpost( 'uninstall' ) ) {
			check_admin_referer( 'uninstall', 'gf_addon_uninstall' );

			return $this->uninstall_addon();
		}

		return false;
	}

	/**
	 * Removes all settings and deactivates the Add-On.
	 *
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 */
	public function uninstall_addon() {

		if ( ! $this->current_user_can_any( $this->_capabilities_uninstall ) ) {
			die( esc_html__( "You don't have adequate permission to uninstall this add-on: " . $this->_title, 'gravityforms' ) );
		}

		$continue = $this->uninstall();
		if ( false === $continue ) {
			return false;
		}

		global $wpdb;
		$lead_meta_table = GFFormsModel::get_lead_meta_table_name();

		$forms        = GFFormsModel::get_forms();
		$all_form_ids = array();

		// remove entry meta
		foreach ( $forms as $form ) {
			$all_form_ids[] = $form->id;
			$entry_meta     = $this->get_entry_meta( array(), $form->id );
			if ( is_array( $entry_meta ) ) {
				foreach ( array_keys( $entry_meta ) as $meta_key ) {
					$sql = $wpdb->prepare( "DELETE from $lead_meta_table WHERE meta_key=%s", $meta_key );
					$wpdb->query( $sql );
				}
			}
		}

		//remove form settings
		if ( ! empty( $all_form_ids ) ) {
			$form_metas = GFFormsModel::get_form_meta_by_id( $all_form_ids );
			require_once( GFCommon::get_base_path() . '/form_detail.php' );
			foreach ( $form_metas as $form_meta ) {
				if ( isset( $form_meta[ $this->_slug ] ) ) {
					unset( $form_meta[ $this->_slug ] );
					$form_json = json_encode( $form_meta );
					GFFormDetail::save_form_info( $form_meta['id'], addslashes( $form_json ) );
				}
			}
		}

		//removing options
		delete_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
		delete_option( 'gravityformsaddon_' . $this->_slug . '_app_settings' );
		delete_option( 'gravityformsaddon_' . $this->_slug . '_version' );


		//Deactivating plugin
		deactivate_plugins( $this->_path );
		update_option( 'recently_activated', array( $this->_path => time() ) + (array) get_option( 'recently_activated' ) );

		return true;

	}

	/**
	 * Called when the user chooses to uninstall the Add-On  - after permissions have been checked and before removing
	 * all Add-On settings and Form settings.
	 *
	 * Override this method to perform additional functions such as dropping database tables.
	 *
	 *
	 * Return false to cancel the uninstall request.
	 */
	protected function uninstall() {
		return true;
	}

	//--------------  Enforce minimum GF version  ---------------------------------------------------

	/**
	 * Target for the after_plugin_row action hook. Checks whether the current version of Gravity Forms
	 * is supported and outputs a message just below the plugin info on the plugins page.
	 *
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 */
	public function plugin_row() {
		if ( ! self::is_gravityforms_supported( $this->_min_gravityforms_version ) ) {
			$message = $this->plugin_message();
			self::display_plugin_message( $message, true );
		}
	}

	/**
	 * Returns the message that will be displayed if the current version of Gravity Forms is not supported.
	 *
	 * Override this method to display a custom message.
	 */
	public function plugin_message() {
		$message = sprintf( esc_html__( 'Gravity Forms %s is required. Activate it now or %spurchase it today!%s', 'gravityforms' ), $this->_min_gravityforms_version, "<a href='http://www.gravityforms.com'>", '</a>' );

		return $message;
	}

	/**
	 * Formats and outs a message for the plugin row.
	 *
	 * Not intended to be overridden or called directly by Add-Ons.
	 *
	 * @ignore
	 *
	 * @param      $message
	 * @param bool $is_error
	 */
	public static function display_plugin_message( $message, $is_error = false ) {
		$style = $is_error ? 'style="background-color: #ffebe8;"' : '';
		echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
	}

	//--------------- Logging -------------------------------------------------------------

	/**
	 * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
	 *
	 * Not intended to be overridden by Add-Ons.
	 *
	 * @ignore
	 */
	public function log_error( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( $this->_slug, $message, KLogger::ERROR );
		}
	}

	/**
	 * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
	 *
	 * Not intended to be overridden by Add-Ons.
	 *
	 * @ignore
	 */
	public function log_debug( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( $this->_slug, $message, KLogger::DEBUG );
		}
	}

	//--------------- Locking ------------------------------------------------------------

	/**
	 * Returns the configuration for locking
	 *
	 * e.g.
	 *
	 *  array(
	 *     "object_type" => 'contact',
	 *     "capabilities" => array("gravityforms_contacts_edit_contacts"),
	 *     "redirect_url" => admin_url("admin.php?page=gf_contacts"),
	 *     "edit_url" => admin_url(sprintf("admin.php?page=gf_contacts&id=%d", $contact_id)),
	 *     "strings" => $strings
	 *     );
	 *
	 * Override this method to implement locking
	 */
	public function get_locking_config() {
		return array();
	}


	/**
	 * Returns TRUE if the current page is the edit page. Otherwise, returns FALSE
	 *
	 * Override this method to implement locking on the edit page.
	 */
	public function is_locking_edit_page() {
		return false;
	}

	/**
	 * Returns TRUE if the current page is the list page. Otherwise, returns FALSE
	 *
	 * Override this method to display locking info on the list page.
	 */
	public function is_locking_list_page() {
		return false;
	}

	/**
	 * Returns TRUE if the current page is the view page. Otherwise, returns FALSE
	 *
	 * Override this method to display locking info on the view page.
	 */
	public function is_locking_view_page() {
		return false;
	}

	/**
	 * Returns the ID of the object to be locked. E.g. Form ID
	 *
	 * Override this method to implement locking
	 */
	public function get_locking_object_id() {
		return 0;
	}

	/**
	 * Outputs information about the user currently editing the specified object
	 *
	 * @param int  $object_id The Object ID
	 * @param bool $echo      Whether to echo
	 *
	 * @return string The markup for the lock info
	 */
	public function lock_info( $object_id, $echo = true ) {
		$gf_locking = new GFAddonLocking( $this->get_locking_config(), $this );
		$lock_info  = $gf_locking->lock_info( $object_id, false );
		if ( $echo ) {
			echo $lock_info;
		}

		return $lock_info;
	}

	/**
	 * Outputs class for the row for the specified Object ID on the list page.
	 *
	 * @param int  $object_id The object ID
	 * @param bool $echo      Whether to echo
	 *
	 * @return string The markup for the class
	 */
	public function list_row_class( $object_id, $echo = true ) {
		$gf_locking = new GFAddonLocking( $this->get_locking_config(), $this );
		$class      = $gf_locking->list_row_class( $object_id, false );
		if ( $echo ) {
			echo $class;
		}

		return $class;
	}

	/**
	 * Checked whether an object is locked
	 *
	 * @param int|mixed $object_id The object ID
	 *
	 * @return bool
	 */
	public function is_object_locked( $object_id ) {
		$gf_locking = new GFAddonLocking( $this->get_locking_config(), $this );

		return $gf_locking->is_locked( $object_id );
	}

	//------------- Field Value Retrieval -------------------------------------------------

	/**
	 * Returns the value of the mapped field.
	 *
	 * @param string $setting_name
	 * @param array $form
	 * @param array $entry
	 * @param mixed $settings
	 *
	 * @return string
	 */
	protected function get_mapped_field_value( $setting_name, $form, $entry, $settings = false ) {

		$field_id = $this->get_setting( $setting_name, '', $settings );

		return $this->get_field_value( $form, $entry, $field_id );
	}

	/**
	 * Returns the value of the selected field.
	 *
	 * @access private
	 *
	 * @param array $form
	 * @param array $entry
	 * @param string $field_id
	 *
	 * @return string field value
	 */
	public function get_field_value( $form, $entry, $field_id ) {

		$field_value = '';

		switch ( strtolower( $field_id ) ) {

			case 'form_title':
				$field_value = rgar( $form, 'title' );
				break;

			case 'date_created':
				$date_created = rgar( $entry, strtolower( $field_id ) );
				if ( empty( $date_created ) ) {
					//the date created may not yet be populated if this function is called during the validation phase and the entry is not yet created
					$field_value = gmdate( 'Y-m-d H:i:s' );
				} else {
					$field_value = $date_created;
				}
				break;

			case 'ip':
			case 'source_url':
			case 'id':
				$field_value = rgar( $entry, strtolower( $field_id ) );
				break;

			default:
				$field = GFFormsModel::get_field( $form, $field_id );

				if ( is_object( $field ) ) {
					$is_integer = $field_id == intval( $field_id );
					$input_type = $field->get_input_type();

					if ( $is_integer && $input_type == 'address' ) {

						$field_value = $this->get_full_address( $entry, $field_id );

					} elseif ( $is_integer && $input_type == 'name' ) {

						$field_value = $this->get_full_name( $entry, $field_id );

					} elseif ( $input_type == 'list' ) {

						$field_value = $this->get_list_field_value( $entry, $field_id, $field );

					} else {

						$field_value = $field->get_value_export( $entry, $field_id );

					}
				} else {

					$field_value = rgar( $entry, $field_id );

				}

		}

		return $this->maybe_override_field_value( $field_value, $form, $entry, $field_id );
	}

	/**
	 * Enables use of the gform_SLUG_field_value filter to override the field value. Override this function to prevent the filter being used or to implement a custom filter.
	 *
	 * @param string $field_value
	 * @param array $form
	 * @param array $entry
	 * @param string $field_id
	 *
	 * @return string
	 */
	public function maybe_override_field_value( $field_value, $form, $entry, $field_id ) {
		/* Get Add-On slug */
		$slug = str_replace( 'gravityforms', '', $this->_slug );

		return gf_apply_filters( "gform_{$slug}_field_value", array(
			$form['id'],
			$field_id
		), $field_value, $form, $entry, $field_id );
	}

	/**
	 * Returns the combined value of the specified Address field.
	 *
	 * @param array $entry
	 * @param string $field_id
	 *
	 * @return string
	 */
	protected function get_full_address( $entry, $field_id ) {

		return GF_Fields::get( 'address' )->get_value_export( $entry, $field_id );
	}

	/**
	 * Returns the combined value of the specified Name field.
	 *
	 * @param array $entry
	 * @param string $field_id
	 *
	 * @return string
	 */
	protected function get_full_name( $entry, $field_id ) {

		return GF_Fields::get( 'name' )->get_value_export( $entry, $field_id );
	}

	/**
	 * Returns the value of the specified List field.
	 *
	 * @param array $entry
	 * @param string $field_id
	 * @param object $field
	 *
	 * @return string
	 */
	protected function get_list_field_value( $entry, $field_id, $field ) {

		return $field->get_value_export( $entry, $field_id );
	}
	
	/**
	 * Returns the field ID of the first field of the desired type.
	 * 
	 * @access public
	 * @param string $field_type
	 * @param int $subfield_id (default: null)
	 * @param int $form_id (default: null)
	 * @return string
	 */
	public function get_first_field_by_type( $field_type, $subfield_id = null, $form_id = null, $return_first_only = true ) {
		
		/* Get the current form ID. */
		if ( rgblank( $form_id ) ) {
			
			$form_id = rgget( 'id' );
			
		}
		
		/* Get the form. */
		$form = GFAPI::get_form( $form_id );
		
		/* Get the request field type for the form. */
		$fields = GFAPI::get_fields_by_type( $form, array( $field_type ) );
		
		if ( count( $fields ) == 0 || ( count( $fields ) > 1 && $return_first_only ) ) {
			
			return null;
			
		} else {
			
			if ( rgblank( $subfield_id ) ) {
				
				return $fields[0]->id;
				
			} else {
				
				return $fields[0]->id . '.' . $subfield_id;
				
			}
			
		}
		
	}
	
	//--------------- Notes ------------------
	/**
	 * Override this function to specify a custom avatar (i.e. the payment gateway logo) for entry notes created by the Add-On
	 * @return  string - A fully qualified URL for the avatar
	 */
	public function note_avatar() {
		return false;
	}

	public function notes_avatar( $avatar, $note ) {
		if ( $note->user_name == $this->_short_title && empty( $note->user_id ) && $this->method_is_overridden( 'note_avatar', 'GFAddOn' ) ) {
			$new_avatar = $this->note_avatar();
		}

		return empty( $new_avatar ) ? $avatar : "<img alt='{$this->_short_title}' src='{$new_avatar}' class='avatar avatar-48' height='48' width='48' />";
	}

	public function add_note( $entry_id, $note, $note_type = null ) {

		$user_id   = 0;
		$user_name = $this->_short_title;

		GFFormsModel::add_note( $entry_id, $user_id, $user_name, $note, $note_type );

	}

	//--------------  Helper functions  ---------------------------------------------------

	protected final function method_is_overridden( $method_name, $base_class = 'GFAddOn' ) {
		$reflector = new ReflectionMethod( $this, $method_name );
		$name      = $reflector->getDeclaringClass()->getName();

		return $name !== $base_class;
	}
	
	/**
	 * Returns the url of the root folder of the current Add-On.
	 *
	 * @param string $full_path Optional. The full path the the plugin file.
	 *
	 * @return string
	 */
	public function get_base_url( $full_path = '' ) {
		if ( empty( $full_path ) ) {
			$full_path = $this->_full_path;
		}

		return plugins_url( null, $full_path );
	}

	/**
	 * Returns the url of the Add-On Framework root folder.
	 *
	 * @return string
	 */
	final public static function get_gfaddon_base_url() {
		return plugins_url( null, __FILE__ );
	}

	/**
	 * Returns the physical path of the Add-On Framework root folder.
	 *
	 * @return string
	 */
	final public static function get_gfaddon_base_path() {
		return self::_get_base_path();
	}

	/**
	 * Returns the physical path of the plugins root folder.
	 *
	 * @param string $full_path
	 *
	 * @return string
	 */
	public function get_base_path( $full_path = '' ) {
		if ( empty( $full_path ) ) {
			$full_path = $this->_full_path;
		}
		$folder = basename( dirname( $full_path ) );

		return WP_PLUGIN_DIR . '/' . $folder;
	}

	/**
	 * Returns the physical path of the Add-On Framework root folder
	 *
	 * @return string
	 */
	private static function _get_base_path() {
		$folder = basename( dirname( __FILE__ ) );

		return GFCommon::get_base_path() . '/includes/' . $folder;
	}

	/**
	 * Returns the URL of the Add-On Framework root folder
	 *
	 * @return string
	 */
	private static function _get_base_url() {
		$folder = basename( dirname( __FILE__ ) );

		return GFCommon::get_base_url() . '/includes/' . $folder;
	}

	/**
	 * Checks whether the Gravity Forms is installed.
	 *
	 * @return bool
	 */
	public function is_gravityforms_installed() {
		return class_exists( 'GFForms' );
	}

	public function table_exists( $table_name ) {
		global $wpdb;

		$count = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

		return ! empty( $count );
	}

	/**
	 * Checks whether the current version of Gravity Forms is supported
	 *
	 * @param $min_gravityforms_version
	 *
	 * @return bool|mixed
	 */
	public function is_gravityforms_supported( $min_gravityforms_version = '' ) {
		if ( isset( $this->_min_gravityforms_version ) && empty( $min_gravityforms_version ) ) {
			$min_gravityforms_version = $this->_min_gravityforms_version;
		}

		if ( empty( $min_gravityforms_version ) ) {
			return true;
		}

		if ( class_exists( 'GFCommon' ) ) {
			$is_correct_version = version_compare( GFCommon::$version, $min_gravityforms_version, '>=' );

			return $is_correct_version;
		} else {
			return false;
		}
	}

	/**
	 * Returns this plugin's short title. Used to display the plugin title in small areas such as tabs
	 */
	protected function get_short_title() {
		return isset( $this->_short_title ) ? $this->_short_title : $this->_title;
	}

	/**
	 * Returns the unescaped URL for the plugin settings tab associated with this plugin
	 *
	 */
	protected function get_plugin_settings_url() {
		return add_query_arg( array( 'page' => 'gf_settings', 'subview' => $this->_slug ), admin_url( 'admin.php' ) );
	}

	/**
	 * Returns the current form object based on the id query var. Otherwise returns false
	 */
	protected function get_current_form() {

		return rgempty( 'id', $_GET ) ? false : GFFormsModel::get_form_meta( rgget( 'id' ) );
	}

	/**
	 * Returns TRUE if the current request is a postback, otherwise returns FALSE
	 */
	protected function is_postback() {
		return is_array( $_POST ) && count( $_POST ) > 0;
	}

	/**
	 * Returns TRUE if the settings "Save" button was pressed
	 */
	protected function is_save_postback() {
		return ! rgempty( 'gform-settings-save' );
	}

	/**
	 * Returns TRUE if the current page is the form editor page. Otherwise, returns FALSE
	 */
	protected function is_form_editor() {

		if ( rgget( 'page' ) == 'gf_edit_forms' && ! rgempty( 'id', $_GET ) && rgempty( 'view', $_GET ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns TRUE if the current page is the form settings page, or a specific form settings tab (specified by the $tab parameter). Otherwise returns FALSE
	 *
	 * @param string $tab - Specifies a specific form setting page/tab
	 *
	 * @return bool
	 */
	protected function is_form_settings( $tab = null ) {

		$is_form_settings = rgget( 'page' ) == 'gf_edit_forms' && rgget( 'view' ) == 'settings';
		$is_tab           = $this->_tab_matches( $tab );

		if ( $is_form_settings && $is_tab ) {
			return true;
		} else {
			return false;
		}
	}

	private function _tab_matches( $tabs ) {
		if ( $tabs == null ) {
			return true;
		}

		if ( ! is_array( $tabs ) ) {
			$tabs = array( $tabs );
		}

		$current_tab = rgempty( 'subview', $_GET ) ? 'settings' : rgget( 'subview' );

		foreach ( $tabs as $tab ) {
			if ( strtolower( $tab ) == strtolower( $current_tab ) ) {
				return true;
			}
		}
	}

	/**
	 * Returns TRUE if the current page is the plugin settings main page, or a specific plugin settings tab (specified by the $tab parameter). Otherwise returns FALSE
	 *
	 * @param string $tab - Specifies a specific plugin setting page/tab.
	 *
	 * @return bool
	 */
	protected function is_plugin_settings( $tab = '' ) {

		$is_plugin_settings = rgget( 'page' ) == 'gf_settings';
		$is_tab             = $this->_tab_matches( $tab );

		if ( $is_plugin_settings && $is_tab ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns TRUE if the current page is the app settings main page, or a specific apps settings tab (specified by the $tab parameter). Otherwise returns FALSE
	 *
	 * @param string $tab - Specifies a specific app setting page/tab.
	 *
	 * @return bool
	 */
	protected function is_app_settings( $tab = '' ) {

		$is_app_settings = rgget( 'page' ) == $this->_slug . '_settings';
		$is_tab          = $this->_tab_matches( $tab );

		if ( $is_app_settings && $is_tab ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns TRUE if the current page is the plugin page. Otherwise returns FALSE
	 * @return bool
	 */
	protected function is_plugin_page() {

		return strtolower( rgget( 'page' ) ) == strtolower( $this->_slug );
	}

	/**
	 * Returns TRUE if the current page is the entry view page. Otherwise, returns FALSE
	 * @return bool
	 */
	protected function is_entry_view() {
		if ( rgget( 'page' ) == 'gf_entries' && rgget( 'view' ) == 'entry' && ( ! isset( $_POST['screen_mode'] ) || rgpost( 'screen_mode' ) == 'view' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns TRUE if the current page is the entry edit page. Otherwise, returns FALSE
	 * @return bool
	 */
	protected function is_entry_edit() {
		if ( rgget( 'page' ) == 'gf_entries' && rgget( 'view' ) == 'entry' && rgpost( 'screen_mode' ) == 'edit' ) {
			return true;
		}

		return false;
	}

	protected function is_entry_list() {
		if ( rgget( 'page' ) == 'gf_entries' && ( rgget( 'view' ) == 'entries' || rgempty( 'view', $_GET ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns TRUE if the current page is the results page. Otherwise, returns FALSE
	 */
	protected function is_results() {
		if ( rgget( 'page' ) == 'gf_entries' && rgget( 'view' ) == 'gf_results_' . $this->_slug ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns TRUE if the current page is the print page. Otherwise, returns FALSE
	 */
	protected function is_print() {
		if ( rgget( 'gf_page' ) == 'print-entry' ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns TRUE if the current page is the preview page. Otherwise, returns FALSE
	 */
	protected function is_preview() {
		if ( rgget( 'gf_page' ) == 'preview' ) {
			return true;
		}

		return false;
	}

	public function has_deprecated_elements() {
		$deprecated = GFAddOn::get_all_deprecated_protected_methods( get_class( $this ) );
		if ( ! empty( $deprecated ) ) {
			return true;
		}

		return false;
	}

	public static function get_all_deprecated_protected_methods($add_on_class_name = ''){
		$deprecated = array();
		$deprecated = array_merge( $deprecated, self::get_deprecated_protected_methods_for_base_class( 'GFAddOn', $add_on_class_name )) ;
		$deprecated = array_merge( $deprecated, self::get_deprecated_protected_methods_for_base_class( 'GFFeedAddOn', $add_on_class_name ) ) ;
		$deprecated = array_merge( $deprecated, self::get_deprecated_protected_methods_for_base_class( 'GFPaymentAddOn', $add_on_class_name ) ) ;
		return $deprecated;
	}

	public static function get_deprecated_protected_methods_for_base_class( $base_class_name, $add_on_class_name = '' ) {
		$deprecated = array();

		if ( ! class_exists( $base_class_name ) ) {
			return $deprecated;
		}

		$base_class_names = array(
			'GFAddOn',
			'GFFeedAddOn',
			'GFPaymentAddOn'
		);

		$base_class = new ReflectionClass( $base_class_name );

		$classes = empty($add_on_class_name) ? get_declared_classes() : array( $add_on_class_name );

		foreach ( $classes as $class ) {
			if ( ! is_subclass_of( $class, $base_class_name ) || in_array( $class, $base_class_names ) ) {
				continue;
			}

			$add_on_class   = new ReflectionClass( $class );
			$add_on_methods = $add_on_class->getMethods( ReflectionMethod::IS_PROTECTED );
			foreach ( $add_on_methods as $method ) {
				$method_name               = $method->getName();
				$base_has_method           = $base_class->hasMethod( $method_name );
				$is_declared_by_base_class = $base_has_method && $base_class->getMethod( $method_name )->getDeclaringClass()->getName() == $base_class_name;
				$is_overridden             = $method->getDeclaringClass()->getName() == $class;
				if ( $is_declared_by_base_class && $is_overridden ) {
					$deprecated[] = $class . '::' . $method_name;
				}
			}
		}
		return $deprecated;
	}

}
