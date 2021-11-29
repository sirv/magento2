/**
 * Primary images synchronizer
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/copy_primary_images.html',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, copyPrimaryImagesTpl, uiAlert) {
    'use strict';

    $.widget('sirv.copyPrimaryImages', {

        options: {
            ajaxUrl: null
        },
        isBusy: false,
        isInProgress: false,
        isFailed: false,
        isCanceled: false,
        products: [],
        counters: {
            total: 0,
            withMedia: 0,
            withoutMedia: 0,
            toProcess: 0,
            processed: 0,
            copied: 0,
            failed: 0
        },
        percents: {
            processed: 0,
            copied: 0,
            failed: 0
        },
        failedData: {},
        modalWindow: null,
        confirmMessage: $.mage.__('Are you sure you want to stop copying?'),
        errorMessage: $.mage.__('Some errors occurred during the copying!'),

        /** @inheritdoc */
        _create: function () {
            $(this.element).attr('disabled', true).addClass('disabled');
            this.element.on('click', $.proxy(this._processStart, this));
            this._getProductsData();
        },

        _getProductsData: function () {
            this._doRequest(
                'get_magento_data',
                {},
                this._productsDataSuccessed,
                this._productsDataFailed
            );
        },

        _productsDataSuccessed: function (data) {
            var total = Number(data.total),
                withoutMedia = data.products.length,
                withMedia = total - withoutMedia;

            $('body .products_with_images_counter').html(withMedia);
            $('body .products_without_images_counter').html(withoutMedia);
            $(this.element).removeClass('disabled').attr('disabled', false);
        },

        _processStart: function () {
            if (!this.isBusy) {
                this.isBusy = true;
                $(this.element).attr('disabled', true).addClass('disabled');
                $('body').trigger('processStart');
                this._doRequest(
                    'get_magento_data',
                    {},
                    this._preparingForCopying,
                    this._productsDataFailed
                );
            }
            return false;
        },

        _preparingForCopying: function (data) {
            this.isInProgress = false;
            this.isFailed = false;
            this.isCanceled = false;

            this.products = data.products;

            this.counters.total = Number(data.total);
            this.counters.withMedia = this.counters.total - this.products.length;
            this.counters.withoutMedia = this.products.length;
            this.counters.toProcess = this.products.length;
            this.counters.processed = 0;
            this.counters.copied = 0;
            this.counters.failed = 0;

            this.percents.processed = 0;
            this.percents.copied = 0;
            this.percents.failed = 0;

            this.failedData = {};

            $('body').trigger('processStop');

            if (!this.counters.total) {
                uiAlert({
                    title: $.mage.__('Notice'),
                    content: $.mage.__('No products found!'),
                    actions: {always: function(){}}
                });
                return;
            }

            if (this.counters.total == this.counters.withMedia) {
                uiAlert({
                    title: $.mage.__('Notice'),
                    content: $.mage.__('No products found!'),
                    actions: {always: function(){}}
                });
                return;
            }

            this._displayModalWindow();
            this.modalWindow.find('.progress-bar-holder').addClass('stripes');
            this.isInProgress = true;
            this._doCopying();
        },

        _productsDataFailed: function (message) {
            $('body').trigger('processStop');
            uiAlert({
                title: $.mage.__('Error'),
                content: $.mage.__(message),
                actions: {always: function(){}}
            });
        },

        _displayModalWindow: function () {
            this._createModalWindow();
            this.modalWindow.html('');
            $(mageTemplate(copyPrimaryImagesTpl, {
                'counters': this.counters
            })).appendTo(this.modalWindow);
            this.modalWindow.modal('openModal');
        },

        /**
         * Copy primary images
         */
        _doCopying: function () {
            if (this.isCanceled) {
                return;
            }

            var products = [], chunkSize = 10, i = 0;
            while (this.products.length) {
                products.push(this.products.pop());
                i++;
                if (i == chunkSize) {
                    break;
                }
            }

            if (products.length) {
                this._doRequest(
                    'copy_primary_images',
                    {'products': products},
                    this._copyingSuccessed,
                    this._copyingFailed
                );
            } else {
                this._copyingCompleted();
            }
        },

        _copyingSuccessed: function (data) {
            var product, message, copied = 0, failed = 0;
            for (var i = data.products.length - 1; i >= 0; i--) {
                product = data.products[i];
                if (product.copied) {
                    copied++;
                } else {
                    failed++;
                    message = product.message;
                    if (typeof(this.failedData[message]) == 'undefined') {
                        this.failedData[message] = 1;
                    } else {
                        this.failedData[message]++;
                    }
                }
            }

            this.counters.withMedia += copied;
            this.counters.withoutMedia -= copied;
            this.counters.copied += copied;
            this.counters.failed += failed;
            this.counters.processed = this.counters.copied + this.counters.failed;

            this._calculatePercents();

            this._updateProgressView();

            if (this.counters.toProcess > this.counters.processed) {
                this._doCopying();
                return;
            }

            this._copyingCompleted();
        },

        /**
         * Copying failed
         * @protected
         */
        _copyingFailed: function (mesaage) {
            this.isFailed = true;
            this._calculatePercents();
            this._updateProgressView();
            this._copyingCompleted();
            uiAlert({
                title: $.mage.__('Error'),
                content: $.mage.__(mesaage),
                actions: {always: function(){}}
            });
        },

        /**
         * Copying completed
         * @protected
         */
        _copyingCompleted: function () {
            this.modalWindow.find('.progress-bar-holder').removeClass('stripes');
            if (!this.isFailed) {
                $(this.element).removeClass('disabled').attr('disabled', false);
            }
            this.isInProgress = false;
        },

        _closeModalWindow: function () {
            if (this.isInProgress && !window.confirm(this.confirmMessage)) {
                return;
            }
            this.isCanceled = true;
            this.modalWindow.modal('closeModal');
            if (!this.isInProgress) {
                this.isBusy = false;
                $(this.element).removeClass('disabled').attr('disabled', false);
            }
        },

        /**
         * Calculate percents
         */
        _calculatePercents: function () {
            var counters = this.counters,
                percents = this.percents,
                scale = 100,
                restPercent;

            percents.copied = Math.floor(counters.copied * 100 * scale / counters.toProcess);
            percents.failed = Math.floor(counters.failed * 100 * scale / counters.toProcess);
            percents.processed = percents.copied + percents.failed;

            if (counters.toProcess == counters.processed) {
                restPercent = 100 * scale - percents.processed;
                if (restPercent > 0) {
                    if (percents.copied) {
                        percents.copied += restPercent;
                    } else {
                        percents.failed += restPercent;
                    }
                    percents.processed = 100 * scale;
                }
            }

            percents.copied = percents.copied / scale;
            percents.failed = percents.failed / scale;
            percents.processed = percents.processed / scale;
        },

        /**
         * Update progress view
         */
        _updateProgressView: function () {
            var counters = this.counters,
                percents = this.percents;

            this.modalWindow.find('.progress-bar-copied').css('width', percents.copied + '%');
            this.modalWindow.find('.progress-bar-failed').css('width', (percents.copied  + percents.failed) + '%');
            this.modalWindow.find('.progress-value').html(counters.processed);

            this.modalWindow.find('.list-item-products-with-images .list-item-value').html(counters.withMedia);
            this.modalWindow.find('.list-item-products-without-images .list-item-value').html(counters.withoutMedia);

            var message, count, idClass, li,
                ul = this.modalWindow.find('.progress-counters-list');
            for (message in this.failedData) {
                count = this.failedData[message];
                idClass = message.replace(/[^a-zA-Z]/g, '_').toLowerCase();
                if (ul.find('.' + idClass).length) {
                    ul.find('.' + idClass + ' .list-item-value').html(count);
                } else {
                    li = ul.find('.list-item').last().clone();
                    li.removeClass().addClass('list-item list-item-failed').addClass(idClass);
                    li.find('.list-item-title').html(message);
                    li.find('.list-item-value').html(count);
                    ul.append(li);
                }
            }

            $('body .products_with_images_counter').html(counters.withMedia);
            $('body .products_without_images_counter').html(counters.withoutMedia);
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
                modalClass:  'sirv-cmi-modal',
                title: $.mage.__('Copy primary images from Sirv to Magento'),
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

            successCallback = $.proxy(successCallback, this);
            failureCallback = $.proxy(failureCallback, this);

            $.ajax({
                url: this.options.ajaxUrl,
                data: data,
                type: 'post',
                dataType: 'json',
                context: this,
                showLoader: false,
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
                    var message = null;
                    if (typeof errorThrown == 'string') {
                        message = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        message = errorThrown.message;
                    }
                    if (console && console.error && errorThrown) console.error(errorThrown);
                    message = message || self.errorMessage;
                    failureCallback(message);
                }
            });
        }
    });

    return $.sirv.copyPrimaryImages;
});
