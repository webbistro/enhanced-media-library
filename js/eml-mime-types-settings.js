( function( $, _ ) {

    var l10n = wpuxss_eml_mimetype_options_l10n_data;



    // create new mime type
    $( document ).on( 'click', '.wpuxss-eml-button-create-mime', function() {

        $('.wpuxss-eml-mime-type-list').find('.wpuxss-eml-clone').clone().attr('class','wpuxss-eml-clone-mime').prependTo('.wpuxss-eml-mime-type-list tbody').show(300).find('input').first().focus();

        return false;
    });

    // remove mime type
    $( document ).on( 'click', 'tr .wpuxss-eml-button-remove', function() {

        $(this).closest('tr').hide( 300, function() {
            $(this).remove();
        });

        return false;
    });

    // on change of an extension during creation
    $( document ).on( 'blur', '.wpuxss-eml-clone-mime .wpuxss-eml-type', function() {

        var extension = $(this).val().toLowerCase(),
        mime_type_tr = $(this).closest('tr');

        $(this).val(extension);

        mime_type_tr.find('.wpuxss-eml-mime').attr('name','wpuxss_eml_mimes['+extension+'][mime]');
        mime_type_tr.find('.wpuxss-eml-singular').attr('name','wpuxss_eml_mimes['+extension+'][singular]');
        mime_type_tr.find('.wpuxss-eml-plural').attr('name','wpuxss_eml_mimes['+extension+'][plural]');
        mime_type_tr.find('.wpuxss-eml-filter').attr('name','wpuxss_eml_mimes['+extension+'][filter]');
        mime_type_tr.find('.wpuxss-eml-upload').attr('name','wpuxss_eml_mimes['+extension+'][upload]');
    });


    // on change of a mime type during creation
    $( document ).on( 'blur', '.wpuxss-eml-clone-mime .wpuxss-eml-mime', function() {

        var mime_type = $(this).val().toLowerCase(),
        mime_type_tr = $(this).closest('tr');

        $(this).val(mime_type);
    });

    // mime types restoration warning
    $( document ).on( 'click', '#eml-restore-mime-types-settings', function( event ) {

        var name = this.name,
            value = $(this).val();


        event.preventDefault();

        emlConfirmDialog( l10n.mime_restoring_confirm_title, l10n.mime_restoring_confirm_text, l10n.mime_restoring_yes, l10n.cancel, 'button button-primary eml-warning-button' )
        .done( function() {

            emlFullscreenSpinnerStart( l10n.in_progress_restoring_text );

            $('<input type="hidden"/>').attr( 'name', name )
                .val( value )
                .appendTo( $('#wpuxss-eml-form-mimetypes') );

            $('#wpuxss-eml-form-mimetypes').submit();

        })
        .fail(function() {
            return false;
        });
    });

    // on mime types form submit
    $( '#wpuxss-eml-form-mimetypes' ).submit( function( event ) {

        submit_it = true;
        alert_text = '';

        $('.wpuxss-eml-clone-mime').each(function( index ) {

            if ( ! $('.wpuxss-eml-type',this).val() || $('.wpuxss-eml-type',this).val() == '' ||
                 ! $('.wpuxss-eml-mime',this).val() || $('.wpuxss-eml-mime',this).val() == '' ) {

                submit_it = false;
                alert_text = eml.l10n.mime.mime_error_empty_fields;
            }
            else if ( $('[id="'+$('.wpuxss-eml-type',this).val()+'"]').length > 0 ||
                      $('.wpuxss-eml-mime[value="'+$('.wpuxss-eml-mime',this).val()+'"]').length > 0 ) {

                submit_it = false;
                alert_text = eml.l10n.mime.mime_error_duplicate;
            }

            if ( ! $('.wpuxss-eml-singular',this).val() || $('.wpuxss-eml-singular',this).val() == '' ||
                 ! $('.wpuxss-eml-plural',this).val() || $('.wpuxss-eml-plural',this).val() == '' ) {

                $('.wpuxss-eml-singular',this).val($('.wpuxss-eml-mime',this).val());
                $('.wpuxss-eml-plural',this).val($('.wpuxss-eml-mime',this).val());
            }
        });

        if ( ! submit_it && alert_text != '' ) alert(alert_text);

        return submit_it;
    });

})( jQuery, _ );
