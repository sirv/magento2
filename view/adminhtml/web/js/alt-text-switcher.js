/**
 * AltText option widget
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

    $.widget('sirv.altTextSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            if (this.element.is(':checked')) {
                this._toggleDisplay(this.element.attr('value') == 'false');
            }
            this.element.on('change', $.proxy(function (e) {
                this._toggleDisplay(this.element.attr('value') == 'false');
            }, this));
        },

        /**
         * Display or hide options
         * @param {Bool} hide
         */
        _toggleDisplay: function (hide) {
            $('div[data-ui-id$="-alt-text-rule"]').css('display', hide ? 'none' : 'block');
        }
    });

    return $.sirv.altTextSwitcher;
});
