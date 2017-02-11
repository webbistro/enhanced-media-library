<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



if ( ! class_exists( 'EML_Settings' ) ) :

class EML_Settings {

    /**
     * Constructor. Intentionally left empty.
     *
     * @since   3.0
     */

    function __construct() {}



    /**
     *  The real constructor to initialize EML_Settings.
     *
     *  @since  3.0
     *  @date   30/01/17
     *
     *  @param  N/A
     *  @return N/A
     */

    function initialize() {

        // settings actions
        add_action( 'admin_init', array( $this, 'register_setting' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'export_eml_settings' ) );
        add_action( 'admin_init', array( $this, 'import_eml_settings' ) );
        add_action( 'admin_init', array( $this, 'restore_eml_settings' ) );
        add_action( 'admin_init', array( $this, 'cleanup_eml_settings' ) );

        // settings filters
        add_filter( 'submenu_file', array( $this, 'submenu_file' ), 10, 2 );
        add_filter( 'plugin_action_links_' . eml()->get_option( 'basename' ), array( $this, 'eml_settings_link' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }



    /**
     *  Registers plugin options.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function register_setting() {

        // plugin settings: media library
        register_setting(
            'media-library',                        //option_group
            'wpuxss_eml_lib_options',               //option_name
             array( $this, 'sanitize_lib_options' ) //sanitize_callback
        );

        // plugin settings: processed taxonomies
        register_setting(
            'media-taxonomies',
            'wpuxss_eml_taxonomies',
            array( $this, 'sanitize_taxonomies' )
        );

        // plugin settings: taxonomies options
        register_setting(
            'media-taxonomies',
            'wpuxss_eml_tax_options',
            array( $this, 'sanitize_tax_options' )
        );

        // plugin settings: mime types
        register_setting(
            'mime-types',
            'wpuxss_eml_mimes',
            array( $this, 'sanitize_mime_types' )
        );

        // plugin settings: mime types backup
        register_setting(
            'eml-mime-types-backup',
            'wpuxss_eml_mimes_backup'
        );

        // plugin settings: all settings backup before import
        register_setting(
            'eml-backup',
            'wpuxss_eml_backup'
        );
    }



    /**
     *  Sanitizes media library options.
     *
     *  @type   callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function sanitize_lib_options( $input ) {

        foreach ( (array)$input as $key => $option ) {

            if ( 'media_orderby' === $key || 'media_order' === $key ) {
                $input[$key] = sanitize_text_field( $option );
            }
            else {
                $input[$key] = isset( $option ) && !! $option ? 1 : 0;
            }
        }

        if ( ! isset( $input['media_order'] ) ) {
            $input['media_order'] = 'ASC';
        }

        add_settings_error(
            'media-library',
            'eml_library_settings_saved',
            __('Media Library settings saved.', 'enhanced-media-library'),
            'updated'
        );

        return $input;
    }



    /**
     *  Sanitizes media taxonomies options.
     *
     *  @type   callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function sanitize_taxonomies( $input ) {

        if ( ! $input ) $input = array();


        foreach ( $input as $taxonomy => $params ) {

            $sanitized_taxonomy = sanitize_key( $taxonomy );

            if ( isset( $params['create_taxonomy'] ) ) {

                unset( $input[$taxonomy]['create_taxonomy'] );

                if ( taxonomy_exists( $sanitized_taxonomy ) ) {

                    unset( $input[$taxonomy] );
                    continue;
                }
            }


            if ( ! empty( $sanitized_taxonomy ) ) {

                $input[$sanitized_taxonomy] = $input[$taxonomy];
                unset( $input[$taxonomy] );
                $taxonomy = $sanitized_taxonomy;
            }
            else {
                unset( $input[$taxonomy] );
                continue;
            }


            if ( taxonomy_exists( $taxonomy ) ) {

                $tax_object = get_taxonomy( $taxonomy );
                $object_type = (array) $tax_object->object_type;
                $media_only = ( in_array( 'attachment', $object_type ) && count( $object_type ) == 1 ) ? 1 : 0;
            }
            else {
                $media_only = 1;
            }


            $input[$taxonomy]['media_only'] = $media_only;
            $input[$taxonomy]['eml_media'] = isset( $params['eml_media'] ) && !! $params['eml_media'] ? 1 : 0;

            if ( (bool) $input[$taxonomy]['eml_media'] ) {
                $input[$taxonomy]['hierarchical'] = isset($params['hierarchical']) && !! $params['hierarchical'] ? 1 : 0;
                $input[$taxonomy]['show_in_rest'] = isset($params['show_in_rest']) && !! $params['show_in_rest'] ? 1 : 0;
                $input[$taxonomy]['sort'] = isset($params['sort']) && !! $params['sort'] ? 1 : 0;
                $input[$taxonomy]['show_admin_column'] = isset($params['show_admin_column']) && !! $params['show_admin_column'] ? 1 : 0;
                $input[$taxonomy]['show_in_nav_menus'] = isset($params['show_in_nav_menus']) && !! $params['show_in_nav_menus'] ? 1 : 0;
                $input[$taxonomy]['rewrite']['with_front'] = isset($params['rewrite']['with_front']) && !! $params['rewrite']['with_front'] ? 1 : 0;
                $input[$taxonomy]['rewrite']['slug'] = isset($params['rewrite']['slug']) ? $this->sanitize_slug( $params['rewrite']['slug'], $taxonomy ) : '';
            }

            if ( ! (bool) $input[$taxonomy]['eml_media'] ) {
                $input[$taxonomy]['taxonomy_auto_assign'] = isset($params['taxonomy_auto_assign']) && !! $params['taxonomy_auto_assign'] ? 1 : 0;
            }

            // if ( (bool) $media_only ) {
                $input[$taxonomy]['tax_archive'] = isset($params['tax_archive']) && !! $params['tax_archive'] ? 1 : 0;
            // }
            // else {
            //     $input[$taxonomy]['tax_archive'] = 0;
            // }


            $input[$taxonomy]['assigned'] = isset($params['assigned']) && !! $params['assigned'] ? 1 : 0;
            $input[$taxonomy]['admin_filter'] = isset($params['admin_filter']) && !! $params['admin_filter'] ? 1 : 0;
            $input[$taxonomy]['media_uploader_filter'] = isset($params['media_uploader_filter']) && !! $params['media_uploader_filter'] ? 1 : 0;
            $input[$taxonomy]['media_popup_taxonomy_edit'] = isset($params['media_popup_taxonomy_edit']) && !! $params['media_popup_taxonomy_edit'] ? 1 : 0;


            if ( isset( $params['labels'] ) ) {

                $default_labels = array(
                    'menu_name' => $params['labels']['name'],
                    'all_items' => 'All ' . $params['labels']['name'],
                    'edit_item' => 'Edit ' . $params['labels']['singular_name'],
                    'view_item' => 'View ' . $params['labels']['singular_name'],
                    'update_item' => 'Update ' . $params['labels']['singular_name'],
                    'add_new_item' => 'Add New ' . $params['labels']['singular_name'],
                    'new_item_name' => 'New ' . $params['labels']['singular_name'] . ' Name',
                    'parent_item' => 'Parent ' . $params['labels']['singular_name'],
                    'search_items' => 'Search ' . $params['labels']['name']
                );

                foreach ( $params['labels'] as $label => $value ) {

                    $input[$taxonomy]['labels'][$label] = sanitize_text_field($value);

                    if ( empty($value) && isset($default_labels[$label]) )
                        $input[$taxonomy]['labels'][$label] = sanitize_text_field($default_labels[$label]);
                }
            }
        }

        add_settings_error(
            'media-taxonomies',
            'eml_taxonomy_settings_saved',
            __('Media Taxonomies settings saved.', 'enhanced-media-library'),
            'updated'
        );

        return $input;
    }



    /**
     *  Sanitizes media taxonomies options.
     *
     *  @type   callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function sanitize_tax_options( $input ) {

        foreach ( (array)$input as $key => $option ) {
            $input[$key] = isset( $option ) && !! $option ? 1 : 0;
        }

        return $input;
    }



    /**
     *  Sanitizes media taxonomies options.
     *
     *  @type   callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function sanitize_mime_types( $input ) {

        if ( ! $input ) $input = array();


        if ( isset( $_POST['eml-restore-mime-types-settings'] ) ) {

            $input = get_option( 'wpuxss_eml_mimes_backup' , array() );

            add_settings_error(
                'mime-types',
                'eml_mime_types_restored',
                __('MIME Types settings restored.', 'enhanced-media-library'),
                'updated'
            );
        }
        else {

            add_settings_error(
                'mime-types',
                'eml_mime_types_saved',
                __('MIME Types settings saved.', 'enhanced-media-library'),
                'updated'
            );
        }


        foreach ( $input as $type => $mime ) {

            $sanitized_type = $this->sanitize_extension( $type );

            if ( $sanitized_type !== $type ) {

                $input[$sanitized_type] = $input[$type];
                unset($input[$type]);
                $type = $sanitized_type;
            }

            $input[$type]['filter'] = isset( $mime['filter'] ) && !! $mime['filter'] ? 1 : 0;
            $input[$type]['upload'] = isset( $mime['upload'] ) && !! $mime['upload'] ? 1 : 0;

            $input[$type]['mime'] = sanitize_mime_type($mime['mime']);
            $input[$type]['singular'] = sanitize_text_field($mime['singular']);
            $input[$type]['plural'] = sanitize_text_field($mime['plural']);
        }

        return $input;
    }



    /**
     *  Sanitizes slugs.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $slug (sting)
     *  @param  $fallback_slug (sting)
     *  @return (sting)
     */

    function sanitize_slug( $slug, $fallback_slug = '' ) {

        $slug_array = explode ( '/', $slug );
        $slug_array = array_filter( $slug_array );
        $slug_array = array_map ( 'remove_accents', $slug_array );
        $slug_array = array_map ( 'sanitize_title_with_dashes', $slug_array );

        $slug = implode ( '/', $slug_array );

        if ( '' === $slug || false === $slug )
            $slug = $fallback_slug;

        return $slug;
    }



    /**
     *  Sanitizes MIME types extensions.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $key (sting)
     *  @return (sting)
     */

    function sanitize_extension( $key ) {

        $raw_key = $key;
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9|]/', '', $key );
        return apply_filters( 'sanitize_key', $key, $raw_key );
    }



    /**
     *  Adds plugin options admin pages.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function admin_menu() {

        global $menu, $submenu;


        if ( isset( $submenu['options-general.php'] ) ) {

            foreach( $submenu['options-general.php'] as $k => $d ){

                if( 'options-media.php' == $d['2'] ) {

                    $page = isset( $_GET['page'] ) && in_array( $_GET['page'], array('media','media-library','media-taxonomies','mime-types') ) ? $_GET['page'] : 'media';
                    $submenu['options-general.php'][$k]['2'] = 'options-general.php?page=' . $page;
                    break;
                }
            }
        }


        $eml_media_options_page = add_submenu_page(
            null,
            __('Media Settings','enhanced-media-library'),  //page_title
            '',                                             //menu_title
            'manage_options',                               //capability
            'media',                                        //menu_slug
            array( $this, 'print_media_settings' )          //callback
        );

        $eml_media_library_settings_page = add_submenu_page(
            null,
            __('Media Library','enhanced-media-library'),
            '',
            'manage_options',
            'media-library',
            array( $this, 'print_media_library_settings' )
        );

        $eml_taxonomies_settings_page = add_submenu_page(
            null,
            __('Taxonomies','enhanced-media-library'),
            '',
            'manage_options',
            'media-taxonomies',
            array( $this, 'print_taxonomies_settings' )
        );

        $eml_mime_types_settings_page = add_submenu_page(
            null,
            __('MIME Types','enhanced-media-library'),
            '',
            'manage_options',
            'mime-types',
            array( $this, 'print_mime_types_settings' )
        );

        // TODO: for future
        // add_action('admin_print_scripts-options-media.php', 'wpuxss_eml_options_page_scripts');

        $eml_options_page = add_options_page(
            __('Enhanced Media Library','enhanced-media-library'),
            __('Enhanced Media Library','enhanced-media-library'),
            'manage_options',
            'eml-settings',
            array( $this, 'print_eml_utility_settings' )
        );



        add_action( 'load-' . $eml_media_options_page, array( $this, 'load_media_options_page' ) );

        add_action( 'admin_print_scripts-' . $eml_media_library_settings_page, array( $this, 'print_media_library_settings_page_scripts') );
        add_action( 'admin_print_scripts-' . $eml_taxonomies_settings_page, array( $this, 'print_taxonomies_settings_page_scripts' ) );
        add_action( 'admin_print_scripts-' . $eml_mime_types_settings_page, array( $this, 'print_mime_types_settings_page_scripts' ) );

        add_action( 'admin_print_scripts-' . $eml_options_page, array( $this, 'print_eml_options_page_scripts' ) );
    }



    /**
     *  Ensures compatibility with default options-media.php for third-parties.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function load_media_options_page() {

        do_action( 'load-options-media.php' );
    }



    /**
     *  Ensures correct URLs for media sub-pages.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function submenu_file( $submenu_file, $parent_file ) {

        if ( 'options-general.php' == $parent_file && isset( $_GET['page'] ) && in_array( $_GET['page'], array('media','media-library','media-taxonomies','mime-types') ) ) {
            $submenu_file = 'options-general.php?page=' . $_GET['page'];
        }

        return $submenu_file;
    }



    /**
     *  Displays media settings tabs.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $active (string) : active tab
     *  @return N/A
     */

    function print_media_settings_tabs( $active ) { ?>

        <h2 class="nav-tab-wrapper wp-clearfix" id="eml-options-media-tabs">
            <a href="<?php echo get_admin_url( null, 'options-general.php?page=media' ); ?>" class="nav-tab<?php echo ( 'media' == $active ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'General', 'enhanced-media-library' ); ?></a>
            <a href="<?php echo get_admin_url( null, 'options-general.php?page=media-library' ); ?>" class="nav-tab<?php echo ( 'library' == $active ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'Media Library', 'enhanced-media-library' ); ?></a>
            <a href="<?php echo get_admin_url( null, 'options-general.php?page=media-taxonomies' ); ?>" class="nav-tab<?php echo ( 'taxonomies' == $active ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'Media Taxonomies', 'enhanced-media-library' ); ?></a>
            <a href="<?php echo get_admin_url( null, 'options-general.php?page=mime-types' ); ?>" class="nav-tab<?php echo ( 'mimetypes' == $active ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'MIME Types', 'enhanced-media-library' ); ?></a>
        </h2>

    <?php
    }



    /**
     *  Based on wp-admin/options-media.php
     *  Displays media settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_media_settings() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_die( __('You do not have sufficient permissions to access this page.','enhanced-media-library') );


        $title = __('Media Settings','enhanced-media-library');
        ?>

        <div class="wrap">
        <h1><?php echo esc_html( $title ); ?></h1>

        <?php $this->print_media_settings_tabs( 'media' ); ?>

        <form action="options.php" method="post">
        <?php settings_fields('media'); ?>

        <h2 class="title"><?php _e('Image sizes') ?></h2>
        <p><?php _e( 'The sizes listed below determine the maximum dimensions in pixels to use when adding an image to the Media Library.' ); ?></p>

        <table class="form-table">
        <tr>
        <th scope="row"><?php _e('Thumbnail size') ?></th>
        <td>
        <label for="thumbnail_size_w"><?php _e('Width'); ?></label>
        <input name="thumbnail_size_w" type="number" step="1" min="0" id="thumbnail_size_w" value="<?php form_option('thumbnail_size_w'); ?>" class="small-text" />
        <label for="thumbnail_size_h"><?php _e('Height'); ?></label>
        <input name="thumbnail_size_h" type="number" step="1" min="0" id="thumbnail_size_h" value="<?php form_option('thumbnail_size_h'); ?>" class="small-text" /><br />
        <input name="thumbnail_crop" type="checkbox" id="thumbnail_crop" value="1" <?php checked('1', get_option('thumbnail_crop')); ?>/>
        <label for="thumbnail_crop"><?php _e('Crop thumbnail to exact dimensions (normally thumbnails are proportional)'); ?></label>
        </td>
        </tr>

        <tr>
        <th scope="row"><?php _e('Medium size') ?></th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Medium size'); ?></span></legend>
        <label for="medium_size_w"><?php _e('Max Width'); ?></label>
        <input name="medium_size_w" type="number" step="1" min="0" id="medium_size_w" value="<?php form_option('medium_size_w'); ?>" class="small-text" />
        <label for="medium_size_h"><?php _e('Max Height'); ?></label>
        <input name="medium_size_h" type="number" step="1" min="0" id="medium_size_h" value="<?php form_option('medium_size_h'); ?>" class="small-text" />
        </fieldset></td>
        </tr>

        <tr>
        <th scope="row"><?php _e('Large size') ?></th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Large size'); ?></span></legend>
        <label for="large_size_w"><?php _e('Max Width'); ?></label>
        <input name="large_size_w" type="number" step="1" min="0" id="large_size_w" value="<?php form_option('large_size_w'); ?>" class="small-text" />
        <label for="large_size_h"><?php _e('Max Height'); ?></label>
        <input name="large_size_h" type="number" step="1" min="0" id="large_size_h" value="<?php form_option('large_size_h'); ?>" class="small-text" />
        </fieldset></td>
        </tr>

        <?php do_settings_fields('media', 'default'); ?>
        </table>

        <?php
        /**
         * @global array $wp_settings
         */
        if ( isset( $GLOBALS['wp_settings']['media']['embeds'] ) ) : ?>
        <h2 class="title"><?php _e('Embeds') ?></h2>
        <table class="form-table">
        <?php do_settings_fields( 'media', 'embeds' ); ?>
        </table>
        <?php endif; ?>

        <?php if ( !is_multisite() ) : ?>
        <h2 class="title"><?php _e('Uploading Files'); ?></h2>
        <table class="form-table">
        <?php
        // If upload_url_path is not the default (empty), and upload_path is not the default ('wp-content/uploads' or empty)
        if ( get_option('upload_url_path') || ( get_option('upload_path') != 'wp-content/uploads' && get_option('upload_path') ) ) :
        ?>
        <tr>
        <th scope="row"><label for="upload_path"><?php _e('Store uploads in this folder'); ?></label></th>
        <td><input name="upload_path" type="text" id="upload_path" value="<?php echo esc_attr(get_option('upload_path')); ?>" class="regular-text code" />
        <p class="description"><?php
        	/* translators: %s: wp-content/uploads */
        	printf( __( 'Default is %s' ), '<code>wp-content/uploads</code>' );
        ?></p>
        </td>
        </tr>

        <tr>
        <th scope="row"><label for="upload_url_path"><?php _e('Full URL path to files'); ?></label></th>
        <td><input name="upload_url_path" type="text" id="upload_url_path" value="<?php echo esc_attr( get_option('upload_url_path')); ?>" class="regular-text code" />
        <p class="description"><?php _e('Configuring this is optional. By default, it should be blank.'); ?></p>
        </td>
        </tr>
        <?php endif; ?>
        <tr>
        <th scope="row" colspan="2" class="th-full">
        <label for="uploads_use_yearmonth_folders">
        <input name="uploads_use_yearmonth_folders" type="checkbox" id="uploads_use_yearmonth_folders" value="1"<?php checked('1', get_option('uploads_use_yearmonth_folders')); ?> />
        <?php _e('Organize my uploads into month- and year-based folders'); ?>
        </label>
        </th>
        </tr>

        <?php do_settings_fields('media', 'uploads'); ?>
        </table>
        <?php endif; ?>

        <?php do_settings_sections('media'); ?>

        <?php submit_button(); ?>

        </form>

        </div>

        <?php
    }



