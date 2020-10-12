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

    $.widget('sirv.productGalleryViewSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            var value = $('[name=magictoolbox\\[product_gallery_view\\]]:checked').val();
            if (value != 'smv') {
                this._switchDisabled(true);
            }
            this.element.on('change', $.proxy(this._eventHandler, this));
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            this._switchDisabled(!(this.element.attr('value') == 'smv'));
        },

        /**
         * Switch disabled attribute
         * @param {Bool} disabled
         */
        _switchDisabled: function (disabled) {
            $('[name=magictoolbox\\[viewer_contents\\]]').prop('disabled', disabled);
            $('[name=magictoolbox\\[product_assets_folder\\]]').prop('disabled', disabled);
            $('[name=magictoolbox\\[smv_js_options\\]]').prop('disabled', disabled);
            $('[name=magictoolbox\\[smv_max_height\\]]').prop('disabled', disabled);
        }
    });

    return $.sirv.productGalleryViewSwitcher;
});
