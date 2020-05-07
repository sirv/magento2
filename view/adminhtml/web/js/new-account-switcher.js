/**
 * New account switcher widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.switcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            this.element.on('change', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            var fields = {
                    'first_and_last_name': 'yes',
                    'alias': 'yes',
                    'connect': 'no',
                    'register': 'yes'
                },
                value = this.element.attr('value'),
                disabled = (value == 'yes'),
                display;

            for (name in fields) {
                display = fields[name] == value ? 'none' : 'block';
                $('.field-mt-' + name).css('display', display);
            }

            $('[name=magictoolbox\\[first_and_last_name\\]]').prop('disabled', disabled);
            $('[name=magictoolbox\\[alias\\]]').prop('disabled', disabled);
        }
    });

    return $.sirv.switcher;
});