    /**
     *  Displays media library settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_media_library_settings() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'enhanced-media-library' ) );


        $eml_lib_options = eml()->get_option( 'lib_options' );
        $title = __('Media Settings','enhanced-media-library'); ?>


        <div id="wpuxss-eml-media-library-options-wrap" class="wrap">

            <h1><?php echo esc_html( $title ); ?></h1>

            <?php $this->print_media_settings_tabs( 'library' ); ?>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder">

                    <div id="postbox-container-2" class="postbox-container">

                        <form id="wpuxss-eml-form-taxonomies" method="post" action="options.php">

                            <?php settings_fields( 'media-library' ); ?>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('Media Items Order','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <table class="form-table">

                                        <tr>
                                            <th scope="row"><label for="wpuxss_eml_lib_options[media_orderby]"><?php _e('Order media items by','enhanced-media-library'); ?></label></th>
                                            <td>
                                                <select name="wpuxss_eml_lib_options[media_orderby]" id="wpuxss_eml_lib_options_media_orderby">
                                                    <option value="date" <?php selected( $eml_lib_options['media_orderby'], 'date' ); ?>><?php _e('Date','enhanced-media-library'); ?></option>
                                                    <option value="title" <?php selected( $eml_lib_options['media_orderby'], 'title' ); ?>><?php _e('Title','enhanced-media-library'); ?></option>
                                                    <option value="menuOrder" <?php selected( $eml_lib_options['media_orderby'], 'menuOrder' ); ?>><?php _e('Custom Order','enhanced-media-library'); ?></option>
                                                </select>
                                                <?php _e('For media library and media popups','enhanced-media-library'); ?>
                                                <p class="description"><?php _e( 'Option allows to change order by drag and drop with Custom Order value.', 'enhanced-media-library' ); ?></p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="wpuxss_eml_lib_options[media_order]"><?php _e('Sort order','enhanced-media-library'); ?></label></th>
                                            <td>
                                                <select name="wpuxss_eml_lib_options[media_order]" id="wpuxss_eml_lib_options_media_order">
                                                    <option value="ASC" <?php selected( $eml_lib_options['media_order'], 'ASC' ); ?>><?php _e('Ascending','enhanced-media-library'); ?></option>
                                                    <option value="DESC" <?php selected( $eml_lib_options['media_order'], 'DESC' ); ?>><?php _e('Descending','enhanced-media-library'); ?></option>
                                                </select>
                                                <?php _e('For media library and media popups','enhanced-media-library'); ?>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php submit_button(); ?>

                                </div>

                            </div>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('Media Shortcodes','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <table class="form-table">

                                        <tr>
                                            <th scope="row"><label for="wpuxss_eml_lib_options[enhance_media_shortcodes]"><?php _e('Enhanced media shortcodes','enhanced-media-library'); ?></label></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php _e('Enhanced media shortcodes','enhanced-media-library'); ?></span></legend>
                                                    <label><input name="wpuxss_eml_lib_options[enhance_media_shortcodes]" type="hidden" value="0" /><input name="wpuxss_eml_lib_options[enhance_media_shortcodes]" type="checkbox" value="1" <?php checked( true, (bool) $eml_lib_options['enhance_media_shortcodes'], true ); ?> /> <?php _e('Enhance WordPress media shortcodes to make them understand media taxonomies, upload date, and media items number limit','enhanced-media-library'); ?></label>
                                                    <p class="description"><?php _e( 'Gallery example:', 'enhanced-media-library' );  ?> [gallery media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                    <p class="description"><?php _e( 'Audio playlist example:', 'enhanced-media-library' ); ?> [playlist media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                    <p class="description"><?php _e( 'Video playlist example:', 'enhanced-media-library' ); ?> [playlist type="video" media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                    <p class="description"><?php
                                                    printf( __( '%sWarning:%s Incompatibility with other gallery plugins or themes possible!', 'enhanced-media-library' ), '<strong style="color:red">', '</strong>' );

                                                    printf( __( '%sLearn more%s.', 'enhanced-media-library' ), ' <a href="http://www.wpuxsolutions.com/documents/enhanced-media-library/enhanced-gallery-possible-conflicts/">', '</a> ' );

                                                    printf( __( 'Please check out your gallery front-end and back-end functionality once this option activated. If you find an issue please inform plugin authors at %s or %s.', 'enhanced-media-library' ), '<a href="https://wordpress.org/support/plugin/enhanced-media-library">wordpress.org</a>', '<a href="http://www.wpuxsolutions.com/support/create-new-ticket/">wpuxsolutions.com</a>' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php submit_button(); ?>

                                </div>

                            </div>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('Media Scripts','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <table class="form-table">

                                        <tr>
                                            <th scope="row"><label for="wpuxss_eml_lib_options[frontend_scripts]"><?php _e('Front-end scripts','enhanced-media-library'); ?></label></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php _e('Front-end scripts','enhanced-media-library'); ?></span></legend>
                                                    <label><input name="wpuxss_eml_lib_options[frontend_scripts]" type="hidden" value="0"><input name="wpuxss_eml_lib_options[frontend_scripts]" type="checkbox" value="1" <?php checked( true, (bool) $eml_lib_options['frontend_scripts'], true ); ?> /> Add media filters and checkboxes for a front-end media editor</label>
                                                    <p class="description"><?php _e( 'Try this if you need media filters or categories for visual page builders, front-end forms, etc.', 'enhanced-media-library' );  ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                    </table>

                                    <?php submit_button(); ?>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        <?php
    }



    /**
     *  Displays taxonomies settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_taxonomies_settings() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'enhanced-media-library' ) );

        $processed_taxonomies = eml()->taxonomies->get_processed_taxonomies();

        $title = __('Media Settings','enhanced-media-library'); ?>


        <div id="wpuxss-eml-global-options-wrap" class="wrap">

            <h1><?php echo esc_html( $title ); ?></h1>

            <?php $this->print_media_settings_tabs( 'taxonomies' ); ?>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder">

                    <div id="postbox-container-2" class="postbox-container">

                        <form id="wpuxss-eml-form-taxonomies" method="post" action="options.php">

                            <?php settings_fields( 'media-taxonomies' ); ?>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('Media Taxonomies','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <p><?php _e('Assign following taxonomies to Media Library:','enhanced-media-library'); ?></p>

                                    <?php $html = '';


                                    foreach ( $processed_taxonomies as $taxonomy_name => $params ) {

                                        $taxonomy = get_taxonomy( $taxonomy_name );
                                        // $object_type = (array) $taxonomy->object_type;


                                        // if ( empty( $object_type ) || in_array( 'attachment', $object_type ) && count( $object_type ) == 1 ) {
                                        if ( (bool) $params['media_only'] ) {

                                            $assigned = (bool) $params['assigned'];
                                            $eml_media = (bool) $params['eml_media'];

                                            if ( $eml_media )
                                                $li_class = 'wpuxss-eml-taxonomy';
                                            else
                                                $li_class = 'wpuxss-non-eml-taxonomy';

                                            $html .= '<li class="' . $li_class . '" id="' . $taxonomy->name . '">';

                                            $html .= '<input name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][eml_media]" type="hidden" value="' . $eml_media . '" />';
                                            $html .= '<label><input class="wpuxss-eml-assigned" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][assigned]" type="checkbox" value="1" ' . checked( true, $assigned, false ) . ' title="' . __('Assign Taxonomy','enhanced-media-library') . '" />' . $taxonomy->label . '</label>';
                                            $html .= '<a class="wpuxss-eml-button-edit" title="' . __('Edit Taxonomy','enhanced-media-library') . '" href="javascript:;">' . __('Edit','enhanced-media-library') . ' &darr;</a>';

                                            if ( $eml_media ) {

                                                $html .= '<a class="wpuxss-eml-button-remove" title="' . __('Delete Taxonomy','enhanced-media-library') . '" href="javascript:;">&ndash;</a>';

                                                $html .= '<div class="wpuxss-eml-taxonomy-edit" style="display:none;">';

                                                $html .= '<div class="wpuxss-eml-labels-edit">';
                                                $html .= '<h4>' . __('Labels','enhanced-media-library') . '</h4>';
                                                $html .= '<ul>';
                                                $html .= '<li><label>' . __('Singular','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-singular_name" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][singular_name]" value="' . esc_html($taxonomy->labels->singular_name) . '" /></li>';
                                                $html .= '<li><label>' . __('Plural','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-name" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][name]" value="' . esc_html($taxonomy->labels->name) . '" /></li>';
                                                $html .= '<li><label>' . __('Menu Name','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-menu_name" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][menu_name]" value="' . esc_html($taxonomy->labels->menu_name) . '" /></li>';
                                                $html .= '<li><label>' . __('All','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-all_items" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][all_items]" value="' . esc_html($taxonomy->labels->all_items) . '" /></li>';
                                                $html .= '<li><label>' . __('Edit','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-edit_item" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][edit_item]" value="' . esc_html($taxonomy->labels->edit_item) . '" /></li>';
                                                $html .= '<li><label>' . __('View','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-view_item" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][view_item]" value="' . esc_html($taxonomy->labels->view_item) . '" /></li>';
                                                $html .= '<li><label>' . __('Update','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-update_item" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][update_item]" value="' . esc_html($taxonomy->labels->update_item) . '" /></li>';
                                                $html .= '<li><label>' . __('Add New','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-add_new_item" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][add_new_item]" value="' . esc_html($taxonomy->labels->add_new_item) . '" /></li>';
                                                $html .= '<li><label>' . __('New','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-new_item_name" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][new_item_name]" value="' . esc_html($taxonomy->labels->new_item_name) . '" /></li>';
                                                $html .= '<li><label>' . __('Parent','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-parent_item" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][parent_item]" value="' . esc_html($taxonomy->labels->parent_item) . '" /></li>';
                                                $html .= '<li><label>' . __('Search','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-search_items" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][labels][search_items]" value="' . esc_html($taxonomy->labels->search_items) . '" /></li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';

                                                $html .= '<div class="wpuxss-eml-settings-edit">';
                                                $html .= '<h4>' . __('Settings','enhanced-media-library') . '</h4>';
                                                $html .= '<ul>';
                                                $html .= '<li><label>' . __('Taxonomy Name','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-taxonomy-name" name="" value="' . esc_attr($taxonomy->name) . '" disabled="disabled" /></li>';
                                                $html .= '<li><label>' . __('Hierarchical','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-hierarchical" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][hierarchical]" value="1" ' . checked( 1, $taxonomy->hierarchical, false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Taxonomy archive page','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-tax_archive" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][tax_archive]" value="1" ' . checked( 1, $params['tax_archive'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Column for List View','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-show_admin_column" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][show_admin_column]" value="1" ' . checked( 1, $taxonomy->show_admin_column, false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Filter for List View','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-admin_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][admin_filter]" value="1" ' . checked( 1, (bool) $params['admin_filter'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Filter for Grid View / Media Popup','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-media_uploader_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_uploader_filter]" value="1" ' . checked( 1, (bool) $params['media_uploader_filter'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Edit in Media Popup','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-media_popup_taxonomy_edit" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_popup_taxonomy_edit]" value="1" ' . checked( 1, (bool) $params['media_popup_taxonomy_edit'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Show in Nav Menu','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-show_in_nav_menus" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][show_in_nav_menus]" value="1" ' . checked( 1, $taxonomy->show_in_nav_menus, false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Remember Terms Order (sort)','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-sort" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][sort]" value="1" ' . checked( 1, $taxonomy->sort, false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Show in REST','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-show_in_rest" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][show_in_rest]" value="1" ' . checked( 1, $taxonomy->show_in_rest, false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Rewrite Slug','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-slug" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][rewrite][slug]" value="' . esc_attr($taxonomy->rewrite['slug']) . '" /></li>';
                                                $html .= '<li><label>' . __('Slug with Front','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-with_front" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][rewrite][with_front]" value="1" ' . checked( 1, $taxonomy->rewrite['with_front'], false ) . ' /></li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';

                                                $html .= '</div>';
                                            }
                                            else {

                                                $html .= '<div class="wpuxss-eml-taxonomy-edit" style="display:none;">';

                                                $html .= '<div class="wpuxss-eml-settings-edit">';
                                                $html .= '<h4>' . __('Settings','enhanced-media-library') . '</h4>';
                                                $html .= '<ul>';
                                                $html .= '<li><label>' . __('Taxonomy archive page','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-tax_archive" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][tax_archive]" value="1" ' . checked( 1, (bool) $params['tax_archive'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Filter for List View','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-admin_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][admin_filter]" value="1" ' . checked( 1, (bool) $params['admin_filter'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Filter for Grid View / Media Popup','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-media_uploader_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_uploader_filter]" value="1" ' . checked( 1, (bool) $params['media_uploader_filter'], false ) . ' /></li>';
                                                $html .= '<li><label>' . __('Edit in Media Popup','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-media_popup_taxonomy_edit" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_popup_taxonomy_edit]" value="1" ' . checked( 1, (bool) $params['media_popup_taxonomy_edit'], false ) . ' /></li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';
                                                $html .= '</div>';
                                            }
                                            $html .= '</li>';
                                        }
                                    } // foreach ( $processed_taxonomies )

                                    $html .= '<li class="wpuxss-eml-clone" style="display:none">';
                                    $html .= '<input name="" type="hidden" class="wpuxss-eml-eml_media" value="1" />';
                                    $html .= '<input name="" type="hidden" class="wpuxss-eml-create_taxonomy" value="1" />';
                                    $html .= '<label class="wpuxss-eml-taxonomy-label"><input class="wpuxss-eml-assigned" name="" type="checkbox" class="wpuxss-eml-assigned" value="1" checked="checked" title="' . __('Assign Taxonomy','enhanced-media-library') . '" />' . '<span>' . __('New Taxonomy','enhanced-media-library') . '</span></label>';

                                    $html .= '<a class="wpuxss-eml-button-remove" title="' . __('Delete Taxonomy','enhanced-media-library') . '" href="javascript:;">&ndash;</a>';

                                    $html .= '<div class="wpuxss-eml-taxonomy-edit">';

                                    $html .= '<div class="wpuxss-eml-labels-edit">';
                                    $html .= '<h4>' . __('Labels','enhanced-media-library') . '</h4>';
                                    $html .= '<ul>';
                                    $html .= '<li><label>' . __('Singular','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-singular_name" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Plural','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-name" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Menu Name','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-menu_name" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('All','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-all_items" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Edit','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-edit_item" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('View','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-view_item" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Update','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-update_item" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Add New','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-add_new_item" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('New','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-new_item_name" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Parent','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-parent_item" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Search','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-search_items" name="" value="" /></li>';
                                    $html .= '</ul>';
                                    $html .= '</div>';

                                    $html .= '<div class="wpuxss-eml-settings-edit">';
                                    $html .= '<h4>' . __('Settings','enhanced-media-library') . '</h4>';
                                    $html .= '<ul>';
                                    $html .= '<li><label>' . __('Taxonomy Name','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-taxonomy-name" name="" value="" disabled="disabled" /></li>';
                                    $html .= '<li><label>' . __('Hierarchical','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-hierarchical" name="" value="1" checked="checked" /></li>';
                                    $html .= '<li><label>' . __('Taxonomy archive page','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-tax_archive" name="" value="1" checked="checked" /></li>';
                                    $html .= '<li><label>' . __('Column for List View','enhanced-media-library') . '</label><input class="wpuxss-eml-show_admin_column" type="checkbox" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Filter for List View','enhanced-media-library') . '</label><input class="wpuxss-eml-admin_filter" type="checkbox"  name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Filter for Grid View / Media Popup','enhanced-media-library') . '</label><input class="wpuxss-eml-media_uploader_filter" type="checkbox" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Edit in Media Popup','enhanced-media-library') . '</label><input class="wpuxss-eml-media_popup_taxonomy_edit" type="checkbox" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Show in Nav Menu','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-show_in_nav_menus" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Remember Terms Order (sort)','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-sort" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Show in REST','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-show_in_rest" name="" value="1" /></li>';
                                    $html .= '<li><label>' . __('Rewrite Slug','enhanced-media-library') . '</label><input type="text" class="wpuxss-eml-slug" name="" value="" /></li>';
                                    $html .= '<li><label>' . __('Slug with Front','enhanced-media-library') . '</label><input type="checkbox" class="wpuxss-eml-with_front" name="" value="1" checked="checked" /></li>';
                                    $html .= '</ul>';
                                    $html .= '</div>';

                                    $html .= '</div>';
                                    $html .= '</li>'; ?>

                                    <?php if ( ! empty( $html ) ) : ?>

                                        <ul class="wpuxss-eml-settings-list wpuxss-eml-media-taxonomy-list">
                                            <?php echo $html; ?>
                                        </ul>
                                        <div class="wpuxss-eml-button-container-right"><a class="add-new-h2 wpuxss-eml-button-create-taxonomy" href="javascript:;">+ <?php _e( 'Add New Taxonomy', 'enhanced-media-library' ); ?></a></div>
                                    <?php endif; ?>

                                    <?php submit_button(); ?>
                                </div>

                            </div>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('Non-Media Taxonomies','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <p><?php _e('Assign following taxonomies to Media Library:','enhanced-media-library'); ?></p>

                                    <?php $unuse = array( 'revision', 'nav_menu_item', 'attachment' );

                                    foreach ( get_post_types( array(),'objects' ) as $post_type ) {

                                        if ( ! in_array( $post_type->name, $unuse ) ) {

                                            $taxonomies = get_object_taxonomies( $post_type->name, 'objects' );

                                            if ( ! empty( $taxonomies ) ) {

                                                $html = '';

                                                foreach ( $taxonomies as $taxonomy ) {

                                                    if ( ! array_key_exists( $taxonomy->name, $processed_taxonomies ) ) {
                                                        continue;
                                                    }

                                                    $html .= '<li class="wpuxss-non-eml-taxonomy" id="' . $taxonomy->name . '">';
                                                    $html .= '<input name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][eml_media]" type="hidden" value="' . (int) $processed_taxonomies[$taxonomy->name]['eml_media'] . '" />';
                                                    $html .= '<label><input class="wpuxss-eml-assigned" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][assigned]" type="checkbox" value="1" ' . checked( 1, (bool) $processed_taxonomies[$taxonomy->name]['assigned'], false ) . ' title="' . __('Assign Taxonomy','enhanced-media-library') . '" />' . $taxonomy->label . '</label>';
                                                    $html .= '<a class="wpuxss-eml-button-edit" title="' . __('Edit Taxonomy','enhanced-media-library') . '" href="javascript:;">' . __('Edit','enhanced-media-library') . ' &darr;</a>';
                                                    $html .= '<div class="wpuxss-eml-taxonomy-edit" style="display:none;">';

                                                    $html .= '<h4>' . __('Settings','enhanced-media-library') . '</h4>';
                                                    $html .= '<ul>';
                                                    // $html .= '<li><input type="checkbox" class="wpuxss-eml-tax_archive" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][tax_archive]" id="wpuxss_eml_taxonomies-' . $taxonomy->name . '-tax_archive" value="1" ' . checked( 1, (bool) $processed_taxonomies[$taxonomy->name]['tax_archive'], false ) . ' /><label for="wpuxss_eml_taxonomies-' . $taxonomy->name . '-tax_archive">' . __('Taxonomy archive page','enhanced-media-library') . '</label></li>';
                                                    $html .= '<li><input type="checkbox" class="wpuxss-eml-admin_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][admin_filter]" id="wpuxss_eml_taxonomies-' . $taxonomy->name . '-admin_filter" value="1" ' . checked( 1, (bool) $processed_taxonomies[$taxonomy->name]['admin_filter'], false ) . ' /><label for="wpuxss_eml_taxonomies-' . $taxonomy->name . '-admin_filter">' . __('Filter for List View','enhanced-media-library') . '</label></li>';
                                                    $html .= '<li><input type="checkbox" class="wpuxss-eml-media_uploader_filter" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_uploader_filter]" id="wpuxss_eml_taxonomies-' . $taxonomy->name . '-media_uploader_filter" value="1" ' . checked( 1, (bool) $processed_taxonomies[$taxonomy->name]['media_uploader_filter'], false ) . ' /><label for="wpuxss_eml_taxonomies-' . $taxonomy->name . '-media_uploader_filter">' . __('Filter for Grid View / Media Popup','enhanced-media-library') . '</label></li>';
                                                    $html .= '<li><input type="checkbox" class="wpuxss-eml-media_popup_taxonomy_edit" name="wpuxss_eml_taxonomies[' . $taxonomy->name . '][media_popup_taxonomy_edit]" id="wpuxss_eml_taxonomies-' . $taxonomy->name . '-media_popup_taxonomy_edit" value="1" ' . checked( 1, (bool) $processed_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'], false ) . ' /><label for="wpuxss_eml_taxonomies-' . $taxonomy->name . '-media_popup_taxonomy_edit">' . __('Edit in Media Popup','enhanced-media-library') . '</label></li>';

                                                    $options = '';
                                                    $html .= apply_filters( 'wpuxss_eml_extend_non_media_taxonomy_options', $options, $taxonomy, $post_type, $processed_taxonomies );

                                                    $html .= '</ul>';

                                                    $html .= '</div>';
                                                    $html .= '</li>';
                                                } // foreach ( $taxonomies ) ?>

                                                <?php if ( ! empty( $html ) ) : ?>

                                                    <h4><?php echo $post_type->label; ?></h4>
                                                    <ul class="wpuxss-eml-settings-list wpuxss-eml-non-media-taxonomy-list">
                                                        <?php echo $html; ?>
                                                    </ul>

                                                <?php endif;
                                            } // if
                                        } // if
                                    } // foreach ( get_post_types )

                                    submit_button(); ?>

                                </div>

                            </div>

                            <h2><?php _e('Options','enhanced-media-library'); ?></h2>

                            <?php $eml_tax_options = eml()->get_option( 'tax_options' ); ?>

                            <div class="postbox">

                                <h3 class="hndle"><?php _e('For All Taxonomies Assigned to Media Library','enhanced-media-library'); ?></h3>

                                <div class="inside">

                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('Assign all like hierarchical','enhanced-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php _e('Assign all like hierarchical','enhanced-media-library'); ?></span></legend>
                                                    <label><input name="wpuxss_eml_tax_options[edit_all_as_hierarchical]" type="hidden" value="0" /><input name="wpuxss_eml_tax_options[edit_all_as_hierarchical]" type="checkbox" value="1" <?php checked( true, (bool) $eml_tax_options['edit_all_as_hierarchical'], true ); ?> /> <?php _e('Show non-hierarchical taxonomies like hierarchical in Grid View / Media Popup','enhanced-media-library'); ?></label>
                                                </fieldset>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><?php _e('Force filters','enhanced-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php _e('Force filters','enhanced-media-library'); ?></span></legend>
                                                    <label><input name="wpuxss_eml_tax_options[force_filters]" type="hidden" value="0" /><input name="wpuxss_eml_tax_options[force_filters]" type="checkbox" value="1" <?php checked( true, (bool) $eml_tax_options['force_filters'], true ); ?> /> <?php _e('Show media filters for ANY Media Popup','enhanced-media-library'); ?></label>
                                                    <p class="description"><?php _e( 'Try this if filters are not shown for third-party plugins or themes.', 'enhanced-media-library' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><?php _e('Show count','enhanced-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php _e('Show count','enhanced-media-library'); ?></span></legend>
                                                    <label><input name="wpuxss_eml_tax_options[show_count]" type="hidden" value="0" /><input name="wpuxss_eml_tax_options[show_count]" type="checkbox" value="1" <?php checked( true, (bool) $eml_tax_options['show_count'], true ); ?> /> <?php _e('Show item count per category for media filters','enhanced-media-library'); ?></label>
                                                    <p class="description"><?php _e( 'Turning off this option can significantly reduce loading time for media library especially in case you have a lot of categories.', 'enhanced-media-library' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                    </table>

                                    <?php submit_button(); ?>

                                </div>

                            </div>

                            <?php do_action( 'wpuxss_eml_extend_taxonomies_option_page' ); ?>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        <?php
    }



    /**
     *  Displays MIME types settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_mime_types_settings() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_die( __('You do not have sufficient permissions to access this page.','enhanced-media-library') );

        $eml_mime_types = eml()->get_option( 'mime_types' );

        $title = __('Media Settings','enhanced-media-library'); ?>

        <div id="wpuxss-eml-global-options-wrap" class="wrap">

            <h1>
                <?php echo esc_html( $title ); ?>
                <a class="add-new-h2 wpuxss-eml-button-create-mime" href="javascript:;">+ <?php _e('Add New MIME Type','enhanced-media-library'); ?></a>
            </h1>

            <?php $this->print_media_settings_tabs( 'mimetypes' ); ?>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder">

                    <div id="postbox-container-2" class="postbox-container">

                        <form method="post" action="options.php" id="wpuxss-eml-form-mimetypes">

                            <?php settings_fields( 'mime-types' ); ?>

                            <?php $this->print_mime_types_buttons(); ?>

                            <table class="wpuxss-eml-mime-type-list wp-list-table widefat" cellspacing="0">
                                <thead>
                                <tr>
                                    <th scope="col" class="manage-column wpuxss-eml-column-extension"><?php _e('Extension','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-mime"><?php _e('MIME Type','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-singular"><?php _e('Singular Label','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-plural"><?php _e('Plural Label','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-filter"><?php _e('Add Filter','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-upload"><?php _e('Allow Upload','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-delete"></th>
                                </tr>
                                </thead>


                                <tbody>

                                <?php
                                $allowed_mimes = get_allowed_mime_types();
                                $all_mimes = wp_get_mime_types();
                                ksort( $all_mimes, SORT_STRING );
                                ?>

                                <?php foreach ( $all_mimes as $type => $mime ) :

                                    if ( isset( $eml_mime_types[$type] ) ) :

                                        $label = '<code>'. str_replace( '|', '</code>, <code>', $type ) .'</code>';

                                        $allowed = false;
                                        if ( array_key_exists( $type,$allowed_mimes ) )
                                            $allowed = true; ?>

                                        <tr>
                                        <td id="<?php echo $type; ?>"><?php echo $label; ?></td>
                                        <td><code><?php echo $mime; ?></code><input type="hidden" class="wpuxss-eml-mime" name="wpuxss_eml_mimes[<?php echo $type; ?>][mime]" value="<?php echo $eml_mime_types[$type]['mime']; ?>" /></td>
                                        <td><input type="text" name="wpuxss_eml_mimes[<?php echo $type; ?>][singular]" value="<?php echo esc_html($eml_mime_types[$type]['singular']); ?>" /></td>
                                        <td><input type="text" name="wpuxss_eml_mimes[<?php echo $type; ?>][plural]" value="<?php echo esc_html($eml_mime_types[$type]['plural']); ?>" /></td>
                                        <td class="checkbox_td"><input type="checkbox" name="wpuxss_eml_mimes[<?php echo $type; ?>][filter]" title="<?php _e('Add Filter','enhanced-media-library'); ?>" value="1" <?php checked(1, (bool) $eml_mime_types[$type]['filter']); ?> /></td>
                                        <td class="checkbox_td"><input type="checkbox" name="wpuxss_eml_mimes[<?php echo $type; ?>][upload]" title="<?php _e('Allow Upload','enhanced-media-library'); ?>" value="1" <?php checked(true, $allowed); ?> /></td>
                                        <td><a class="wpuxss-eml-button-remove" title="<?php _e('Delete MIME Type','enhanced-media-library'); ?>" href="javascript:;">&ndash;</a></td>
                                        </tr>

                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <tr class="wpuxss-eml-clone" style="display:none;">
                                    <td><input type="text" class="wpuxss-eml-type" placeholder="jpg|jpeg|jpe" /></td>
                                    <td><input type="text" class="wpuxss-eml-mime" placeholder="image/jpeg" /></td>
                                    <td><input type="text" class="wpuxss-eml-singular" placeholder="Image" /></td>
                                    <td><input type="text" class="wpuxss-eml-plural" placeholder="Images" /></td>
                                    <td class="checkbox_td"><input type="checkbox" class="wpuxss-eml-filter" title="<?php _e('Add Filter','enhanced-media-library'); ?>" value="1" /></td>
                                    <td class="checkbox_td"><input type="checkbox" class="wpuxss-eml-upload" title="<?php _e('Allow Upload','enhanced-media-library'); ?>" value="1" /></td>
                                    <td><a class="wpuxss-eml-button-remove" title="<?php _e('Delete MIME Type','enhanced-media-library'); ?>" href="javascript:;">&ndash;</a></td>
                                </tr>

                                </tbody>
                                <tfoot>
                                <tr>
                                    <th scope="col" class="manage-column wpuxss-eml-column-extension"><?php _e('Extension','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-mime"><?php _e('MIME Type','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-singular"><?php _e('Singular Label','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-plural"><?php _e('Plural Label','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-filter"><?php _e('Add Filter','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-upload"><?php _e('Allow Upload','enhanced-media-library'); ?></th>
                                    <th scope="col" class="manage-column wpuxss-eml-column-delete"></th>
                                </tr>
                                </tfoot>
                            </table>

                            <?php $this->print_mime_types_buttons(); ?>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        <?php
    }



    /**
     *  Displays MIME types buttons for settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_mime_types_buttons() { ?>

        <p class="submit">
            <?php submit_button( __( 'Save Changes', 'enhanced-media-library' ), 'primary', 'eml-save-mime-types-settings', false ); ?>

            <input type="button" name="eml-restore-mime-types-settings" id="eml-restore-mime-types-settings" class="button" value="<?php _e('Restore WordPress default MIME Types','enhanced-media-library'); ?>">
        </p>

        <?php
    }



    /**
     *  Displays EML utility settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_eml_utility_settings() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_die( __('You do not have sufficient permissions to access this page.','enhanced-media-library') );
        ?>

        <div id="wpuxss-eml-global-options-wrap" class="wrap">

            <h2><?php echo( eml()->get_option( 'name') . ' ' ); _e( 'Settings', 'enhanced-media-library' ); ?></h2>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder columns-2">

                    <div id="postbox-container-2" class="postbox-container">

                        <div class="postbox">

                            <h3 class="hndle"><?php _e( 'Export', 'enhanced-media-library' ); ?></h3>

                            <div class="inside">

                                <p><?php _e( 'Plugin settings tabs <strong>Media Library</strong>, <strong>Media Taxonomies</strong>, and <strong>MIME Types</strong> will be exported to a configuration file. It allows you to easily import the configuration into another website.', 'enhanced-media-library' ); ?></p>

                                <form method="post">

                                    <?php wp_nonce_field( 'eml_settings_export_nonce', 'eml-settings-export-nonce' ); ?>
                                    <?php submit_button( __( 'Export Plugin Settings', 'enhanced-media-library' ), 'primary', 'eml-settings-export' ); ?>
                                </form>

                            </div>

                        </div>


                        <div class="postbox">

                            <h3 class="hndle"><?php _e( 'Import', 'enhanced-media-library' ); ?></h3>

                            <div class="inside">

                                <p><?php _e( 'Plugin settings tabs <strong>Media Library</strong>, <strong>Media Taxonomies</strong>, and <strong>MIME Types</strong> will be imported from a configuration file which can be obtained by exporting the settings on another website using the export button above.', 'enhanced-media-library' ); ?></p>
                                <p><?php _e( 'All plugin settings will be overridden by the import. You will have a chance to restore current data from an automatic backup in case you are not satisfied with the result of the import.', 'enhanced-media-library' ); ?></p>

                                <form method="post" enctype="multipart/form-data">

                                    <p><input type="file" name="import_file"/></p>

                                    <?php wp_nonce_field( 'eml_settings_import_nonce', 'eml-settings-import-nonce' ); ?>
                                    <?php submit_button( __( 'Import Plugin Settings', 'enhanced-media-library' ), 'primary', 'eml-settings-import' ); ?>
                                </form>

                            </div>

                        </div>


                        <?php $wpuxss_eml_backup = get_option( 'wpuxss_eml_backup' ); ?>

                        <div class="postbox">

                            <h3 class="hndle"><?php _e( 'Restore', 'enhanced-media-library' ); ?></h3>

                            <div class="inside">

                                <?php if ( empty( $wpuxss_eml_backup ) ) : ?>

                                    <p><?php _e( 'No backup available at the moment.', 'enhanced-media-library' ); ?></p>

                                    <p><?php _e( 'Backup will be created automatically before any import operation.', 'enhanced-media-library' ); ?></p>

                                <?php else : ?>

                                    <p><?php _e( 'The backup has been automatically created before the latest import operation.', 'enhanced-media-library' ); ?></p>
                                    <form method="post">

                                        <?php wp_nonce_field( 'eml_settings_restore_nonce', 'eml-settings-restore-nonce' ); ?>
                                        <?php submit_button( __( 'Restore Settings from the Backup', 'enhanced-media-library' ), 'primary', 'eml-settings-restore' ); ?>
                                    </form>

                                <?php endif; ?>


                            </div>

                        </div>


                        <div class="postbox">

                            <h3 class="hndle"><?php _e( 'Complete Cleanup', 'enhanced-media-library' ); ?></h3>

                            <div class="inside">

                                <?php $eml_taxonomies = eml()->taxonomies->get_processed_taxonomies( array( 'eml_media' => true ) ); ?>

                                <ul>
                                    <li><strong><?php _e( 'What will be deleted:', 'enhanced-media-library' ); ?></strong></li>
                                    <?php foreach( (array) $eml_taxonomies as $taxonomy => $params ) : ?>
                                        <li><?php _e( 'All', 'enhanced-media-library' );
                                        echo ' ' . $params['labels']['name']; ?></li>
                                    <?php endforeach; ?>
                                    <li><?php _e( 'All plugin options', 'enhanced-media-library' ); ?></li>
                                    <li><?php _e( 'All plugin backups stored in database', 'enhanced-media-library' ); ?></li>
                                </ul>

                                <ul>
                                    <li><strong><?php _e( 'What will remain intact:', 'enhanced-media-library' ); ?></strong></li>
                                    <li><?php _e( 'All media items', 'enhanced-media-library' ); ?></li>
                                    <li><?php _e( 'All taxonomies not listed above', 'enhanced-media-library' ); ?></li>
                                </ul>

                                <p><?php _e( 'The plugin cannot delete itself because of security reason. Please delete it manually from plugin list after cleanup.', 'enhanced-media-library' ); ?></p>

                                <p><strong style="color:red;"><?php _e( 'If you are not sure about this operation please create a backup of your database prior to cleanup!', 'enhanced-media-library' ); ?></strong></p>

                                <form id="eml-form-cleanup" method="post">

                                    <input type='hidden' name='eml-settings-cleanup' />
                                    <?php wp_nonce_field( 'eml_settings_cleanup_nonce', 'eml-settings-cleanup-nonce' ); ?>
                                    <?php submit_button( __( 'Delete All Data & Deactivate', 'enhanced-media-library' ), 'primary', 'eml-settings-cleanup' ); ?>
                                </form>

                            </div>

                        </div>


                        <?php do_action( 'wpuxss_eml_extend_settings_page' ); ?>

                    </div>

                    <div id="postbox-container-1" class="postbox-container">

                        <?php $this->print_eml_credits(); ?>

                    </div>

                </div>

            </div>

        </div>

        <?php
    }



    /**
     *  Displays EML credits.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_eml_credits() {

        $version = eml()->version;
        $eml_name = eml()->get_option( 'name' ); ?>

        <div class="postbox" id="wpuxss-credits">

            <h3 class="hndle"><?php echo( $eml_name . ' ' . $version ); ?></h3>

            <div class="inside">

                <h4><?php _e( 'Changelog', 'enhanced-media-library' ); ?></h4>
                <p><?php _e( 'What\'s new in', 'enhanced-media-library' ); ?> <a href="http://wordpress.org/plugins/enhanced-media-library/changelog/"><?php _e( 'version', 'enhanced-media-library' ); echo ' ' . $version; ?></a>.</p>

                <?php if ( 'Enhanced Media Library PRO' !== $eml_name ) { ?>

                    <h4>Enhanced Media Library PRO</h4>
                    <p><?php _e( 'More features under the hood', 'enhanced-media-library' ); ?> <a href="http://www.wpuxsolutions.com/plugins/enhanced-media-library/">www.wpuxsolutions.com</a>.</p>

                <?php } ?>

                <h4><?php _e( 'Support', 'enhanced-media-library' ); ?></h4>
                <p><?php _e( 'Feel free to ask for help on', 'enhanced-media-library' ); ?> <a href="http://www.wpuxsolutions.com/support/">www.wpuxsolutions.com</a>. <?php _e( 'Support is free for both versions of the plugin.', 'enhanced-media-library' ); ?></p>

                <h4><?php _e( 'Plugin rating', 'enhanced-media-library' ); ?> <span class="dashicons dashicons-thumbs-up"></span></h4>
                <p><?php _e( 'Please', 'enhanced-media-library' ); ?> <a href="http://wordpress.org/support/view/plugin-reviews/enhanced-media-library"><?php _e( 'vote for the plugin', 'enhanced-media-library' ); ?></a>. <?php _e( 'Thanks!', 'enhanced-media-library' ); ?></p>

                <h4><?php _e( 'Other plugins you may find useful', 'enhanced-media-library' ); ?></h4>
                <ul>
                    <li><a href="http://wordpress.org/plugins/toolbar-publish-button/">Toolbar Publish Button</a></li>
                </ul>

                <div class="author">
                    <span><a href="http://www.wpuxsolutions.com/">wpUXsolutions</a> by <a class="logo-webbistro" href="http://twitter.com/webbistro"><span class="icon-webbistro">@</span>webbistro</a></span>
                </div>

            </div>

        </div>

        <?php
    }



    /**
     *  Enqueues scripts for media library settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_media_library_settings_page_scripts() {

        $version = eml()->version;
        $dir = eml()->get_option( 'dir' );


        wp_enqueue_script( 'eml-media-library-settings', $dir . 'js/eml-media-library-settings.js', array( 'jquery' ), $version, true );
    }



    /**
     *  Enqueues scripts for taxonomies settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_taxonomies_settings_page_scripts() {

        $version = eml()->version;
        $dir = eml()->get_option( 'dir' );


        wp_enqueue_script( 'eml-taxonomies-settings', $dir . 'js/eml-taxonomies-settings.js', array( 'jquery', 'underscore', 'eml-admin' ), $version, true );

        wp_localize_script( 'eml-taxonomies-settings', 'wpuxss_eml_taxonomies_options_l10n_data', array(
            'edit' => __( 'Edit', 'enhanced-media-library' ),
            'close' => __( 'Close', 'enhanced-media-library' ),
            'view' => __( 'View', 'enhanced-media-library' ),
            'update' => __( 'Update', 'enhanced-media-library' ),
            'add_new' => __( 'Add New', 'enhanced-media-library' ),
            'new' => __( 'New', 'enhanced-media-library' ),
            'name' => __( 'Name', 'enhanced-media-library' ),
            'parent' => __( 'Parent', 'enhanced-media-library' ),
            'all' => __( 'All', 'enhanced-media-library' ),
            'search' => __( 'Search', 'enhanced-media-library' ),

            'tax_new' => __( 'New Taxonomy', 'enhanced-media-library' ),

            'tax_deletion_confirm_title' => __( 'Remove Taxonomy', 'enhanced-media-library' ),
            'tax_deletion_confirm_text_p1' => '<p>' . __( 'Taxonomy will be removed.', 'enhanced-media-library' ) . '</p>',
            'tax_deletion_confirm_text_p2' => '<p>' . __( 'Taxonomy terms (categories) will remain intact in the database. If you create a taxonomy with the same name in the future, its terms (categories) will be available again.', 'enhanced-media-library' ) . '</p>',
            'tax_deletion_confirm_text_p3' => '<p>' . __( 'Media items will remain intact.', 'enhanced-media-library' ) . '</p>',
            'tax_deletion_confirm_text_p4' => '<p>' . __( 'Are you still sure?', 'enhanced-media-library' ) . '</p>',
            'tax_deletion_yes' => __( 'Yes, remove taxonomy', 'enhanced-media-library' ),

            'tax_error_duplicate_title' => __( 'Duplicate', 'enhanced-media-library' ),
            'tax_error_duplicate_text' => __( 'Taxonomy with the same name already exists. Please chose other one.', 'enhanced-media-library' ),

            'tax_error_empty_fileds_title' => __( 'Empty Fields', 'enhanced-media-library' ),
            'tax_error_empty_both' => __( 'Please choose Singular and Plural names for all new taxomonies.', 'enhanced-media-library' ),
            'tax_error_empty_singular' => __( 'Please choose Singular name for all new taxomonies.', 'enhanced-media-library' ),
            'tax_error_empty_plural' => __( 'Please choose Plural Name for all new taxomonies.', 'enhanced-media-library' ),

            'okay' => __( 'Ok', 'enhanced-media-library' ),
            'cancel' => __( 'Cancel', 'enhanced-media-library' ),

            'sync_warning_title' => __( 'Synchronize Now', 'enhanced-media-library' ),
            'sync_warning_text' => __( 'This operation cannot be canceled! Are you still sure?', 'enhanced-media-library' ),
            'sync_warning_yes' => __( 'Synchronize', 'enhanced-media-library' ),
            'sync_warning_no' => __( 'Cancel', 'enhanced-media-library' ),
            'in_progress_sync_text' => __( 'Synchronizing...', 'enhanced-media-library' ),

            'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' )
        ));
    }



    /**
     *  Enqueues scripts for MIME types settings page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_mime_types_settings_page_scripts() {

        $version = eml()->version;
        $dir = eml()->get_option( 'dir' );


        wp_enqueue_script( 'eml-mime-types-settings', $dir . 'js/eml-mime-types-settings.js', array( 'jquery', 'underscore' ), $version, true );

        wp_localize_script( 'eml-mime-types-settings', 'wpuxss_eml_mimetype_options_l10n_data', array(
            'mime_restoring_confirm_title' => __( 'Restore WordPress default MIME Types', 'enhanced-media-library' ),
            'mime_restoring_confirm_text' => __( 'Warning! All your custom MIME Types will be deleted by this operation.', 'enhanced-media-library' ),
            'mime_restoring_yes' => __( 'Restore Defaults', 'enhanced-media-library' ),
            'in_progress_restoring_text' => __( 'Restoring...', 'enhanced-media-library' ),

            'cancel' => __( 'Cancel', 'enhanced-media-library' ),

            'mime_error_empty_fields' => __( 'Please fill into all fields.', 'enhanced-media-library' ),
            'mime_error_duplicate' => __( 'Duplicate extensions or MIME types. Please chose other one.', 'enhanced-media-library' )
        ));
    }



    /**
     *  Enqueues scripts EML options page.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function print_eml_options_page_scripts() {

        $version = eml()->version;
        $dir = eml()->get_option( 'dir' );


        wp_enqueue_script( 'eml-options', $dir . 'js/eml-options.js', array( 'jquery', 'underscore', 'eml-admin' ), $version, true );

        wp_localize_script( 'eml-options', 'wpuxss_eml_options_l10n_data', array(
            'cleanup_warning_title' => __( 'Complete Cleanup', 'enhanced-media-library' ),
            'cleanup_warning_text_p1' => '<p>' . __( 'You are about to <strong style="text-transform:uppercase">delete all plugin data</strong> from the database including backups.', 'enhanced-media-library' ) . '</p>',
            'cleanup_warning_text_p2' => '<p>' . __( 'This operation cannot be canceled! Are you still sure?', 'enhanced-media-library') . '</p>',
            'cleanup_warning_yes' => __( 'Yes, delete all data', 'enhanced-media-library' ),
            'in_progress_cleanup_text' => __( 'Cleaning...', 'enhanced-media-library' ),
            'cancel' => __( 'Cancel', 'enhanced-media-library' )
        ));
    }



    /**
     *  Exports plugin settings.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function export_eml_settings() {

        if ( ! isset( $_POST['eml-settings-export'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['eml-settings-export-nonce'], 'eml_settings_export_nonce' ) )
            return;

        if ( ! current_user_can( 'manage_options' ) )
            return;


        $settings = $this->get_settings();

        ignore_user_abort( true );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=eml-settings-' . date('m-d-Y_hia') . '.json' );
        header( "Expires: 0" );

        echo json_encode( $settings );

        exit;
    }



    /**
     *  Imports plugin settings.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function import_eml_settings() {

        if ( ! isset( $_POST['eml-settings-import'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['eml-settings-import-nonce'], 'eml_settings_import_nonce' ) )
            return;

        if ( ! current_user_can( 'manage_options' ) )
            return;


        $import_file = $_FILES['import_file'];

        if ( empty( $import_file['tmp_name'] ) ) {

            add_settings_error(
                'eml-settings',
                'eml_settings_file_absent',
                __('Please upload a file to import settings.', 'enhanced-media-library'),
                'error'
            );

            return;
        }


        // backup settings
        $settings = $this->get_settings();
        update_option( 'wpuxss_eml_backup', $settings );


        $json_data = file_get_contents( $import_file['tmp_name'] );
        $settings = json_decode( $json_data, true );

        $this->update_settings( $settings );

        add_settings_error(
            'eml-settings',
            'eml_settings_imported',
            __('Plugin settings imported.', 'enhanced-media-library'),
            'updated'
        );

        // TODO: not sure if we actually need this
        //wp_safe_redirect( admin_url( 'options-general.php?page=eml-settings' ) );
        //exit;
    }



    /**
     *  Restores plugin settings from backup.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function restore_eml_settings() {

        if ( ! isset( $_POST['eml-settings-restore'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['eml-settings-restore-nonce'], 'eml_settings_restore_nonce' ) )
            return;

        if ( ! current_user_can( 'manage_options' ) )
            return;


        $wpuxss_eml_backup = get_option( 'wpuxss_eml_backup' );

        $this->update_settings( $wpuxss_eml_backup );

        update_option( 'wpuxss_eml_backup', '' );

        add_settings_error(
            'eml-settings',
            'eml_settings_restored',
            __('Plugin settings restored from the backup.', 'enhanced-media-library'),
            'updated'
        );
    }



    /**
     *  Cleans up ALL plugin settings from the database.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function cleanup_eml_settings() {

        global $wpdb;


        if ( ! isset( $_POST['eml-settings-cleanup'] ) )
            return;

        if ( ! wp_verify_nonce( $_POST['eml-settings-cleanup-nonce'], 'eml_settings_cleanup_nonce' ) )
            return;

        if ( ! current_user_can( 'manage_options' ) )
            return;

        foreach ( eml()->taxonomies->get_processed_taxonomies() as $taxonomy => $params ) {

            $terms = get_terms( $taxonomy, array( 'fields' => 'all', 'get' => 'all' ) );
            $term_pairs = eml()->taxonomies->get_media_term_pairs( $terms, 'id=>tt_id' );

            if ( (bool) $params['eml_media'] ) {

                foreach ( $term_pairs as $id => $tt_id ) {
                    wp_delete_term( $id, $taxonomy );
                }

                $wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );
                delete_option( $taxonomy . '_children' );
            }
            elseif ( ! empty( $term_pairs ) ) {

                $deleted_tt_ids = array();
                $rows2remove_format = join( ', ', array_fill( 0, count( $term_pairs ), '%d' ) );

                $results = $wpdb->get_results( $wpdb->prepare(
                	"
                        SELECT $wpdb->term_relationships.term_taxonomy_id, $wpdb->term_relationships.object_id
                        FROM $wpdb->term_relationships
                        INNER JOIN $wpdb->posts
                        ON $wpdb->term_relationships.object_id = $wpdb->posts.ID
                        WHERE $wpdb->posts.post_type = 'attachment'
                        AND $wpdb->term_relationships.term_taxonomy_id IN ($rows2remove_format)
                	",
                    $term_pairs
                ) );

                foreach ( $results as $result ) {
                    $deleted_tt_ids[$result->object_id][] = $result->term_taxonomy_id;
                }

                foreach( $deleted_tt_ids as $attachment_id => $tt_ids ) {
                    do_action( 'delete_term_relationships', $attachment_id, $tt_ids );
                }

                $removed = $wpdb->query( $wpdb->prepare(
                	"
                        DELETE $wpdb->term_relationships.* FROM $wpdb->term_relationships
                        INNER JOIN $wpdb->posts
                        ON $wpdb->term_relationships.object_id = $wpdb->posts.ID
                        WHERE $wpdb->posts.post_type = 'attachment'
                        AND $wpdb->term_relationships.term_taxonomy_id IN ($rows2remove_format)
                	",
                    $term_pairs
                ) );

                if ( false !== $removed ) {

                    foreach( $deleted_tt_ids as $attachment_id => $tt_ids ) {
                        do_action( 'deleted_term_relationships', $attachment_id, $tt_ids );
                    }
                }
            }
        }


        $options = array(
            'wpuxss_eml_version',
            'wpuxss_eml_taxonomies',
            'wpuxss_eml_lib_options',
            'wpuxss_eml_tax_options',
            'wpuxss_eml_mimes_backup',
            'wpuxss_eml_mimes',
            'wpuxss_eml_backup',
            // 'wpuxss_eml_pro_bulkedit_savebutton_off',
            // 'wpuxss_eml_pro_license_key',
        );

        $options = apply_filters( 'wpuxss_eml_extend_cleanup_options', $options );

        foreach ( $options as $option ) {
            delete_option( $option );
        }


        deactivate_plugins( eml()->get_option( 'basename' ) );

        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }



    /**
     *  Returns basic EML settings from the database.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return $settings (array)
     */

