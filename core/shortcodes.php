<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



if ( ! class_exists( 'EML_Shortcodes' ) ) :

class EML_Shortcodes {

    /**
     * Constructor. Intentionally left empty.
     *
     * @since   3.0
     */

    function __construct() {}



    /**
     *  The real constructor to initialize EML_Shortcodes.
     *
     *  @since  3.0
     *  @date   30/01/17
     *
     *  @param  N/A
     *  @return N/A
     */

    function initialize() {

        if ( $this->is_on() ) {

            // media assets
            add_action( 'wp_enqueue_media', array( $this, 'enqueue_media_assets' ) );

            // shortcodes filters
            add_filter( 'shortcode_atts_gallery', array( $this, 'shortcode_atts' ), 10, 3 );
            add_filter( 'shortcode_atts_playlist', array( $this, 'shortcode_atts' ), 10, 3 );
            add_filter( 'shortcode_atts_slideshow', array( $this, 'shortcode_atts' ), 10, 3 ); // JetPack
        }
    }



    /**
     *  Checks if media shortcodes is active option.
     *
     *  @since  3.0
     *  @date   24/09/16
     *
     *  @param  N/A
     *  @return (boolean)
     */

    function is_on() {

        $lib_options = eml()->get_option( 'lib_options' );

        return (bool) $lib_options['enhance_media_shortcodes'];
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

        $lib_options = eml()->get_option( 'lib_options' );


        if ( ( ! (bool) $lib_options['frontend_scripts'] || ! current_user_can( 'manage_categories' ) ) && ! is_admin() ) {
            return;
        }


        // scripts for shortcodes
        wp_enqueue_script( 'eml-media-shortcodes' );
        wp_enqueue_script( 'eml-media-editor' );

        wp_localize_script( 'eml-media-shortcodes', 'wpuxss_eml_enhanced_medialist_l10n', array(
            'uploaded_to' => __( 'Uploaded to post #', 'enhanced-media-library' ),
            'based_on' => __( 'Based On', 'enhanced-media-library' )
        ));
    }



    /**
     *  Extends shortcode atts with taxonomies.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function shortcode_atts( $out, $pairs, $atts ) {

        $is_filter_based = false;
        $id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;


        // enforce order defaults
        $pairs['order'] = 'ASC';
        $pairs['orderby'] = 'menu_order ID';


        foreach ( $pairs as $name => $default ) {
    		if ( array_key_exists( $name, $atts ) )
    			$out[$name] = $atts[$name];
    		else
    			$out[$name] = $default;
    	}


        if ( isset( $atts['monthnum'] ) && isset( $atts['year'] ) ) {
            $is_filter_based = true;
        }


        $tax_query = array();


        foreach ( eml()->taxonomies->get_processed_taxonomies( array( 'assigned' => true ) ) as $taxonomy => $params ) {

            if ( isset( $atts[$taxonomy] ) ) {

                $terms = explode( ',', $atts[$taxonomy] );

                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $terms,
                    'operator' => 'IN',
                );

                $is_filter_based = true;
            }
        }


        if ( ! $is_filter_based ) {
            return $out;
        }


        $ids = array();

        $mime_type = isset( $out['type'] ) && ( 'audio' === $out['type'] || 'video' === $out['type'] ) ? $out['type'] : 'image';

        $query = array(
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => $mime_type,
            'order' => $out['order'],
            'orderby' => $out['orderby'],
            'posts_per_page' => isset( $atts['limit'] ) ? intval( $atts['limit']  ) : -1, //TODO: add pagination
            'fields' => 'ids'
        );

        if ( isset( $atts['monthnum'] ) && isset( $atts['year'] ) ) {

            $query['monthnum'] = $atts['monthnum'];
            $query['year'] = $atts['year'];
        }

        if ( 'post__in' === $out['orderby'] ) {
            $query['orderby'] = 'menu_order ID';
        }

        if ( ! empty( $tax_query ) ) {

            $tax_query['relation'] = 'AND';
            $query['tax_query'] = $tax_query;
        }

        if ( $id ) {
            $query['post_parent'] = $id;
        }

        $ids = get_posts( $query );

        if ( ! empty( $ids ) ) {
            $out['ids'] = $out['include'] = implode( ',', $ids ); // TODO: ids ????
        }

        return $out;
    }


} // class EML_Shortcodes


endif; // class_exists
