/**
 * Cache widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/cache.html',
    'text!sirv/template/failed.html',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, cachedItemsTpl, failedItemsTpl, uiAlert) {
    'use strict';

    $.widget('sirv.cache', {
        options: {
            ajaxUrl: null
        },
        isBusy: false,
        syncStatus: 'synced',
        pageNum: 0,
        pageSize: 100,
        modalWindow: null,
        isModalClosed: true,
        errorMessage: $.mage.__('Some errors occurred during the ...!'),
        titles: {
            'synced': 'Images synced to Sirv',
            'queued': 'Queued images',
            'failed': 'Failed images'
        },

        /** @inheritdoc */
        _create: function () {
            this.element.on('sirv-cache', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            switch (data.action) {
                case 'view-synced-items':
                    this._viewItems('synced');
                    break;
                case 'view-queued-items':
                    this._viewItems('queued');
                    break;
                case 'view-failed-items':
                    this._viewFailedItems();
                    break;
                case 'clear-synced-items':
                    this._clearItems('synced');
                    break;
                case 'clear-queued-items':
                    this._clearItems('queued');
                    break;
                case 'clear-failed-items':
                    this._clearItems('failed');
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Unknown action!'));
            }
        },

        /**
         * View items
         * @param {String} status - item status
         */
        _viewItems: function (status) {
            if (this.isBusy) {
                return;
            }

            this.isBusy = true;
            this.syncStatus = status;

            this._getItemsInfo();
        },

        /**
         * Get cached images info
         * @protected
         */
        _getItemsInfo: function () {
            this._doRequest(
                'view',
                {'status': this.syncStatus, 'pageNum': this.pageNum, 'pageSize': this.pageSize},
                this._displayItems
            );
        },

        /**
         * Display items
         * @param {Object} data
         */
        _displayItems: function (data) {
            var id, t;
            for (id in data.items) {
                t = data.items[id].mtime;
                data.items[id]['mtimestr'] = new Date(t).toLocaleString(
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

            this._createModalWindow();

            this.modalWindow.html('');
            $(mageTemplate(cachedItemsTpl, {
                'items': data.items,
            })).appendTo(this.modalWindow);

            var modalParent, previousButton, nextButton;
            modalParent = this.modalWindow.parents('.sirv-assets-modal');
            previousButton = modalParent.find('.action-previous-button');
            nextButton = modalParent.find('.action-next-button');

            previousButton.attr('disabled', data.page == 0 ? true : false);
            previousButton.removeClass('hidden-element');
            nextButton.attr('disabled', !data.next);
            nextButton.removeClass('hidden-element');

            this._addPageSupportText(data);

            if (this.isModalClosed) {
                this.isModalClosed = false;
                this.modalWindow.modal('openModal');
            }
        },

        /**
         * Add page support text
         * @param {Object} data - page data
         */
        _addPageSupportText: function (data) {
            var pageSupportText, pageSupportTextSpan, modalParent;
            if (data.total) {
                modalParent = this.modalWindow.parents('.sirv-assets-modal');
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
         * View failed items
         */
        _viewFailedItems: function () {
            if (this.isBusy) {
                return;
            }

            this.isBusy = true;
            this.syncStatus = 'failed';

            this._doRequest(
                'view-failed',
                {'status': 'failed'},
                this._displayFailedItems
            );
        },

        /**
         * Display failed items
         * @param {Object} data
         */
        _displayFailedItems: function (data) {
            if (data && data.failed && data.failed.count) {
                data.message = data.failed.count + ' image' +
                    (data.failed.count == 1 ? '' : 's') +
                    ' could not be synced to Sirv.';

                this._createModalWindow();
                this.modalWindow.html('');
                $(mageTemplate(failedItemsTpl, {
                    'data': data,
                })).appendTo(this.modalWindow);

                if (this.isModalClosed) {
                    this.isModalClosed = false;
                    this.modalWindow.modal('openModal');

                    var modalParent, previousButton, nextButton;
                    modalParent = this.modalWindow.parents('.sirv-assets-modal');
                    previousButton = modalParent.find('.action-previous-button');
                    nextButton = modalParent.find('.action-next-button');
                    previousButton.addClass('hidden-element');
                    nextButton.addClass('hidden-element');
                    modalParent.find('.modal-footer .page-support-text').addClass('hidden-element');
                }
            }
        },

        /**
         * Clear items
         * @param {String} status
         * @protected
         */
        _clearItems: function (status) {
            var action = 'flush-' + status;
            $('body').trigger('processStart');
            $('[data-role=sirv-synchronizer]').trigger('sirv-sync', [{
                'action': action
            }]);
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

            title = this.titles[this.syncStatus] || '';

            if (this.modalWindow) {
                this.modalWindow.parents('.sirv-assets-modal').find('.modal-title').html($.mage.__(title));
                return;
            }

            dialogProperties = {
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-assets-modal',
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
                this.syncStatus = 'synced';
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

    return $.sirv.cache;
});