    function get_settings() {

        $wpuxss_eml_taxonomies = get_option( 'wpuxss_eml_taxonomies', array() );
        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options', null );
        $wpuxss_eml_tax_options = get_option( 'wpuxss_eml_tax_options', null );
        $wpuxss_eml_mimes = get_option( 'wpuxss_eml_mimes', array() );

        $settings = array (
            'taxonomies' => $wpuxss_eml_taxonomies,
            'lib_options' => $wpuxss_eml_lib_options,
            'tax_options' => $wpuxss_eml_tax_options,
            'mimes' => $wpuxss_eml_mimes,
        );

        return $settings;
    }



    /**
     *  Updates basic EML settings in the database.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $settings (array)
     *  @return N/A
     */

    function update_settings( $settings ) {

        if ( isset( $settings['taxonomies'] ) ) {
            update_option( 'wpuxss_eml_taxonomies', $settings['taxonomies'] );
        }
        if ( isset( $settings['lib_options'] ) ) {
            update_option( 'wpuxss_eml_lib_options', $settings['lib_options'] );
        }
        if ( isset( $settings['tax_options'] ) ) {
            update_option( 'wpuxss_eml_tax_options', $settings['tax_options'] );
        }
        if ( isset( $settings['mimes'] ) ) {
            update_option( 'wpuxss_eml_mimes', $settings['mimes'] );
        }
    }



    /**
     *  Adds settings link to the plugin action links.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function eml_settings_link( $links ) {

        return array_merge(
            array(
                'settings' => '<a href="' . admin_url('options-general.php?page=media') . '">' . __( 'Media Settings', 'enhanced-media-library' ) . '</a>'
            ),
            array(
                'utility' => '<a href="' . admin_url('options-general.php?page=eml-settings') . '">' . __( 'Utility', 'enhanced-media-library' ) . '</a>'
            ),
            $links
        );
    }



    /**
     *  Adds Vote! link to the plugin meta row.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function plugin_row_meta( $links, $file ) {

        if ( eml()->get_option( 'basename' ) == $file ) {

            $links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/enhanced-media-library" target="_blank"><span class="dashicons dashicons-thumbs-up"></span> ' . __( 'Vote!', 'enhanced-media-library' ) . '</a>';
        }

        return $links;
    }


} // class EML_Settings


endif; // class_exists
