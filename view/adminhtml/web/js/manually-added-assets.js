/**
 * Sirv manually added assets widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/asset_picker_modal.html',
    'Magento_Ui/js/modal/confirm',
    'productGallery',
    'Magento_Ui/js/modal/modal'
], function ($, mageTemplate, assetPickerModalTpl, uiConfirm) {
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

    $.widget('sirv.manuallyAddedAssets', $.mage.productGallery, {
        options: {
            imageSelector: '[data-role=sirv-asset]',
            imageElementSelector: '[data-role=image-element]',
            template: '[data-template=image]',
            initialized: false,
            productId: '',
        },

        modalWindow: null,

        assetPickerData: {
            id: 'sirv-asset-picker-frame',
            templateUrl: '',
            sirvBaseUrl: '',
            folderContentUrl: ''
        },

        /**
         * Gallery creation
         * @protected
         */
        _create: function () {
            this.options.images = this.options.images || this.element.data('images');
            this.options.parentComponent = this.options.parentComponent || this.element.data('parent-component');

            this.assetPickerData = this.options.assetPickerConfig || this.element.data('asset-picker-config');

            this.options.productId = this.options.productId || this.element.data('product-id');

            this.imgTmpl = mageTemplate(this.element.find(this.options.template).html().trim());

            this._bind();

            $.each(this.options.images, $.proxy(function (index, imageData) {
                this.element.trigger('addItem', imageData);
            }, this));

            this.options.initialized = true;
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
                resort: '_resort',
                closeModalWindow: '_closeModalWindow',
                'click [data-role=delete-button]': function (event) {
                    event.preventDefault();
                    this._removeItemHandler(event);
                    return false;
                },
                'click .action-add-item': function (event) {
                    this._displayModalWindow();
                    return false;
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

            this.element.sortable({
                distance: 8,
                items: this.options.imageSelector,
                tolerance: 'pointer',
                cancel: 'input, button, .uploader',
                update: $.proxy(function () {
                    this.element.trigger('resort');
                }, this)
            });
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
        },

        /**
         * Create modal window
         * @protected
         */
        _createModalWindow: function () {
            if (this.modalWindow) {
                return this.modalWindow;
            }

            var self = this,
                dialogProperties,
                content;

            dialogProperties = {
                /* NOTE: unique wrapper classes to avoid overlay issue */
                wrapperClass: 'modals-wrapper sirv-modals-wrapper sirv-modals-wrapper-asset-picker',
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-asset-picker-modal',
                title: '',
                autoOpen: false,
                clickableOverlay: false,
                type: 'popup',
                innerScroll: true,
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'close-button',
                    click: function () {
                        self._closeModalWindow();
                    }
                }],
                closed: function () {
                },
                modalCloseBtnHandler: function () {
                    self._closeModalWindow();
                },
                keyEventHandlers: {
                    /**
                     * Tab key press handler
                     */
                    tabKey: function () {
                        if (document.activeElement === this.modal[0]) {
                            this._setFocus('start');
                        }
                    },
                    /**
                     * Escape key press handler
                     */
                    escapeKey: function () {
                        if (this.options.isOpen && this.modal.find(document.activeElement).length ||
                            this.options.isOpen && this.modal[0] === document.activeElement) {
                            self._closeModalWindow();
                        }
                    }
                }
            };

            this.modalWindow = $('<div></div>').modal(dialogProperties);

            return this.modalWindow;
        },

        /**
         * Close modal window
         * @protected
         */
        _closeModalWindow: function () {
            this.modalWindow.modal('closeModal');
        },

        /**
         * Display modal window
         * @protected
         */
        _displayModalWindow: function () {
            if (this.modalWindow) {
                this.modalWindow.modal('openModal');
                return;
            }

            this._createModalWindow();
            this.modalWindow.html('');
            $(mageTemplate(assetPickerModalTpl, {
                'assetPickerData': this.assetPickerData
            })).appendTo(this.modalWindow);

            this.modalWindow.modal('openModal');
        }
    });

    return $.sirv.manuallyAddedAssets;
});
