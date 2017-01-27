<?php
/*
Plugin Name: Enhanced Media Library
Plugin URI: http://wpUXsolutions.com
Description: This plugin will be handy for those who need to manage a lot of media files.
Version: 3.0.beta2-25
Author: wpUXsolutions
Author URI: https://wpUXsolutions.com
Text Domain: enhanced-media-library
Domain Path: /languages
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Copyright 2013-2016  wpUXsolutions  (email : wpUXsolutions@gmail.com)
*/



if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



if ( ! class_exists( 'eml' ) ) :

class eml {

    /**
     * EML version.
     *
     * @var string
     */
    public $version = '3.0.beta2-25';

    /**
     * EML options.
     *
     * @var array
     */
    public $options = null;

    /**
     * Taxonomies instance.
     *
     * @var EML_Taxonomies
     */
    public $taxonomies = null;

    /**
     * Shortcodes instance.
     *
     * @var EML_Shortcodes
     */
    public $shortcodes = null;

    /**
     * MIME Types instance.
     *
     * @var EML_MimeTypes
     */
    public $mimetypes = null;

    /**
     * Settings instance.
     *
     * @var EML_Settings
     */
    public $settings = null;



    /**
     *  Main EML Instance.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return (object) the one EML instance
     */

    public static function instance() {

        static $instance = null;

        if ( null === $instance ) {
            $instance = new eml;
            $instance->initialize();
        }

        return $instance;
    }



    /**
     * Constructor. Intentionally left empty.
     *
     * @since   3.0
     */

    function __construct() {}



    /**
     *  The real constructor to initialize EML.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function initialize() {

        // options
        $this->options = array(

            'name'              => __('Enhanced Media Library', 'enhanced-media-library'),
            'dir'               => plugin_dir_url( __FILE__ ),
            'basename'          => plugin_basename( __FILE__ ),
            'slug'              => $this->get_slug(),
            'file'              => __FILE__,

            'taxonomies'        => get_option( 'wpuxss_eml_taxonomies', array() ),
            'lib_options'       => get_option( 'wpuxss_eml_lib_options', null ),
            'tax_options'       => get_option( 'wpuxss_eml_tax_options', null ),
            'mime_types'        => get_option( 'wpuxss_eml_mimes', array() )
        );


        // on update
        $version = get_option( 'wpuxss_eml_version', null );

        if ( ! is_null( $version ) && version_compare( $version, $this->version, '<>' ) ) {
            $this->on_update();
        }


        // includes and class injections
        include_once( 'core/taxonomies.php' );
        include_once( 'core/shortcodes.php' );
        include_once( 'core/mime-types.php' );
        include_once( 'core/settings.php' );


        if ( file_exists( plugin_dir_path( __FILE__ ) . 'pro/enhanced-media-library-pro.php') ) {

            include_once( 'pro/enhanced-media-library-pro.php' );
        }
        else {
            $this->taxonomies = new EML_Taxonomies();
            $this->shortcodes = new EML_Shortcodes();
            $this->settings   = new EML_Settings();
        }


        $this->mimetypes  = new EML_MimeTypes();


        // init actions
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
        add_action( 'init', array( $this, 'register_admin_assets' ) );
        add_action( 'init', array( $this, 'register_media_assets' ) );
        // add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // activation hook
        add_action( 'activate_' . $this->get_option( 'basename' ), array( $this, 'on_activation' ), 20 );
    }



    /**
     *  Get plugin's slug.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return $slug (string)
     */

    function get_slug() {

        $path_array = array_filter( explode ( '/', plugin_dir_url( __FILE__ ) ) );
        $slug = end( $path_array );

        return $slug;
    }



    /**
     *  Returns a value from the options.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $name (string) the option name to return
     *  @param  $value (mixed) default value
     *  @return $value (mixed)
     */

    function get_option( $name, $value = null ) {

        if ( isset( $this->options[$name] ) ) {
            $value = $this->options[$name];
        }

        return $value;
    }



    /**
     *  Updates a value into the options.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $name (string)
     *  @param  $value (mixed)
     *  @return N/A
     */

    function update_option( $name, $value ) {

        $this->options[$name] = $value;
    }



