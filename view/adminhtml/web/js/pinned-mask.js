/**
 * Pinned images mask widget
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

    $.widget('sirv.pinnedMask', {

        options: {
            nameSelector: '[name=mt-config\\[pinned_items\\]\\[images\\]]',
            targetClass: '.field-mt-pinned_items-mask'
        },

        /** @inheritdoc */
        _create: function () {
            var value = $(this.options.nameSelector + ':checked').val();
            if (value == 'no') {
                this._switchDisabled(true);
            }
            this.element.find(this.options.nameSelector).on('change', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            var value = $(this.options.nameSelector + ':checked').val();
            this._switchDisabled(value == 'no');
        },

        /**
         * Switch disabled attribute
         * @param {Bool} disabled
         */
        _switchDisabled: function (disabled) {
            $(this.options.targetClass).css('display', disabled ? 'none' : 'block');
        }
    });

    return $.sirv.pinnedMask;
});
