/**
 * Auto fetch switcher widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.autoFetchSwitcher', {

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
            var subOption = this.element.parents('.admin__field-control').find('.admin__field-sub-option');
            subOption.insertAfter(this.element.parents('.admin__field-option'));
            if (this.element.attr('value') == 'none') {
                subOption.addClass('hidden');
            } else {
                subOption.removeClass('hidden');
            }
        }
    });

    return $.sirv.autoFetchSwitcher;
});