    /**
     *  Loads plugin text domain.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function load_plugin_textdomain() {

        load_plugin_textdomain( 'enhanced-media-library', false, dirname( $this->get_option( 'basename' ) ) . '/languages' );
    }



    /**
     *  Run actions on init.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    // function init() {
    //
    //     // on update
    //     $version = get_option( 'wpuxss_eml_version', null );
    //
    //     if ( ! is_null( $version ) && version_compare( $version, $this->version, '<>' ) ) {
    //         $this->on_update();
    //     }
    // }



    /**
     *  Sets initial plugin settings.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function on_activation() {

        if ( ! is_null( get_option( 'wpuxss_eml_version', null ) ) ) {
            return;
        }


        // update version
        update_option( 'wpuxss_eml_version', $this->version );


        // set initial settings
        $eml_taxonomies['media_category'] = array(
            'assigned' => 1,
            'eml_media' => 1,
            'media_only' => 1,
            'public' => 1,

            'labels' => array(
                'name' => __( 'Media Categories', 'enhanced-media-library' ),
                'singular_name' => __( 'Media Category', 'enhanced-media-library' ),
                'menu_name' => __( 'Media Categories', 'enhanced-media-library' ),
                'all_items' => __( 'All Media Categories', 'enhanced-media-library' ),
                'edit_item' => __( 'Edit Media Category', 'enhanced-media-library' ),
                'view_item' => __( 'View Media Category', 'enhanced-media-library' ),
                'update_item' => __( 'Update Media Category', 'enhanced-media-library' ),
                'add_new_item' => __( 'Add New Media Category', 'enhanced-media-library' ),
                'new_item_name' => __( 'New Media Category Name', 'enhanced-media-library' ),
                'parent_item' => __( 'Parent Media Category', 'enhanced-media-library' ),
                'parent_item_colon' => __( 'Parent Media Category:', 'enhanced-media-library' ),
                'search_items' => __( 'Search Media Categories', 'enhanced-media-library' )
            ),

            'hierarchical' => 1,
            'tax_archive' => 1,

            'show_admin_column' => 1,
            'admin_filter' => 1,          // list view filter
            'media_uploader_filter' => 1, // grid view filter
            'media_popup_taxonomy_edit' => 1,

            'show_in_nav_menus' => 1,
            'sort' => 0,
            'show_in_rest' => 0,
            'rewrite' => array(
                'slug' => 'media_category',
                'with_front' => 1
            )
        );

        $eml_lib_options = array(
            'enhance_media_shortcodes' => 0,
            'media_orderby' => 'date',
            'media_order' => 'DESC',
            'frontend_scripts' => 0
        );

        $eml_tax_options = array(
            // 'tax_archives' => 1,
            'edit_all_as_hierarchical' => 0,
            'force_filters' => 0,
            'show_count' => 1
        );

        $allowed_mimes = get_allowed_mime_types();
        $eml_mime_types = array();

        foreach ( wp_get_mime_types() as $type => $mime ) {

            $eml_mime_types[$type] = array(
                'mime'     => $mime,
                'singular' => $mime,
                'plural'   => $mime,
                'filter'   => 0,
                'upload'   => isset( $allowed_mimes[$type] ) ? 1 : 0
            );
        }

        // backup mimes without PDF
        update_option( 'wpuxss_eml_mimes_backup', $eml_mime_types );

        $eml_mime_types['pdf']['singular'] = 'PDF';
        $eml_mime_types['pdf']['plural'] = 'PDFs';
        $eml_mime_types['pdf']['filter'] = 1;

        update_option( 'wpuxss_eml_taxonomies', $eml_taxonomies );
        update_option( 'wpuxss_eml_lib_options', $eml_lib_options );
        update_option( 'wpuxss_eml_tax_options', $eml_tax_options );
        update_option( 'wpuxss_eml_mimes', $eml_mime_types );

        $this->update_option( 'taxonomies', $eml_taxonomies );
        $this->update_option( 'lib_options', $eml_lib_options );
        $this->update_option( 'tax_options', $eml_tax_options );
        $this->update_option( 'mime_types', $eml_mime_types );
    }



    /**
     *  Makes changes to plugin options on update.
     *
     *  @since  3.0
     *  @date   24/09/16
     */

