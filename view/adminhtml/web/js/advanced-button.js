/**
 * Advanced button widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/backend/button',
    'loader'
], function ($, button, loader) {
    'use strict';

    $.widget('sirv.advancedbutton', $.ui.button, {

        options: {
            id: null,
            name: null,
            actionsData: {}
        },
        actionKey: null,

        /**
         * Button creation
         * @protected
         */
        _create: function () {
            var options = this.options,
                keys;

            if (options.id) {
                options.name = 'advancedbutton[' + options.id + ']';
            }

            keys = Object.keys(options.actionsData);
            if (keys.length) {
                this.actionKey = keys[0];
            }

            this._bind();
        },

        /**
         * Bind handler on button click.
         * @protected
         */
        _bind: function () {
            if (this.options.name) {
                $('[name="' + this.options.name + '"')
                    .off('change')
                    .on('change', $.proxy(this._change, this));
            }
            this.element
                .off('click.button')
                .on('click.button', $.proxy(this._click, this));
        },

        /**
         * Switcher change handler
         * @protected
         */
        _change: function (event) {
            this.actionKey = $(event.target).val();
        },

        /**
         * Button click handler
         * @protected
         */
        _click: function () {
            var options = this.options, data, eventData;

            if (this.actionKey && options.actionsData) {
                data = options.actionsData[this.actionKey] || {};
                data.target = data.target || this.element;
                if (data.event && data.target) {
                    eventData = data.eventData || {};
                    if (data.showLoader) {
                        $('body').trigger('processStart');
                    }
                    $(data.target).trigger(data.event, [eventData]);
                }
            }
        }
    });

    return $.sirv.advancedbutton;
});
