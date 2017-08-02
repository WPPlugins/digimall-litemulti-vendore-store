<?php
/*
Plugin Name: Digimall (Lite) - Multi-vendor for Woocommerce
Plugin URI: https://wordpress.org/plugins/Digimall-lite/
Description: An e-commerce multi-vendore plugin for WordPress. Powered by Themeasset.
Version: 2.4.2
Author: ThemeAsset
Author URI: http://themeasset.com
License: GPL2
*/

/**
 * Copyright (c) 2015 Themeasset (email: info@themeasset.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Backwards compatibility for older than PHP 5.3.0
if ( !defined( '__DIR__' ) ) {
    define( '__DIR__', dirname( __FILE__ ) );
}

define( 'Digimall_PLUGIN_VERSION', '2.4.2' );
define( 'Digimall_DIR', __DIR__ );
define( 'Digimall_INC_DIR', __DIR__ . '/includes' );
define( 'Digimall_LIB_DIR', __DIR__ . '/lib' );
define( 'Digimall_PLUGIN_ASSEST', plugins_url( 'assets', __FILE__ ) );
// give a way to turn off loading styles and scripts from parent theme

if ( !defined( 'Digimall_LOAD_STYLE' ) ) {
    define( 'Digimall_LOAD_STYLE', true );
}

if ( !defined( 'Digimall_LOAD_SCRIPTS' ) ) {
    define( 'Digimall_LOAD_SCRIPTS', true );
}

/**
 * Autoload class files on demand
 *
 * `Digimall_Installer` becomes => installer.php
 * `Digimall_Template_Report` becomes => template-report.php
 *
 * @since 1.0
 *
 * @param string  $class requested class name
 */
