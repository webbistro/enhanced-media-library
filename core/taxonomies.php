<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



if ( ! class_exists( 'EML_Taxonomies' ) ) :

class EML_Taxonomies {

    /**
     * Processed terms.
     *
     * @var array
     */
    public $terms = array();

    /**
     * Constructor.
     *
     * @since   3.0
     */

    function __construct() {

        // media assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_media', array( $this, 'enqueue_media_assets' ) );

        // taxonomies actions
        add_action( 'init', array( $this, 'register_eml_media_taxonomies' ) );
        add_action( 'init', array( $this, 'process_taxonomies' ), 999 );
        add_action( 'init', array( $this, 'process_terms' ), 999 );
        add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
        add_action( 'parse_tax_query', array( $this, 'parse_tax_query' ) );
        add_action( 'wp_ajax_save-attachment-compat', array( $this, 'ajax_save_attachment_compat' ), 0 );
        add_action( 'wp_ajax_delete-post', array( $this, 'ajax_delete_post' ), 0 );
        add_action( 'wp_ajax_save-attachment-order', array( $this, 'ajax_save_attachment_order' ), 0 );
        add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 99 );

        // taxonomies filters
        add_filter( 'ajax_query_attachments_args', array( $this, 'ajax_query_attachments_args' ), 20 );
        add_filter( 'wp_dropdown_cats', array( $this, 'wp_dropdown_cats' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
    }



    /**
     *  Enqueues taxonomies scripts for media library list mode.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function enqueue_admin_assets() {

        global $current_screen;


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';


        // scripts for media library list Mode
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode ) {

            wp_enqueue_script( 'eml-media-list-mode' );

            wp_localize_script( 'eml-media-list-mode', 'wpuxss_eml_media_list_l10n', array(
                '$_GET'             => wp_json_encode($_GET),
                'uncategorized'     => __( 'All Uncategorized', 'enhanced-media-library' ),
                'reset_all_filters' => __( 'Reset All Filters', 'enhanced-media-library' )
            ));
        }
    }



    /**
     *  Enqueues media scripts.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function enqueue_media_assets() {

        global $wp_version,
               $current_screen;


        if ( ! is_admin() ) {
            return;
        }


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';


        $tax_options = eml()->get_option( 'tax_options' );
        $lib_options = eml()->get_option( 'lib_options' );
        $media_views_localize = $this->get_media_views_localize();


        // scripts
        wp_enqueue_script( 'eml-media-models' );
        wp_enqueue_script( 'eml-media-views' );


        // localize
        wp_localize_script( 'eml-media-models', 'wpuxss_eml_media_models_l10n', array(
            'media_orderby'   => $lib_options['media_orderby'],
            'media_order'     => $lib_options['media_order'],
            'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' )
        ));

        wp_localize_script( 'eml-media-views', 'wpuxss_eml_media_views_l10n', array(
            'terms'                     => $media_views_localize['terms'],
            'taxonomies'                => $media_views_localize['taxonomies'],
            'filter_taxonomies'         => $media_views_localize['filter_taxonomies'],
            'compat_taxonomies'         => $media_views_localize['compat_taxonomies'],
            'compat_taxonomies_to_hide' => $media_views_localize['compat_taxonomies_to_hide'],
            'is_tax_compat'             => count( $media_views_localize['compat_taxonomies'] ) - count( $media_views_localize['compat_taxonomies_to_hide'] ) > 0 ? 1 : 0,
            'force_filters'             => $tax_options['force_filters'],
            'wp_version'                => $wp_version,
            'uncategorized'             => __( 'All Uncategorized', 'enhanced-media-library' ),
            'filter_by'                 => __( 'Filter by', 'enhanced-media-library' ),
            'in'                        => __( 'All', 'enhanced-media-library' ),
            'not_in'                    => __( 'Not in', 'enhanced-media-library' ),
            'reset_filters'             => __( 'Reset All Filters', 'enhanced-media-library' ),
            'current_screen'            => isset( $current_screen ) ? $current_screen->id : ''
        ));


        // scripts for media library Grid Mode
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {
            wp_enqueue_script( 'eml-media-grid-mode' );
        }
    }



    /**
     *  Retrieves taxonomies and terms to pass them to media library's JS.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return (array)
     */

    function get_media_views_localize() {

        $taxonomies = array();
        $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );
        $filter_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true, 'media_uploader_filter' => true ) );
        $compat_taxonomies_to_hide = $this->get_processed_taxonomies( array( 'assigned' => true, 'media_popup_taxonomy_edit' => false ) );
        $terms_id_tt_id = $this->get_media_term_pairs( $this->terms, 'id=>tt_id' );


        $walker = new EML_Walker_Media_Taxonomy_Uploader_Filter;


        foreach ( array_keys( $filter_taxonomies ) as $taxonomy_name ) {

            $taxonomy_terms = $this->get_taxonomy_terms( $taxonomy_name );

            if ( ! empty( $taxonomy_terms ) ) {

                $formatted_terms = array();
                $terms_id_name = $this->get_media_term_pairs( $taxonomy_terms, 'id=>name' );
                $taxonomy = get_taxonomy( $taxonomy_name );

                $tax_args = array( 'taxonomy' => $taxonomy_name );
                $tax_args['disabled'] = ! current_user_can( $taxonomy->cap->assign_terms );
                $tax_args['selected_cats'] = array();

                $output = call_user_func_array( array( $walker, 'walk' ), array( $taxonomy_terms, 0, $tax_args ) );
                $html = str_replace( '}{', '},{', $output );
                $html = '[' . $html . ']';
                $formatted_terms = json_decode( $html, true );

                $taxonomies[$taxonomy_name] = array(
                    'singular_name' => $taxonomy->labels->singular_name,
                    'plural_name'   => $taxonomy->labels->name,
                    'term_list'     => $formatted_terms,
                    'terms'         => $terms_id_name
                );
            }
        } // foreach

        return array(
            'terms'                     => $terms_id_tt_id,
            'taxonomies'                => $taxonomies,
            'filter_taxonomies'         => array_keys( $filter_taxonomies ),
            'compat_taxonomies'         => array_keys( $processed_taxonomies ),
            'compat_taxonomies_to_hide' => array_keys( $compat_taxonomies_to_hide )
        );
    }



    /**
     *  Registers media taxonomies created by plugin.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function register_eml_media_taxonomies() {

        foreach ( $this->get_processed_taxonomies( array( 'eml_media' => true ) ) as $taxonomy => $params ) {

            if ( empty( $params['labels']['singular_name'] ) || empty( $params['labels']['name'] ) ) {
                continue;
            }

            register_taxonomy(
                $taxonomy,
                'attachment',
                array(
                    'labels' => $params['labels'],
                    'public' => true,
                    'show_admin_column' => (bool) $params['show_admin_column'],
                    'show_in_nav_menus' => (bool) $params['show_in_nav_menus'],
                    'hierarchical' => (bool) $params['hierarchical'],
                    'update_count_callback' => '_eml_update_attachment_term_count',
                    'sort' => (bool) $params['sort'],
                    'show_in_rest' => (bool) $params['show_in_rest'],
                    'query_var' => $taxonomy,
                    'rewrite' => array(
                        'slug' => $params['rewrite']['slug'],
                        'with_front' => (bool) $params['rewrite']['with_front']
                    )
                )
            );
        } // endforeach
    }



    /**
     *  Processes all potential media taxonomies created by third-parties.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function process_taxonomies() {

        global $wp_taxonomies;


        $processed_taxonomies = eml()->get_option( 'taxonomies' );
        $change = false;


        foreach ( get_taxonomies( array( 'show_ui' => true, 'public' => true ), 'objects' ) as $taxonomy => $params ) {

            if ( array_key_exists( $taxonomy, $processed_taxonomies ) ) {
                continue;
            }

            $change = true;

            $processed_taxonomies[$taxonomy] = array(
                'eml_media' => 0,
                'admin_filter' => 0,
                'media_uploader_filter' => 0,
                'media_popup_taxonomy_edit' => 0,
                'taxonomy_auto_assign' => 0
            );

            if ( in_array( 'attachment', $params->object_type ) )
                $processed_taxonomies[$taxonomy]['assigned'] = 1;
            else
                $processed_taxonomies[$taxonomy]['assigned'] = 0;
        } // foreach


        foreach ( $processed_taxonomies as $taxonomy_name => $params ) {

            // if ( (bool) $params['eml_media'] ) {
            //     continue;
            // }


            $taxonomy = get_taxonomy( $taxonomy_name );

            if ( (bool) $params['assigned'] ) {

                register_taxonomy_for_object_type( $taxonomy_name, 'attachment' );

                if ( in_array( 'post', $taxonomy->object_type ) ) {
                    $wp_taxonomies[$taxonomy_name]->update_count_callback = '_eml_update_post_term_count';
                }
            }

            if ( ! (bool) $params['assigned'] ) {

                unregister_taxonomy_for_object_type( $taxonomy_name, 'attachment' );

                if ( in_array( 'post', $taxonomy->object_type ) ) {
                    unset( $wp_taxonomies[$taxonomy_name]->update_count_callback );
                }
            }
        } // foreach


        // TODO: test this carefully
        // clean currently unregistered non-eml taxonomies out of processed taxonomies
        foreach( $processed_taxonomies as $taxonomy => $params ) {

            if ( ! (bool) $params['eml_media'] && ! isset( $taxonomies[$taxonomy] ) ) {
                unset( $processed_taxonomies[$taxonomy] );
            }
        }


        if ( $change ) {

            update_option( 'wpuxss_eml_taxonomies', $processed_taxonomies );
            eml()->update_option( 'taxonomies', $processed_taxonomies );
        }
    }



    /**
     *  Retrieves processed taxonomies filtered by params.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $args (array) : array( 'eml_media' => true, 'assigned' => true, etc. )
     *  @return $taxonomies (array)
     */

    function get_processed_taxonomies( $args = array() ) {

        $taxonomies = eml()->get_option( 'taxonomies' );


        if ( empty( $args ) ) {
            return $taxonomies;
        }

        foreach( $taxonomies as $taxonomy => $params ) {

            foreach( $args as $arg => $value ) {

                if ( (bool) $params[$arg] !== $value ) {
                    unset( $taxonomies[$taxonomy] );
                }
            }
        }

        return $taxonomies;
    }



    /**
     *  Saves terms of all processed taxonomies.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return N/A
     */

    function process_terms() {

        $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );
        $processed_terms = (array) get_terms( array_keys( $processed_taxonomies ), array( 'fields'=>'all', 'get'=>'all' ) );

        $this->terms = $processed_terms;
    }



    /**
     *  Filters terms per processed taxonomy.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $taxonomy (string)
     *  @return $processed_terms (array)
     */

    function get_taxonomy_terms( $taxonomy ) {

        $processed_terms = $this->terms;


        foreach( $processed_terms as $key => $term ) {

            if ( $taxonomy !== $term->taxonomy ) {
                unset( $processed_terms[$key] );
            }
        }

        return array_values( $processed_terms );
    }



    /**
     *  Retrieves term by slug from terms list.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $slug (string)
     *  @param  $taxonomy (string)
     *  @return (array)
     */

    function get_term_by_slug( $slug, $taxonomy ) {

        $processed_terms = $this->get_taxonomy_terms( $taxonomy );


        foreach ( $processed_terms as $key => $term ) {

            if ( $slug === $term->slug ) {
                return $processed_terms[$key];
            }
        }

        return null;
    }



    /**
     *  Enhances tax_query for attachment ajax requests.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function ajax_query_attachments_args( $query ) {

        // print_r($query);

        $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );

        $eml_query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();
        $keys = array(
            'uncategorized'
        );
        // foreach ( get_taxonomies_for_attachments( 'objects' ) as $t ) {
        //     if ( $t->query_var && isset( $query[ $t->query_var ] ) ) {
        //         $keys[] = $t->query_var;
        //     }
        // }
        foreach ( $processed_taxonomies as $taxonomy => $params ) {
            if ( isset( $eml_query[$taxonomy] ) ) {
                $keys[] = $taxonomy;
            }
        }
        $eml_query = array_intersect_key( $eml_query, array_flip( $keys ) );
        $query = array_merge ( $query, $eml_query );

        $uncategorized = ( isset( $query['uncategorized'] ) && $query['uncategorized'] ) ? 1 : 0;

        $tax_query = array();

        // print_r( $keys );
        // print_r( $eml_query );


        foreach ( $processed_taxonomies as $taxonomy_name => $params ) {

            // $taxonomy = get_taxonomy( $taxonomy_name );
            $terms = $this->get_taxonomy_terms( $taxonomy_name );
            $terms_id_tt_id = $this->get_media_term_pairs( $terms, 'id=>tt_id' );

            // print_r($terms);
            // wp_die();


            if ( $uncategorized ) {

                if ( ! empty( $terms_id_tt_id ) ) {

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => $terms_id_tt_id,
                        'operator' => 'NOT IN'
                    );

                    unset( $query['uncategorized'] );
                }
            }
            else {

                // print_r($taxonomy);

                if ( isset( $query[$taxonomy_name] ) && $query[$taxonomy_name] ) {

                    // print_r($query);

                    if( is_numeric( $query[$taxonomy_name] ) ||
                        is_array( $query[$taxonomy_name] ) ) {

                        $tax_query[] = array(
                            'taxonomy' => $taxonomy_name,
                            'field' => 'term_id',
                            'terms' => (array) $query[$taxonomy_name]
                        );
                    }
                    elseif ( 'not_in' === $query[$taxonomy_name] ) {

                        $tax_query[] = array(
                            'taxonomy' => $taxonomy_name,
                            'field' => 'term_id',
                            'terms' => $terms_id_tt_id,
                            'operator' => 'NOT IN',
                        );
                    }
                    elseif ( 'in' === $query[$taxonomy_name] ) {

                        $tax_query[] = array(
                            'taxonomy' => $taxonomy_name,
                            'field' => 'term_id',
                            'terms' => $terms_id_tt_id,
                            'operator' => 'IN',
                        );
                    }

                    unset( $query[$taxonomy_name] );
                }
            }

            // if ( isset( $query[$taxonomy->query_var] ) ) {
            //     unset( $query[$taxonomy->query_var] );
            // }

        } // endforeach

        if ( ! empty( $tax_query ) ) {

            $tax_query['relation'] = 'AND';
            $query['tax_query'] = $tax_query;
        }

        // print_r($query);

        return $query;
    }



    /**
     *  Adds taxonomy filters to media library List Mode.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function restrict_manage_posts() {

        global $current_screen,
               $wp_query;


        $media_library_mode = get_user_option( 'media_library_mode'  ) ? get_user_option( 'media_library_mode'  ) : 'grid';


        if ( ! isset( $current_screen ) || 'upload' !== $current_screen->base || 'list' !== $media_library_mode ) {
            return;
        }

        $eml_tax_options = eml()->get_option( 'tax_options' );
        $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );
        $uncategorized = ( isset( $_REQUEST['attachment-filter'] ) && 'uncategorized' === $_REQUEST['attachment-filter'] ) ? 1 : 0;


        foreach ( $processed_taxonomies as $taxonomy_name => $params ) {

            $taxonomy = get_taxonomy( $taxonomy_name );


            if ( $processed_taxonomies[$taxonomy_name]['admin_filter'] ) {

                echo "<label for='{$taxonomy_name}' class='screen-reader-text'>" . __('Filter by','enhanced-media-library') . " {$taxonomy->labels->singular_name}</label>";

                $selected = ( ! $uncategorized && isset( $wp_query->query[$taxonomy_name] ) ) ? $wp_query->query[$taxonomy_name] : 0;

                wp_dropdown_categories(
                    array(
                        'show_option_all'    =>  __( 'Filter by', 'enhanced-media-library' ) . ' ' . $taxonomy->labels->singular_name,
                        'show_option_in'     =>  '— ' . __( 'All', 'enhanced-media-library' ) . ' ' . $taxonomy->labels->name . ' —',
                        'show_option_not_in' =>  '— ' . __( 'Not in', 'enhanced-media-library' ) . ' ' . $taxonomy->labels->singular_name . ' —',
                        'taxonomy'           =>  $taxonomy_name,
                        'name'               =>  $taxonomy_name,
                        'orderby'            =>  'name',
                        'selected'           =>  $selected,
                        'hierarchical'       =>  true,
                        'show_count'         =>  (bool) $eml_tax_options['show_count'],
                        'hide_empty'         =>  false,
                        'hide_if_empty'      =>  true,
                        'class'              =>  'eml-taxonomy-filters',
                        'walker'             =>  new EML_Walker_CategoryDropdown()
                    )
                );
            }
        } // endforeach
    }



    /**
     *  Modifies taxonomy filters in media library List Mode.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function wp_dropdown_cats( $output, $r ) {

        global $current_screen;


        if ( ! is_admin() || empty( $output ) || ! isset( $current_screen ) ) {
            return $output;
        }


        $media_library_mode = get_user_option( 'media_library_mode' ) ? get_user_option( 'media_library_mode' ) : 'grid';


        if ( 'upload' !== $current_screen->base || 'list' !== $media_library_mode ) {
            return $output;
        }


        $whole_select = $output;
        $options_array = array();

        while ( strlen( $whole_select ) >= 7 && false !== ( $option_pos = strpos( $whole_select, '<option', 7 ) ) ) {

            $options_array[] = substr( $whole_select, 0, $option_pos );
            $whole_select = substr( $whole_select, $option_pos );
        }
        $options_array[] = $whole_select;

        if ( empty( $options_array ) )
            return $output;

        $new_output = '';

        if ( isset( $r['show_option_in'] ) && (bool) $r['show_option_in'] ) {

            $show_option_in = $r['show_option_in'];
            $selected = ( 'in' === strval($r['selected']) ) ? " selected='selected'" : '';
            $new_output .= "\t<option value='in'$selected>$show_option_in</option>\n";
        }

        if ( isset( $r['show_option_not_in'] ) && (bool) $r['show_option_not_in'] ) {

            $show_option_not_in = $r['show_option_not_in'];
            $selected = ( 'not_in' === strval($r['selected']) ) ? " selected='selected'" : '';
            $new_output .= "\t<option value='not_in'$selected>$show_option_not_in</option>\n";
        }

        array_splice( $options_array, 2, 0, $new_output );

        $output = implode('', $options_array);

        return $output;
    }



    /**
     *  Parses tax_query.
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function parse_tax_query( $query ) {

        global $current_screen;


        if ( ! isset( $current_screen ) || ! is_admin() || ! isset( $query->query_vars['order'] ) ) {
            return;
        }


        $media_library_mode = get_user_option( 'media_library_mode' ) ? get_user_option( 'media_library_mode' ) : 'grid';

        if ( 'upload' !== $current_screen->base || 'list' !== $media_library_mode ) {
            return;
        }


        if ( isset( $_REQUEST['category'] ) )
            $query->query['category'] = $query->query_vars['category'] = $_REQUEST['category'];

        if ( isset( $_REQUEST['post_tag'] ) )
            $query->query['post_tag'] = $query->query_vars['post_tag'] = $_REQUEST['post_tag'];

        if ( isset( $query->query_vars['taxonomy'] ) && isset( $query->query_vars['term'] ) ) {

            $tax = $query->query_vars['taxonomy'];
            $term = $this->get_term_by_slug( $query->query_vars['term'], $tax );

            if ( $term ) {

                $query->query_vars[$tax] = $term->term_id;
                $query->query[$tax] = $term->term_id;

                unset( $query->query_vars['taxonomy'] );
                unset( $query->query_vars['term'] );

                unset( $query->query['taxonomy'] );
                unset( $query->query['term'] );
            }
        }


        $tax_query = array();
        $uncategorized = ( isset( $_REQUEST['attachment-filter'] ) && 'uncategorized' === $_REQUEST['attachment-filter'] ) ? 1 : 0;

        foreach ( $this->get_processed_taxonomies( array( 'assigned' => true ) ) as $taxonomy_name => $params ) {

            $terms = $this->get_taxonomy_terms( $taxonomy_name );
            $terms_id_tt_id = $this->get_media_term_pairs( $terms, 'id=>tt_id' );

            if ( ! isset( $_REQUEST['filter_action'] ) && isset( $_REQUEST[$taxonomy_name] ) ) {

                $term = $this->get_term_by_slug( $_REQUEST[$taxonomy_name], $taxonomy_name );

                if ( $term ) {

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => array( $term->term_id )
                    );

                    $query->query_vars[$taxonomy_name] = $term->term_id;
                    $query->query[$taxonomy_name] = $term->term_id;
                }
            }
            elseif ( $uncategorized ) {

                $tax_query[] = array(
                    'taxonomy' => $taxonomy_name,
                    'field' => 'term_id',
                    'terms' => $terms_id_tt_id,
                    'operator' => 'NOT IN',
                );

                if ( isset( $query->query[$taxonomy_name] ) ) unset( $query->query[$taxonomy_name] );
                if ( isset( $query->query_vars[$taxonomy_name] ) ) unset( $query->query_vars[$taxonomy_name] );
            }
            elseif ( isset( $query->query[$taxonomy_name] ) && $query->query[$taxonomy_name] ) {

                if ( is_numeric( $query->query[$taxonomy_name] ) ) {

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => array( $query->query[$taxonomy_name] )
                    );
                }
                elseif ( 'not_in' === $query->query[$taxonomy_name] ) {

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => $terms_id_tt_id,
                        'operator' => 'NOT IN',
                    );
                }
                elseif ( 'in' === $query->query[$taxonomy_name] ) {

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => $terms_id_tt_id,
                        'operator' => 'IN',
                    );
                }
            }
        } // endforeach

        if ( ! empty( $tax_query ) ) {
            $query->tax_query = new WP_Tax_Query( $tax_query );
        }
    }



    /**
     *  Prepares taxonomies for attachment edit.
     *  Based on /wp-admin/includes/media.php
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function attachment_fields_to_edit( $form_fields, $post ) {

        $eml_tax_options = eml()->get_option( 'tax_options' );
        $walker = new EML_Walker_Term_Checklist;
        // $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );


        foreach( $form_fields as $taxonomy => $args ) {

            if ( ! taxonomy_exists( $taxonomy ) /*|| ! array_key_exists( $taxonomy, $processed_taxonomies )*/ ) {
                unset( $form_fields[$taxonomy] );
                continue;
            }

            if ( ! (bool) $args['hierarchical'] && ! (bool) $eml_tax_options['edit_all_as_hierarchical'] ) {
                continue;
            }


            $terms = $this->get_taxonomy_terms( $taxonomy );


            if ( ! empty( $terms ) ) {

                $tax = get_taxonomy( $taxonomy );
                $tax_args = array( 'taxonomy' => $taxonomy );
                $tax_args['disabled'] = ! current_user_can( $tax->cap->assign_terms );
                $tax_args['selected_cats'] = wp_get_object_terms( $post->ID, $taxonomy, array_merge( $tax_args, array( 'fields' => 'ids' ) ) );
                $output = call_user_func_array( array( $walker, 'walk' ), array( $terms, 0, $tax_args ) );
                // $output = wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy, 'checked_ontop' => false, 'echo' => false ) );
                $output = '<ul class="term-list">' . $output . '</ul>';
            }
            else {
                $output = '<ul class="term-list"><li>No ' . $args['label'] . ' found.</li></ul>';
            }

            unset( $form_fields[$taxonomy]['value'] );

            $form_fields[$taxonomy]['input'] = 'html';
            $form_fields[$taxonomy]['html'] = $output;
        } // foreach

        return $form_fields;
    }



    /**
     *  Saves meta including categories for a single attachment.
     *  Based on /wp-admin/includes/ajax-actions.php
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function ajax_save_attachment_compat() {

        if ( ! isset( $_REQUEST['id'] ) )
            wp_send_json_error();

        if ( ! $id = absint( $_REQUEST['id'] ) )
            wp_send_json_error();

        if ( empty( $_REQUEST['attachments'] ) || empty( $_REQUEST['attachments'][ $id ] ) )
            wp_send_json_error();


        $eml_tax_options = eml()->get_option( 'tax_options' );
        $attachment_data = $_REQUEST['attachments'][ $id ];

        check_ajax_referer( 'update-post_' . $id, 'nonce' );

        if ( ! current_user_can( 'edit_post', $id ) )
            wp_send_json_error();

        $post = get_post( $id, ARRAY_A );

        if ( 'attachment' != $post['post_type'] )
            wp_send_json_error();

        /** This filter is documented in wp-admin/includes/media.php */
        $post = apply_filters( 'attachment_fields_to_save', $post, $attachment_data );

        if ( isset( $post['errors'] ) ) {

            $errors = $post['errors']; // @todo return me and display me!
            unset( $post['errors'] );
        }

        wp_update_post( $post );


        $processed_taxonomies = $this->get_processed_taxonomies( array( 'assigned' => true ) );
        $processed_taxonomy_names = array_keys( $processed_taxonomies );


        if ( (bool) $eml_tax_options['show_count'] ) {

            // $terms = get_terms( $processed_taxonomy_names, array( 'fields'=>'all', 'get'=>'all' ) );
            // $terms = $this->terms;
            $term_pairs = $this->get_media_term_pairs( $this->terms, 'id=>tt_id' );
        }


        foreach ( $processed_taxonomy_names as $taxonomy ) {

            if ( isset( $attachment_data[ $taxonomy ] ) ) {

                $term_ids = array_map( 'trim', preg_split( '/,+/', $attachment_data[ $taxonomy ] ) );
            }
            elseif ( isset( $_REQUEST['tax_input'] ) && isset( $_REQUEST['tax_input'][ $taxonomy ] ) ) {

                $term_ids = array_keys( $_REQUEST['tax_input'][ $taxonomy ], 1 );
                $term_ids = array_map( 'intval', $term_ids );
            }

            wp_set_object_terms( $id, $term_ids, $taxonomy, false );

            if ( (bool) $eml_tax_options['show_count'] ) {

                foreach( $term_pairs as $term_id => $tt_id) {
                    $tcount[$term_id] = $this->get_media_term_count( $term_id, $tt_id );
                }
            }
        }

        if ( ! $attachment = wp_prepare_attachment_for_js( $id ) )
            wp_send_json_error();

        if ( (bool) $eml_tax_options['show_count'] )
            $attachment['tcount'] = $tcount;


        wp_send_json_success( $attachment );
    }



    /**
     *  Deletes attachment and cleans up its terms.
     *  Based on /wp-admin/includes/ajax-actions.php
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function ajax_delete_post() {

        if ( empty( $action ) )
            $action = 'delete-post';

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

        check_ajax_referer( "{$action}_$id" );

        if ( ! current_user_can( 'delete_post', $id ) )
            wp_die( -1 );

        if ( ! $post = get_post( $id ) )
            wp_die( 1 );


        if ( 'attachment' === $post->post_type ) {

            $response = array();
            $eml_tax_options = eml()->get_option('tax_options');

            if ( wp_delete_post( $id ) ) {

                if ( (bool) $eml_tax_options['show_count'] ) {

                    $terms = (array) get_terms( get_object_taxonomies( 'attachment', 'names' ), array( 'fields'=>'all', 'get'=>'all' ) );

                    foreach( $this->get_media_term_pairs( $terms, 'id=>tt_id' ) as $term_id => $tt_id ) {
                        $response['tcount'][$term_id] = $this->get_media_term_count( $term_id, $tt_id );
                    }
                }

                wp_send_json_success( $response );
            }
            else
                wp_send_json_error();
        }
        elseif ( wp_delete_post( $id ) )
            wp_die( 1 );
        else
            wp_die( 0 );
    }



    /**
     *  Saves attachments order for custom order option.
     *  Based on /wp-admin/includes/ajax-actions.php
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function ajax_save_attachment_order() {

        global $wpdb;


        if ( ! isset( $_REQUEST['post_id'] ) )
            wp_send_json_error();

        if ( empty( $_REQUEST['attachments'] ) )
            wp_send_json_error();

        if ( $post_id = absint( $_REQUEST['post_id'] ) ) {

            check_ajax_referer( 'update-post_' . $post_id, 'nonce' );

            if ( ! current_user_can( 'edit_post', $post_id ) )
                wp_send_json_error();
        }
        else {
            check_ajax_referer( 'eml-bulk-edit-nonce', 'nonce' );
        }


        $attachments = $_REQUEST['attachments'];
        $attachments2edit = array();

        foreach ( $attachments as $attachment_id => $menu_order ) {

            if ( ! current_user_can( 'edit_post', $attachment_id ) )
                continue;
            if ( ! $attachment = get_post( $attachment_id ) )
                continue;
            if ( 'attachment' != $attachment->post_type )
                continue;

            $attachments2edit[$attachment_id] = $menu_order;
        }


        asort( $attachments2edit );
        $order = array_keys( $attachments2edit );
        $order_format = join( ', ', array_fill( 0, count( $order ), '%d' ) );
        $wpdb->query( 'SELECT @i:=0' );


        $result = $wpdb->query( $wpdb->prepare(
            "
                UPDATE $wpdb->posts SET $wpdb->posts.menu_order = ( @i:=@i+1 )
                WHERE $wpdb->posts.ID IN ( $order_format ) ORDER BY FIELD( $wpdb->posts.ID, $order_format )
            ",
            array_merge( $order, $order )
        ) );


        if ( ! $result ) {
            wp_send_json_error();
        }

        wp_send_json_success();
    }



    /**
     *  Retrieves media term pairs.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $terms (array)
     *  @param  $mode (string)
     *  @return $term_pairs (array)
     */

    function get_media_term_pairs( $terms = array(), $mode = 'id=>tt_id' ) {

        $term_pairs = array();


        foreach( $terms as $term ) {

            if ( 'id=>tt_id' === $mode ) {
                $term_pairs[$term->term_id] = $term->term_taxonomy_id;
                continue;
            }

            if ( 'tt_id=>id' === $mode ) {
                $term_pairs[$term->term_taxonomy_id] = $term->term_id;
                continue;
            }

            if ( 'id=>name' === $mode ) {
                $term_pairs[$term->term_id] = $term->name;
                continue;
            }
        }

        return $term_pairs;
    }



    /**
     *  Re-counts media term items.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  $term_id (int)
     *  @param  $tt_id (int)
     *  @return $count (int)
     */

    function get_media_term_count( $term_id, $tt_id ) {

        global $wpdb;


        $terms = array( $tt_id );

        $children = $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy
        WHERE parent = %d", (int) $term_id ) );


        if ( ! empty( $children ) ) {

            foreach ( $children as $child ) {
                $terms[] = $child->term_taxonomy_id;
            }
        }

        $terms_format = join( ', ', array_fill( 0, count( $terms ), '%d' ) );

        $results = $wpdb->get_results( $wpdb->prepare(
            "
                SELECT ID FROM $wpdb->posts, $wpdb->term_relationships WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_type = 'attachment' AND ( post_status = 'publish' OR post_status = 'inherit' ) AND term_taxonomy_id IN ($terms_format) GROUP BY ID
            ",
            $terms
        ) );

        $count = $results ? $wpdb->num_rows : 0;

        return $count;
    }



    /**
     *  Modifies $query for taxonomy archive page (front-end).
     *
     *  @type   action callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function pre_get_posts( $query ) {

        global $current_screen;

        if ( ! is_admin() && $query->is_main_query() ) {

            $eml_tax_options = eml()->get_option('tax_options');

            if ( $eml_tax_options['tax_archives'] ) {

                $processed_taxonomies = $this->get_processed_taxonomies( array( 'eml_media' => true, 'assigned' => true ) );

                foreach ( $processed_taxonomies as $taxonomy => $params ) {

                    if ( is_tax( $taxonomy ) ) {

                        $query->set( 'post_type', 'attachment' );
                        $query->set( 'post_status', 'inherit' );
                    }
                }
            }
        }

        if ( is_admin() && $query->is_main_query() &&  'attachment' === $query->get('post_type') ) {

            $media_library_mode = get_user_option( 'media_library_mode'  ) ? get_user_option( 'media_library_mode'  ) : 'grid';
            $eml_lib_options = eml()->get_option('lib_options');

            $query_orderby = $query->get('orderby');
            $query_order = $query->get('order');

            if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode && empty( $query_orderby ) && empty( $query_order ) ) {

                $orderby = ( 'menuOrder' === $eml_lib_options['media_orderby'] ) ? 'menu_order' : $eml_lib_options['media_orderby'];
                $order = $eml_lib_options['media_order'];

                $query->set('orderby', $orderby );
                $query->set('order', $order );
            }
        }
    }


} // class EML_Taxonomies


endif; // class_exists





if ( ! class_exists( 'EML_Walker_CategoryDropdown' ) ) :

class EML_Walker_CategoryDropdown extends Walker_CategoryDropdown {

    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

        $eml_tax_options = eml()->get_option( 'tax_options' );

        $pad = str_repeat('&nbsp;', $depth * 3);

        /** This filter is documented in wp-includes/category-template.php */
        $cat_name = apply_filters( 'list_cats', $category->name, $category );

        if ( isset( $args['value_field'] ) && isset( $category->{$args['value_field']} ) ) {
            $value_field = $args['value_field'];
        } else {
            $value_field = 'term_id';
        }

        $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $category->{$value_field} ) . "\"";

        // Type-juggling causes false matches, so we force everything to a string.
        if ( (string) $category->{$value_field} === (string) $args['selected'] )
            $output .= ' selected="selected"';
        $output .= '>';
        $output .= $pad.$cat_name;


        if ( $args['show_count'] && (bool) $eml_tax_options['show_count'] ) {

            $count = eml()->taxonomies->get_media_term_count( $category->term_id, $category->term_taxonomy_id );
            $output .= '&nbsp;&nbsp;('. number_format_i18n( $count ) .')';
        }

        $output .= "</option>\n";
    }

} // class EML_Walker_CategoryDropdown


