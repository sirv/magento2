/**
 * Product gallery view switcher widget
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

    $.widget('sirv.productGalleryViewSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            var value = $('[name=mt-config\\[product_gallery_view\\]]:checked').val();
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
            var options = [
                'product_assets_folder',
                'slides_order',
                'viewer_contents',
                'pinned_items',
                'smv_layout',
                'smv_grid_gap',
                'smv_aspect_ratio',
                'smv_max_height',
                'use_placeholder_with_smv',
                'image_zoom',
                'smv_js_options',
                'smv_custom_css',
                'assets_cache_ttl',
                'assets_cache',
                'copy_primary_images_to_magento'
            ];
            var i, l, selector, display;
            l = options.length;
            display = disabled ? 'none' : 'block';
            for (i = 0; i < l; i++) {
                selector = 'div[data-ui-id$="-' + options[i].replace(/_/g, '-') + '"]';
                $(selector).css('display', display);
            }
            $('.admin-field-group-legend').slice(1).css('display', display);
            $('.admin-field-group-comment').slice(1).css('display', display);
            $('.admin-field-group-separator').css('display', display);
        }
    });

    return $.sirv.productGalleryViewSwitcher;
});
