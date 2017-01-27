window.wp = window.wp || {};



( function( $, _ ) {

    var media = wp.media,
        l10n = media.view.l10n,
        original = {};




    _.extend( media.view.Attachment.Library.prototype, {

        buttons: {
            check  : true,
            edit   : true,
            remove : false, // TODO: consider 'delete' button
            attach : false // TODO: consider 'attach' button
        }
    });



    var newEvents = { 'click .edit': 'emlEditAttachment' };
    _.extend( newEvents, media.view.Attachment.prototype.events);

    _.extend( media.view.Attachment.prototype, {

        template:  media.template('attachment-grid-view'),

        events: newEvents,

        emlEditAttachment: function( event ) {

            if ( this.controller.isModeActive( 'eml-grid' ) ) {

                this.controller.trigger( 'edit:attachment', this.model);

                event.stopPropagation();
                return;
            }
        }
    });




    _.extend( media.view.Attachment.Details.prototype, {

        editAttachment: function( event ) {

            if ( this.controller.isModeActive( 'eml-grid' ) ) {

                event.preventDefault();
                this.controller.trigger( 'edit:attachment', this.model);
            }
        }
    });




    media.view.MediaFrame.emlManage = media.view.MediaFrame.Select.extend({

        initialize: function() {

            var self = this;

            _.defaults( this.options, {
                title    : '',
                modal    : false,
                multiple : 'reset',
                state    : 'library',
                mode     : [ 'eml-grid', 'edit' ]
            });

            $( document ).on( 'click', '.page-title-action', _.bind( this.addNewClickHandler, this ) );

            // Ensure core and media grid view UI is enabled.
            this.$el.addClass('wp-core-ui');

            this.gridRouter = new media.view.MediaFrame.Manage.Router();

            // Call 'initialize' directly on the parent class.
            media.view.MediaFrame.Select.prototype.initialize.apply( this, arguments );

            // Append the frame view directly the supplied container.
            this.$el.appendTo( this.options.container );

            this.render();
        },

        createStates: function() {

            var options = this.options;

            if ( this.options.states ) {
                return;
            }

            this.states.add([

                new media.controller.Library({
                    library            : media.query( options.library ),
                    title              : options.title,
                    multiple           : options.multiple,

                    content            : 'browse',
                    toolbar            : false,
                    menu               : false,
                    router             : false,

                    contentUserSetting : true,

                    searchable         : true,
                    filterable         : 'all',

                    autoSelect         : true,
                    idealColumnWidth   : $( window ).width() < 640 ? 135 : 150
                })
            ]);
        },

        bindHandlers: function() {

            media.view.MediaFrame.Select.prototype.bindHandlers.apply( this, arguments );
            this.on( 'edit:attachment', this.openEditAttachmentModal, this );
        },

        addNewClickHandler: function( event ) {

            event.preventDefault();
            this.trigger( 'toggle:upload:attachment' );
        },

        browseContent: function( contentRegion ) {

            var state = this.state();

            this.$el.removeClass('hide-toolbar');

            // Browse our library of attachments.
            this.browserView = contentRegion.view = new media.view.AttachmentsBrowser({
                controller: this,
                collection: state.get('library'),
                selection:  state.get('selection'),
                model:      state,
                sortable:   state.get('sortable'),
                search:     state.get('searchable'),
                filters:    state.get('filterable'),
                date:       state.get('date'), // ???
                display:    state.has('display') ? state.get('display') : state.get('displaySettings'),
                dragInfo:   state.get('dragInfo'),

                idealColumnWidth: state.get('idealColumnWidth'),
                suggestedWidth:   state.get('suggestedWidth'),
                suggestedHeight:  state.get('suggestedHeight'),

                AttachmentView: state.get('AttachmentView')
            });

            this.browserView.on( 'ready', _.bind( this.bindDeferred, this ) );
        },

        bindDeferred: function() {

            if ( ! this.browserView.dfd ) {
                return;
            }
            this.browserView.dfd.done( _.bind( this.startHistory, this ) );
        },

        startHistory: function() {

            // Verify pushState support and activate
            if ( window.history && window.history.pushState ) {
                Backbone.history.start( {
                    root: _wpMediaGridSettings.adminUrl,
                    pushState: true
                } );
            }
        },

        openEditAttachmentModal: function( model ) {

            wp.media( {
                frame:       'edit-attachments',
                controller:  this,
                library:     this.state().get('library'),
                model:       model
            } );
        }
    });




    _.extend( media.view.UploaderInline.prototype, {

        show: function() {

            this.$el.removeClass( 'hidden' );
            if ( this.controller.browserView ) {
                this.controller.browserView.attachments.$el.css( 'top', this.$el.outerHeight() + 20 + 'px' );
            }
        },

        hide: function() {

            this.$el.addClass( 'hidden' );
            if ( this.controller.browserView ) {
                this.controller.browserView.attachments.$el.css( 'top', 0 );
            }
        }
    });




    original.controllerLibrary = {

        beforeUpload: media.controller.Library.prototype.beforeUpload
    };

    _.extend( media.controller.Library.prototype, {

        beforeUpload: function() {

            original.controllerLibrary.beforeUpload.apply( this, arguments );
            this.frame.browserView.uploader.hide();
        }
    });



    $( document ).ready( function() {

        var $mediaGridWrap = $( '#wp-media-grid' );

        if ( $mediaGridWrap.length && window.wp && window.wp.media ) {

            media.frame = new media.view.MediaFrame.emlManage({
                container: $('#wp-media-grid')
            });
        }
    });



    // TODO: move to PHP side
    $('body').addClass('eml-grid');


})( jQuery, _ );