endif; // class_exists





if ( ! class_exists( 'EML_Walker_Media_Taxonomy_Uploader_Filter' ) ) :

class EML_Walker_Media_Taxonomy_Uploader_Filter extends Walker {

    public $tree_type = 'category';
    public $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this


    public function start_lvl( &$output, $depth = 0, $args = array() ) {

        $output .= "";
    }

    public function end_lvl( &$output, $depth = 0, $args = array() ) {

        $output .= "";
    }

    public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

        if ( empty( $args['taxonomy'] ) ) {
            $taxonomy = 'category';
        } else {
            $taxonomy = $args['taxonomy'];
        }


        $eml_tax_options = eml()->get_option( 'tax_options' );
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);

        $count = ( (bool) $eml_tax_options['show_count'] ) ? '&nbsp;&nbsp;('. number_format_i18n( eml()->taxonomies->get_media_term_count( $category->term_id, $category->term_taxonomy_id ) ) .')' : '';

        $el = array(
            'term_id' => $category->term_id,
            'term_name' => $indent . esc_html( apply_filters( 'the_category', $category->name ) ) . $count
        );

        $output .= json_encode( $el );
    }

    public function end_el( &$output, $category, $depth = 0, $args = array() ) {

            $output .= "";
    }

} // class EML_Walker_Media_Taxonomy_Uploader_Filter


