//Props: https://github.com/fusioneng/Shortcake/
var GformShortcodeUI;

( function (gfShortCodeUI, $) {

    var sui = window.GformShortcodeUI = {
        models: {},
        collections: {},
        views: {},
        utils: {},
        strings: {}
    };

    /**
     * Shortcode Attribute Model.
     */
    sui.models.ShortcodeAttribute = Backbone.Model.extend({
        defaults: {
            attr: '',
            label: '',
            type: '',
            section: '',
            description: '',
            default: '',
            value: ''
        }
    });

    /**
     * Shortcode Attributes collection.
     */
    sui.models.ShortcodeAttributes = Backbone.Collection.extend({
        model: sui.models.ShortcodeAttribute,
        //  Deep Clone.
        clone: function () {
            return new this.constructor(_.map(this.models, function (m) {
                return m.clone();
            }));
        }

    });

    /**
     * Shortcode Model
     */
    sui.models.Shortcode = Backbone.Model.extend({

        defaults: {
            label: '',
            shortcode_tag: '',
            action_tag: '',
            attrs: sui.models.ShortcodeAttributes,
        },

        /**
         * Custom set method.
         * Handles setting the attribute collection.
         */
        set: function (attributes, options) {

            if (attributes.attrs !== undefined && !( attributes.attrs instanceof sui.models.ShortcodeAttributes )) {

                _.each(attributes.attrs, function (attr) {
                    if (attr.default != undefined) {
                        attr.value = attr.default
                    }
                });

                attributes.attrs = new sui.models.ShortcodeAttributes(attributes.attrs);
            }

            return Backbone.Model.prototype.set.call(this, attributes, options);
        },

        /**
         * Custom toJSON.
         * Handles converting the attribute collection to JSON.
         */
        toJSON: function (options) {
            options = Backbone.Model.prototype.toJSON.call(this, options);
            if (options.attrs !== undefined && ( options.attrs instanceof sui.models.ShortcodeAttributes )) {
                options.attrs = options.attrs.toJSON();
            }
            return options;
        },

        /**
         * Custom clone
         * Make sure we don't clone a reference to attributes.
         */
        clone: function () {
            var clone = Backbone.Model.prototype.clone.call(this);
            clone.set('attrs', clone.get('attrs').clone());
            return clone;
        },

        /**
         * Get the shortcode as... a shortcode!
         *
         * @return string eg [shortcode attr1=value]
         */
        formatShortcode: function () {

            var template, shortcodeAttributes, attrs = [], content, action = '', actions = [];

            this.get('attrs').each(function (attr) {

                var val = attr.get('value');
                var type = attr.get('type');
                var def = attr.get('default');

                // Skip empty attributes.
                // Skip unchecked checkboxes that have don't have default='true'.
                if (( ( !val || val.length < 1 ) && type != 'checkbox') || ( type == 'checkbox' && def != 'true' && !val )) {
                    return;
                }

                // Handle content attribute as a special case.
                if (attr.get('attr') === 'content') {
                    content = attr.get('value');
                } else {
                    attrs.push(attr.get('attr') + '="' + val + '"');
                }

            });


            template = "[{{ shortcode }} {{ attributes }}]"

            if (content && content.length > 0) {
                template += "{{ content }}[/{{ shortcode }}]"
            }

            template = template.replace(/{{ shortcode }}/g, this.get('shortcode_tag'));
            template = template.replace(/{{ attributes }}/g, attrs.join(' '));
            template = template.replace(/{{ content }}/g, content);

            return template;

        },

        validate: function (shortcode) {
            var errors = [];
            var id = shortcode.attrs.findWhere({attr: 'id'});
            if (!id.get('value')) {
                errors.push({'id': sui.strings.pleaseSelectAForm});
            }

            return errors.length ? errors : null;
        }

    });

    // Shortcode Collection
    sui.collections.Shortcodes = Backbone.Collection.extend({
        model: sui.models.Shortcode
    });


    /**
     * Single edit shortcode content view.
     */
    sui.views.editShortcodeForm = wp.Backbone.View.extend({

        el: '#gform-shortcode-ui-container',

        template: wp.template('gf-shortcode-default-edit-form'),

        hasAdvancedValue: false,

        events: {
            'click #gform-update-shortcode': 'insertShortcode',
            'click #gform-insert-shortcode': 'insertShortcode',
            'click #gform-cancel-shortcode': 'cancelShortcode'
        },

        initialize: function () {

            _.bindAll(this, 'beforeRender', 'render', 'afterRender');

            var t = this;
            this.render = _.wrap(this.render, function (render) {
                t.beforeRender();
                render();
                t.afterRender();
                return t;
            });


            this.model.get('attrs').each(function (attr) {
                switch (attr.get('section')) {
                    case 'required':
                        t.views.add(
                            '.gf-edit-shortcode-form-required-attrs',
                            new sui.views.editAttributeField({model: attr, parent: t})
                        );
                        break;
                    case 'standard':
                        t.views.add(
                            '.gf-edit-shortcode-form-standard-attrs',
                            new sui.views.editAttributeField({model: attr, parent: t})
                        );
                        break;
                    default:
                        t.views.add(
                            '.gf-edit-shortcode-form-advanced-attrs',
                            new sui.views.editAttributeField({model: attr, parent: t})
                        );
                        if (!t.hasAdvancedVal) {
                            t.hasAdvancedVal = attr.get('value') !== '';
                        }
                }
            });

            this.listenTo(this.model, 'change', this.render);
        },

        beforeRender: function () {
            //
        },

        afterRender: function () {
            gform_initialize_tooltips();

            $('#gform-insert-shortcode').toggle(this.options.viewMode == 'insert');
            $('#gform-update-shortcode').toggle(this.options.viewMode != 'insert');
            $('#gf-edit-shortcode-form-advanced-attrs').toggle(this.hasAdvancedVal);
        },

        insertShortcode: function (e) {

            var isValid = this.model.isValid({validate: true});

            if (isValid) {
                send_to_editor(this.model.formatShortcode());
                tb_remove();

                this.dispose();

            } else {
                _.each(this.model.validationError, function (error) {
                    _.each(error, function (message, attr) {
                        alert(message);
                    });
                });
            }
        },
        cancelShortcode: function (e) {
            tb_remove();
            this.dispose();
        },
        dispose: function () {
            this.remove();
            $('#gform-shortcode-ui-wrap').append('<div id="gform-shortcode-ui-container"></div>');
        }
    });

    sui.views.editAttributeField = Backbone.View.extend({

        tagName: "div",

        initialize: function (options) {
            this.parent = options.parent;
        },

        events: {
            'keyup  input[type="text"]': 'updateValue',
            'keyup  textarea': 'updateValue',
            'change select': 'updateValue',
            'change #gf-shortcode-attr-action': 'updateAction',
            'change input[type=checkbox]': 'updateCheckbox',
            'change input[type=radio]': 'updateValue',
            'change input[type=email]': 'updateValue',
            'change input[type=number]': 'updateValue',
            'change input[type=date]': 'updateValue',
            'change input[type=url]': 'updateValue',

        },


        render: function () {
            this.template = wp.media.template('gf-shortcode-ui-field-' + this.model.get('type'));
            return this.$el.html(this.template(this.model.toJSON()));
        },

        /**
         * Input Changed Update Callback.
         *
         * If the input field that has changed is for content or a valid attribute,
         * then it should update the model.
         */
        updateValue: function (e) {
            var $el = $(e.target);
            this.model.set('value', $el.val());
        },

        updateCheckbox: function (e) {
            var $el = $(e.target);
            var val = $el.prop('checked');

            this.model.set('value', val);
        },

        updateAction: function (e) {
            var $el = $(e.target),
                val = $el.val();

            this.model.set('value', val);
            var m = this.parent.model;
            var newShortcodeModel = sui.shortcodes.findWhere({shortcode_tag: 'gravityform', action_tag: val});

            // copy over values to new shortcode model
            var currentAttrs = m.get('attrs');
            newShortcodeModel.get('attrs').each(function (attr) {
                var newAt = attr.get('attr');
                var currentAtModel = currentAttrs.findWhere({attr: newAt});
                if (typeof currentAtModel != 'undefined') {
                    var currentAt = currentAtModel.get('attr');
                    if (newAt == currentAt) {
                        var currentVal = currentAtModel.get('value');
                        attr.set('value', String(currentVal));
                    }
                }
            });
            $(this.parent.el).empty();
            var viewMode = this.parent.options.viewMode;
            this.parent.dispose();
            this.parent.model.set(newShortcodeModel);
            GformShortcodeUI = new sui.views.editShortcodeForm({model: newShortcodeModel, viewMode: viewMode});
            GformShortcodeUI.render();

        }

    });

    sui.utils.shortcodeViewConstructor = {

        initialize: function( options ) {
            this.shortcodeModel = this.getShortcodeModel( this.shortcode );
        },

        /**
         * Get the shortcode model given the view shortcode options.
         * Must be a registered shortcode (see sui.shortcodes)
         */
        getShortcodeModel: function( options ) {

            var actionTag = typeof options.attrs.named.action != 'undefined' ? options.attrs.named.action : '';
            var shortcodeModel = sui.shortcodes.findWhere({action_tag: actionTag});

            if ( ! shortcodeModel ) {
                return;
            }

            var shortcode = shortcodeModel.clone();

            shortcode.get('attrs').each(function (attr) {

                if (attr.get('attr') in options.attrs.named) {
                    attr.set('value', options.attrs.named[attr.get('attr')]);
                }

                if (attr.get('attr') === 'content' && ( 'content' in options )) {
                    attr.set('value', options.content);
                }

            });

            return shortcode;

        },

        /**
         * Return the preview HTML.
         * If empty, fetches data.
         *
         * @return string
         */
        getContent : function() {
            if ( ! this.content ) {
                this.fetch();
            }
            return this.content;
        },

        /**
         * Fetch preview.
         * Async. Sets this.content and calls this.render.
         *
         * @return undefined
         */
        fetch : function() {

            var self = this;

            if ( ! this.fetching ) {

                this.fetching = true;

                var attr = this.shortcodeModel.get('attrs').findWhere({attr: 'id'});
                var formId = attr.get('value');
                var data;
                data = {
                    action: 'gf_do_shortcode',
                    post_id: $('#post_ID').val(),
                    form_id: formId,
                    shortcode: this.shortcodeModel.formatShortcode(),
                    nonce: gfShortcodeUIData.previewNonce
                };

                $.post(ajaxurl, data).done(function(response) {
                    self.content = response;
                }).fail(function () {
                    self.content = '<span class="gf_shortcode_ui_error">' + gfShortcodeUIData.strings.errorLoadingPreview + '</span>';
                }).always(function () {
                    delete self.fetching;
                    self.render(true);
                });

            }
        },

        setLoader: function() {
            this.setContent(
                '<div class="loading-placeholder">' +
                '<div class="dashicons dashicons-feedback"></div>' +
                '<div class="wpview-loading"><ins></ins></div>' +
                '</div>'
            );
        },

        // Backwards compatability for WP pre-4.2
        View: {
            overlay: true,

            shortcodeHTML: false,

            setContent: function (html, option) {
                this.getNodes(function (editor, node, content) {
                    var el = ( option === 'wrap' || option === 'replace' ) ? node : content,
                        insert = html;

                    if (_.isString(insert)) {
                        insert = editor.dom.createFragment(insert);
                    }

                    if (option === 'replace') {
                        editor.dom.replace(insert, el);
                    } else if (option === 'remove') {
                        node.parentNode.insertBefore(insert, node.nextSibling);
                        $(node).remove();
                    } else {
                        el.innerHTML = '';
                        el.appendChild(insert);
                    }
                });
            },


            initialize: function (options) {
                var actionTag = typeof options.shortcode.attrs.named.action != 'undefined' ? options.shortcode.attrs.named.action : '';
                var shortcodeModel = sui.shortcodes.findWhere({action_tag: actionTag});

                if (!shortcodeModel) {
                    this.shortcodeHTML = decodeURIComponent(options.encodedText);
                    this.shortcode = false;
                    return;
                }

                var shortcode = shortcodeModel.clone();

                shortcode.get('attrs').each(function (attr) {

                    if (attr.get('attr') in options.shortcode.attrs.named) {
                        attr.set(
                            'value',
                            options.shortcode.attrs.named[attr.get('attr')]
                        );
                    }

                    if (attr.get('attr') === 'content' && ( 'content' in options.shortcode )) {
                        attr.set('value', options.shortcode.content);
                    }

                });

                this.shortcode = shortcode;
            },

            loadingPlaceholder: function () {
                return '' +
                    '<div class="loading-placeholder">' +
                    '<div class="dashicons dashicons-feedback"></div>' +
                    '<div class="wpview-loading"><ins></ins></div>' +
                    '</div>';
            },

            /**
             * @see wp.mce.View.getEditors
             */
            getEditors: function (callback) {
                var editors = [];

                _.each(tinymce.editors, function (editor) {
                    if (editor.plugins.wpview) {
                        if (callback) {
                            callback(editor);
                        }

                        editors.push(editor);
                    }
                }, this);

                return editors;
            },

            /**
             * @see wp.mce.View.getNodes
             */
            getNodes: function (callback) {
                var nodes = [],
                    self = this;

                this.getEditors(function (editor) {
                    $(editor.getBody())
                        .find('[data-wpview-text="' + self.encodedText + '"]')
                        .each(function (i, node) {
                            if (callback) {
                                callback(editor, node, $(node).find('.wpview-content').get(0));
                            }

                            nodes.push(node);
                        });
                });

                return nodes;
            },

            /**
             * Set the HTML. Modeled after wp.mce.View.setIframes
             *
             */
            setIframes: function (body) {
                var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;

                if (body.indexOf('<script') === -1) {
                    this.shortcodeHTML = body;
                    this.render(true);
                    return;
                }

                this.getNodes(function (editor, node, content) {
                    var dom = editor.dom,
                        styles = '',
                        bodyClasses = editor.getBody().className || '',
                        iframe, iframeDoc, i, resize;

                    content.innerHTML = '';
                    var head = '';

                    if (!wp.mce.views.sandboxStyles) {
                        tinymce.each(dom.$('link[rel="stylesheet"]', editor.getDoc().head), function (link) {
                            if (link.href && link.href.indexOf('skins/lightgray/content.min.css') === -1 &&
                                link.href.indexOf('skins/wordpress/wp-content.css') === -1) {

                                styles += dom.getOuterHTML(link) + '\n';
                            }
                        });

                        wp.mce.views.sandboxStyles = styles;
                    } else {
                        styles = wp.mce.views.sandboxStyles;
                    }

                    // Seems Firefox needs a bit of time to insert/set the view nodes, or the iframe will fail
                    // especially when switching Text => Visual.
                    setTimeout(function () {
                        iframe = dom.add(content, 'iframe', {
                            src: tinymce.Env.ie ? 'javascript:""' : '',
                            frameBorder: '0',
                            id: 'gf-shortcode-preview-' + new Date().getTime(),
                            allowTransparency: 'true',
                            scrolling: 'no',
                            'class': 'wpview-sandbox',
                            style: {
                                width: '100%',
                                display: 'block'
                            }
                        });

                        iframeDoc = iframe.contentWindow.document;

                        iframeDoc.open();
                        iframeDoc.write(
                            '<!DOCTYPE html>' +
                            '<html>' +
                            '<head>' +
                            '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' +
                            head +
                            styles +
                            '<style>' +
                            'html {' +
                            'background: transparent;' +
                            'padding: 0;' +
                            'margin: 0;' +
                            '}' +
                            'body#wpview-iframe-sandbox {' +
                            'background: transparent;' +
                            'padding: 1px 0 !important;' +
                            'margin: -1px 0 0 !important;' +
                            '}' +
                            'body#wpview-iframe-sandbox:before,' +
                            'body#wpview-iframe-sandbox:after {' +
                            'display: none;' +
                            'content: "";' +
                            '}' +
                            '</style>' +
                            '</head>' +
                            '<body id="wpview-iframe-sandbox" class="' + bodyClasses + '">' +
                            body +
                            '</body>' +
                            '</html>'
                        );
                        iframeDoc.close();

                        resize = function () {
                            // Make sure the iframe still exists.
                            iframe.contentWindow && $(iframe).height($(iframeDoc.body).height());
                        };

                        if (MutationObserver) {
                            new MutationObserver(_.debounce(function () {
                                resize();
                            }, 100))
                                .observe(iframeDoc.body, {
                                    attributes: true,
                                    childList: true,
                                    subtree: true
                                });
                        } else {
                            for (i = 1; i < 6; i++) {
                                setTimeout(resize, i * 700);
                            }
                        }

                        resize();

                        editor.on('wp-body-class-change', function () {
                            iframeDoc.body.className = editor.getBody().className;
                        });


                    }, 50);
                });

            },

            /**
             * Render the shortcode
             *
             * To ensure consistent rendering - this makes an ajax request to the admin and displays.
             * @return string html
             */
            getHtml: function () {

                if (!this.shortcode) {
                    this.setContent(this.shortcodeHTML, 'remove');
                    return;
                }

                var data;

                if (false === this.shortcodeHTML) {
                    var attr = this.shortcode.get('attrs').findWhere({attr: 'id'});
                    var formId = attr.get('value');
                    data = {
                        action: 'gf_do_shortcode',
                        post_id: $('#post_ID').val(),
                        form_id: formId,
                        shortcode: this.shortcode.formatShortcode(),
                        nonce: gfShortcodeUIData.previewNonce
                    };

                    $.post(ajaxurl, data, $.proxy(this.setIframes, this));

                }
                return this.shortcodeHTML;
            },
        },

        edit : function( shortcodeString ) {

            var currentShortcode;

            // Backwards compatability for WP pre-4.2
            if ( 'object' === typeof( shortcodeString ) ) {
                shortcodeString = decodeURIComponent( jQuery(shortcodeString).attr('data-wpview-text') );
            }

            currentShortcode = wp.shortcode.next('gravityform', shortcodeString);

            if ( currentShortcode ) {

                var action = currentShortcode.shortcode.attrs.named.action ? currentShortcode.shortcode.attrs.named.action : '';

                var defaultShortcode = sui.shortcodes.findWhere({
                    shortcode_tag: currentShortcode.shortcode.tag,
                    action_tag: action
                });

                if (!defaultShortcode) {
                    return;
                }

                var currentShortcodeModel = defaultShortcode.clone();

                // convert attribute strings to object.
                _.each(currentShortcode.shortcode.attrs.named, function (val, key) {
                    attr = currentShortcodeModel.get('attrs').findWhere({attr: key});
                    if (attr) {
                        attr.set('value', val);
                    }
                });


                var idAttr = currentShortcodeModel.get('attrs').findWhere({attr: 'id'});
                var formId = idAttr.get('value');
                $('#add_form_id').val(formId);

                GformShortcodeUI = new sui.views.editShortcodeForm({model: currentShortcodeModel, viewMode: 'update'});
                GformShortcodeUI.render();

                $('#gform-insert-shortcode').hide();
                $('#gform-update-shortcode').show();
                tb_show("Edit Gravity Form", "#TB_inline?inlineId=select_gravity_form&width=753&height=686", "");

            }
        },
    };

    $(document).ready(function () {

        sui.strings = gfShortcodeUIData.strings;

        sui.shortcodes = new sui.collections.Shortcodes( gfShortcodeUIData.shortcodes );

        if( ! gfShortcodeUIData.previewDisabled && typeof wp.mce != 'undefined'){
            wp.mce.views.register( 'gravityform', $.extend(true, {}, sui.utils.shortcodeViewConstructor) );
        }

        $(document).on('click', '.gform_media_link', function () {
            sui.shortcodes = new sui.collections.Shortcodes(gfShortcodeUIData.shortcodes);
            var shortcode = sui.shortcodes.findWhere({shortcode_tag: 'gravityform', action_tag: ''});
            GformShortcodeUI = new sui.views.editShortcodeForm({model: shortcode, viewMode: 'insert'});
            GformShortcodeUI.render();
            tb_show("Insert Gravity Form", "#TB_inline?inlineId=select_gravity_form&width=753&height=686", "");
        });

    });

}(window.gfShortcodeUI = window.gfShortcodeUI || {}, jQuery));

