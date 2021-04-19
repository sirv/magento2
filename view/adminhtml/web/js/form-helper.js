/**
 * Form helper
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

    $.widget('sirv.formHelper', {

        options: {
        },

        /** @inheritdoc */
        _create: function () {
            $('[name=mt-config\\[email\\]]').on('keydown', $.proxy(this._eventHandler, this));
            $('[name=mt-config\\[password\\]]').on('keydown', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         */
        _eventHandler: function (e) {
            if (e.keyCode === $.ui.keyCode.ENTER) {
                $('#mt-connect').click();
            }
        }
    });

    return $.sirv.formHelper;
});
