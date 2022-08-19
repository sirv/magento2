/**
 * Synchronizer widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, uiAlert) {
    'use strict';

    var simulator = null;

    $.widget('sirv.synchronizer', {

        options: {
            ajaxUrl: null,
            total: 0,
            synced: 0,
            queued: 0,
            failed: 0
        },

        selectors: {
            content: '.sirv-sync-content',
            notificationContainer: '[data-role="sirv-messages"]',
            buttons: {
                save: '#sirv-save-config-button',
                sync: '#sirv-sync-media-button',
                flushUrl: '#mt-urls_cache-button',
                flushAsset: '#mt-assets_cache-button'
            },
            bars: {
                holder: '.sirv-sync-content .progress-bar-holder',
                timer: '.sirv-sync-content .progress-bar-timer',
                synced: '.sirv-sync-content .progress-bar-synced',
                queued: '.sirv-sync-content .progress-bar-queued',
                failed: '.sirv-sync-content .progress-bar-failed'
            },
            texts: {
                progressLabel: '.sirv-sync-content .sync-progress-label',
                completedLabel: '.sirv-sync-content .sync-completed-label',
                failedLabel: '.sirv-sync-content .sync-failed-label',
                progressPercent: '.sirv-sync-content .progress-percent-value',
                completed: '.sirv-sync-content .items-completed-value',
                total: '.sirv-sync-content .items-total-value',
                synced: '.sirv-sync-content .progress-counters-list .list-item-synced .list-item-value',
                queued: '.sirv-sync-content .progress-counters-list .list-item-queued .list-item-value',
                failed: '.sirv-sync-content .progress-counters-list .list-item-failed .list-item-value',
                estimatedDurationNotice: '.sirv-sync-content .estimated-duration-notice'
            },
            actionLinks: {
                synced: '.sirv-sync-content .view-synced-items-link, .sirv-sync-content .clear-synced-items-link',
                queued: '.sirv-sync-content .view-queued-items-link, .sirv-sync-content .clear-queued-items-link',
                failed: '.sirv-sync-content .view-failed-items-link, .sirv-sync-content .clear-failed-items-link'
            },
            viewFailedLink: '.sirv-sync-content .sirv-view-failed-link'
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
                        '<strong><%= data.message %></strong><br />' +
                    '</span>' +
                    '</div>',
            list:   '<div id="<%- data.id %>" class="message message-error list-message">' +
                    '<span class="message-text">' +
                        '<strong><%= data.message %></strong><br />' +
                    '</span>' +
                    '<ul>' +
                    '<% _.each(data.items, function(item, i) { %>' +
                    '<li><a target="_blank" href="<%- item.url %>" title="' +
                    'File <% if (item.isFile) { %>exists<% } else { %>does not exist<% } %>.' +
                    '<% if (item.fileSize) { %> Size <%- item.fileSize %> bytes.<% } %>' +
                    '"><%- item.path %></a></li>' +
                    '<% }); %>' +
                    '</ul>' +
                    '</div>'
        },

        modalWindow: null,

        confirmMessage: $.mage.__('Are you sure you want to stop synchronization?'),
        errorMessage: $.mage.__('Some errors occurred during the synchronization!'),

        timerId: null,
        timeIsLeft: 0,

        doDeleteCachedImages: false,
        useHttpAuth: false,
        httpAuthUser: '',
        httpAuthPass: '',

        /** @inheritdoc */
        _create: function () {
            this.counters.total = Number(this.options.total);
            this.counters.synced = Number(this.options.synced);
            this.counters.queued = Number(this.options.queued);
            this.counters.failed = Number(this.options.failed);
            this.counters.cached = this.counters.synced + this.counters.queued + this.counters.failed;
            this._bind();
            this._getStorageSize();
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
                case 'flush-queued':
                    this._flushCache('queued');
                    break;
                case 'flush-synced':
                    this._flushCache('synced');
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
                case 'flush-empty-assets':
                case 'flush-notempty-assets':
                case 'flush-all-assets':
                    this._flushAssetCache('empty', data.actionUrl);
                    break;
                case 'flush-magento-images-cache':
                    setLocation(data.actionUrl);
                    break;
                case 'disconnect-account':
                    setLocation(data.actionUrl);
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
            $(this.selectors.viewFailedLink).addClass('hidden-element');
            this._hideActionLinks();
            this._updateEstimatedDurationMessage();

            this._addStripes();

            this._getModalWindow();
            this.modalWindow.modal('openModal');

            this.syncStage = 1;

            if (this.counters.total == this.counters.cached) {
                /* NOTE: all images must be in cache, so we can skip stage 1 */
                this.syncStage = 2;
            }

            this.doDeleteCachedImages = $('[name=mt-config\\[delete_cached_images\\]]:checked').val();
            if ($('[name=mt-config\\[http_auth\\]\\[\\]]').prop('checked')) {
                this.httpAuthUser = $('[name=mt-config\\[http_auth_user\\]]').val();
                this.httpAuthPass = $('[name=mt-config\\[http_auth_pass\\]]').val();
                this.useHttpAuth = !!(this.httpAuthUser && this.httpAuthPass);
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

            this._removeStripes();
            this._getSimulator().stop();
            if (!this.isSyncInProgress) {
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
                /* NOTE: unique wrapper classes to avoid overlay issue */
                wrapperClass: 'modals-wrapper sirv-modals-wrapper sirv-modals-wrapper-sync',
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-sync-modal',
                /* title: $.mage.__('Synchronize media'), */
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
                this._syncCompleted();
                return;
            }

            if (this.syncStage == 1 || this.syncStage == 2) {
                this._getSimulator().start();
            }

            var data = {
                'syncStage': this.syncStage,
                'doClean': this.doDeleteCachedImages
            };

            if (this.useHttpAuth) {
                data.httpAuthUser = this.httpAuthUser;
                data.httpAuthPass = this.httpAuthPass;
            }

            this._doRequest(
                'synchronize',
                data,
                this._syncSuccessed,
                this._syncFailed
            );
        },

        /**
         * Get storage size via AJAX request
         */
        _getStorageSize: function () {
            this._doRequest(
                'get_storage_size',
                {},
                function (data) {
                    $('.approximate-storage-size').html(Math.ceil(data.size / 1000000) + ' MB');
                },
                function (message) {
                    uiAlert({
                        title: $.mage.__('Error'),
                        content: $.mage.__(message)
                    });
                }
            );
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
                    /* textStatus: 'parsererror'|'error'|'abort'|'timeout' */
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
        },

        /**
         * Sync request successed
         * @param {Object} data
         */
        _syncSuccessed: function (data) {
            var counters = this.counters;

            this._getSimulator().stop();

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
                    this._syncCompleted();
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

            this._syncCompleted();
        },

        /**
         * Calculate percents
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
                    } else if (percents.queued) {
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

            $(selectors.bars.synced).css('width', percents.synced + '%');
            $(selectors.bars.queued).css('width', percents.synced + percents.queued + '%');
            $(selectors.bars.failed).css('width', percents.synced + percents.queued + percents.failed + '%');

            $(selectors.texts.synced).html(counters.synced);
            $(selectors.texts.queued).html(counters.queued);
            $(selectors.texts.failed).html(counters.failed);

            $(selectors.texts.total).html(counters.total);
            $(selectors.texts.completed).html(counters.synced);
            $(selectors.texts.progressPercent).html(percents.synced);
        },

        /**
         * Update estimated duration message
         */
        _updateEstimatedDurationMessage: function () {
            var estimatedDurationNoticeSelector = this.selectors.texts.estimatedDurationNotice,
                counters = this.counters,
                speed = this.options.maxSpeed,
                imagesToSync,
                estimatedDuration,
                timeUnits,
                mSpeed,
                sSpeed;

            imagesToSync = counters.total - counters.synced - counters.failed;
            if (imagesToSync < 1) {
                $(estimatedDurationNoticeSelector).html('');
                return;
            }

            if (imagesToSync >= speed) {
                estimatedDuration = Math.ceil(imagesToSync / speed);
                timeUnits = estimatedDuration > 1 ? 'hours' : 'hour';
            } else {
                mSpeed = speed / 60;
                if (imagesToSync >= mSpeed) {
                    estimatedDuration = Math.ceil(imagesToSync / mSpeed);
                    timeUnits = estimatedDuration > 1 ? 'minutes' : 'minute';
                } else {
                    sSpeed = mSpeed / 60;
                    estimatedDuration = Math.ceil(imagesToSync / sSpeed);
                    timeUnits = estimatedDuration > 1 ? 'seconds' : 'second';
                }
            }

            $(estimatedDurationNoticeSelector).html($.mage.__(
                'Estimated duration up to ' + estimatedDuration + ' ' + timeUnits + ' at ' + speed + ' images/hour.'
            ));
        },

        /**
         * Get simulator
         */
        _getSimulator: function () {
            if (simulator) {
                return simulator;
            }

            var selectors = this.selectors,
                counters = this.counters,
                percents = this.percents,
                synced = 0,
                queued = 0,
                failed = 0,
                cached = 0,
                isNew = true,
                simulate = null,
                interval = 0,
                timerId = null;

            simulate = function () {
                var syncedPercents, queuedPercents, cachedPercents, failedPercents;

                if (isNew) {
                    if (cached == counters.total) {
                        return;
                    }
                    synced++;
                    cached++;
                } else {
                    if (queued == 0) {
                        return;
                    }
                    synced++;
                    queued--;
                }

                syncedPercents = Math.floor(synced * 100 * 100 / counters.total) / 100;
                queuedPercents = Math.floor(queued * 100 * 100 / counters.total) / 100;
                failedPercents = Math.floor(failed * 100 * 100 / counters.total) / 100;
                cachedPercents = Math.floor(cached * 100 * 100 / counters.total) / 100;

                $(selectors.bars.synced).css('width', syncedPercents + '%');
                $(selectors.bars.queued).css('width', syncedPercents + queuedPercents + '%');
                $(selectors.bars.failed).css('width', syncedPercents + queuedPercents + failedPercents + '%');
                $(selectors.texts.synced).html(synced);
                $(selectors.texts.queued).html(queued);
                $(selectors.texts.completed).html(synced);
                $(selectors.texts.progressPercent).html(syncedPercents);

                timerId = setTimeout(simulate, interval);
            };
            simulate = $.proxy(simulate, this);

            simulator = {};
            simulator.start = function () {
                if (timerId !== null) {
                    clearTimeout(timerId);
                    timerId = null;
                }

                var rest;

                rest = counters.total - counters.cached;
                if (rest < 1) {
                    if (counters.queued < 1) {
                        return;
                    }
                    rest = counters.queued;
                    isNew = false;
                }
                interval = rest < 60 ? Math.floor(60 * 1000 / rest) : 1000;
                synced = counters.synced;
                queued = counters.queued;
                failed = counters.failed;
                cached = counters.cached;

                timerId = setTimeout(simulate, interval);
            };

            simulator.stop = function (reset) {
                if (timerId !== null) {
                    clearTimeout(timerId);
                    timerId = null;
                }

                if (reset) {
                    $(selectors.bars.synced).css('width', percents.synced + '%');
                    $(selectors.bars.queued).css('width', percents.synced + percents.queued + '%');
                    $(selectors.bars.failed).css('width', percents.synced + percents.queued + percents.failed +'%');
                    $(selectors.texts.synced).html(counters.synced);
                    $(selectors.texts.queued).html(counters.queued);
                    $(selectors.texts.completed).html(counters.synced);
                    $(selectors.texts.progressPercent).html(percents.synced);
                }
            };

            return simulator;
        },

        /**
         * Rate limit exceeded
         * @param {Object} data
         */
        _rateLimitExceeded: function (data) {
            var selectors = this.selectors,
                timeIsLeft = (data.expireTime - data.currentTime) * 1000;

            $(selectors.bars.timer).attr('data-content', this._getTimerMessage(timeIsLeft));
            $(selectors.bars.holder).addClass('timer-on');

            this._displayNotification({
                id: 'rate_limit_exceeded_message',
                type: 'notice',
                message: this._improveRateLimitMessage(data.message, timeIsLeft)
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
         * Improve rate limit message
         * @param {String} message
         */
        _improveRateLimitMessage: function (message) {
            var matches, fph;

            matches = message.match(/\((\d+)\)\.\s+Retry\s+after/);
            if (matches) {
                fph = matches[1].replace(/(\d)(\d\d\d)$/, '$1,$2');

                message = 'Rate limit exceeded (' + fph + ' files per hour). ' +
                    'Sync will resume once the hourly limit refreshes.'
            }

            return message;
        },

        /**
         * Update timer
         */
        _updateRateLimitTimer: function () {
            var selectors = this.selectors;

            this.timeIsLeft -= 1000;
            $(selectors.bars.timer).attr('data-content', this._getTimerMessage(this.timeIsLeft));

            if (this.isSyncCanceled || this.timeIsLeft <= 0) {
                $(selectors.bars.holder).removeClass('timer-on');

                //NOTE: clear previous notices
                $(this.selectors.notificationContainer).html('');

                if (this.isSyncCanceled) {
                    this._syncCompleted();
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
         * @param {Integer} time
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
         * Get timer message (Resuming in {hh} hr {mm} min {ss} sec)
         * @param {Integer} time
         */
        _getTimerMessage: function (time) {
            var h, m, s, msg = 'Resuming in';

            if (time <= 0) {
                return 'Resuming in 0 sec';
            }

            s = Math.floor(time / 1000);

            h = Math.floor(s / 3600);
            s -= h * 3600;

            m = Math.floor(s / 60);
            s -= m * 60;

            if (h > 0) {
                h = (h < 10 ? '0' : '') + h;
                msg += ' ' + h + ' hr';
            }
            if (m > 0) {
                m = (m < 10 ? '0' : '') + m;
                msg += ' ' + m + ' min';
            }
            s = (s < 10 ? '0' : '') + s;
            msg += ' ' + s + ' sec';

            return msg;
        },

        /**
         * Sync completed
         */
         _syncCompleted: function () {
            this._removeStripes();

            $(this.selectors.texts.progressLabel).addClass('hidden-element');
            $(this.selectors.texts.failedLabel).addClass('hidden-element');
            $(this.selectors.texts.completedLabel).removeClass('hidden-element');

            if (this.counters.failed) {
                $(this.selectors.viewFailedLink).removeClass('hidden-element');
            } else {
                $(this.selectors.viewFailedLink).addClass('hidden-element');
            }
            this._displayActionLinks();

            if (!this.isSyncFailed) {
                this._enableButtons();
            }

            this.isSyncInProgress = false;
        },

        /**
         * Sync failed
         * @param {String} message
         */
        _syncFailed: function (message) {
            this.isSyncFailed = true;

            this._calculatePercents();
            this._getSimulator().stop(true);

            this._removeStripes();

            $(this.selectors.texts.progressLabel).addClass('hidden-element');
            $(this.selectors.texts.completedLabel).addClass('hidden-element');
            $(this.selectors.texts.failedLabel).removeClass('hidden-element');

            if (this.counters.failed) {
                $(this.selectors.viewFailedLink).removeClass('hidden-element');
            } else {
                $(this.selectors.viewFailedLink).addClass('hidden-element');
            }
            this._displayActionLinks();

            if (message) {
                this._displayNotification({
                    id: 'sync_failed_message',
                    type: 'error',
                    message: message
                });
            }

            this.isSyncInProgress = false;
        },

        /**
         * Flush cache
         * @param {String} flushMethod
         */
        _flushCache: function (flushMethod) {
            this._disableButtons();
            this._addStripes();
            this._doRequest('flush', {'flushMethod': flushMethod}, this._flushSuccessed, this._flushFailed);
        },

        /**
         * Flush asset cache
         * @param {String} flushMethod
         * @param {String} actionUrl
         */
        _flushAssetCache: function (flushMethod, actionUrl) {
            this._disableButtons();
            this._addStripes();
            setLocation(actionUrl);
        },

        /**
         * Flush successed
         * @param {Object} data
         */
        _flushSuccessed: function (data) {
            var counters = this.counters;

            switch (data.method) {
                case 'failed':
                    counters.failed = 0;
                    break;
                case 'queued':
                    counters.queued = 0;
                    break;
                case 'synced':
                    counters.synced = 0;
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

            var i = 1,
                el = $('#failed_images_list_' + i);
            while (el.length) {
                el.remove();
                i++;
                el = $('#failed_images_list_' + i);
            }
            $('#failed_images_message').remove();

            !counters.failed && $(this.selectors.viewFailedLink).addClass('hidden-element');
            this._displayActionLinks();

            this._removeStripes();
            this._enableButtons();
            $('body').trigger('processStop');
        },

        /**
         * Flush failed
         * @param {String} message
         */
        _flushFailed: function (message) {
            this._syncFailed(message);
            $('body').trigger('processStop');
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
         * Hide action links
         */
        _hideActionLinks: function () {
            var key, selector;
            for (key in this.selectors.actionLinks) {
                selector = this.selectors.actionLinks[key];
                $(selector).addClass('hidden-element');
            }
        },

        /**
         * Display action links
         */
        _displayActionLinks: function () {
            var key, selector;
            for (key in this.selectors.actionLinks) {
                selector = this.selectors.actionLinks[key];
                if (this.counters[key]) {
                    $(selector).removeClass('hidden-element');
                } else {
                    $(selector).addClass('hidden-element');
                }
            }
        },

        /**
         * Add stripes
         */
        _addStripes: function () {
            $(this.selectors.bars.holder).addClass('stripes');
        },

        /**
         * Remove stripes
         */
        _removeStripes: function () {
            $(this.selectors.bars.holder).removeClass('stripes');
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
         * @param {Object} data
         */
        _getFailedSuccessed: function (data) {
            if (data && data.failed && data.failed.count) {
                var message = data.failed.count + ' image' +
                    (data.failed.count == 1 ? '' : 's') +
                    ' could not be synced to Sirv.';

                this._displayNotification({
                    id: 'failed_images_message',
                    type: 'error',
                    message: message
                });

                var self = this, i = 0;
                $.each(data.failed.groups, function (message, list) {
                    i++;
                    self._displayNotification({
                        id: 'failed_images_list_' + i,
                        type: 'list',
                        message: message,
                        items: list
                    });
                });
            }

            this._enableButtons();
            $('body').trigger('processStop');
        },

        /**
         * Display notification
         * @param {Object} data
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
