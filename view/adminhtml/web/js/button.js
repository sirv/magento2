/**
 * Button widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/backend/button',
    'loader'
], function ($, button, loader) {
    'use strict';

    $.widget('sirv.button', $.ui.button, {

        options: {
            showLoader: false
        },

        /**
         * Button click handler
         * @protected
         */
        _click: function () {
            if (this.options.showLoader) {
                $('body').trigger('processStart');
            }

            this._super();
        }
    });

    return $.sirv.button;
});
