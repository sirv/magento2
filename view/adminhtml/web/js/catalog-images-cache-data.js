/**
 * Catalog images cache data widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, uiAlert, uiConfirm) {
    'use strict';

    $.widget('sirv.catalogImagesCacheData', {

        options: {
            ajaxUrl: null,
            flushUrl: null,
            cacheInfo: {
                count: 0,
                countLabel: '0',
                timestamp: 0,
                date: ''
            }
        },
        dirList: [],
        imagesCounter: 0,
        timerId: null,
        progressCounter: 0,
        errorMessage: 'An error occurred while retrieving information about the multimedia storage. Please try again. If you see this message again, please ' +
            '<a target="_blank" href="https://sirv.com/help/support/#support">inform the Sirv support team</a>.',

        /** @inheritdoc */
        _create: function () {
            if (this.options.cacheInfo.timestamp) {
                if (!this.options.cacheInfo.count) {
                    $('.catalog-images-cache-flush').addClass('hidden');
                }
                this.imagesCounter = this.options.cacheInfo.count;
            } else {
                $('.catalog-images-cache-data-spinner').removeClass('hidden');
                $('.catalog-images-cache-data-progress').removeClass('hidden');
                $('.catalog-images-cache-data').addClass('hidden');
                this._runCounter();
                this._getDirList();
            }

            $('.catalog-images-cache-info-recalculate-link').on('click', $.proxy(this._doRecalculate, this));
            $('.catalog-images-cache-flush-link').on('click', $.proxy(this._doFlush, this));
        },

        /**
         * Recalculate link click handler
         * @protected
         */
        _doRecalculate: function () {
            $('.catalog-images-cache-data-spinner').removeClass('hidden');
            $('.catalog-images-cache-data-progress').removeClass('hidden');
            $('.catalog-images-cache-data').addClass('hidden');

            this._runCounter();
            this._getDirList();

            return false;
        },

        /**
         * Flush link click handler
         * @protected
         */
        _doFlush: function () {
            var confirmationMessage = '<b>Delete ' +
                    this.options.cacheInfo.countLabel +
                    ' resized Magento image' +
                    (this.options.cacheInfo.count == 1 ? '' : 's') +
                    '?</b><br/>This might take a long time.<br/>Keep this page open until deletion is complete.',
                flushUrl = this.options.flushUrl;

            uiConfirm({
                content: $.mage.__(confirmationMessage),
                actions: {
                    confirm: function (event) {
                        $('body').trigger('processStart');
                        setLocation(flushUrl);
                    }
                },
                buttons: [{
                    text: $.mage.__('Cancel'),
                    class: 'action-secondary action-dismiss',
                    click: function (event) {
                        this.closeModal(event);
                    }
                }, {
                    text: $.mage.__('Empty image cache'),
                    class: 'action-primary action-accept',
                    click: function (event) {
                        this.closeModal(event, true);
                    }
                }]
            });

            return false;
        },

        /**
         * Get catalog images cache dir list
         */
        _getDirList: function () {
            this._doRequest(
                'get_dir_list',
                {},
                function (data) {
                    if (data) {
                        if (typeof(data.list) != 'undefined') {
                            if (typeof(data.list.length) != 'undefined') {
                                this.dirList = data.list;
                                this.imagesCounter = 0;
                                this._getImagesCacheInfo();
                                return;
                            }
                        }
                    }
                    this._widgetStop();
                    if (console && console.warn) console.warn(data);
                    this._widgetFailed('Catalog images cache directory not found!');
                },
                this._widgetFailed
            );
        },

        /**
         * Get catalog images cache info
         */
        _getImagesCacheInfo: function () {
            if (this.dirList.length) {
                let dir = this.dirList.pop();
                this._doRequest(
                    'get_images_cache_info',
                    {'dir': dir},
                    function (data) {
                        this.imagesCounter += data.count;
                        this._getImagesCacheInfo();
                    },
                    this._widgetFailed
                );
            } else {
                this._updateImagesCacheInfo();
            }
        },

        /**
         * Update images cache info
         */
        _updateImagesCacheInfo: function () {
            this._doRequest(
                'update_images_cache_info',
                {'count': this.imagesCounter},
                function (data) {
                    $('.catalog-images-cache-info-count').html(
                        data.countLabel +
                        ' resized image' +
                        (data.count == 1 ? '' : 's') +
                        ' on ' + data.date
                    );
                    this.options.cacheInfo = data;
                    this._widgetStop();
                },
                this._widgetFailed
            );
        },

        /**
         * Run counter
         * @param {Bool} start
         * @protected
         */
        _runCounter: function (start) {
            var counterFnc, delay = 1000, self = this;

            if (typeof(start) == 'undefined') {
                start = true;
            }

            if (this.timerId !== null) {
                clearTimeout(this.timerId);
                this.timerId = null;
            }

            this.progressCounter = 0;
            $('.catalog-images-cache-data-progress-counter').html(0);

            if (!start) {
                return;
            }

            counterFnc = function () {
                self.progressCounter++;
                if (self.progressCounter == 100) {
                    return;
                }
                $('.catalog-images-cache-data-progress-counter').html(self.progressCounter);
                self.timerId = setTimeout(
                    counterFnc,
                    delay + Math.floor(self.progressCounter / 10) * 1000
                );
            }

            this.timerId = setTimeout(counterFnc, delay);
        },

        /**
         * Widget stop
         * @protected
         */
        _widgetStop: function () {
            this._runCounter(false);
            $('.catalog-images-cache-data-spinner').addClass('hidden');
            $('.catalog-images-cache-data-progress').addClass('hidden');
            $('.catalog-images-cache-data').removeClass('hidden');
            if (this.imagesCounter) {
                $('.catalog-images-cache-flush').removeClass('hidden');
            } else {
                $('.catalog-images-cache-flush').addClass('hidden');
            }
        },

        /**
         * Widget failed
         * @param {String} message
         * @protected
         */
        _widgetFailed: function (message) {
            this._widgetStop();
            uiAlert({
                title: $.mage.__('Error'),
                content: $.mage.__(message)
            });
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

    return $.sirv.catalogImagesCacheData;
});