function Digimall_autoload( $class ) {
    if ( stripos( $class, 'Digimall_' ) !== false ) {
        $class_name = str_replace( array( 'Digimall_', '_' ), array( '', '-' ), $class );
        $file_path = __DIR__ . '/classes/' . strtolower( $class_name ) . '.php';

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}

spl_autoload_register( 'Digimall_autoload' );

/**
 * Asset_Digimall class
 *
 * @class Asset_Digimall The class that holds the entire Asset_Digimall plugin
 */
final class Asset_Digimall {

    private $is_pro = false;

    /**
     * Constructor for the Asset_Digimall class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     * @uses is_admin()
     * @uses add_action()
     */
    public function __construct() {
        global $wpdb;

        $wpdb->Digimall_withdraw = $wpdb->prefix . 'Digimall_withdraw';
        $wpdb->Digimall_orders   = $wpdb->prefix . 'Digimall_orders';

        //includes file
        $this->includes();

        // init actions and filter
        $this->init_filters();
        $this->init_actions();

        // initialize classes
        $this->init_classes();

        //for reviews ajax request
        $this->init_ajax();

        do_action( 'Digimall_loaded' );
    }

    /**
     * Initializes the Asset_Digimall() class
     *
     * Checks for an existing Asset_Asset_Digimall() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new Asset_Digimall();
        }

        return $instance;
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Get the template path.
     *
     * @return string
     */
    public function template_path() {
        return apply_filters( 'Digimall_template_path', 'Digimall/' );
    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public static function activate() {
        global $wpdb;

        $wpdb->Digimall_withdraw     = $wpdb->prefix . 'Digimall_withdraw';
        $wpdb->Digimall_orders       = $wpdb->prefix . 'Digimall_orders';
        $wpdb->Digimall_announcement = $wpdb->prefix . 'Digimall_announcement';

        require_once __DIR__ . '/includes/functions.php';

        $installer = new Digimall_Installer();
        $installer->do_install();
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public static function deactivate() {

    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
        load_plugin_textdomain( 'Digimall', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    function init_actions() {

        // Localize our plugin
        add_action( 'admin_init', array( $this, 'load_table_prifix' ) );

        add_action( 'init', array( $this, 'localization_setup' ) );
        add_action( 'init', array( $this, 'register_scripts' ) );

        add_action( 'template_redirect', array( $this, 'redirect_if_not_logged_seller' ), 11 );

        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'login_enqueue_scripts', array( $this, 'login_scripts') );

        // add_action( 'admin_init', array( $this, 'install_theme' ) );
        add_action( 'admin_init', array( $this, 'block_admin_access' ) );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links' ) );
    }

    public function register_scripts() {
        $suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        // register styles
        wp_register_style( 'jquery-ui', plugins_url( 'assets/css/jquery-ui-1.10.0.custom.css', __FILE__ ), false, null );
        wp_register_style( 'fontawesome', plugins_url( 'assets/css/font-awesome.min.css', __FILE__ ), false, null );
        wp_register_style( 'Digimall-extra', plugins_url( 'assets/css/Digimall-extra.css', __FILE__ ), false, null );
        wp_register_style( 'Digimall-style', plugins_url( 'assets/css/style.css', __FILE__ ), false, null );
        wp_register_style( 'Digimall-chosen-style', plugins_url( 'assets/css/chosen.min.css', __FILE__ ), false, null );
        wp_register_style( 'Digimall-magnific-popup', plugins_url( 'assets/css/magnific-popup.css', __FILE__ ), false, null );

        // register scripts
        wp_register_script( 'jquery-flot', plugins_url( 'assets/js/flot-all.min.js', __FILE__ ), false, null, true );
        wp_register_script( 'jquery-chart', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), false, null, true );
        wp_register_script( 'Digimall-tabs-scripts', plugins_url( 'assets/js/jquery.easytabs.min.js', __FILE__ ), false, null, true );
        wp_register_script( 'Digimall-hashchange-scripts', plugins_url( 'assets/js/jquery.hashchange.min.js', __FILE__ ), false, null, true );
        wp_register_script( 'Digimall-tag-it', plugins_url( 'assets/js/tag-it.min.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_register_script( 'chosen', plugins_url( 'assets/js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_register_script( 'Digimall-popup', plugins_url( 'assets/js/jquery.magnific-popup.min.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_register_script( 'bootstrap-tooltip', plugins_url( 'assets/js/bootstrap-tooltips.js', __FILE__ ), false, null, true );
        wp_register_script( 'form-validate', plugins_url( 'assets/js/form-validate.js', __FILE__ ), array( 'jquery' ), null, true  );

        wp_register_script( 'Digimall-script', plugins_url( 'assets/js/all.js', __FILE__ ), false, null, true );
        wp_register_script( 'Digimall-product-shipping', plugins_url( 'assets/js/single-product-shipping.js', __FILE__ ), false, null, true );
    }

    /**
     * Enqueue admin scripts
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     */
    public function scripts() {

        if ( is_singular( 'product' ) && !get_query_var( 'edit' ) ) {
            wp_enqueue_script( 'Digimall-product-shipping' );
            $localize_script = array(
                'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'Digimall_reviews' ),
                'ajax_loader' => plugins_url( 'assets/images/ajax-loader.gif', __FILE__ ),
                'seller'      => array(
                    'available'    => __( 'Available', 'Digimall' ),
                    'notAvailable' => __( 'Not Available', 'Digimall' )
                ),
                'delete_confirm' => __('Are you sure?', 'Digimall' ),
                'wrong_message' => __('Something is wrong, Please try again.', 'Digimall' ),
            );
            wp_localize_script( 'jquery', 'Digimall', $localize_script );
        }

        $page_id = Digimall_get_option( 'dashboard', 'Digimall_pages' );

        // bailout if not dashboard
        if ( ! $page_id ) {
            return;
        }

        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        $localize_script = array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'Digimall_reviews' ),
            'ajax_loader' => plugins_url( 'assets/images/ajax-loader.gif', __FILE__ ),
            'seller'      => array(
                'available'    => __( 'Available', 'Digimall' ),
                'notAvailable' => __( 'Not Available', 'Digimall' )
            ),
            'delete_confirm' => __('Are you sure?', 'Digimall' ),
            'wrong_message' => __('Something is wrong, Please try again.', 'Digimall' ),
            'duplicates_attribute_messg' => __( 'Sorry this attribute option already exists, Try another one.', 'Digimall' ),
            'variation_unset_warning' => __( 'Warning! This product will not have any variations if this option is not checked.', 'Digimall' ),
        );

        $form_validate_messages = array(
            'required'        => __( "This field is required", 'Digimall' ),
            'remote'          => __( "Please fix this field.", 'Digimall' ),
            'email'           => __( "Please enter a valid email address." , 'Digimall' ),
            'url'             => __( "Please enter a valid URL." , 'Digimall' ),
            'date'            => __( "Please enter a valid date." , 'Digimall' ),
            'dateISO'         => __( "Please enter a valid date (ISO)." , 'Digimall' ),
            'number'          => __( "Please enter a valid number." , 'Digimall' ),
            'digits'          => __( "Please enter only digits." , 'Digimall' ),
            'creditcard'      => __( "Please enter a valid credit card number." , 'Digimall' ),
            'equalTo'         => __( "Please enter the same value again." , 'Digimall' ),
            'maxlength_msg'   => __( "Please enter no more than {0} characters." , 'Digimall' ),
            'minlength_msg'   => __( "Please enter at least {0} characters." , 'Digimall' ),
            'rangelength_msg' => __( "Please enter a value between {0} and {1} characters long." , 'Digimall' ),
            'range_msg'       => __( "Please enter a value between {0} and {1}." , 'Digimall' ),
            'max_msg'         => __( "Please enter a value less than or equal to {0}." , 'Digimall' ),
            'min_msg'         => __( "Please enter a value greater than or equal to {0}." , 'Digimall' ),
        );

        wp_localize_script( 'form-validate', 'DigimallValidateMsg', $form_validate_messages );

        // load only in Digimall dashboard and edit page
        if ( is_page( $page_id ) || ( get_query_var( 'edit' ) && is_singular( 'product' ) ) ) {


            if ( Digimall_LOAD_STYLE ) {
                wp_enqueue_style( 'jquery-ui' );
                wp_enqueue_style( 'fontawesome' );
                wp_enqueue_style( 'Digimall-extra' );
                wp_enqueue_style( 'Digimall-style' );
                wp_enqueue_style( 'Digimall-magnific-popup' );
            }

            if ( Digimall_LOAD_SCRIPTS ) {

                wp_enqueue_script( 'jquery' );
                wp_enqueue_script( 'jquery-ui' );
                wp_enqueue_script( 'jquery-ui-autocomplete' );
                wp_enqueue_script( 'jquery-ui-datepicker' );
                wp_enqueue_script( 'underscore' );
                wp_enqueue_script( 'post' );
                wp_enqueue_script( 'Digimall-tag-it' );
                wp_enqueue_script( 'bootstrap-tooltip' );
                wp_enqueue_script( 'form-validate' );
                wp_enqueue_script( 'Digimall-tabs-scripts' );
                wp_enqueue_script( 'jquery-chart' );
                wp_enqueue_script( 'jquery-flot' );
                wp_enqueue_script( 'chosen' );
                wp_enqueue_media();
                wp_enqueue_script( 'Digimall-popup' );

                wp_enqueue_script( 'Digimall-script' );
                wp_localize_script( 'jquery', 'Digimall', $localize_script );
            }
        }

        // store and my account page
        $custom_store_url = Digimall_get_option( 'custom_store_url', 'Digimall_general', 'store' );
        if ( get_query_var( $custom_store_url ) || get_query_var( 'store_review' ) || is_account_page() ) {

            if ( Digimall_LOAD_STYLE ) {
                wp_enqueue_style( 'fontawesome' );
                wp_enqueue_style( 'Digimall-style' );
            }


            if ( Digimall_LOAD_SCRIPTS ) {
                wp_enqueue_script( 'jquery-ui-sortable' );
                wp_enqueue_script( 'jquery-ui-datepicker' );
                wp_enqueue_script( 'bootstrap-tooltip' );
                wp_enqueue_script( 'chosen' );
                wp_enqueue_script( 'form-validate' );
                wp_enqueue_script( 'Digimall-script' );
                wp_localize_script( 'jquery', 'Digimall', $localize_script );
            }
        }

        // load Digimall style on every pages. requires for shortcodes in other pages
        if ( Digimall_LOAD_STYLE ) {
            wp_enqueue_style( 'Digimall-style' );
            wp_enqueue_style( 'fontawesome' );
        }

        //load country select js in seller settings store template
        global $wp;
        if ( isset( $wp->query_vars['settings'] ) == 'store' ) {
            wp_enqueue_script( 'wc-country-select' );
        }

        do_action( 'Digimall_after_load_script' );
    }


    /**
     * Include all the required files
     *
     * @return void
     */
    function includes() {
        $lib_dir     = __DIR__ . '/lib/';
        $inc_dir     = __DIR__ . '/includes/';
        $classes_dir = __DIR__ . '/classes/';

        require_once $inc_dir . 'functions.php';
        require_once $inc_dir . 'widgets/menu-category.php';
        require_once $inc_dir . 'widgets/store-menu-category.php';
        require_once $inc_dir . 'widgets/bestselling-product.php';
        require_once $inc_dir . 'widgets/top-rated-product.php';
        require_once $inc_dir . 'widgets/store-menu.php';
        require_once $inc_dir . 'wc-functions.php';

        // Load free or pro moduels
        if ( file_exists( Digimall_INC_DIR . '/pro/Digimall-pro-loader.php' ) ) {
            include_once Digimall_INC_DIR . '/pro/Digimall-pro-loader.php';

            $this->is_pro = true;
        }
        else if ( file_exists( Digimall_INC_DIR . '/free/Digimall-free-loader.php' ) ) {
            include_once Digimall_INC_DIR . '/free/Digimall-free-loader.php';
        }

        if ( is_admin() ) {
            require_once $inc_dir . 'admin/admin.php';
            require_once $inc_dir . 'admin/ajax.php';
            require_once $inc_dir . 'admin-functions.php';
        } else {
            require_once $inc_dir . 'wc-template.php';
            require_once $inc_dir . 'template-tags.php';
        }

    }

    /**
     * Initialize filters
     *
     * @return void
     */
    function init_filters() {
        add_filter( 'posts_where', array( $this, 'hide_others_uploads' ) );
        add_filter( 'body_class', array( $this, 'add_dashboard_template_class' ), 99 );
        add_filter( 'wp_title', array( $this, 'wp_title' ), 20, 2 );
    }

    /**
     * Hide other users uploads for `seller` users
     *
     * Hide media uploads in page "upload.php" and "media-upload.php" for
     * sellers. They can see only thier uploads.
     *
     * FIXME: fix the upload counts
     *
     * @global string $pagenow
     * @global object $wpdb
     *
     * @param string  $where
     *
     * @return string
     */
    function hide_others_uploads( $where ) {
        global $pagenow, $wpdb;

        if ( ( $pagenow == 'upload.php' || $pagenow == 'media-upload.php' ) && current_user_can( 'Digimalldar' ) ) {
            $user_id = get_current_user_id();

            $where .= " AND $wpdb->posts.post_author = $user_id";
        }

        return $where;
    }

    /**
     * Init ajax classes
     *
     * @return void
     */
    function init_ajax() {
        $doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

        if ( $doing_ajax ) {
            Digimall_Ajax::init()->init_ajax();
            new Digimall_Pageviews();
        }
    }

    /**
     * Init all the classes
     *
     * @return void
     */
    function init_classes() {
        if ( is_admin() ) {
            new Digimall_Admin_User_Profile();
            Digimall_Admin_Ajax::init();
            new Digimall_Upgrade();
        } else {
            new Digimall_Pageviews();
        }

        new Digimall_Rewrites();
        Digimall_Email::init();

        if ( is_user_logged_in() ) {
            Digimall_Template_Main::init();
            Digimall_Template_Dashboard::init();
            Digimall_Template_Products::init();
            Digimall_Template_Orders::init();
            Digimall_Template_Products::init();
            Digimall_Template_Withdraw::init();
            Digimall_Template_Shortcodes::init();
            Digimall_Template_Settings::init();
        }
    }

    /**
     * Redirect if not logged Seller
     *
     * @since 2.4
     *
     * @return void [redirection]
     */
    function redirect_if_not_logged_seller() {
        global $post;

        $page_id = Digimall_get_option( 'dashboard', 'Digimall_pages' );

        if ( ! $page_id ) {
            return;
        }

        if ( is_page( $page_id ) ) {
            Digimall_redirect_login();
            Digimall_redirect_if_not_seller();
        }
    }

    /**
     * Block user access to admin panel for specific roles
     *
     * @global string $pagenow
     */
    function block_admin_access() {
        global $pagenow, $current_user;

        // bail out if we are from WP Cli
        if ( defined( 'WP_CLI' ) ) {
            return;
        }

        $no_access   = Digimall_get_option( 'admin_access', 'Digimall_general', 'on' );
        $valid_pages = array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'media-upload.php' );
        $user_role   = reset( $current_user->roles );

        if ( ( $no_access == 'on' ) && ( !in_array( $pagenow, $valid_pages ) ) && in_array( $user_role, array( 'seller', 'customer' ) ) ) {
            wp_redirect( home_url() );
            exit;
        }
    }

    /**
     * Load jquery in login page
     *
     * @since 2.4
     *
     * @return void
     */
    function login_scripts() {
        wp_enqueue_script( 'jquery' );
    }

    /**
     * Scripts and styles for admin panel
     */
    function admin_enqueue_scripts() {
        wp_enqueue_script( 'Digimall_slider_admin', Digimall_PLUGIN_ASSEST.'/js/admin.js', array( 'jquery' ) );
    }

    /**
     * Load table prefix for withdraw and orders table
     *
     * @since 1.0
     *
     * @return void
     */
    function load_table_prifix() {
        global $wpdb;

        $wpdb->Digimall_withdraw = $wpdb->prefix . 'Digimall_withdraw';
        $wpdb->Digimall_orders   = $wpdb->prefix . 'Digimall_orders';
    }

    /**
     * Add body class for Digimall-dashboard
     *
     * @param array $classes
     */
    function add_dashboard_template_class( $classes ) {
        $page_id = Digimall_get_option( 'dashboard', 'Digimall_pages' );

        if ( ! $page_id ) {
            return $classes;
        }

        if ( is_page( $page_id ) || ( get_query_var( 'edit' ) && is_singular( 'product' ) ) ) {
            $classes[] = 'Digimall-dashboard';
        }

        if ( Digimall_is_store_page () ) {
            $classes[] = 'Digimall-store';
        }

        return $classes;
    }


    /**
     * Create a nicely formatted and more specific title element text for output
     * in head of document, based on current view.
     *
     * @since Digimall 1.0.4
     *
     * @param string  $title Default title text for current view.
     * @param string  $sep   Optional separator.
     *
     * @return string The filtered title.
     */
    function wp_title( $title, $sep ) {
        global $paged, $page;

        if ( is_feed() ) {
            return $title;
        }

        if ( Digimall_is_store_page() ) {
            $site_title = get_bloginfo( 'name' );
            $store_user = get_userdata( get_query_var( 'author' ) );
            $store_info = Digimall_get_store_info( $store_user->ID );
            $store_name = esc_html( $store_info['store_name'] );
            $title      = "$store_name $sep $site_title";

            // Add a page number if necessary.
            if ( $paged >= 2 || $page >= 2 ) {
                $title = "$title $sep " . sprintf( __( 'Page %s', 'Digimall' ), max( $paged, $page ) );
            }

            return $title;
        }

        return $title;
    }

    /**
     * Returns if the plugin is in PRO version
     *
     * @since 2.4
     *
     * @return boolean
     */
    public function is_pro() {
        return $this->is_pro;
    }

    /**
     * Plugin action links
     *
     * @param  array  $links
     *
     * @since  2.4
     *
     * @return array
     */
    function plugin_action_links( $links ) {

        if ( ! $this->is_pro() ) {
            $links[] = '<a href="https://Asset.com/products/plugins/Digimall/" target="_blank">' . __( 'Get PRO', 'Digimall' ) . '</a>';
        }

        $links[] = '<a href="' . admin_url( 'admin.php?page=Digimall-settings' ) . '">' . __( 'Settings', 'Digimall' ) . '</a>';
        $links[] = '<a href="http://docs.Asset.com/category/plugins/Digimall-plugins/" target="_blank">' . __( 'Documentation', 'Digimall' ) . '</a>';

        return $links;
    }

} // Asset_Digimall

/**
 * Load Digimall Plugin when all plugins loaded
 *
 * @return void
 */
function Digimall_load_plugin() {
    $Digimall = Asset_Digimall::init();

}

add_action( 'plugins_loaded', 'Digimall_load_plugin', 5 );

register_activation_hook( __FILE__, array( 'Asset_Digimall', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Asset_Digimall', 'deactivate' ) );
