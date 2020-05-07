/**
 * Swatch renderer widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Viewer contents
     *
     * 1 Magento images/videos
     * 2 Magento images/videos + Sirv assets
     * 3 Sirv assets + Magento images/videos
     * 4 Sirv assets only
     */
    var MAGENTO_ASSETS = 1,
        MAGENTO_AND_SIRV_ASSETS = 2,
        SIRV_AND_MAGENTO_ASSETS = 3,
        SIRV_ASSETS = 4;

    var mixin = {
        options: {
            sirvConfig: {
                enabled: false,
                simpleProductId: null,
                currentProductId: null,
                smvContainerSelector: 'div.smv-pg-container',
                slides: [],
                dataIds: {},
                additionalAssets: {},
                viewerContentsSource: MAGENTO_ASSETS,
                baseUrl: ''
            }
        },

        /**
         * Creation
         *
         * @private
         */
        _create: function () {
            var spConfig, sirvConfig, smvContainer;

            this._super();

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
            sirvConfig.dataIds[spConfig.productId] = $.parseJSON(smvContainer.attr('data-initial-slides'));

            if (spConfig && spConfig.sirvConfig) {
                if (spConfig.sirvConfig.assetsData) {
                    sirvConfig.additionalAssets = spConfig.sirvConfig.assetsData;
                }
                if (spConfig.sirvConfig.viewerContentsSource) {
                    sirvConfig.viewerContentsSource = spConfig.sirvConfig.viewerContentsSource;
                    if (typeof(sirvConfig.viewerContentsSource) == 'string') {
                        sirvConfig.viewerContentsSource = parseInt(sirvConfig.viewerContentsSource, 10);
                    }
                }
                if (spConfig.sirvConfig.baseUrl) {
                    sirvConfig.baseUrl = spConfig.sirvConfig.baseUrl;
                }
            }
        },

        /**
         * Load media gallery using ajax or json config
         *
         * @param {String|undefined} eventName
         * @private
         */
        _loadMedia: function (eventName) {
            var productId = null;

            if (!this.options.useAjax) {
                productId = this.getProduct();
                if (typeof(productId) == 'undefined') {
                    productId = null;
                }
            }

            this.options.sirvConfig.simpleProductId = productId;

            this._super(eventName);
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
            if (response.variantProductId) {
                this.options.sirvConfig.simpleProductId = response.variantProductId;
            } else {
                this.options.sirvConfig.simpleProductId = null;
            }

            this._super($this, response, isInProductView);
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
                return;
            }

            this.updateSirvMediaViewer(this._super, images, context, isInProductView, null);
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
                galleryData,
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

            var i, l, pId, dataIds, items, enabledIds = [];

            smViewerNode = smvContainer.find('.Sirv').get(0);
            smViewer = Sirv.getInstance(smViewerNode);
            items = this._getSlides(productId);

            if (items.length) {
                if (typeof(sirvConfig.dataIds[productId]) == 'undefined') {
                    sirvConfig.dataIds[productId] = [];

                    for (i = items.length - 1; i >= 0; i--) {
                        smViewer.insertItem(items[i].slide, 0);
                        sirvConfig.dataIds[productId].unshift(items[i].id);
                    }
                } else {
                    dataIds = sirvConfig.dataIds[productId];
                    for (i = 0, l = dataIds.length; i < l; i++) {
                        smViewer.enableItem(dataIds[i]);
                    }
                }

                smViewer.jump(sirvConfig.dataIds[productId][0]);

                enabledIds.push(productId);
                if (!doReplace) {
                    enabledIds.push(spConfig.productId);
                }
            } else {
                if (doReplace) {
                    dataIds = sirvConfig.dataIds[spConfig.productId];
                    for (i = 0, l = dataIds.length; i < l; i++) {
                        smViewer.enableItem(dataIds[i]);
                    }
                }

                smViewer.jump(sirvConfig.dataIds[spConfig.productId][0]);

                enabledIds.push(spConfig.productId);
            }

            for (pId in sirvConfig.dataIds) {
                if (enabledIds.indexOf(pId) != -1) {
                    continue;
                }
                dataIds = sirvConfig.dataIds[pId];
                for (i = 0, l = dataIds.length; i < l; i++) {
                    smViewer.disableItem(dataIds[i]);
                }
            }

            sirvConfig.currentProductId = productId;
        },

        /**
         * Get slides for viewer
         *
         * @param {Integer} id
         * @returns Array
         * @private
         */
        _getSlides: function (id) {
            var sirvConfig = this.options.sirvConfig,
                assetsData = [];

            if (typeof(sirvConfig.slides[id]) != 'object') {
                sirvConfig.slides[id] = this._getAssetsData(id);
            }

            return sirvConfig.slides[id];
        },

        /**
         * Get assets data
         *
         * @param {Integer} id
         * @returns Array
         * @private
         */
        _getAssetsData: function (id) {
            var sirvConfig = this.options.sirvConfig,
                assetsData = [],
                assetsData1 = [],
                assetsData2 = [];

            if (sirvConfig.viewerContentsSource != SIRV_ASSETS) {
                assetsData1 = this._getMagentoAssetsData(id);
            }

            if (sirvConfig.viewerContentsSource != MAGENTO_ASSETS) {
                assetsData2 = this._getSirvAssetsData(id);
            }

            switch (sirvConfig.viewerContentsSource) {
                case MAGENTO_ASSETS:
                    assetsData = assetsData1;
                    break;
                case MAGENTO_AND_SIRV_ASSETS:
                    assetsData = assetsData1;
                    assetsData2.forEach(function (item) {
                        assetsData.push(item);
                    });
                    break;
                case SIRV_AND_MAGENTO_ASSETS:
                    assetsData = assetsData2;
                    assetsData1.forEach(function (item) {
                        assetsData.push(item);
                    });
                    break;
                case SIRV_ASSETS:
                    assetsData = assetsData2;
                    break;
            }

            return assetsData;
        },

        /**
         * Get Magento assets data
         *
         * @param {Integer} id
         * @returns Array
         * @private
         */
        _getMagentoAssetsData: function (id) {
            var spConfig = this.options.jsonConfig,
                sirvConfig = this.options.sirvConfig,
                mageGalleryData = null,
                assetsData = [];

            if (spConfig.images && spConfig.images[id] && spConfig.images[id].length) {
                mageGalleryData = spConfig.images[id];
            }

            if (mageGalleryData) {
                mageGalleryData = this._sortImages(mageGalleryData);

                $.each(mageGalleryData, function (index, data) {
                    var dataId = 'item-' + id + '-' + index,
                        element,
                        arr;

                    switch (data.type) {
                        case 'image':
                            arr = data.full.split('?', 2);
                            if (arr.length == 2) {
                                arr[1] = arr[1].replace('+', '%20');
                            }
                            if (arr[0].indexOf(sirvConfig.baseUrl) === 0) {
                                element = document.createElement('div');
                                element.setAttribute('data-type', 'zoom');
                            } else {
                                element = document.createElement('img');
                                element.setAttribute('data-type', 'static');
                            }
                            element.setAttribute('data-id', dataId);
                            element.setAttribute('data-src', arr.join('?'));
                            break;
                        case 'video':
                            element = document.createElement('div');
                            element.setAttribute('data-id', dataId);
                            element.setAttribute('data-src', data.videoUrl);
                            break;
                        default:
                            return;
                    }

                    assetsData.push({
                        'id': dataId,
                        'slide': element
                    });
                });
            }

            return assetsData;
        },

        /**
         * Get Sirv assets data
         *
         * @param {Integer} id
         * @returns Array
         * @private
         */
        _getSirvAssetsData: function (id) {
            var sirvConfig = this.options.sirvConfig,
                assetsData = [];

            if (sirvConfig.additionalAssets[id]) {
                $.each(sirvConfig.additionalAssets[id].slides, function (dataId, htmlString) {
                    var element = document.createElement('div');
                    element.innerHTML = htmlString.trim();
                    assetsData.push({
                        'id': dataId,
                        'slide': element.firstChild
                    });
                });
            }

            return assetsData;
        }
    };

    return function (widget) {

        if (typeof(widget) == 'undefined') {
            widget = $.mage.SwatchRenderer;
        }

        /* NOTE: to skip multiple mixins */
        if (typeof(widget.prototype.options.sirvConfig) != 'undefined') {
            return widget;
        }

        $.widget('mage.SwatchRenderer', widget, mixin);

        return $.mage.SwatchRenderer;
    };
});
