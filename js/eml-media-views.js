window.wp = window.wp || {};
window.eml = window.eml || { l10n: {} };


( function( $, _ ) {

    var media = wp.media,
        l10n = media.view.l10n,
        original = {};



    _.extend( eml.l10n, wpuxss_eml_media_views_l10n );



    /**
     * wp.media.controller.Library
     *
     */
    original.controllerLibrary = {

        activate: media.controller.Library.prototype.activate
    };

    _.extend( media.controller.Library.prototype, {

        activate: function() {

            original.controllerLibrary.activate.apply( this, arguments );

            wp.Uploader.queue.on( 'add', this.beforeUpload, this );
            wp.Uploader.queue.on( 'reset', this.afterUpload, this );
        },

        beforeUpload: function() {

            if ( wp.Uploader.queue.length == 1 ) {
                $('.attachment-filters:has(option[value!="all"]:selected)').val( 'all' ).change();
            }
        },

        afterUpload: function() {

            var library = this.get( 'library' ),
                selection = this.get( 'selection' ),
                orderby = library.props.get( 'orderby' );


            if ( 'menuOrder' === orderby ) {
                library.saveMenuOrder();
            }

            // $('.attachment-filters:has(option[value!="all"]:selected)').val( 'all' ).change();

            library.reset( library.models );

            selection.trigger( 'selection:unsingle', selection.model, selection );
            selection.trigger( 'selection:single', selection.model, selection );

            // media.model.Query.cleanQueries();
        },

        uploading: function( attachment ) {

            var content = this.frame.content,
                selection = this.get( 'selection' );


            if ( 'upload' === content.mode() ) {
                this.frame.content.mode('browse');
            }

            if ( this.get( 'autoSelect' ) ) {

                if ( wp.Uploader.queue.length == 1 && selection.length ) {
                    selection.reset();
                }
                selection.add( attachment );
                selection.trigger( 'selection:unsingle', selection.model, selection );
                selection.trigger( 'selection:single', selection.model, selection );
            }
        }
    });



    /**
     * wp.media.view.AttachmentCompat
     *
     */
    var newAttachmentCompatEvents = {
        'click input'  : 'preSave'
    };

    _.extend( media.view.AttachmentCompat.prototype.events, newAttachmentCompatEvents );

    _.extend( media.view.AttachmentCompat.prototype, {

        preSave: function() {

            this.noRender = true;

            media.model.Query.cleanQueries();
        },

        render: function() {

            var compat = this.model.get('compat'),
                $compat_el = this.$el,
                tcount = this.model.get('tcount');


            _.each( tcount, function( count, term_id ) {

                var $option = $( '.eml-taxonomy-filters option[value="'+term_id+'"]' ),
                    text = $option.text();

                text = text.replace( /\(.*?\)/, '('+count+')' );
                $option.text( text );
            });

            if ( ! compat || ! compat.item ) {
                return;
            }

            if ( this.noRender ) {
                return this;
            }

            this.views.detach();
            this.$el.html( compat.item );
            this.views.render();


            // TODO: find a better solution
            if ( this.controller.isModeActive( 'select' ) &&
                 'edit-attachment' != this.controller.state().get('id') ) {

                $.each( eml.l10n.compat_taxonomies_to_hide, function( id, taxonomy ) {
                    $compat_el.find( '.compat-field-'+taxonomy ).remove();
                });
            }


            // TODO: find a better solution
            $.each( eml.l10n.compat_taxonomies, function( id, taxonomy ) {

                $compat_el.find( '.compat-field-'+taxonomy+' .label' ).addClass( 'eml-tax-label' );
                $compat_el.find( '.compat-field-'+taxonomy+' .field' ).addClass( 'eml-tax-field' );
            });

            return this;
        }
    });



    /**
     * wp.media.view.AttachmentFilters
     *
     */
    _.extend( media.view.AttachmentFilters.prototype, {

        change: function() {

            var filter = this.filters[ this.el.value ],
                selection = this.controller.state().get( 'selection' ),
                resetFilterButton = this.controller.content.get().toolbar.get( 'resetFilterButton' ),


                all = $('.attachment-filters').length,
                unchanged = $('.attachment-filters').map(function(){
                    return this.value
                }).get().filter( function( val ){
                    return 'all' === val
                }).length;


            if ( filter ) {
                this.model.set( filter.props );
            }

            if ( filter && selection && selection.length && wp.Uploader.queue.length !== 1 ) {
                selection.reset();
            }


            if ( filter && media.view.settings.mediaTrash && ! _.isUndefined( this.controller.toolbar ) ) {
                this.controller.toolbar.get().$('.media-selection').toggleClass( 'trash', 'trash' === filter.props.status );
            }


            if ( _.isUndefined( resetFilterButton ) ) {
                return;
            }

            resetFilterButton.model.set( 'disabled', all === unchanged );
        },

        select: function() {

            var model = this.model,
                value = 'all',
                props = model.toJSON();


            props = _.omit( props, 'orderby', 'order' );

            _.find( this.filters, function( filter, id ) {

                var filterProps = _.omit( filter.props, 'orderby', 'order' );

                var equal = _.all( filterProps, function( prop, key ) {
                    return prop === ( _.isUndefined( props[ key ] ) ? null : props[ key ] );
                });

                if ( equal ) {
                    return value = id;
                }
            });

            this.$el.val( value );
        }
    });




    /**
     * wp.media.view.AttachmentFilters
     *
     */
    original.AttachmentFilters = {

        All: {
            createFilters: media.view.AttachmentFilters.All.prototype.createFilters
        },

        Uploaded: {
            createFilters: media.view.AttachmentFilters.Uploaded.prototype.createFilters
        }
    };



    /**
     * wp.media.view.AttachmentFilters.All
     *
     */
    _.extend( media.view.AttachmentFilters.All.prototype, {

        createFilters: function() {

            var uncategorizedProps,
                taxonomies = _.intersection( _.keys( eml.l10n.taxonomies ), eml.l10n.filter_taxonomies );


            original.AttachmentFilters.All.createFilters.apply( this, arguments );

            _.each( this.filters, function( filter, key ) {
                filter.props['uncategorized'] = null;
                filter.props['orderby'] = eml.l10n.media_orderby;
                filter.props['order'] = eml.l10n.media_order;
            });

            this.filters.uncategorized = {
                text:  eml.l10n.uncategorized,
                props: {
                    uploadedTo    : null,
                    uncategorized : true,
                    status        : null,
                    type          : null,
                    orderby       : eml.l10n.media_orderby,
                    order         : eml.l10n.media_order
                },
                priority: 60
            };


            uncategorizedProps = this.filters.uncategorized.props;

            _.each( taxonomies, function( taxonomy ) {
                uncategorizedProps[taxonomy] = null;
            });

            if ( media.view.settings.mediaTrash &&
                ( this.controller.isModeActive( 'grid' ) ||
                this.controller.isModeActive( 'eml-grid' ) ) ) {

                this.filters.trash = {
                    text:  l10n.trash,
                    props: {
                        uploadedTo : null,
                        status     : 'trash',
                        type       : null,
                        orderby    : 'date',
                        order      : 'DESC'
                    },
                    priority: 70
                };
            }
        }
    });



    /**
     * wp.media.view.AttachmentFilters.Uploaded
     *
     */
    _.extend( media.view.AttachmentFilters.Uploaded.prototype, {

        createFilters: function() {

            var uncategorizedProps,
                taxonomies = _.intersection( _.keys( eml.l10n.taxonomies ), eml.l10n.filter_taxonomies );


            original.AttachmentFilters.Uploaded.createFilters.apply( this, arguments );

            _.each( this.filters, function( filter, key ) {
                filter.props['orderby'] = eml.l10n.media_orderby;
                filter.props['order'] = eml.l10n.media_order;
            });
        }
    });



    /**
     * wp.media.view.AttachmentFilters.Taxonomy
     *
     */
    media.view.AttachmentFilters.Taxonomy = media.view.AttachmentFilters.extend({

        id: function() {

            return 'media-attachment-'+this.options.taxonomy+'-filters';
        },

        className: function() {

            // TODO: get rid of excess class name that duplicates id
            return 'attachment-filters eml-taxonomy-filters attachment-'+this.options.taxonomy+'-filter';
        },

        createFilters: function() {

            var filters = {},
                self = this;


            _.each( self.options.termList || {}, function( term, key ) {

                var term_id = term['term_id'],
                    term_name = $("<div/>").html(term['term_name']).text();

                filters[ term_id ] = {
                    text: term_name,
                    props: {
                        uncategorized : null,
                        orderby       : eml.l10n.media_orderby,
                        order         : eml.l10n.media_order
                    },
                    priority: key+4
                };

                filters[term_id]['props'][self.options.taxonomy] = term_id;
            });

            filters.all = {
                text: eml.l10n.filter_by + ' ' + self.options.singularName,
                props: {
                    uncategorized : null,
                    orderby       : eml.l10n.media_orderby,
                    order         : eml.l10n.media_order
                },
                priority: 1
            };

            filters['all']['props'][self.options.taxonomy] = null;

            filters.in = {
                text: '&#8212; ' + eml.l10n.in + ' ' + self.options.pluralName + ' &#8212;',
                props: {
                    uncategorized : null,
                    orderby       : eml.l10n.media_orderby,
                    order         : eml.l10n.media_order
                },
                priority: 2
            };

            filters['in']['props'][self.options.taxonomy] = 'in';

            filters.not_in = {
                text: '&#8212; ' + eml.l10n.not_in + ' ' + self.options.singularName + ' &#8212;',
                props: {
                    uncategorized : null,
                    orderby       : eml.l10n.media_orderby,
                    order         : eml.l10n.media_order
                },
                priority: 3
            };

            filters['not_in']['props'][self.options.taxonomy] = 'not_in';

            this.filters = filters;
        }
    });



    /**
     * wp.media.view.Button.resetFilters
     *
     */
    media.view.Button.resetFilters = media.view.Button.extend({

        id: 'reset-all-filters',

        initialize: function() {

            media.view.Button.prototype.initialize.apply( this, arguments );
            this.controller.on( 'select:activate select:deactivate', this.toogleResetFilters, this );
        },

        click: function( event ) {

            if ( '#' === this.attributes.href ) {
                event.preventDefault();
            }

            $('.attachment-filters:has(option[value!="all"]:selected)').each( function( index ) {
                $(this).val( 'all' ).change();
            });
        },

        toogleResetFilters: function() {
            this.$el.toggleClass( 'hidden' );
        }
    });



    /**
     * wp.media.view.Button.DeleteSelected
     *
     */
    media.view.Button.DeleteSelected = media.view.Button.extend({

        initialize: function() {

            media.view.Button.prototype.initialize.apply( this, arguments );
            if ( this.options.filters ) {
                this.options.filters.model.on( 'change', this.filterChange, this );
            }
            this.controller.state().get( 'selection' ).on( 'add remove reset', this.toggleDisabled, this );
        },

        filterChange: function( model ) {
            if ( 'trash' === model.get( 'status' ) ) {
                this.model.set( 'text', l10n.restoreSelected );
            } else if ( wp.media.view.settings.mediaTrash ) {
                this.model.set( 'text', l10n.trash );
            } else {
                this.model.set( 'text', l10n.deletePermanently );
            }
        },

        toggleDisabled: function() {
            this.model.set( 'disabled', ! this.controller.state().get( 'selection' ).length );
        },

        render: function() {
            media.view.Button.prototype.render.apply( this, arguments );
            this.toggleDisabled();
            return this;
        },

        click: function() {

            var changed = [], removed = [],
                selection = this.controller.state().get( 'selection' ),
                library = this.controller.state().get( 'library' );

            if ( ! selection.length ) {
                return;
            }

            if ( ! media.view.settings.mediaTrash && ! window.confirm( l10n.warnBulkDelete ) ) {
                return;
            }

            if ( media.view.settings.mediaTrash &&
                'trash' !== selection.at( 0 ).get( 'status' ) &&
                ! window.confirm( l10n.warnBulkTrash ) ) {

                return;
            }

            selection.each( function( model ) {
                if ( ! model.get( 'nonces' )['delete'] ) {
                    removed.push( model );
                    return;
                }

                if ( media.view.settings.mediaTrash && 'trash' === model.get( 'status' ) ) {
                    model.set( 'status', 'inherit' );
                    changed.push( model.save() );
                    removed.push( model );
                } else if ( media.view.settings.mediaTrash ) {
                    model.set( 'status', 'trash' );
                    changed.push( model.save() );
                    removed.push( model );
                } else {
                    model.destroy({wait: true});
                }
            } );

            if ( changed.length ) {
                selection.remove( removed );

                $.when.apply( null, changed ).then( _.bind( function() {
                    library._requery( true );
                    this.controller.trigger( 'selection:action:done' );
                }, this ) );
            } else {
                this.controller.trigger( 'selection:action:done' );
            }
        }
    });



    /**
     * wp.media.view.Button.DeleteSelectedPermanently
     *
     */
    media.view.Button.DeleteSelectedPermanently = media.view.Button.DeleteSelected.extend({

        filterChange: function( model ) {

            this.canShow = ( 'trash' === model.get( 'status' ) );
            this.$el.toggleClass( 'hidden', ! this.canShow );
            this.controller.browserView.fixLayout();
        },

        render: function() {

            media.view.Button.prototype.render.apply( this, arguments );
            this.$el.toggleClass( 'hidden', ! this.canShow );
            return this;
        },

        click: function() {

            var removed = [], selection = this.controller.state().get( 'selection' ),
                library = this.controller.state().get( 'library' );

            if ( ! selection.length || ! window.confirm( l10n.warnBulkDelete ) ) {
                return;
            }

            selection.each( function( model ) {

                if ( ! model.get( 'nonces' )['delete'] ) {
                    removed.push( model );
                    return;
                }

                model.destroy({wait: true});
            } );

            this.controller.trigger( 'selection:action:done' );
        }
    });



    /**
     * wp.media.view.Button.Deselect
     *
     */
    media.view.Button.Deselect = media.view.Button.extend({

        initialize: function() {

            media.view.Button.prototype.initialize.apply( this, arguments );
            this.controller.state().get( 'selection' ).on( 'add remove reset', this.toggleDisabled, this );
        },

        toggleDisabled: function() {
            this.model.set( 'disabled', ! this.controller.state().get( 'selection' ).length );
        },

        click: function( event ) {

            event.preventDefault();

            var selection = this.controller.state().get( 'selection' );

            selection.reset();

            // Keep focus inside media modal
            if ( this.controller.modal ) {
                this.controller.modal.focusManager.focus();
            }
        }
    });



    /**
     * wp.media.view.AttachmentsBrowser
     *
     */
    original.AttachmentsBrowser = {

        initialize: media.view.AttachmentsBrowser.prototype.initialize,
        createToolbar: media.view.AttachmentsBrowser.prototype.createToolbar,
    };

    _.extend( media.view.AttachmentsBrowser.prototype, {

        initialize: function() {

            original.AttachmentsBrowser.initialize.apply( this, arguments );

            this.on( 'ready', this.fixLayout, this );
            this.$window = $( window );
            this.$window.on( 'resize', _.debounce( _.bind( this.fixLayout, this ), 15 ) );

            if ( $('.notice-dismiss').length ) {
                $( document ).on( 'click', '.notice-dismiss', _.debounce( _.bind( this.fixLayout, this), 250 ) );
            }
        },

        fixLayout: function() {

            if ( ! this.controller.isModeActive( 'select' ) &&
                 ! this.controller.isModeActive( 'eml-grid' ) ) {
                return;
            }


            var $browser = this.$el,
                $attachments = $browser.find('.attachments'),
                $uploader = $browser.find('.uploader-inline'),
                $toolbar = $browser.find('.media-toolbar');


            if ( this.controller.isModeActive( 'select' ) ) {

                $attachments.css( 'top', $toolbar.height() + 10 + 'px' );
                $uploader.css( 'top', $toolbar.height() + 10 + 'px' );
                $browser.find('.eml-loader').css( 'top', $toolbar.height() + 10 + 'px' );

                // TODO: find a better place for it, something like fixLayoutOnce
                $toolbar.find('.media-toolbar-secondary').prepend( $toolbar.find('.instructions') );
            }

            if ( this.controller.isModeActive( 'eml-grid' ) )
            {
                var messagesOuterHeight = 0,
                    $messages = $('.eml-media-css .updated:visible, .eml-media-css .error:visible, .eml-media-css .notice:visible'),
                    $update = $('.eml-media-css .update-nag' ),
                    $wrap = $('body.upload-php.eml-grid #wpbody-content > .wrap');

                if ( ! _.isUndefined( $messages ) )
                {
                    $messages.each( function() {
                        messagesOuterHeight += $(this).outerHeight( true );
                    });

                    messagesOuterHeight = messagesOuterHeight ? messagesOuterHeight - 15 : 0;
                }

                if ( ! _.isUndefined( $update ) )
                {
                    $wrap.css( 'top', $update.outerHeight( true ) + 'px' );
                }

                $browser.css( 'top', $toolbar.outerHeight() + messagesOuterHeight + 15 + 'px' );
                $toolbar.css( 'top', - $toolbar.outerHeight() - 25 + 'px' );
            }
        },

        createToolbar: function() {

            var LibraryViewSwitcher, Filters, toolbarOptions,
                self = this,
                i = 1;

            toolbarOptions = {
                controller: this.controller
            };

            if ( this.controller.isModeActive( 'grid' ) ||
                this.controller.isModeActive( 'eml-grid' ) ) {

                toolbarOptions.className = 'media-toolbar wp-filter';
            }

            /**
            * @member {wp.media.view.Toolbar}
            */
            this.toolbar = new media.view.Toolbar( toolbarOptions );

            this.views.add( this.toolbar );

            this.toolbar.set( 'spinner', new media.view.Spinner({
                priority: -40
            }) );


            if ( -1 !== $.inArray( this.options.filters, [ 'uploaded', 'all' ] ) ||
                ( parseInt( eml.l10n.force_filters ) &&
                ! this.controller.isModeActive( 'eml-bulk-edit' ) &&
                'gallery-edit' !== this.controller._state &&
                'playlist-edit' !== this.controller._state &&
                'video-playlist-edit' !== this.controller._state ) ||
                'customize' === eml.l10n.current_screen ) {

                if ( this.controller.isModeActive( 'grid' ) ||
                    this.controller.isModeActive( 'eml-grid' ) ) {

                    LibraryViewSwitcher = media.View.extend({
                        className: 'view-switch media-grid-view-switch',
                        template: media.template( 'media-library-view-switcher')
                    });

                    this.toolbar.set( 'libraryViewSwitcher', new LibraryViewSwitcher({
                        controller: this.controller,
                        priority: -90
                    }).render() );
                }

                this.toolbar.set( 'filtersLabel', new media.view.Label({
                    value: l10n.filterByType,
                    attributes: {
                        'for':  'media-attachment-filters'
                    },
                    priority:   -80
                }).render() );

                if ( 'uploaded' === this.options.filters ) {
                    this.toolbar.set( 'filters', new media.view.AttachmentFilters.Uploaded({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority:   -80
                    }).render() );
                } else {
                    Filters = new media.view.AttachmentFilters.All({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority:   -80
                    });

                    this.toolbar.set( 'filters', Filters.render() );
                }

                if ( eml.l10n.wp_version >= '4.0' ) {

                    this.toolbar.set( 'dateFilterLabel', new media.view.Label({
                        value: l10n.filterByDate,
                        attributes: {
                            'for': 'media-attachment-date-filters'
                        },
                        priority: -75
                    }).render() );
                    this.toolbar.set( 'dateFilter', new media.view.DateFilter({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority: -75
                    }).render() );
                }


                $.each( eml.l10n.taxonomies, function( taxonomy, values ) {

                    if ( -1 !== _.indexOf( eml.l10n.filter_taxonomies, taxonomy ) && values.term_list ) {

                        self.toolbar.set( taxonomy+'FilterLabel', new media.view.Label({
                            value: eml.l10n.filter_by + values.singular_name,
                            attributes: {
                                'for':  'media-attachment-' + taxonomy + '-filters',
                            },
                            priority: -70 + i++
                        }).render() );
                        self.toolbar.set( taxonomy+'-filter', new media.view.AttachmentFilters.Taxonomy({
                            controller: self.controller,
                            model: self.collection.props,
                            priority: -70 + i++,
                            taxonomy: taxonomy,
                            termList: values.term_list,
                            singularName: values.singular_name,
                            pluralName: values.plural_name
                        }).render() );
                    }
                });

                this.toolbar.set( 'resetFilterButton', new media.view.Button.resetFilters({
                    controller: this.controller,
                    text: eml.l10n.reset_filters,
                    disabled: true,
                    priority: -70 + i++
                }).render() );

                if ( this.controller.isModeActive( 'eml-grid' ) ) {

                    this.toolbar.set( 'deselectButton', new media.view.Button.Deselect ({
                        controller: this.controller,
                        text: l10n.cancel,
                        disabled: true,
                        priority: -70 + i++
                    }).render() );

                    this.toolbar.set( 'emlDeleteSelectedButton', new media.view.Button.DeleteSelected({
                        filters: Filters,
                        style: 'primary',
                        // className: 'delete-selected-button',
                        disabled: true,
                        text: media.view.settings.mediaTrash ? l10n.trash : l10n.deletePermanently,
                        controller: this.controller,
                        priority: -70 + i++
                    }).render() );

                    if ( media.view.settings.mediaTrash ) {
                        this.toolbar.set( 'emlDeleteSelectedPermanentlyButton', new media.view.Button.DeleteSelectedPermanently({
                            filters: Filters,
                            style: 'primary',
                            disabled: true,
                            text: l10n.deletePermanently,
                            controller: this.controller,
                            priority: -55
                        }).render() );
                    }
                }

            } // endif


            // in case it is not eml-grid but the original grid somewhere
            if ( this.controller.isModeActive( 'grid' ) ) {

                // BulkSelection is a <div> with subviews, including screen reader text
                this.toolbar.set( 'selectModeToggleButton', new media.view.SelectModeToggleButton({
                    text: l10n.bulkSelect,
                    controller: this.controller,
                    priority: -70
                }).render() );

                this.toolbar.set( 'deleteSelectedButton', new media.view.DeleteSelectedButton({
                    filters: Filters,
                    style: 'primary',
                    disabled: true,
                    text: media.view.settings.mediaTrash ? l10n.trash : l10n.deletePermanently,
                    controller: this.controller,
                    priority: -60,
                    click: function() {
                        var changed = [], removed = [],
                            selection = this.controller.state().get( 'selection' ),
                            library = this.controller.state().get( 'library' );

                        if ( ! selection.length ) {
                            return;
                        }

                        if ( ! mediaTrash && ! window.confirm( l10n.warnBulkDelete ) ) {
                            return;
                        }

                        if ( mediaTrash &&
                            'trash' !== selection.at( 0 ).get( 'status' ) &&
                            ! window.confirm( l10n.warnBulkTrash ) ) {

                            return;
                        }

                        selection.each( function( model ) {
                            if ( ! model.get( 'nonces' )['delete'] ) {
                                removed.push( model );
                                return;
                            }

                            if ( mediaTrash && 'trash' === model.get( 'status' ) ) {
                                model.set( 'status', 'inherit' );
                                changed.push( model.save() );
                                removed.push( model );
                            } else if ( mediaTrash ) {
                                model.set( 'status', 'trash' );
                                changed.push( model.save() );
                                removed.push( model );
                            } else {
                                model.destroy({wait: true});
                            }
                        } );

                        if ( changed.length ) {
                            selection.remove( removed );

                            $.when.apply( null, changed ).then( _.bind( function() {
                                library._requery( true );
                                this.controller.trigger( 'selection:action:done' );
                            }, this ) );
                        } else {
                            this.controller.trigger( 'selection:action:done' );
                        }
                    }
                }).render() );

                if ( media.view.settings.mediaTrash ) {
                    this.toolbar.set( 'deleteSelectedPermanentlyButton', new wp.media.view.DeleteSelectedPermanentlyButton({
                        filters: Filters,
                        style: 'primary',
                        disabled: true,
                        text: l10n.deletePermanently,
                        controller: this.controller,
                        priority: -55,
                        click: function() {
                            var removed = [], selection = this.controller.state().get( 'selection' );

                            if ( ! selection.length || ! window.confirm( l10n.warnBulkDelete ) ) {
                                return;
                            }

                            selection.each( function( model ) {
                                if ( ! model.get( 'nonces' )['delete'] ) {
                                    removed.push( model );
                                    return;
                                }

                                model.destroy({wait: true});
                            } );

                            this.controller.trigger( 'selection:action:done' );
                        }
                    }).render() );
                }
            }

            if ( this.options.search ) {

                this.toolbar.set( 'searchLabel', new media.view.Label({
                    value: l10n.searchMediaLabel,
                    attributes: {
                        'for': 'media-search-input'
                    },
                    priority:   -50
                }).render() );
                this.toolbar.set( 'search', new media.view.Search({
                    controller: this.controller,
                    model:      this.collection.props,
                    priority:   -50
                }).render() );
            }

            if ( this.options.dragInfo ) {
                this.toolbar.set( 'dragInfo', new media.View({
                    el: $( '<div class="instructions">' + l10n.dragInfo + '</div>' )[0],
                    priority: -40
                }) );
            }
        },

        updateContent: function() {

            var view = this,
                noItemsView;

            if ( this.controller.isModeActive( 'grid' ) ||
                 this.controller.isModeActive( 'eml-grid' ) ) {
                noItemsView = view.attachmentsNoResults;
            } else {
                noItemsView = view.uploader;
            }

            if ( ! this.collection.length ) {

                this.toolbar.get( 'spinner' ).show();

                this.dfd = this.collection.more().done( function() {

                    if ( ! view.collection.length ) {
                        noItemsView.$el.removeClass( 'hidden' );
                    } else {
                        noItemsView.$el.addClass( 'hidden' );
                    }
                    view.toolbar.get( 'spinner' ).hide();
                } );

            } else {

                noItemsView.$el.addClass( 'hidden' );
                view.toolbar.get( 'spinner' ).hide();
            }
        },

        createUploader: function() {

            this.uploader = new media.view.UploaderInline({
                controller: this.controller,
                status:     false,
                message:    this.controller.isModeActive( 'grid' ) || this.controller.isModeActive( 'eml-grid' ) ? '' : l10n.noItemsFound,
                canClose:   this.controller.isModeActive( 'grid' ) || this.controller.isModeActive( 'eml-grid' )
            });

            this.uploader.$el.addClass( 'hidden' );
            this.views.add( this.uploader );
        },

        createAttachments: function() {
            this.attachments = new media.view.Attachments({
                controller:           this.controller,
                collection:           this.collection,
                selection:            this.options.selection,
                model:                this.model,
                sortable:             this.options.sortable,
                scrollElement:        this.options.scrollElement,
                idealColumnWidth:     this.options.idealColumnWidth,

                // The single `Attachment` view to be used in the `Attachments` view.
                AttachmentView: this.options.AttachmentView
            });

            // Add keydown listener to the instance of the Attachments view
            this.attachments.listenTo( this.controller, 'attachment:keydown:arrow',     this.attachments.arrowEvent );
            this.attachments.listenTo( this.controller, 'attachment:details:shift-tab', this.attachments.restoreFocus );

            this.views.add( this.attachments );


            if ( this.controller.isModeActive( 'grid' ) ||
                this.controller.isModeActive( 'eml-grid' ) ) {

                this.attachmentsNoResults = new media.View({
                    controller: this.controller,
                    tagName: 'p'
                });

                this.attachmentsNoResults.$el.addClass( 'hidden no-media' );
                this.attachmentsNoResults.$el.html( l10n.noItemsFound );

                this.views.add( this.attachmentsNoResults );
            }
        }
    });



    /**
     * wp.media.view.DateFilter
     *
     * a copy from media-grid.js | for WP less than 4.1
     */
    if ( _.isUndefined( media.view.DateFilter ) ) {

        media.view.DateFilter = media.view.AttachmentFilters.extend({

            id: 'media-attachment-date-filters',

            createFilters: function() {
                var filters = {};
                _.each( media.view.settings.months || {}, function( value, index ) {
                    filters[ index ] = {
                        text: value.text,
                        props: {
                            year: value.year,
                            monthnum: value.month
                        }
                    };
                });
                filters.all = {
                    text:  l10n.allDates,
                    props: {
                        monthnum: false,
                        year:  false
                    },
                    priority: 10
                };
                this.filters = filters;
            }
        });
    }



    /**
     * wp.media.view.MediaFrame.Post
     *
     */
    original.MediaFrame = {

        Post: {
            activate: media.view.MediaFrame.Post.prototype.activate
        }
    };

    _.extend( media.view.MediaFrame.Post.prototype, {

        activate: function() {

            var content = this.content.get();

            original.MediaFrame.Post.activate.apply( this, arguments );

            this.on( 'open', content.fixLayout, content );
            if ( typeof acf !== 'undefined' && $('.acf-expand-details').length ) {
                $( document ).on( 'click', '.acf-expand-details', _.debounce( _.bind( content.fixLayout, content ), 250 ) );
            }
        }
    });



    $( document ).ready( function() {

        // TODO: find a better place for this
        $( document ).on( 'mousedown', '.media-frame .attachments-browser .attachments li', function ( event ) {

            if ( event.ctrlKey || event.shiftKey ) {
                event.preventDefault();
            }
        });
    });



    // TODO: move to the PHP side
    $('body').addClass('eml-media-css');

})( jQuery, _ );
