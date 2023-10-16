/**
 * Changelog widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/changelog.html',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, mageTemplate, changelogTpl) {
    'use strict';

    var changelogData = null;

    $.widget('sirv.changelog', {

        options: {
            changelogUrl: null
        },

        isDisplayed: false,
        modalWindow: null,

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._displayChangelog, this));
        },

        /**
         * Display changelog
         * @protected
         */
        _displayChangelog: function () {
            if (!this.isDisplayed) {
                this.isDisplayed = true;
                if (changelogData) {
                    this._getChangelogSuccessed(changelogData);
                } else {
                    this._getChangelog();
                }
            }
            return false;
        },

        /**
         * Hide changelog
         * @protected
         */
        _hideChangelog: function () {
            if (this.isDisplayed) {
                this.isDisplayed = false;
                this.modalWindow.modal('closeModal');
            }
        },

        /**
         * Get changelog
         * @protected
         */
        _getChangelog: function () {
            $.ajax({
                url: this.options.changelogUrl,
                data: {isAjax: true},
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: true,
                success: function (response, textStatus, jqXHR) {
                    var data = {
                        'error': 'Unexpected response.',
                        'link': '#',
                        'items': []
                    };
                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        changelogData = data = response;
                    }
                    this._getChangelogSuccessed(data);
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
         * Get changelog successed
         * @param {String} data
         * @protected
         */
        _getChangelogSuccessed: function (data) {
            if (!this.modalWindow) {
                this._createModalWindow();
                $(mageTemplate(changelogTpl, {
                    'error': data.error,
                    'link': data.link,
                    'items': data.items,
                })).appendTo(this.modalWindow);
            }
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
                wrapperClass: 'modals-wrapper sirv-modals-wrapper sirv-modals-wrapper-changelog',
                overlayClass: 'modals-overlay sirv-modals-overlay',
                modalClass:  'sirv-changelog-modal',
                title: $.mage.__('What\'s new'),
                autoOpen: false,
                clickableOverlay: false,
                type: 'popup',
                innerScroll: true,
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'close-button',
                    click: function () {
                        self._hideChangelog();
                    }
                }],
                closed: function () {
                },
                modalCloseBtnHandler: function () {
                    self._hideChangelog();
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
                            self._hideChangelog();
                        }
                    }
                }
            };

            this.modalWindow = $('<div></div>').modal(dialogProperties);

            return this.modalWindow;
        }
    });

    return $.sirv.changelog;
});
