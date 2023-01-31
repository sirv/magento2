/**
 * Media storage info widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, uiAlert) {
    'use strict';

    $.widget('sirv.mediaStorageInfo', {

        options: {
            ajaxUrl: null
        },
        errorMessage: $.mage.__('Some errors occurred during the calculation!'),
        isCanceled: false,
        cancelMode: false,
        buttonLabel: '',
        dirList: [],
        storageInfo: {'size': 0, 'count': 0},
        timerId: null,
        progressCounter: 0,

        /** @inheritdoc */
        _create: function () {
            this.buttonLabel = $('#mt-media_storage_info-button span span').html();
            this.element.on('click', $.proxy(this._onClickHandler, this));
        },

        /**
         * Click handler
         * @protected
         */
        _onClickHandler: function () {
            $(this.element).attr('disabled', true).addClass('disabled');

            if (this.cancelMode) {
                this.isCanceled = true;
                $('body').trigger('processStart');
            } else {
                $('.media-storage-info-spinner').removeClass('hidden');
                $('.media-storage-info-progress').removeClass('hidden');
                $('.media-storage-info').addClass('hidden');
                this._runCounter();
                this._getMediaStorageDirList();

                this.cancelMode = true;
                $('#mt-media_storage_info-button span span').html('Cancel');
                $(this.element).removeClass('disabled').attr('disabled', false);
            }

            return false;
        },

        /**
         * Get media storage dir list
         */
        _getMediaStorageDirList: function () {
            this._doRequest(
                'get_dir_list',
                {},
                function (data) {
                    if (this.isCanceled) {
                        this._widgetStop();
                        return;
                    }
                    if (data) {
                        if (typeof(data.list) != 'undefined') {
                            if (typeof(data.list.length) != 'undefined') {
                                if (data.list.length) {
                                    this.list = data.list;
                                    this.storageInfo.size = 0;
                                    this.storageInfo.count = 0;
                                    this._getMediaStorageInfo();
                                    return;
                                }
                            }
                        }
                    }
                    if (console && console.warn) console.warn(data);
                    this._widgetFailed('Media storage directory not found!');
                },
                this._widgetFailed
            );
        },

        /**
         * Get media storage info
         */
        _getMediaStorageInfo: function () {
            if (this.list.length) {
                let dir = this.list.pop();
                this._doRequest(
                    'get_media_storage_info',
                    {'dir': dir},
                    function (data) {
                        if (this.isCanceled) {
                            this._widgetStop();
                            return;
                        }
                        this.storageInfo.size += data.size;
                        this.storageInfo.count += data.count;
                        this._getMediaStorageInfo();
                    },
                    this._widgetFailed
                );
            } else {
                this._updateMediaStorageInfo();
            }
        },

        /**
         * Update media storage info
         */
        _updateMediaStorageInfo: function () {
            this._doRequest(
                'update_media_storage_info',
                this.storageInfo,
                function (data) {
                    $('.media-storage-info').html(
                        '<span class="media-storage-info-size">' +
                        Math.ceil(this.storageInfo.size / 1000000) + ' MB</span>' +
                        ' (<span class="media-storage-info-count">' +
                        this.storageInfo.count + ' image' +
                        (this.storageInfo.count == 1 ? '' : 's') +
                        '</span>)' +
                        ' on <span class="media-storage-info-timestamp">' +
                        data.date +
                        '</span>'
                    );
                    this.buttonLabel = 'Recalculate';
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
            $('.media-storage-info-progress-counter').html(0);

            if (!start) {
                return;
            }

            counterFnc = function () {
                self.progressCounter++;
                if (self.progressCounter == 100) {
                    return;
                }
                $('.media-storage-info-progress-counter').html(self.progressCounter);
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
            this.cancelMode = false;
            this.isCanceled = false;
            this._runCounter(false);
            $('.media-storage-info-spinner').addClass('hidden');
            $('.media-storage-info-progress').addClass('hidden');
            $('.media-storage-info').removeClass('hidden');
            $('#mt-media_storage_info-button span span').html(this.buttonLabel);
            $('body').trigger('processStop');
            $(this.element).removeClass('disabled').attr('disabled', false);
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

    return $.sirv.mediaStorageInfo;
});
