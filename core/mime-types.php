<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



if ( ! class_exists( 'EML_MimeTypes' ) ) :

class EML_MimeTypes {

    /**
     * Constructor. Intentionally left empty.
     *
     * @since   3.0
     */

    function __construct() {}



    /**
     *  The real constructor to initialize EML_MimeTypes.
     *
     *  @since  3.0
     *  @date   30/01/17
     *
     *  @param  N/A
     *  @return N/A
     */

    function initialize() {

        // mime types filters
        add_filter( 'post_mime_types', array( $this, 'post_mime_types' ) );
        add_filter( 'upload_mimes', array( $this, 'upload_mimes' ) );
        add_filter( 'mime_types', array( $this, 'mime_types' ) );
    }



    /**
     *  Corrects available MIME Types according to plugin settings.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function mime_types( $existing_mimes ) {

        $eml_mime_types = eml()->get_option( 'mime_types' );


        if ( empty( $eml_mime_types ) ) {
            return $existing_mimes;
        }

        foreach ( $eml_mime_types as $type => $mime ) {

            if ( ! isset( $existing_mimes[$type] ) )
                $existing_mimes[$type] = $mime['mime'];
        }

        foreach ( $existing_mimes as $type => $mime ) {

            if ( ! isset( $eml_mime_types[$type] ) && isset( $existing_mimes[$type] ) )
                unset( $existing_mimes[$type] );
        }

        return $existing_mimes;
    }



    /**
     *  Corrects MIME Types allowed for upload.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function upload_mimes( $existing_mimes = array() ) {

        $eml_mime_types = eml()->get_option( 'mime_types' );


        if ( empty( $eml_mime_types ) ) {
            return $existing_mimes;
        }

        foreach ( $eml_mime_types as $type => $mime ) {

            if ( (bool) $mime['upload'] ) {

                if ( ! isset( $existing_mimes[$type] ) )
                    $existing_mimes[$type] = $mime['mime'];
            }
            else {

                 if ( isset( $existing_mimes[$type] ) )
                    unset( $existing_mimes[$type] );
            }
        }

        return $existing_mimes;
    }



    /**
     *  Allows filtering by MIME Types.
     *
     *  @type   filter callback
     *  @since  3.0
     *  @date   24/09/16
     */

    function post_mime_types( $post_mime_types ) {

        $eml_mime_types = eml()->get_option( 'mime_types' );


        if ( empty( $eml_mime_types ) ) {
            return $post_mime_types;
        }

        foreach ( $eml_mime_types as $type => $mime ) {

            if ( (bool) $mime['filter'] ) {

                $post_mime_types[$mime['mime']] = array(
                    $mime['singular'],
                    'Manage ' . $mime['singular'],
                    _n_noop($mime['singular'] . ' <span class="count">(%s)</span>', $mime['plural'] . ' <span class="count">(%s)</span>')
                );
            }
        }

        return $post_mime_types;
    }


} // class EML_MimeTypes


endif; // class_exists
