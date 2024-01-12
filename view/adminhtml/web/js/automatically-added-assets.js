/**
 * Sirv automatically added assets widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'Magento_Ui/js/modal/confirm',
    'productGallery'
], function ($, mageTemplate, uiConfirm) {
    'use strict';

    /**
     * Formats incoming bytes value to a readable format.
     *
     * @param {Number} bytes
     * @returns {String}
     */
    function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'],
            i;

        if (bytes === 0) {
            return '0 Byte';
        }

        i = window.parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));

        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }

    $.widget('sirv.automaticallyAddedAssets', $.mage.productGallery, {
        options: {
            imageSelector: '[data-role=sirv-asset]',
            imageElementSelector: '[data-role=image-element]',
            template: '[data-template=image]',
            initialized: false,
            refreshCacheUrl: '',
            assetsGalleryUrl: '',
            productId: '',
            images: [],
            folderPath: '',
            folderExists: false
        },

        /**
         * Gallery creation
         * @protected
         */
        _create: function () {
            this.options.images = this.options.images || this.element.data('images');
            this.options.parentComponent = this.options.parentComponent || this.element.data('parent-component');
            this.options.refreshCacheUrl = this.options.refreshCacheUrl || this.element.data('refresh-cache-url');
            this.options.assetsGalleryUrl = this.options.assetsGalleryUrl || this.element.data('assets-gallery-url');
            this.options.productId = this.options.productId || this.element.data('product-id');

            this.imgTmpl = mageTemplate(this.element.find(this.options.template).html().trim());

            this._bind();

            if (this.options.images.length) {
                $.each(this.options.images, $.proxy(function (index, imageData) {
                    this.element.trigger('addItem', imageData);
                }, this));
                this.options.initialized = true;
            } else {
                this._getAssetsData();
            }
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            var events = {
                addItem: '_addItem',
                removeItem: '_removeItem',
                setPosition: '_setPosition',
                'click [data-role=delete-button]': function (event) {
                    event.preventDefault();
                    this._removeItemHandler(event);
                    return false;
                },
                'click .action-refresh': function (event) {
                    $('body').trigger('processStart');

                    var ids = [this.options.productId];
                    $.ajax({
                        url: this.options.refreshCacheUrl,
                        data: {isAjax: true, ids: ids},
                        type: 'get',
                        dataType: 'json',
                        context: this,
                        showLoader: false,
                        success: function (response, textStatus, jqXHR) {
                            location.reload();
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            var errorMessage = null;
                            if (typeof errorThrown == 'string') {
                                errorMessage = errorThrown;
                            } else if (typeof errorThrown == 'object') {
                                errorMessage = errorThrown.message;
                            }
                            console && console.error && errorMessage && console.warn(errorMessage);
                        }
                    });
                }
            };

            events['click ' + this.options.imageSelector] = function (event) {
                if ($(event.currentTarget).is('[data-role=delete-button]')) {
                    return false;
                }

                var imageData, $imageContainer;
                imageData = $(event.currentTarget).data('imageData');

                window.open(imageData.viewUrl, '_blank');

                return false;
            };

            this._on(events);
        },

        /**
         * Get assets data
         * @protected
         */
        _getAssetsData: function () {
            var self = this;
            $('body').trigger('processStart');
            $.ajax({
                url: this.options.assetsGalleryUrl,
                data: {
                    isAjax: true,
                    action: 'get_assets_data',
                    productId: this.options.productId,
                },
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: false,
                success: function (response, textStatus, jqXHR) {
                    self.options.folderPath = (response && response.data && response.data.folderPath) ?
                        response.data.folderPath : '';
                    self.options.folderExists = (response && response.data && response.data.folderExists) ?
                        response.data.folderExists : false;
                    self.options.images = (response && response.data && response.data.assets) ? response.data.assets : [];

                    $.each(self.options.images, $.proxy(function (index, imageData) {
                        self.element.trigger('addItem', imageData);
                    }, self));

                    if (self.options.folderExists) {
                        if (!self.options.images.length) {
                            self.element.find('.sirv-no-files').removeClass('hidden-element');
                        }
                    } else {
                        self.element.find('.sirv-no-folder').removeClass('hidden-element');
                        var fieldset = self.element.parents('.admin__fieldset').first();
                        fieldset.find('.sirv-assets-folder-link').addClass('sirv-no-folder');
                        fieldset.find('.create-folder-link')
                            .on('click', $.proxy(self._createFolder, self));
                        fieldset.find('.sirv-assets-folder-link + span.sirv-no-folder')
                            .removeClass('hidden-element');
                    }
                    self.options.initialized = true;
                    $('body').trigger('processStop');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                }
            });
        },

        /**
         * Create folder
         * @private
         */
        _createFolder: function () {
            var self = this;
            $('body').trigger('processStart');
            $.ajax({
                url: this.options.assetsGalleryUrl,
                data: {
                    isAjax: true,
                    action: 'create_folder',
                    folderPath: this.options.folderPath,
                },
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: false,
                success: function (response, textStatus, jqXHR) {
                    self.element.find('.sirv-no-folder').addClass('hidden-element');
                    self.element.find('.sirv-no-files').removeClass('hidden-element');
                    var fieldset = self.element.parents('.admin__fieldset').first();
                    fieldset.find('.sirv-assets-folder-link').removeClass('sirv-no-folder');
                    fieldset.find('.sirv-assets-folder-link + span.sirv-no-folder').addClass('hidden-element');
                    $('body').trigger('processStop');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                }
            });

            return false;
        },

        /**
         * Remove item handler
         * @param event
         * @private
         */
        _removeItemHandler: function (event) {
            var $imageContainer, $element;

            $imageContainer = $(event.currentTarget).closest(this.options.imageSelector);
            $element = this.element;

            uiConfirm({
                title: 'Remove file',
                content: $.mage.__('Are you sure you want to remove this file from the gallery?'),
                actions: {
                    confirm: function (event) {
                        //NOTE: 'Remove file' button
                        $element.trigger('removeItem', $imageContainer.data('imageData'));
                    },
                    cancel: function (event) {
                        //NOTE: 'Cancel' button
                    }
                },
                buttons: [{
                    text: $.mage.__('Cancel'),
                    class: 'action-secondary action-dismiss',
                    click: function (event) {
                        this.closeModal(event, false);
                    }
                }, {
                    text: $.mage.__('Remove file'),
                    class: 'action-primary action-accept',
                    click: function (event) {
                        this.closeModal(event, true);
                    }
                }]
            });
        },

        /**
         * Add image
         * @param event
         * @param imageData
         * @private
         */
        _addItem: function (event, imageData) {
            var count = this.element.find(this.options.imageSelector).length,
                element,
                imgElement;

            imageData = $.extend({
                'file_id': imageData.value_id ? imageData.value_id : Math.random().toString(33).substr(2, 18),
                'position': count + 1,
                'sizeLabel': bytesToSize(imageData.size)
            }, imageData);

            element = this.imgTmpl({
                data: imageData
            });

            element = $(element).data('imageData', imageData);

            if (count === 0) {
                element.prependTo(this.element);
            } else {
                element.insertAfter(this.element.find(this.options.imageSelector + ':last'));
            }

            imgElement = element.find(this.options.imageElementSelector);
            imgElement.on('load', this._updateImageDimesions.bind(this, element));
            imgElement.on('error', this._processFailedItem.bind(this, element));

            this._contentUpdated();
        },

        /**
         * Updates image's dimensions information.
         *
         * @param {jQeuryCollection} imgContainer
         */
        _updateImageDimesions: function (imgContainer) {
            var data = imgContainer.data('imageData'),
                $dimens;

            if (data.width && data.height) {
                $dimens = imgContainer.find('[data-role=image-dimens]');
                $dimens.text(data.width + 'x' + data.height + ' px');
            } else {
                imgContainer.find('[data-role=image-dimens-wrapper]').remove();
            }
        },

        /**
         * Process failed item
         *
         * @param {jQeuryCollection} imgContainer
         */
        _processFailedItem: function (imgContainer) {
            var imgElement = imgContainer.find(this.options.imageElementSelector);
            imgElement.addClass('failed-item');
            imgContainer.find('[data-role=image-dimens-wrapper]').remove();
            imgContainer.find('[data-role=image-size]').remove();
        }
    });

    return $.sirv.automaticallyAddedAssets;
});
