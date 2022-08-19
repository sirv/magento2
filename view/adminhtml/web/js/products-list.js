/**
 * Products list widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/products_list.html',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, productsTpl, uiAlert) {
    'use strict';

    $.widget('sirv.productsList', {
        options: {
            ajaxUrl: null
        },
        isBusy: false,
        processId: 0,
        itemGroup: 'with_image',
        pageNum: 0,
        pageSize: 100,
        modalWindow: null,
        modalClass: 'sirv-product-list-modal',
        isModalClosed: true,
        errorMessage: $.mage.__('Some errors occurred during the ...!'),
        titles: {
            'with_image': 'Products with images',
            'without_image': 'Products without images',
            'no_assets': 'Product has no assets on Sirv'
        },

        /** @inheritdoc */
        _create: function () {
            this.element.on('sirv-products', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            this.processId = Date.now();

            switch (data.action) {
                case 'view-items-with-image':
                    this._viewItems('with_image');
                    break;
                case 'view-items-without-image':
                    this._viewItems('without_image');
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Unknown action!'));
            }
        },

        /**
         * View items
         * @param {String} group - item group
         */
        _viewItems: function (group) {
            if (this.isBusy) {
                return;
            }

            this.isBusy = true;
            this.itemGroup = group;

            this._getItemsInfo();
        },

        /**
         * Get cached images info
         * @protected
         */
        _getItemsInfo: function () {
            this._doRequest(
                'view',
                {
                    'group': this.itemGroup,
                    'pageNum': this.pageNum,
                    'pageSize': this.pageSize,
                    'pId': this.processId
                },
                this._displayItems
            );
        },

        /**
         * Display items
         * @param {Object} data
         */
        _displayItems: function (data) {
            var i, il, j, gl, t;
            for (i = 0, il = data.items.length; i < il; i++) {
                for (j = 0, gl = data.items[i].gallery.length; j < gl; j++) {
                    t = data.items[i].gallery[j].mtime;
                    data.items[i].gallery[j]['mtimestr'] = new Date(t * 1000).toLocaleString(
                        'en-US',
                        {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: true
                        }
                    );
                }
            }

            this._createModalWindow();

            this.modalWindow.html('');
            $(mageTemplate(productsTpl, {
                'products': data.items.length ? data.items : null
            })).appendTo(this.modalWindow);

            var modalParent, previousButton, nextButton;
            modalParent = this.modalWindow.parents('.' + this.modalClass);
            previousButton = modalParent.find('.action-previous-button');
            nextButton = modalParent.find('.action-next-button');

            previousButton.attr('disabled', data.page == 0 ? true : false);
            previousButton.removeClass('hidden-element');
            nextButton.attr('disabled', !data.next);
            nextButton.removeClass('hidden-element');

            this._addPageSupportText(data);

            this.modalWindow.find('.sirv-assets-product-items-action-link').on(
                'click',
                $.proxy(this._displayGallery, this)
            );

            if (this.isModalClosed) {
                this.isModalClosed = false;
                this.modalWindow.modal('openModal');
            }
        },

        /**
         * Display gallery
         * @param {Object} e - event object
         * @protected
         */
        _displayGallery: function (e) {
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
         * Add page support text
         * @param {Object} data - page data
         */
        _addPageSupportText: function (data) {
            var pageSupportText, pageSupportTextSpan, modalParent;
            if (data.total) {
                modalParent = this.modalWindow.parents('.' + this.modalClass);
                pageSupportText = (this.pageNum * this.pageSize + 1) + '-' +
                    (this.pageNum * this.pageSize + (data.next ? this.pageSize : data.count)) +
                    ' of ' + data.total + ' images';
                pageSupportTextSpan = modalParent.find('.modal-footer .page-support-text');
                if (pageSupportTextSpan.length) {
                    pageSupportTextSpan.html(pageSupportText);
                    pageSupportTextSpan.removeClass('hidden-element');
                } else {
                    pageSupportText = '<span class="page-support-text">' + pageSupportText + '</span>';
                    modalParent.find('.modal-footer .action-previous-button').after(pageSupportText);
                }
            }
        },

        /**
         * Do AJAX request
         * @param {String} action
         * @param {Object} params
         * @param {Function} successCallback
         * @param {Function} failureCallback
         */
        _doRequest: function (action, params, successCallback, failureCallback) {
            var self = this,
                data = {
                    isAjax: true,
                    dataAction: action
                };

            data = $.extend(data, params);

            if (typeof(failureCallback) == 'undefined') {
                failureCallback = this._displayError;
            }

            successCallback = $.proxy(successCallback, this);
            failureCallback = $.proxy(failureCallback, this);

            $.ajax({
                url: this.options.ajaxUrl,
                data: data,
                type: 'post',
                dataType: 'json',
                context: this,
                showLoader: true,
                success: function (response, textStatus, jqXHR) {
                    var success = false,
                        error = false;

                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        if (response.success) {
                            success = true;
                        }
                        if (response.data && response.data.error) {
                            error = response.data.error;
                        }
                        if (response.error && response.message) {
                            error = response.message;
                        }
                    }

                    if (success && !error) {
                        successCallback(response.data);
                    } else {
                        error = error || 'Unknown error!';
                        if (console && console.warn) console.warn(error);
                        failureCallback(error);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                    errorMessage = errorMessage || self.errorMessage;
                    failureCallback(errorMessage);
                }
            });
        },

        /**
         * Create modal window
         * @protected
         */
        _createModalWindow: function () {
            var self = this,
                dialogProperties,
                title;

            title = this.titles[this.itemGroup] || '';

            if (this.modalWindow) {
                this.modalWindow.parents('.' + this.modalClass).find('.modal-title').html($.mage.__(title));
                return;
            }

            dialogProperties = {
                /* NOTE: unique wrapper classes to avoid overlay issue */
                wrapperClass: 'modals-wrapper sirv-modals-wrapper sirv-modals-wrapper-products-list',
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass: this.modalClass,
                title: $.mage.__(title),
                autoOpen: false,
                clickableOverlay: false,
                type: 'popup',
                innerScroll: true,
                buttons: [{
                    text: $.mage.__('<'),
                    class: 'action-previous-button',
                    attr: {'title': 'Previous Page'},
                    click: function (event) {
                        if (self.pageNum > 0) {
                            self.pageNum--;
                            self._getItemsInfo();
                        }
                    }
                }, {
                    text: $.mage.__('>'),
                    class: 'action-next-button',
                    attr: {'title': 'Next Page'},
                    click: function (event) {
                        self.pageNum++;
                        self._getItemsInfo();
                    }
                }, {
                    text: $.mage.__('Close'),
                    class: 'close-button',
                    attr: {'title': 'Close Window'},
                    click: function () {
                        self._closeWindow();
                    }
                }],
                opened: function (event) {
                },
                closed: function (event) {
                },
                modalCloseBtnHandler: function () {
                    self._closeWindow();
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
                            self._closeWindow();
                        }
                    }
                }
            };

            this.modalWindow = $('<div></div>').modal(dialogProperties);
        },

        /**
         * Close widget window
         * @protected
         */
        _closeWindow: function () {
            if (this.isBusy) {
                this.isBusy = false;
                this.modalWindow.modal('closeModal');
                this.isModalClosed = true;
                this.itemGroup = 'with_image';
                this.pageNum = 0;
                this.pageSize = 100;
            }
        },

        /**
         * Display error
         * @param {String} errorMessage
         * @protected
         */
        _displayError: function (errorMessage) {
            uiAlert({
                title: $.mage.__('Error'),
                content: $.mage.__(errorMessage),
                actions: {
                    always: function(){}
                }
            });
        }
    });

    return $.sirv.productsList;
});