    function on_update() {

        // global $wp_taxonomies;


        // update version
        update_option( 'wpuxss_eml_version', $this->version );


        // correct settings if needed
        $eml_taxonomies  = $this->get_option( 'taxonomies' );
        $eml_lib_options = $this->get_option( 'lib_options' );
        $eml_tax_options = $this->get_option( 'tax_options' );


        foreach( $eml_taxonomies as $taxonomy => $params ) {

            // since 2.0.2
            if ( (bool) $params['eml_media'] && ! isset( $params['rewrite']['with_front'] ) ) {
                $eml_taxonomies[$taxonomy]['rewrite']['with_front'] = 1;
            }

            // since 2.0.4
            if ( ! isset( $eml_taxonomies[$taxonomy]['media_popup_taxonomy_edit'] ) ) {
                $eml_taxonomies[$taxonomy]['media_popup_taxonomy_edit'] = 0;
            }

            // since 2.1.6
            if ( (bool) $params['eml_media'] && ! isset( $params['show_in_rest'] ) ) {
                $eml_taxonomies[$taxonomy]['show_in_rest'] = 0;
            }

            // since 2.2
            if ( ! (bool) $params['eml_media'] && ! isset( $params['taxonomy_auto_assign'] ) ) {
                $eml_taxonomies[$taxonomy]['taxonomy_auto_assign'] = 0;
            }

            // unset since 2.2
            // if ( $taxonomy == 'link_category' ) {
            //     unset( $eml_taxonomies[$taxonomy] );
            // }

            // unset since 2.2
            if ( ! (bool) $params['eml_media'] ) {

                if ( isset( $params['hierarchical'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['hierarchical'] );
                }
                if ( isset( $params['rewrite'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['rewrite'] );
                }
                if ( isset( $params['sort'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['sort'] );
                }
                if ( isset( $params['show_admin_column'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['show_admin_column'] );
                }
                if ( isset( $params['show_in_nav_menus'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['show_in_nav_menus'] );
                }
                if ( isset( $params['show_in_rest'] ) ) {
                    unset( $eml_taxonomies[$taxonomy]['show_in_rest'] );
                }
            }

            // error_log( print_r($params,true) );

            // since 3.0
            // we seem to not need it because media_only is being checked every 'init' at 286 of taxonomy.php
            // actually, we need this because it's about tax_archive, not about media_only
            if ( /* (bool) $params['media_only'] && */ ! isset( $params['tax_archive'] ) ) {
                $eml_taxonomies[$taxonomy]['tax_archive'] = isset( $eml_tax_options['tax_archives'] ) ? $eml_tax_options['tax_archives'] : 1;
            }
            // elseif ( isset( $params['tax_archive'] ) ) {
            //     unset( $eml_taxonomies[$taxonomy]['tax_archive'] );
            // }
        }


        // since 2.0.4
        if ( is_null( $eml_tax_options ) ) {

            $eml_tax_options = array(
                // 'tax_archives' => 1,
                'edit_all_as_hierarchical' => 0,
                'force_filters' => 0,
                'show_count' => 1
            );
        }
        else {

            // since 2.3
            if ( ! isset( $eml_tax_options['show_count'] ) )
                $eml_tax_options['show_count'] = 1;

            // since 3.0
            if ( isset( $eml_tax_options['tax_archives'] ) )
                unset( $eml_tax_options['tax_archives'] );
        }


        // since 2.2.1
        if ( is_null( $eml_lib_options ) ) {

            if ( ! is_null( $eml_tax_options ) ) {

                if ( isset( $eml_tax_options['enhance_media_shortcodes'] ) ) {
                    $eml_lib_options['enhance_media_shortcodes'] = $eml_tax_options['enhance_media_shortcodes'];
                    unset( $eml_tax_options['enhance_media_shortcodes'] );
                }
                elseif ( isset( $eml_tax_options['enhance_gallery_shortcode'] ) ) {
                    $eml_lib_options['enhance_media_shortcodes'] = $eml_tax_options['enhance_gallery_shortcode'];
                    unset( $eml_tax_options['enhance_gallery_shortcode'] );
                }
                else {
                    $eml_lib_options['enhance_media_shortcodes'] = 0;
                }

                if ( isset( $eml_tax_options['media_orderby'] ) ) {
                    $eml_lib_options['media_orderby'] = $eml_tax_options['media_orderby'];
                    unset( $eml_tax_options['media_orderby'] );
                }
                else {
                    $eml_lib_options['media_orderby'] = 'date';
                }

                if ( isset( $eml_tax_options['media_order'] ) ) {
                    $eml_lib_options['media_order'] = $eml_tax_options['media_order'];
                    unset( $eml_tax_options['media_order'] );
                }
                else {
                    $eml_lib_options['media_order'] = 'DESC';
                }
            }
            else {
                $eml_lib_options = array(
                    'enhance_media_shortcodes' => 0,
                    'media_orderby' => 'date',
                    'media_order' => 'DESC'
                );
            }

            // since 3.0
            $eml_lib_options['frontend_scripts'] = 0;
        }
        else {

            // since 2.3.1
            if ( ! isset( $eml_lib_options['media_orderby'] ) )
                $eml_lib_options['media_orderby'] = 'date';
            if ( ! isset( $eml_lib_options['media_order'] ) )
                $eml_lib_options['media_order'] = 'DESC';

            // since 3.0
            if ( ! isset( $eml_lib_options['frontend_scripts'] ) )
                $eml_lib_options['frontend_scripts'] = 0;
        }


        update_option( 'wpuxss_eml_taxonomies', $eml_taxonomies );
        update_option( 'wpuxss_eml_lib_options', $eml_lib_options );
        update_option( 'wpuxss_eml_tax_options', $eml_tax_options );

        $this->update_option( 'taxonomies', $eml_taxonomies );
        $this->update_option( 'lib_options', $eml_lib_options );
        $this->update_option( 'tax_options', $eml_tax_options );



        /**
         *  Clean up update_count_callback
         *  Set custom update_count_callback for post type
         *
         *  @since 2.3
         */

        //  echo '<div style="display:none;">';
        //  print_r( $wp_taxonomies );
        //  echo '</div>';

        // foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy => $params ) {
        // foreach ( get_taxonomies( array( 'show_ui' => true, 'public' => true ), 'objects' ) as $taxonomy => $params) {

            // if ( in_array( 'attachment', $params->object_type ) &&
            //      isset( $wp_taxonomies[$taxonomy]->update_count_callback ) &&
            //      '_update_generic_term_count' === $wp_taxonomies[$taxonomy]->update_count_callback ) {
            //
            //     unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            // }

            // $post_types = array( 'post', 'attachment' );
            //
            // if ( count( array_intersect( $post_types, $params->object_type ) ) == count( $post_types ) ) {

            // if ( in_array( 'post', $params->object_type ) &&  ) {
            //
            //     if ( in_array( 'attachment', $params->object_type ) )
                    // $wp_taxonomies[$taxonomy]->update_count_callback = '_eml_update_post_term_count';
                // else
                //     unset( $wp_taxonomies[$taxonomy]->update_count_callback );
        //     }
        // } // foreach
    }



    /**
     *  Registers scripts and styles for admin.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function register_admin_assets() {

        $version = $this->version;
        $dir     = $this->get_option( 'dir' );


        // scripts
        wp_register_script( 'eml-admin', $dir . 'js/eml-admin.js', array( 'jquery', 'jquery-ui-dialog' ), $version, true );


        // styles
        wp_register_style( 'eml-admin', $dir . 'css/eml-admin.css', array(), $version, 'all' );
        // wp_register_style( 'eml-admin-rtl', $dir . 'css/eml-admin-rtl.css', array(), $version, 'all' );
    }



    /**
     *  Registers media scripts.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function register_media_assets() {


        $version = $this->version;
        $dir     = $this->get_option( 'dir' );


        // scripts
        wp_register_script( 'eml-media-models', $dir . 'js/eml-media-models.js', array('media-models'), $version, true );
        wp_register_script( 'eml-media-views', $dir . 'js/eml-media-views.js', array('media-views'), $version, true );
        wp_register_script( 'eml-media-grid-mode', $dir . 'js/eml-media-grid-mode.js', array('eml-media-models','eml-media-views'), $version, true );
        wp_register_script( 'eml-media-list-mode', $dir . 'js/eml-media-list-mode.js', array('jquery'), $version, true );
        wp_register_script( 'eml-media-shortcodes', $dir . 'js/eml-media-shortcodes.js', array('media-views'), $version, true );
        wp_register_script( 'eml-media-editor', $dir . 'js/eml-media-editor.js', array('media-editor','media-views', 'eml-media-shortcodes'), $version, true );


        // styles
        wp_register_style( 'eml-media', $dir . 'css/eml-media.css', array(), $version, 'all' );
    }



    /**
     *  Enqueues scripts and styles for admin.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function enqueue_admin_assets() {

        // scripts
        wp_enqueue_script( 'eml-admin' );

        // styles
        wp_enqueue_style( 'eml-admin' );
        wp_style_add_data( 'eml-admin', 'rtl', 'replace' );

        wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }


} // class eml



/**
 *  The main function.
 *
 *  @since    3.0
 *  @date  24/09/16
 *
 *  @param    N/A
 *  @return   (object)
 */

 function eml() {

    return eml::instance();
 }



// initialize
eml();


endif; // class_exists
