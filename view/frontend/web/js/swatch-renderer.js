/**
 * Swatch renderer widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    var mixin = {
        options: {
            sirvConfig: {
                enabled: false,
                simpleProductId: null,
                currentProductId: null,
                smvContainerSelector: 'div.smv-pg-container',
                slides: [],
                activeSlides: []
            }
        },

        lockedMethods: {},

        /**
         * Creation
         *
         * @private
         */
        _create: function () {
            if (this._lockedOrLockMethod('_create')) {
                this._super();
                return;
            }

            var spConfig, sirvConfig, jsonConfig, smvContainer;

            this._super();
            this._unlockMethod('_create');

            spConfig = this.options.jsonConfig;
            sirvConfig = this.options.sirvConfig;

            if (typeof(spConfig.productId) == 'undefined') {
                return;
            }

            smvContainer = $(sirvConfig.smvContainerSelector);
            if (!smvContainer.length) {
                return;
            }

            sirvConfig.enabled = true;
            sirvConfig.currentProductId = spConfig.productId;

            jsonConfig = $.parseJSON(smvContainer.attr('data-json-config'));
            sirvConfig.slides = jsonConfig['slides'];
            sirvConfig.activeSlides = jsonConfig['active-slides'];
            sirvConfig.currentProductId = jsonConfig['current-id'];
        },

        /**
         * Load media gallery using ajax or json config
         *
         * @param {String|undefined} eventName
         * @private
         */
        _loadMedia: function (eventName) {
            if (this._lockedOrLockMethod('_loadMedia')) {
                this._super(eventName);
                return;
            }

            var productId = null;

            if (!this.options.useAjax) {
                productId = this.getProduct();
                if (typeof(productId) == 'undefined') {
                    productId = null;
                }
            }

            this.options.sirvConfig.simpleProductId = productId;

            this._super(eventName);
            this._unlockMethod('_loadMedia');
        },

        /**
         * Callback for product media
         *
         * @param {Object} $this
         * @param {String} response
         * @param {Boolean} isInProductView
         * @private
         */
        _ProductMediaCallback: function ($this, response, isInProductView) {
            if (this._lockedOrLockMethod('_ProductMediaCallback')) {
                this._super($this, response, isInProductView);
                return;
            }

            if (response.variantProductId) {
                this.options.sirvConfig.simpleProductId = response.variantProductId;
            } else {
                this.options.sirvConfig.simpleProductId = null;
            }

            this._super($this, response, isInProductView);
            this._unlockMethod('_ProductMediaCallback');
        },

        /**
         * Start update images process
         *
         * @param {Array} images
         * @param {jQuery} context
         * @param {Boolean} isInProductView
         * @param {String|undefined} eventName
         */
        updateBaseImage: function (images, context, isInProductView, eventName) {
            if (this._lockedOrLockMethod('updateBaseImage')) {
                this._super(images, context, isInProductView, eventName);
                return;
            }

            if (!this.options.sirvConfig.enabled) {
                this._super(images, context, isInProductView, eventName);
                this._unlockMethod('updateBaseImage');
                return;
            }

            if (typeof(this.processUpdateBaseImage) != 'undefined') {
                var gallery = context.find(this.options.mediaGallerySelector).data('gallery');

                if (eventName === undefined) {
                    this.updateSirvMediaViewer(this.processUpdateBaseImage, images, context, isInProductView, gallery);
                } else {
                    context.find(this.options.mediaGallerySelector).on('gallery:loaded', function (loadedGallery) {
                        loadedGallery = context.find(this.options.mediaGallerySelector).data('gallery');
                        this.updateSirvMediaViewer(this.processUpdateBaseImage, images, context, isInProductView, loadedGallery);
                    }.bind(this));
                }
                this._unlockMethod('updateBaseImage');
                return;
            }

            this.updateSirvMediaViewer(this._super, images, context, isInProductView, null);
            this._unlockMethod('updateBaseImage');
        },

        /**
         * Update Sirv Media Viewer
         *
         * @param {Function} parentMethod
         * @param {Array} images
         * @param {jQuery} context
         * @param {Boolean} isInProductView
         * @param {Object} gallery
         */
        updateSirvMediaViewer: function (parentMethod, images, context, isInProductView, gallery) {
            var spConfig = this.options.jsonConfig,
                sirvConfig = this.options.sirvConfig,
                productId = spConfig.productId,
                doReplace = !(this.options.gallerySwitchStrategy === 'prepend'),
                smvContainer,
                smViewerNode,
                smViewer;

            if (!sirvConfig.enabled) {
                parentMethod.call(this, images, context, isInProductView, gallery);
                return;
            }

            smvContainer = $(sirvConfig.smvContainerSelector);
            if (!smvContainer.length) {
                console && console.warn && console.warn('Sirv Media Viewer container not found!');
                parentMethod.call(this, images, context, isInProductView, gallery);
                return;
            }

            if (sirvConfig.simpleProductId) {
                productId = sirvConfig.simpleProductId;
            }

            //NOTE: there is no need to change gallery
            if (sirvConfig.currentProductId == productId) {
                return;
            }

            if (!isInProductView) {
                //NOTE: do nothing!?
            }

            var i, l, pId, dataIds;

            smViewerNode = smvContainer.find('.Sirv').get(0);
            smViewer = Sirv.getInstance(smViewerNode);
            dataIds = sirvConfig.slides[productId];

            if (dataIds.length) {
                for (i = 0, l = dataIds.length; i < l; i++) {
                    smViewer.enableItem(dataIds[i]);
                }
                for (pId in sirvConfig.slides) {
                    if (pId == productId || !doReplace && pId == spConfig.productId) {
                        continue;
                    }
                    dataIds = sirvConfig.slides[pId];
                    for (i = 0, l = dataIds.length; i < l; i++) {
                        smViewer.disableItem(dataIds[i]);
                    }
                }
                smViewer.jump(sirvConfig.activeSlides[productId]);
            } else {
                if (doReplace) {
                    dataIds = sirvConfig.slides[spConfig.productId];
                    for (i = 0, l = dataIds.length; i < l; i++) {
                        smViewer.enableItem(dataIds[i]);
                    }
                }
                for (pId in sirvConfig.slides) {
                    if (pId == spConfig.productId) {
                        continue;
                    }
                    dataIds = sirvConfig.slides[pId];
                    for (i = 0, l = dataIds.length; i < l; i++) {
                        smViewer.disableItem(dataIds[i]);
                    }
                }
                smViewer.jump(sirvConfig.activeSlides[spConfig.productId]);
            }

            sirvConfig.currentProductId = productId;
        },

        /**
         * Check for method is locked or lock method
         *
         * @param {String} methodName
         * @returns {bool}
         * @private
         */
        _lockedOrLockMethod: function (methodName) {
            if (this.lockedMethods[methodName]) {
                return true;
            }
            this.lockedMethods[methodName] = true;
            return false;
        },

        /**
         * Unlock method
         *
         * @param {String} methodName
         * @private
         */
        _unlockMethod: function (methodName) {
            this.lockedMethods[methodName] = false;
        }
    };

    return function (widget) {
        var widgetNameSpace, widgetName;

        if (typeof(widget) == 'undefined') {
            widget = $.mage.SwatchRenderer;
        }

        widgetNameSpace = widget.prototype.namespace || 'mage';
        widgetName = widget.prototype.widgetName || 'SwatchRenderer';

        /* NOTE: to skip multiple mixins */
        /*
        if (typeof(widget.prototype.options.sirvConfig) != 'undefined') {
            return widget;
        }
        */

        $.widget(widgetNameSpace + '.' + widgetName, widget, mixin);

        return $[widgetNameSpace][widgetName];
    };
});