endif; // class_exists





if ( ! class_exists( 'EML_Walker_Term_Checklist' ) ) :

class EML_Walker_Term_Checklist extends Walker {

    public $tree_type = 'category';
    public $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this


    public function start_lvl( &$output, $depth = 0, $args = array() ) {

        $indent = str_repeat("\t", $depth);
        $output .= "$indent<ul class='children'>\n";
    }

    public function end_lvl( &$output, $depth = 0, $args = array() ) {

        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul>\n";
    }

    public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

        if ( empty( $args['taxonomy'] ) ) {
            $taxonomy = 'category';
        } else {
            $taxonomy = $args['taxonomy'];
        }


        $args['popular_cats'] = empty( $args['popular_cats'] ) ? array() : $args['popular_cats'];
        $class = in_array( $category->term_id, $args['popular_cats'] ) ? ' class="popular-category"' : '';

        $args['selected_cats'] = empty( $args['selected_cats'] ) ? array() : $args['selected_cats'];

        if ( ! empty( $args['list_only'] ) ) {
            $aria_cheched = 'false';
            $inner_class = 'category';

            if ( in_array( $category->term_id, $args['selected_cats'] ) ) {
                $inner_class .= ' selected';
                $aria_cheched = 'true';
            }

            /** This filter is documented in wp-includes/category-template.php */
            $output .= "\n" . '<li' . $class . '>' .
                '<div class="' . $inner_class . '" data-term-id=' . $category->term_id .
                ' tabindex="0" role="checkbox" aria-checked="' . $aria_cheched . '">' .
                esc_html( apply_filters( 'the_category', $category->name ) ) . '</div>';
        } else {
            /** This filter is documented in wp-includes/category-template.php */
            $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" .
                "<label class='selectit'><input value='0' type='hidden' name='tax_input[{$taxonomy}][{$category->term_id}]' /><input value='1' type='checkbox' name='tax_input[{$taxonomy}][{$category->term_id}]' id='in-{$taxonomy}-{$category->term_id}'" .
                checked( in_array( $category->term_id, $args['selected_cats'] ), true, false ) .
                disabled( empty( $args['disabled'] ), false, false ) . " />" .
                esc_html( apply_filters( 'the_category', $category->name ) ) . "</label>";
        }

    }

    public function end_el( &$output, $category, $depth = 0, $args = array() ) {
        $output .= "</li>\n";
    }


