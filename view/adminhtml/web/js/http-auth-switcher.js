/**
 * HTTP auth switcher widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.httpAuthSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            if (!this.element.prop('checked')) {
                this._switchDisabled(true);
            }
            this.element.on('change', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         */
        _eventHandler: function (e) {
            this._switchDisabled(!this.element.prop('checked'));
        },

        /**
         * Switch disabled attribute
         * @param {Bool} disabled
         */
        _switchDisabled: function (disabled) {
            var options = [
                'http_auth_user',
                'http_auth_pass'
            ];
            var i, l, selector, display;
            l = options.length;
            display = disabled ? 'none' : 'block';
            for (i = 0; i < l; i++) {
                selector = 'div[data-ui-id$="-' + options[i].replace(/_/g, '-') + '"]';
                $(selector).css('display', display);
            }
        }
    });

    return $.sirv.httpAuthSwitcher;
});
