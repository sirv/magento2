/**
 * Synchronizer widget
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */

define([
    'jquery',
    'mage/template',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, uiAlert) {
    'use strict';

    $.widget('sirv.synchronizer', {

        options: {
            ajaxUrl: null,
            total: 0,
            synced: 0,
            queued: 0,
            failed: 0
        },

        selectors: {
            container: '#sirv-sync-wraper-container',
            content: '.sirv-sync-wraper',
            notificationContainer: '[data-role="sirv-messages"]',
            buttons: {
                save: '.sirv-save-config-button',
                sync: '.sirv-sync-media-button',
                flush: '.sirv-flush-cache-button, .sirv-flush-cache-button button'
            },
            bars: {
                timer: '.sirv-sync-wraper .progress-bar-timer',
                error: '.sirv-sync-wraper .progress-bar-error',
                holder: '.sirv-sync-wraper .progress-bar-holder',
                synced: '.sirv-sync-wraper .progress-bar-synced',
                queued: '.sirv-sync-wraper .progress-bar-queued',
                failed: '.sirv-sync-wraper .progress-bar-failed'
            },
            texts: {
                progressLabel: '.sirv-sync-wraper .sync-progress-label-text',
                completedLabel: '.sirv-sync-wraper .sync-completed-label-text',
                failedLabel: '.sirv-sync-wraper .sync-failed-label-text',
                progressPercent: '.sirv-sync-wraper .progress-percent-text',
                completed: '.sirv-sync-wraper .items-completed-text',
                total: '.sirv-sync-wraper .items-total-text',
                synced: '.sirv-sync-wraper .items-synced-text',
                queued: '.sirv-sync-wraper .items-queued-text',
                failed: '.sirv-sync-wraper .items-failed-text'
            },
            viewFailedLink: '.sirv-sync-wraper .sirv-view-failed-link'
        },

        counters: {
            total: 0,
            synced: 0,
            queued: 0,
            failed: 0,
            cached: 0
        },

        percents: {
            total: 100,
            synced: 0,
            queued: 0,
            failed: 0,
            cached: 0
        },

        /*
        NOTE: 0 - get sync data
              1 - sync images that are not in cache
              2 - sync images that are in cache but not synced
        */
        syncStage: 1,

        isSyncFailed: false,
        isSyncInProgress: false,
        isSyncCanceled: false,

        notificationTemplates: {
            notice: '<div id="<%- data.id %>" class="message">' +
                    '<span class="message-text">' +
                        '<strong><%- data.message %></strong><br />' +
                    '</span>' +
                    '</div>',
            error:  '<div id="<%- data.id %>" class="message message-error">' +
                    '<span class="message-text">' +
                        '<strong><%- data.message %></strong><br />' +
                    '</span>' +
                    '</div>',
            list:   '<div id="<%- data.id %>" class="message message-error list-message">' +
                    '<span class="message-text">' +
                        '<strong><%- data.message %></strong><br />' +
                    '</span>' +
                    '<ul>' +
                    '<% _.each(data.items, function(item, i) { %>' +
                    '<li><%- item %></li>' +
                    '<% }); %>' +
                    '</ul>' +
                    '</div>'
        },

        modalWindow: null,

        confirmMessage: $.mage.__('Are you sure you want to stop synchronization?'),
        errorMessage: $.mage.__('Some errors occurred during the synchronization!'),

        timerId: null,
        timeIsLeft: 0,

        /** @inheritdoc */
        _create: function () {
            this.counters.total = Number(this.options.total);
            this.counters.synced = Number(this.options.synced);
            this.counters.queued = Number(this.options.queued);
            this.counters.failed = Number(this.options.failed);
            this.counters.cached = this.counters.synced + this.counters.queued + this.counters.failed;

            this._bind();
        },

        /**
         * Bind handlers
         * @protected
         */
        _bind: function () {
            this.element.on('sirv-sync', $.proxy(this._eventHandler, this));
            $(this.selectors.viewFailedLink).on('click', function () {return false;});
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            switch (data.action) {
                case 'start-sync':
                    this._startSync();
                    break;
                case 'stop-sync':
                    this._stopSync();
                    break;
                case 'flush-failed':
                    this._flushCache('failed');
                    break;
                case 'flush-all':
                    this._flushCache('all');
                    break;
                case 'flush-master':
                    this._flushCache('master');
                    break;
                case 'view-failed':
                    this._viewFailed();
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Unknown action!'));
            }
        },

        /**
         * Start synchronization
         */
        _startSync: function () {
            if (this.isSyncFailed || this.isSyncInProgress) {
                return;
            }

            if (!this.counters.total) {
                uiAlert({
                    /*
                    title: $.mage.__('Warning'),
                    */
                    content: $.mage.__('No media to sync!'),
                    actions: {
                        always: function(){
                            /*
                            NOTICE: this action is called 2 times from 'closeModal' method
                                    ($.mage.alert and $.mage.confirm widgets)
                            */
                        }
                    }
                });

                return;
            }

            this.isSyncInProgress = true;
            this.isSyncFailed = false;
            this.isSyncCanceled = false;

            this._disableButtons();

            //NOTE: clear previous notices
            $(this.selectors.notificationContainer).html('');

            $(this.selectors.texts.completedLabel).addClass('hidden-element');
            $(this.selectors.texts.failedLabel).addClass('hidden-element');
            $(this.selectors.texts.progressLabel).removeClass('hidden-element');

            this._addStripes();

            this._getModalWindow();
            this.modalWindow.modal('openModal');

            this.syncStage = 1;

            if (this.counters.total == this.counters.cached) {
                /* NOTE: all images must be in cache, so we can skip stage 1 */
                this.syncStage = 2;
            }

            this._doSync();
        },

        /**
         * Stop synchronization
         */
        _stopSync: function () {
            if (this.isSyncCanceled || this.isSyncInProgress && !window.confirm(this.confirmMessage)) {
                return;
            }

            this.isSyncCanceled = true;

            this.modalWindow.modal('closeModal');

            if (!this.isSyncInProgress) {
                this._removeStripes();
                if (!this.isSyncFailed) {
                    this._enableButtons();
                }
            }
        },

        /**
         * Get modal window
         */
        _getModalWindow: function () {

            if (this.modalWindow) {
                return this.modalWindow;
            }

            var self = this,
                dialogProperties,
                content;

            dialogProperties = {
                overlayClass: 'modals-overlay sirv-modals-overlay',
                title: $.mage.__('Synchronize media'),
                autoOpen: false,
                clickableOverlay: false,
                type: 'popup',
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'close-button',
                    click: function () {
                        self._stopSync();
                    }
                }],
                closed: function () {
                },
                modalCloseBtnHandler: function () {
                    self._stopSync();
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
                            self._stopSync();
                        }
                    }
                }
            };

            //TODO: update content
            content = $(this.selectors.content).clone(true);

            this.modalWindow = content.modal(dialogProperties);

            return this.modalWindow;
        },

        /**
         * Synchronize via AJAX request
         */
        _doSync: function () {
            if (this.isSyncCanceled) {
                this._syncСompleted();
                return;
            }

            this._doRequest('synchronize', {'syncStage': this.syncStage}, this._syncSuccessed, this._syncFailed);
        },

        /**
         * Do AJAX request
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
                /*crossDomain: true,*/
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
                    switch (textStatus) {
                        case 'parsererror':
                        case 'error':
                        case 'abort':
                        case 'timeout':
                        default:
                            message = (typeof errorThrown == 'string' ? errorThrown : null);
                            message = (typeof errorThrown == 'object' ? errorThrown.message : message);
                            if (console && console.error) console.error(errorThrown);
                    }
                    message = message || self.errorMessage;

                    failureCallback(message);
                }
            });
        },

        /**
         * Sync request successed
         */
        _syncSuccessed: function (data) {
            var counters = this.counters;

            switch (this.syncStage) {
                case 0:
                    counters.total = Number(data.total);
                    counters.synced = Number(data.synced);
                    counters.failed = Number(data.failed);
                    counters.queued = Number(data.queued);
                    break;
                case 1:
                    counters.synced += Number(data.synced);
                    counters.failed += Number(data.failed);
                    counters.queued += Number(data.queued);
                    break;
                case 2:
                    counters.synced += Number(data.synced);
                    counters.failed += Number(data.failed);
                    counters.queued -= (Number(data.synced) + Number(data.failed));
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Error: wrong stage!'));
                    this._syncFailed(this.errorMessage);
                    return;
            }

            counters.cached = counters.synced + counters.queued + counters.failed;

            /* NOTE: counters invalidated */
            if (counters.total < counters.cached || counters.queued < 0) {
                if (this.syncStage) {
                    this.syncStage = 0;
                    this._doSync();
                } else {
                    if (console && console.warn) console.warn($.mage.__('Error: counters invalidated!'));
                    this._syncFailed(this.errorMessage);
                }
                return;
            }

            this._calculatePercents();
            this._updateProgressView();

            if (data.ratelimit) {
                this._rateLimitExceeded(data.ratelimit);
                return;
            }

            if (counters.total > counters.cached) {

                //NOTE: protector
                if (data.completed) {
                    this._syncСompleted();
                    return;
                }

                this.syncStage = 1;
                this._doSync();
                return;
            }

            if (counters.total == counters.cached) {
                if (counters.queued) {
                    this.syncStage = 2;
                    this._doSync();
                    return;
                }
            }

            this._syncСompleted();
        },

        /**
         * Update progress view
         */
        _calculatePercents: function () {
            var counters = this.counters,
                percents = this.percents,
                scale = 100,
                restPercent;

            percents.synced = Math.floor(counters.synced * 100 * scale / counters.total);
            percents.queued = Math.floor(counters.queued * 100 * scale / counters.total);
            percents.failed = Math.floor(counters.failed * 100 * scale / counters.total);
            percents.cached = percents.synced + percents.queued + percents.failed;

            if (counters.total == counters.cached) {
                restPercent = 100 * scale - percents.cached;
                if (restPercent > 0) {
                    if (percents.synced) {
                        percents.synced += restPercent;
                    } else if ($percents.queued) {
                        percents.queued += restPercent;
                    } else {
                        percents.failed += restPercent;
                    }
                    percents.cached = 100 * scale;
                }
            }

            percents.synced = percents.synced / scale;
            percents.queued = percents.queued / scale;
            percents.failed = percents.failed / scale;
            percents.cached = percents.cached / scale;
        },

        /**
         * Update progress view
         */
        _updateProgressView: function () {
            var selectors = this.selectors,
                counters = this.counters,
                percents = this.percents;

            $(selectors.bars.synced).attr('data-count', counters.synced).css('width', percents.synced + '%');
            $(selectors.bars.queued).attr('data-count', counters.queued).css('width', percents.queued + '%');
            $(selectors.bars.failed).attr('data-count', counters.failed).css('width', percents.failed + '%');

            $(selectors.texts.synced).html(counters.synced);
            $(selectors.texts.queued).html(counters.queued);
            $(selectors.texts.failed).html(counters.failed);

            $(selectors.texts.total).html(counters.total);
            $(selectors.texts.completed).html(counters.cached);
            $(selectors.texts.progressPercent).html(percents.cached);
        },

        /**
         * Rate limit exceeded
         */
        _rateLimitExceeded: function (data) {
            var selectors = this.selectors,
                timeIsLeft = (data.expireTime - data.currentTime) * 1000;

            $(selectors.bars.timer).attr('data-content', this._timeToString(timeIsLeft));
            $(selectors.bars.holder).addClass('timer-on');

            this._displayNotification({
                id: 'rate_limit_exceeded_message',
                type: 'notice',
                message: data.message
            });

            if (this.timerId !== null) {
                clearInterval(this.timerId);
                this.timerId = null;
            }

            this.timeIsLeft = timeIsLeft;
            this.timerId = setInterval(
                $.proxy(this._updateRateLimitTimer, this),
                1000
            );
        },

        /**
         * Update timer
         */
        _updateRateLimitTimer: function () {
            var selectors = this.selectors;

            this.timeIsLeft -= 1000;
            $(selectors.bars.timer).attr('data-content', this._timeToString(this.timeIsLeft));

            if (this.isSyncCanceled || this.timeIsLeft <= 0) {
                $(selectors.bars.holder).removeClass('timer-on');

                //NOTE: clear previous notices
                $(this.selectors.notificationContainer).html('');

                if (this.isSyncCanceled) {
                    this._syncСompleted();
                } else {
                    this.syncStage = 1;
                    this._doSync();
                }

                clearInterval(this.timerId);
                this.timerId = null;
            }
        },

        /**
         * Convert time (in milliseconds) to string view (hh:mm:ss)
         */
        _timeToString: function (time) {
            var h, m, s;

            if (time <= 0) {
                return '00:00:00';
            }

            s = Math.floor(time / 1000);

            h = Math.floor(s / 3600);
            s -= h * 3600;

            m = Math.floor(s / 60);
            s -= m * 60;

            h = (h < 10 ? '0' : '') + h;
            m = (m < 10 ? '0' : '') + m;
            s = (s < 10 ? '0' : '') + s;

            return h + ':' + m + ':' + s;
        },

        /**
         * Sync completed
         */
         _syncСompleted: function () {
            this._removeStripes();

            $(this.selectors.texts.progressLabel).addClass('hidden-element');
            $(this.selectors.texts.failedLabel).addClass('hidden-element');
            $(this.selectors.texts.completedLabel).removeClass('hidden-element');

            if (this.counters.failed) {
                $(this.selectors.viewFailedLink).removeClass('hidden-element');
            } else {
                $(this.selectors.viewFailedLink).addClass('hidden-element');
            }

            if (!this.isSyncFailed) {
                this._enableButtons();
            }

            this.isSyncInProgress = false;
        },

        /**
         * Sync failed
         */
        _syncFailed: function (message) {
            this.isSyncFailed = true;

            $(this.selectors.bars.holder).addClass('error-on');

            $('.sirv-sync-wraper .progress-list-group').addClass('progress-list-group-faded');

            this._removeStripes();

            $(this.selectors.texts.progressLabel).addClass('hidden-element');
            $(this.selectors.texts.completedLabel).addClass('hidden-element');
            $(this.selectors.texts.failedLabel).removeClass('hidden-element');

            if (message) {
                $(this.selectors.bars.error).attr('data-content', message);
            }

            this.isSyncInProgress = false;
        },

        /**
         * Flush cache
         */
        _flushCache: function (flushMethod) {
            $('#sirv-flush [data-toggle=dropdown].active').trigger('close.dropdown');
            $('#sirv_group_fieldset_synchronization').get(0).scrollIntoView();

            this._disableButtons();
            this._addStripes();
            this._doRequest('flush', {'flushMethod': flushMethod}, this._flushSuccessed, this._flushFailed);
        },

        /**
         * Flush successed
         */
        _flushSuccessed: function (data) {
            var counters = this.counters;

            switch (data.method) {
                case 'failed':
                    counters.failed = 0;
                    break;
                case 'all':
                case 'master':
                    counters.synced = 0;
                    counters.queued = 0;
                    counters.failed = 0;
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Unknown flush method!'));
                    return;
            }

            counters.cached = counters.synced + counters.queued;

            this._calculatePercents();
            this._updateProgressView();

            $('#failed_list_items').remove();
            $('#failed_list_message').remove();
            $(this.selectors.viewFailedLink).addClass('hidden-element');
            this._removeStripes();
            this._enableButtons();
        },

        /**
         * Flush failed
         */
        _flushFailed: function (message) {
            this._syncFailed(message);
        },

        /**
         * Disable buttons
         */
        _disableButtons: function () {
            var key, selector;
            for (key in this.selectors.buttons) {
                selector = this.selectors.buttons[key];
                $(selector).attr('disabled', true).addClass('disabled');
            }
        },

        /**
         * Enable buttons
         */
        _enableButtons: function () {
            var key, selector;
            for (key in this.selectors.buttons) {
                selector = this.selectors.buttons[key];
                $(selector).removeClass('disabled').attr('disabled', false);
            }
        },

        /**
         * Add stripes
         */
        _addStripes: function () {
            var bars = ['synced', 'queued', 'failed', 'holder'],
                key,
                selector;
            for (key in this.selectors.bars) {
                if (bars.indexOf(key) == -1) {
                    continue;
                }
                selector = this.selectors.bars[key];
                $(selector).addClass('stripes');
            }
        },

        /**
         * Remove stripes
         */
        _removeStripes: function () {
            var bars = ['synced', 'queued', 'failed', 'holder'],
                key,
                selector;
            for (key in this.selectors.bars) {
                if (bars.indexOf(key) == -1) {
                    continue;
                }
                selector = this.selectors.bars[key];
                $(selector).removeClass('stripes');
            }
        },

        /**
         * View failed list
         */
        _viewFailed: function () {
            this._disableButtons();
            $('body').trigger('processStart');
            this._doRequest('get_failed', {}, this._getFailedSuccessed, this._syncFailed);
        },

        /**
         * Get failed list successed
         */
        _getFailedSuccessed: function (data) {
            if (data && data.pathes) {
                var message = data.pathes.length + ' image' +
                    (data.pathes.length > 1 ? 's' : '') +
                    ' could not be synced to Sirv because ' +
                    (data.pathes.length > 1 ? 'they are' : 'it is') +
                    ' missing from your server.';

                this._displayNotification({
                    id: 'failed_list_message',
                    type: 'error',
                    message: message
                });
                this._displayNotification({
                    id: 'failed_list_items',
                    type: 'list',
                    message: 'List of images:',
                    items: data.pathes
                });
            }

            this._enableButtons();
            $('body').trigger('processStop');
        },

        /**
         * Display notification
         */
        _displayNotification: function (data) {
            if (typeof(data) == 'undefined') {
                data = {};
            }
            if (typeof(data.id) == 'undefined') {
                data.id = 'sync_message_' + Date.now();
            }
            if (typeof(data.type) == 'undefined') {
                data.type = 'notice';
            }
            if (typeof(data.message) == 'undefined') {
                data.message = '';
            }
            if (typeof(data.list) == 'undefined') {
                data.list = {};
            }

            var template = this.notificationTemplates[data.type],
                node = $('#' + data.id),
                html = '';

            data.message = $.mage.__(data.message);

            html = mageTemplate(template, {
                data: data
            });

            if (node.length) {
                node.replaceWith(html);
            } else {
                $(this.selectors.notificationContainer).append(html);
            }
        }
    });

    return $.sirv.synchronizer;
});