// class EML_Walker_Term_Checklist extends Walker {
//
//     function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
//
//         extract($args);
//
//         if ( empty($taxonomy) )
//             $taxonomy = 'category';
//
//         $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
//         $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class><label class='selectit'><input value='0' type='hidden' name='tax_input[{$taxonomy}][{$category->term_id}]' /><input value='1' type='checkbox' name='tax_input[{$taxonomy}][{$category->term_id}]' id='in-{$taxonomy}-{$category->term_id}'" . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . " />" . esc_html( apply_filters( 'the_category', $category->name ) ) . "</label>";
//     }

} // class EML_Walker_Term_Checklist


endif; // class_exists





if ( ! function_exists( '_eml_update_attachment_term_count' ) ) :

function _eml_update_attachment_term_count( $terms, $taxonomy ) {

    global $wpdb;

    foreach ( (array) $terms as $term ) {

        $count = 0;

        $count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id AND post_type = 'attachment' AND ( post_status = 'publish' OR post_status = 'inherit' ) AND term_taxonomy_id = %d", $term ) );

        do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
        $wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
        do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
    }
}

endif; // function_exists





if ( ! function_exists( '_eml_update_post_term_count' ) ) :

function _eml_update_post_term_count( $terms, $taxonomy ) {

    global $wpdb;

    $object_types = (array) $taxonomy->object_type;

    foreach ( $object_types as &$object_type )
        list( $object_type ) = explode( ':', $object_type );

    $object_types = array_unique( $object_types );

    if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) )
        unset( $object_types[ $check_attachments ] );

    if ( $object_types )
        $object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );

    foreach ( (array) $terms as $term ) {

        $count = 0;

        if ( $object_types )
            $count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ('" . implode("', '", $object_types ) . "') AND term_taxonomy_id = %d", $term ) );

        do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
        $wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
        do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
    }
}

endif; // function_exists
