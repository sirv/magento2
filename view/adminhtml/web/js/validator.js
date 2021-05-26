/**
 * Validator widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/validator.html',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
], function ($, mageTemplate, validatorTpl, uiAlert, uiConfirm) {
    'use strict';

    $.widget('sirv.validator', {
        options: {
            ajaxUrl: null,
            isEmptySessionData: false
        },
        validatorView: null,
        maxId: 0,
        currentPage: 0,
        counters: {
            total: 0,
            valid: 0,
            invalid: 0,
            failed: 0,
            processed: 0
        },
        percents: {
            total: 100,
            valid: 0,
            invalid: 0,
            failed: 0,
            processed: 0
        },
        currentAction: '',
        isValidationCanceled: false,
        isValidationInProgress: false,
        notificationTemplates: {
            notice: '<div id="<%- data.id %>" class="message">' +
                    '<span class="message-text">' +
                        '<strong><%- data.message %></strong><br />' +
                    '</span>' +
                   '</div>',
            error: '<div id="<%- data.id %>" class="message message-error">' +
                    '<span class="message-text">' +
                        '<% if (data.escape) { %>' +
                        '<strong><%- data.message %></strong><br />' +
                        '<% } else { %>' +
                        '<strong><%= data.message %></strong><br />' +
                        '<% } %>' +
                    '</span>' +
                   '</div>'
        },

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._runValidation, this));
            this._enableButton(this.element);
            if (this.options.isEmptySessionData) {
                $.cookie('sirv_validation_data', JSON.stringify(null));
            }
        },

        /**
         * Run validation
         */
        _runValidation: function () {
            var widget = this,
                validationData;

            this._disableButton(this.element);
            validationData = $.cookie('sirv_validation_data');

            try {
                validationData = typeof validationData === 'string' ? JSON.parse(validationData) : null;
                if (typeof validationData !== 'object') {
                    validationData = null;
                }
            } catch (e) {
                validationData = null;
            }

            if (validationData) {
                uiConfirm({
                    content: 'Continue the previous validation process or cancel to start over?',
                    actions: {
                        /** @inheritdoc */
                        confirm: function () {
                            widget.maxId = validationData.maxId;
                            widget.currentPage = validationData.currentPage;
                            widget.counters.total = validationData.counters.total;
                            widget.counters.valid = validationData.counters.valid;
                            widget.counters.invalid = validationData.counters.invalid;
                            widget.counters.failed = validationData.counters.failed;
                            widget.counters.processed = widget.counters.valid + widget.counters.invalid + widget.counters.failed;
                            widget._startValidation();
                        },
                        /** @inheritdoc */
                        cancel: function () {
                            widget.maxId = 0;
                            widget.currentPage = 0;
                            widget.counters.total = 0;
                            widget.counters.valid = 0;
                            widget.counters.invalid = 0;
                            widget.counters.failed = 0;
                            widget.counters.processed = 0;
                            $.cookie('sirv_validation_data', JSON.stringify(null));
                            $('body').trigger('processStart');
                            widget._ajaxSubmit('get_cached_data', {});
                        }
                    }
                });
            } else {
                $('body').trigger('processStart');
                this._ajaxSubmit('get_cached_data', {});
            }
        },

        /**
         * Get data successed
         * @param {Object} response
         */
        _getCachedDataSuccess: function (response) {
            this.maxId = response.maxId;
            this.counters.total = this.maxId;
            this.counters.valid = 0;
            this.counters.invalid = 0;
            this.counters.failed = 0;
            this.counters.processed = this.counters.valid + this.counters.invalid + this.counters.failed;
            $('body').trigger('processStop');
            this._startValidation();
        },

        /**
         * Start validation
         */
        _startValidation: function () {
            if (!this.counters.total) {
                uiAlert({
                    title: $.mage.__('Notice'),
                    content: $.mage.__('Cache is empty! Nothing to validate.'),
                    actions: {
                        always: function() {}
                    }
                });
                this._enableButton(this.element);
                return;
            }

            this._getViewWindow();
            this._calculatePercents();
            this._updateProgressView();
            this._addStripes();

            this.validatorView.find('.sirv-validator-messages .message').remove();
            $('.clear-invalid-items-button').hasClass('hidden-element') || $('.clear-invalid-items-button').addClass('hidden-element');
            $('.clear-failed-items-button').hasClass('hidden-element') || $('.clear-failed-items-button').addClass('hidden-element');

            this.validatorView.modal('openModal');

            this.isValidationCanceled = false;
            this.isValidationInProgress = true;
            this._validateCache();
        },

        /**
         * Get progress window
         */
        _getViewWindow: function () {
            var widget = this,
                template;

            if (!this.validatorView) {
                template = mageTemplate(validatorTpl, {});
                template = '<div>' + template + '</div>';
                this.validatorView = $(template).find('.sirv-validator-container');

                this.validatorView.modal({
                    title: $.mage.__('Validate cache'),
                    autoOpen: false,
                    clickableOverlay: false,
                    type: 'popup',
                    buttons: [{
                        text: 'Clear invalid items',
                        class: 'clear-invalid-items-button hidden-element',
                        click: function() {
                            widget._clearCacheItems('invalid');
                        }
                    }, {
                        text: 'Clear failed items',
                        class: 'clear-failed-items-button hidden-element',
                        click: function() {
                            widget._clearCacheItems('failed');
                        }
                    }, {
                        text: 'Close',
                        class: 'close-button',
                        click: function() {
                            widget._closeViewWindow();
                        }
                    }],
                    closed: function () {
                    },
                    modalCloseBtnHandler: function () {
                        widget._closeViewWindow();
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
                                widget._closeViewWindow();
                            }
                        }
                    }
                });
            }

            return this.validatorView;
        },

        /**
         * Calculate percents
         */
        _calculatePercents: function () {
            var counters = this.counters,
                percents = this.percents,
                scale = 100,
                restPercent;

            percents.valid = Math.floor(counters.valid * 100 * scale / counters.total);
            percents.invalid = Math.floor(counters.invalid * 100 * scale / counters.total);
            percents.failed = Math.floor(counters.failed * 100 * scale / counters.total);
            percents.processed = percents.valid + percents.invalid + percents.failed;

            if (counters.total == counters.processed) {
                restPercent = 100 * scale - percents.processed;
                if (restPercent > 0) {
                    if (percents.valid) {
                        percents.valid += restPercent;
                    } else if (percents.invalid) {
                        percents.invalid += restPercent;
                    } else {
                        percents.failed += restPercent;
                    }
                    percents.processed = 100 * scale;
                }
            }

            percents.valid = percents.valid / scale;
            percents.invalid = percents.invalid / scale;
            percents.failed = percents.failed / scale;
            percents.processed = percents.processed / scale;
        },

        /**
         * Update progress view
         */
        _updateProgressView: function () {
            var counters = this.counters,
                percents = this.percents;

            this.validatorView.find('.progress-bar-valid').css('width', percents.valid + '%');
            this.validatorView.find('.progress-bar-invalid').css('width', percents.valid + percents.invalid + '%');
            this.validatorView.find('.progress-bar-failed').css('width', percents.valid + percents.invalid + percents.failed + '%');

            this.validatorView.find('.list-item-valid .list-item-value').html(counters.valid);
            this.validatorView.find('.list-item-invalid .list-item-value').html(counters.invalid);
            this.validatorView.find('.list-item-failed .list-item-value').html(counters.failed);

            this.validatorView.find('.progress-percent-value').html(percents.processed);
        },

        /**
         * Validate cache
         */
        _validateCache: function () {
            this._ajaxSubmit('validate', {
                'maxId': this.maxId,
                'currentPage': this.currentPage
            });
        },

        /**
         * Validate cache success
         * @param {Object} response
         */
        _validateSuccess: function (response) {
            this.counters.valid += response.valid;
            this.counters.invalid += response.invalid;
            this.counters.failed += response.failed;
            this.counters.processed = this.counters.valid + this.counters.invalid + this.counters.failed;

            if (response.completed) {
                this.counters.total = this.counters.processed;
            }

            $.cookie('sirv_validation_data', JSON.stringify({
                'maxId': this.maxId,
                'currentPage': this.currentPage,
                'counters': {
                    'total': this.counters.total,
                    'valid': this.counters.valid,
                    'invalid': this.counters.invalid,
                    'failed': this.counters.failed
                }
            }));

            this._calculatePercents();
            this._updateProgressView();

            if (this.isValidationCanceled) {
                this._removeStripes();
                this.validatorView.modal('closeModal');
                this._enableButton(this.element);
                $('body').trigger('processStop');
                return;
            }

            if (response.completed || (this.counters.processed >= this.counters.total)) {
                this._completeValidation();
            } else {
                this.currentPage++;
                this._validateCache();
            }
        },

        /**
         * Validation completed
         */
        _completeValidation: function () {
            this.isValidationInProgress = false;

            /* $.cookie('sirv_validation_data', null); */
            /* $.removeCookie('sirv_validation_data'); */

            var message = 'Cache have been validated. ';
            if (this.counters.invalid) {
                if (this.counters.invalid == 1) {
                    message += '1 item is';
                } else {
                    message += this.counters.invalid + ' items are';
                }
                message += ' missing on Sirv. ';
            }
            if (this.counters.failed) {
                message += 'An attempt to check ';
                if (this.counters.failed == 1) {
                    message += '1 item';
                } else {
                    message += this.counters.failed + ' items';
                }
                message += ' failed.';
            }
            if (!this.counters.invalid && !this.counters.failed) {
                message += 'All items are valid.';
            }

            this._displayNotification(message, false, 'complete-items-message');
            this._removeStripes();
            this.counters.invalid && $('.clear-invalid-items-button').removeClass('hidden-element');
            this.counters.failed && $('.clear-failed-items-button').removeClass('hidden-element');

            this._enableButton(this.element);
        },

        /**
         * Ajax submit
         * @param {String} action
         * @param {Object} params
         */
        _ajaxSubmit: function (action, params) {
            this.currentAction = action;

            var data = {
                isAjax: true,
                dataAction: action
            };
            data = $.extend(data, params);

            $.ajax({
                url: this.options.ajaxUrl,
                data: data,
                type: 'post',
                dataType: 'json',
                context: this,
                /*crossDomain: true,*/
                success: $.proxy(this._ajaxSuccess, this),
                error: $.proxy(this._ajaxError, this)
            });
        },

        /**
         * Ajax success
         * @param {mixed} response
         * @param {String} textStatus
         * @param {Object} jqXHR
         */
        _ajaxSuccess: function (response, textStatus, jqXHR) {
            if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                if (response['error']) {
                    this._displayNotification(response['error']);
                    this.isValidationInProgress = false;
                } else {
                    if (this.currentAction) {
                        var parts = this.currentAction.split('_');
                        for (var i = 1; i < parts.length; i++) {
                            if (parts[i].length) {
                                parts[i] = parts[i].charAt(0).toUpperCase() + parts[i].slice(1);
                            }
                        }
                        var methodName = '_' + parts.join('') + 'Success';
                        if (typeof(this[methodName]) == 'function') {
                            this[methodName](response);
                        }
                    }
                }
            }
        },

        /**
         * Ajax error
         * @param {Object} jqXHR
         * @param {String} textStatus
         * @param {Object} errorThrown
         */
        _ajaxError: function (jqXHR, textStatus, errorThrown) {
            if ('parsererror' == textStatus) {
                jqXHR.responseText && jqXHR.responseText.match(/(Parse|Fatal) error/) &&
                    this._displayNotification(jqXHR.responseText, true, null, true);
                console && console.error && errorThrown && console.error(errorThrown);
            }
            if ('error' == textStatus) {
                this._displayNotification(errorThrown, true);
            }
            $('body').trigger('processStop');
        },

        /**
         * Clear cache items
         * @param {String} itemType
         */
        _clearCacheItems: function (itemType) {
            $('body').trigger('processStart');
            this._ajaxSubmit('clear_cache_items', {
                'itemType': itemType
            });
        },

        /**
         * After clear cache items
         * @param {Array} data
         */
        _clearCacheItemsSuccess: function (data) {
            var itemsCount = 0;
            switch (data.type) {
                case 'invalid':
                    itemsCount = this.counters.invalid;
                    this.counters.processed -= this.counters.invalid;
                    this.counters.total -= this.counters.invalid;
                    this.counters.invalid = 0;
                    $('.clear-invalid-items-button').addClass('hidden-element');
                    break;
                case 'failed':
                    itemsCount = this.counters.failed;
                    this.counters.processed -= this.counters.failed;
                    this.counters.total -= this.counters.failed;
                    this.counters.failed = 0;
                    $('.clear-failed-items-button').addClass('hidden-element');
                    break;
                default:
                    if (console && console.warn) console.warn($.mage.__('Unknown item type!'));
                    return;
            }

            $.cookie('sirv_validation_data', JSON.stringify({
                'maxId': this.maxId,
                'currentPage': this.currentPage,
                'counters': {
                    'total': this.counters.total,
                    'valid': this.counters.valid,
                    'invalid': this.counters.invalid,
                    'failed': this.counters.failed
                }
            }));

            this._calculatePercents();
            this._updateProgressView();
            this._displayNotification('Items has been cleared.', false);
            $('body').trigger('processStop');
        },

        /**
         * Close modal window
         * @param {Boolean} confirmationNeeded
         */
        _closeViewWindow: function (confirmationNeeded) {
            if (typeof(confirmationNeeded) == 'undefined') {
                confirmationNeeded = false;
            }

            var message = $.mage.__('Are you sure you want to interrupt validation!?'),
                widget = this,
                cancelValidation = function () {
                    widget.isValidationCanceled = true;
                    if (widget.isValidationInProgress) {
                        $('body').trigger('processStart');
                    } else {
                        widget.validatorView.modal('closeModal');
                        widget._enableButton(widget.element);
                    }
                };

            if (confirmationNeeded || this.isValidationInProgress) {
                uiConfirm({
                    content: message,
                    actions: {
                        /** @inheritdoc */
                        confirm: cancelValidation,
                        /** @inheritdoc */
                        cancel: function () {}
                    }
                });
            } else {
                cancelValidation();
            }
        },

        /**
         * Enable button
         * @param {Node} el
         */
        _enableButton: function (el) {
            if (el.hasClass('disabled')) {
                el.removeClass('disabled');
                el.attr('disabled', false);
            }
        },

        /**
         * Disable button
         * @param {Node} el
         */
        _disableButton: function (el) {
            if (!el.hasClass('disabled')) {
                el.addClass('disabled');
                el.attr('disabled', true);
            }
        },

        /**
         * Add stripes
         */
        _addStripes: function () {
            this.validatorView.find('.progress-bar-holder').addClass('stripes');
        },

        /**
         * Remove stripes
         */
        _removeStripes: function () {
            this.validatorView.find('.progress-bar-holder').removeClass('stripes');
        },

        /**
         * Display notification
         * @param {String} message
         * @param {Boolean} error
         * @param {String} id
         * @param {Boolean} interpolate
         */
        _displayNotification: function (message, error, id, interpolate) {
            if (typeof(error) == 'undefined') {
                error = true;
            }
            if (typeof(id) == 'undefined' || !id) {
                id = 'message_' + Date.now();
            }
            if (typeof(interpolate) == 'undefined') {
                interpolate = false;
            }
            var template = error ? this.notificationTemplates.error : this.notificationTemplates.notice,
                templateHTML = mageTemplate(template, {
                    data: {
                        id: id,
                        message: interpolate ? message : $.mage.__(message),
                        escape: !interpolate,
                    }
                }),
                node,
                notificationContainer;

            node = $('#' + id);
            if (node.length) {
                node.replaceWith(templateHTML);
            } else {
                notificationContainer = this.validatorView.find('.sirv-validator-messages');
                if (notificationContainer.length) {
                    notificationContainer.append(templateHTML);
                } else {
                    uiAlert({
                        modalClass: 'confirm sirv-modal-popup-error',
                        title: $.mage.__('Error'),
                        content: message,
                        actions: {
                            always: function() {}
                        }
                    });
                }
            }
        }
    });

    return $.sirv.validator;
});
