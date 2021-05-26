/**
 * Assets widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/assets.html',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, assetsTpl) {
    'use strict';

    $.widget('sirv.assets', {

        options: {
            assetsUrl: null
        },

        isDisplayed: false,
        modalWindow: null,
        assetsData: null,

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._displayAssets, this));
        },

        /**
         * Display assets
         * @protected
         */
        _displayAssets: function () {
            if (!this.isDisplayed) {
                this.isDisplayed = true;
                if (this.assetsData) {
                    this._getAssetsSuccessed(this.assetsData);
                } else {
                    this._getAssets();
                }
            }
            return false;
        },

        /**
         * Hide assets
         * @protected
         */
        _hideAssets: function () {
            if (this.isDisplayed) {
                this.isDisplayed = false;
                this.modalWindow.modal('closeModal');
            }
        },

        /**
         * Get assets
         * @protected
         */
        _getAssets: function () {
            $.ajax({
                url: this.options.assetsUrl,
                data: {isAjax: true},
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: true,
                success: function (response, textStatus, jqXHR) {
                    var data = {
                        'error': 'Unexpected response.',
                        'products': []
                    };
                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        var pid, i, l, t;
                        for (pid in response.products) {
                            l = response.products[pid].items.length;
                            for (i = 0; i < l; i++) {
                                t = response.products[pid].items[i].mtime;
                                t = new Date(t).toLocaleString(
                                    'en-US',
                                    {
                                        month: 'short',
                                        day: '2-digit',
                                        year: 'numeric',
                                        hour: 'numeric',
                                        minute: '2-digit',
                                        second: '2-digit',
                                        hour12: true
                                    });
                                response.products[pid].items[i].mtime = t;
                            }
                        }

                        this.assetsData = data = response;
                    }
                    this._getAssetsSuccessed(data);
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
         * Get assets successed
         * @param {String} data
         * @protected
         */
        _getAssetsSuccessed: function (data) {
            if (!this.modalWindow) {
                this._createModalWindow();
                $(mageTemplate(assetsTpl, {
                    'error': data.error,
                    'products': $.isEmptyObject(data.products) ? null : data.products,
                })).appendTo(this.modalWindow);
                $('.sirv-assets-product-items-action-link').on(
                    'click',
                    $.proxy(this._displayItems, this)
                );
            }
            this.modalWindow.modal('openModal');
        },

        /**
         * Display items
         * @param {Object} e - event object
         * @protected
         */
        _displayItems: function (e) {
            var el = e.target || e.srcElement;

            while (el && el.tagName.toLowerCase() != 'a') {
                el = el.parentNode || null;
            }

            if (el) {
                el = $(el);
                if (el.hasClass('items-shown')) {
                    el.removeClass('items-shown');
                    el.find('span').html('Show');
                    el.next('.sirv-assets-product-items').addClass('hidden-element');
                } else {
                    el.addClass('items-shown');
                    el.find('span').html('Hide');
                    el.next('.sirv-assets-product-items').removeClass('hidden-element');
                }
            }

            return false;
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
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-assets-modal',
                title: $.mage.__('Content from Sirv'),
                autoOpen: false,
                clickableOverlay: false,
                type: 'popup',
                innerScroll: true,
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'close-button',
                    click: function () {
                        self._hideAssets();
                    }
                }],
                closed: function () {
                },
                modalCloseBtnHandler: function () {
                    self._hideAssets();
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
                            self._hideAssets();
                        }
                    }
                }
            };

            this.modalWindow = $('<div/>').modal(dialogProperties);

            return this.modalWindow;
        }
    });

    return $.sirv.assets;
});
