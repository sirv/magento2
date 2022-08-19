/**
 * Tooltip widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.tooltip', {

        options: {},
        timerId: null,

        _create: function () {
            this._on({
                mouseover: 'open',
                focusin: 'open',
                mouseleave: 'close',
                focusout: 'close'
            });
        },

        open: function (event) {
            if (this.timerId) {
                clearTimeout(this.timerId);
                this.timerId = null;
            }

            this.element.find('.tooltip-content').css('display', 'block');
        },

        close: function (event) {
            var hideFnc;

            if (this.timerId) {
                clearTimeout(this.timerId);
                this.timerId = null;
            }

            hideFnc = function() {
                this.element.find('.tooltip-content').css('display', 'none');
            };
            hideFnc = $.proxy(hideFnc, this);
            this.timerId = setTimeout(hideFnc, 500);
        }
    });

    return $.sirv.tooltip;
});
