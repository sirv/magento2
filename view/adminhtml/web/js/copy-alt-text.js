/**
 * Alt text synchronizer
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/copy_alt_text.html',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, copyAltTextTpl, uiAlert, uiConfirm) {
    'use strict';

    $.widget('sirv.copyAltText', {

        options: {
            ajaxUrl: null
        },
        isBusy: false,
        isInProgress: false,
        isFailed: false,
        isCanceled: false,
        page: 0,
        lastPage: 0,
        counters: {
            total: 0,
            cached: 0,
            copied: 0,
            empty: 0,
            notCached: 0,
            processed: 0,
            failed: 0
        },
        percents: {
            processed: 0
        },
        modalWindow: null,
        confirmToClearCacheMessage: $.mage.__('Some data was already cached during the previous copy process. Do you want to continue copying without affecting the data already copied, or clear the cache and start copying from the beginning to overwrite previous data?'),
        confirmCloseMessage: $.mage.__('Are you sure you want to stop copying?'),
        errorMessage: $.mage.__('Some errors occurred during the copying!'),

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._onClickHandler, this));
        },

        /**
         * Click handler
         * @protected
         */
        _onClickHandler: function () {
            if (!this.isBusy) {
                this.isBusy = true;
                $(this.element).attr('disabled', true).addClass('disabled');
                $('body').trigger('processStart');

                var widget = this;

                this._doRequest(
                    'get_alt_text_data',
                    {},
                    function (data) {
                        widget.counters.total = Number(data.total);
                        widget.counters.cached = Number(data.cached);
                        widget.counters.empty = Number(data.empty);

                        if (!widget.counters.total) {
                            $('body').trigger('processStop');
                            uiAlert({
                                title: $.mage.__('Notice'),
                                content: $.mage.__('No synced images found!'),
                                actions: {always: function(){}}
                            });
                            return;
                        }

                        if (widget.counters.cached || widget.counters.empty) {
                            $('body').trigger('processStop');
                            uiConfirm({
                                content: widget.confirmToClearCacheMessage,
                                actions: {
                                    confirm: function (event) {
                                        //NOTE: 'Continue' button
                                        $('body').trigger('processStart');
                                        widget._initTempData();
                                    },
                                    cancel: function (event) {
                                        //NOTE: 'Clear cache' button
                                        $('body').trigger('processStart');
                                        widget._clearCache();
                                    }
                                },
                                buttons: [{
                                    text: $.mage.__('Clear cache'),
                                    class: 'action-secondary action-dismiss',
                                    click: function (event) {
                                        this.closeModal(event, false);
                                    }
                                }, {
                                    text: $.mage.__('Continue'),
                                    class: 'action-primary action-accept',
                                    click: function (event) {
                                        this.closeModal(event, true);
                                    }
                                }]
                            });
                            return;
                        }

                        widget._initTempData();
                    },
                    this._widgetFailed
                );
            }

            return false;
        },

        /**
         * Clear cache
         * @protected
         */
        _clearCache: function () {
            var widget = this;

            this._doRequest(
                'clear_cache',
                {},
                function (data) {
                    widget.counters.cached = 0;
                    widget.counters.empty = 0;
                    widget._initTempData();
                },
                this._widgetFailed
            );
        },

        /**
         * Init temp data
         * @protected
         */
        _initTempData: function () {
            var widget = this;

            this._doRequest(
                'init_temp_data',
                {},
                function (data) {
                    widget.page = data.page;
                    widget.lastPage = data.lastPage;
                    widget._preparingData();
                },
                this._widgetFailed
            );
        },

        /**
         * Preparing data
         * @protected
         */
        _preparingData: function () {
            this.isInProgress = false;
            this.isFailed = false;
            this.isCanceled = false;

            this.counters.copied = this.counters.cached - this.counters.empty;
            this.counters.processed = this.counters.cached;
            this.counters.notCached = this.counters.total - this.counters.processed;

            this.altTextRule = $('[name=mt-config\\[alt_text_rule\\]]').val();
            this.altTextRule = this.altTextRule.trim();
            if (this.altTextRule == '') {
                this.altTextRule = '{alt-text}';
            }

            $('body').trigger('processStop');
            this._displayModalWindow();
            this._calculatePercents();
            this._updateProgressView();

            if (this.counters.total == this.counters.processed) {
                return;
            }

            this.modalWindow.find('.progress-bar-holder').addClass('stripes');
            this.isInProgress = true;

            this._doRequest(
                'sync_alt_text_data',
                {
                    'page': this.page,
                    'lastPage': this.lastPage,
                    'altTextRule': this.altTextRule
                },
                this._processData,
                this._widgetFailed
            );
        },

        /**
         * Process data
         * @param {Object} data
         * @protected
         */
        _processData: function (data) {
            this.page = data.page;
            this.counters.copied += data.copied;
            this.counters.empty += data.empty;
            this.counters.processed += (data.copied + data.empty);
            this.counters.notCached -= (data.copied + data.empty);

            this._calculatePercents();
            this._updateProgressView();

            if (this.isCanceled ||
                this.page > this.lastPage ||
                this.counters.processed >= this.counters.total ||
                data.completed) {
                this._doRequest(
                    'clean_temp_data',
                    {},
                    this._copyingCompleted,
                    this._widgetFailed
                );
                return;
            }

            this._doRequest(
                'sync_alt_text_data',
                {
                    'page': this.page,
                    'lastPage': this.lastPage,
                    'altTextRule': this.altTextRule
                },
                this._processData,
                this._widgetFailed
            );
        },

        /**
         * Widget failed
         * @param {String} message
         * @protected
         */
        _widgetFailed: function (mesaage) {
            this.isFailed = true;
            $('body').trigger('processStop');
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
            if (this.modalWindow) {
                this.modalWindow.find('.progress-bar-holder').removeClass('stripes');
            }
            if (!this.isFailed) {
                this.isBusy = false;
                $(this.element).removeClass('disabled').attr('disabled', false);
            }
            this.isInProgress = false;
        },

        /**
         * Close modal window
         * @protected
         */
        _closeModalWindow: function () {
            if (this.isInProgress && !window.confirm(this.confirmCloseMessage)) {
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
         * @protected
         */
        _calculatePercents: function () {
            var counters = this.counters,
                percents = this.percents,
                scale = 100;

            percents.processed = Math.floor(counters.processed * 100 * scale / counters.total);
            percents.processed = percents.processed / scale;
        },

        /**
         * Update progress view
         * @protected
         */
        _updateProgressView: function () {
            var counters = this.counters,
                percents = this.percents;

            if (this.modalWindow) {
                this.modalWindow.find('.progress-bar-processed').css('width', percents.processed + '%');
                this.modalWindow.find('.progress-value').html(counters.processed);

                this.modalWindow.find('.list-item-with-alt-text .list-item-value').html(counters.copied);
                this.modalWindow.find('.list-item-empty-alt-text .list-item-value').html(counters.empty);
                this.modalWindow.find('.list-item-without-alt-text .list-item-value').html(counters.notCached);
            }
        },

        /**
         * Display modal window
         * @protected
         */
        _displayModalWindow: function () {
            this._createModalWindow();
            this.modalWindow.html('');
            $(mageTemplate(copyAltTextTpl, {
                'counters': this.counters
            })).appendTo(this.modalWindow);

            this.modalWindow.find('.progress-counters-list').trigger('contentUpdated');

            this.modalWindow.modal('openModal');
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
                wrapperClass: 'modals-wrapper sirv-modals-wrapper sirv-modals-wrapper-alt-text',
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-ciat-modal',
                title: $.mage.__('Copy images alt text to Sirv'),
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

    return $.sirv.copyAltText;
});
