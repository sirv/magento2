/**
 * Configurable widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
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
                currentProductId: null,
                smvContainerSelector: 'div.smv-pg-container',
                slides: [],
                activeSlides: []
            }
        },

        lockedMethods: {},

        /**
         * Initialize configuration
         *
         * @private
         */
        _initializeOptions: function () {
            if (this._lockedOrLockMethod('_initializeOptions')) {
                this._super();
                return;
            }

            var spConfig, sirvConfig, jsonConfig, smvContainer;

            this._super();
            this._unlockMethod('_initializeOptions');

            spConfig = this.options.spConfig;
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
         * Change displayed product images
         *
         * @private
         */
        _changeProductImage: function () {
            if (this._lockedOrLockMethod('_changeProductImage')) {
                this._super();
                return;
            }

            var spConfig = this.options.spConfig,
                sirvConfig = this.options.sirvConfig,
                productId = spConfig.productId,
                doReplace = !(this.options.gallerySwitchStrategy === 'prepend'),
                smvContainer,
                smViewerNode,
                smViewer;

            if (!sirvConfig.enabled) {
                this._super();
                this._unlockMethod('_changeProductImage');
                return;
            }

            smvContainer = $(sirvConfig.smvContainerSelector);
            if (!smvContainer.length) {
                console && console.warn && console.warn('Sirv Media Viewer container not found!');
                this._super();
                this._unlockMethod('_changeProductImage');
                return;
            }

            if (typeof(this.simpleProduct) != 'undefined') {
                productId = this.simpleProduct;
            }

            if (sirvConfig.currentProductId == productId) {
                this._unlockMethod('_changeProductImage');
                return;
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

            this._unlockMethod('_changeProductImage');
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
            widget = $.mage.configurable;
        }

        widgetNameSpace = widget.prototype.namespace || 'mage';
        widgetName = widget.prototype.widgetName || 'configurable';

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
