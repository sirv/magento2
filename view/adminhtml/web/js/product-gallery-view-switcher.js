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

    var doInit = true,
        productGalleryView = 'smv',
        productGallerySetup = 'hidden';

    $.widget('sirv.productGalleryViewSwitcher', {

        options: {},

        /** @inheritdoc */
        _create: function () {
            this.element.on('change', $.proxy(this._eventHandler, this));
            if (doInit) {
                doInit = false;
                productGalleryView = $('[name=mt-config\\[product_gallery_view\\]]:checked').val();
                productGallerySetup = $('[name=mt-config\\[product_gallery_setup\\]]').val();

                if (productGalleryView != 'smv') {
                    this._switchDisabled(true);
                }

                $('.smv-setup-step-1-next').on('click', $.proxy(function(e) {
                    $('#sirv-save-config-button').trigger('click');
                }, this));

                $('.smv-setup-step-2-done').on('click', $.proxy(function(e) {
                    $('.admin-field-additional-info .smv-setup-step-2').css('display', 'none');
                    productGallerySetup = 'step2';
                }, this));

                $('.smv-setup-step-2-contact').on('click', $.proxy(function(e) {
                    $('.smv-setup-step-2-contact-link').click(function() {
                        this.click();
                    });
                    $('.smv-setup-step-2-contact-link').trigger('click');
                }, this));

                if (productGallerySetup == 'step1') {
                    $('.admin-field-additional-info .smv-setup-step-2').css('display', 'block');
                    $('[name=mt-config\\[product_gallery_setup\\]]').val('step2');
                }
            }
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         * @param {Object} data - event data object
         */
        _eventHandler: function (e, data) {
            var enabled = (this.element.attr('value') == 'smv');
            this._switchDisabled(!enabled);
            if (enabled) {
                switch (productGallerySetup) {
                    case 'hidden':
                        $('.admin-field-additional-info .smv-setup-step-1').css('display', 'block');
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('step1');
                        break;
                    case 'step1':
                        $('.admin-field-additional-info .smv-setup-step-2').css('display', 'block');
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('step2');
                        break;
                    case 'step2':
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('step2');
                        break;
                }
            } else {
                switch (productGallerySetup) {
                    case 'hidden':
                        $('.admin-field-additional-info .smv-setup-step-1').css('display', 'none');
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('hidden');
                        break;
                    case 'step1':
                        $('.admin-field-additional-info .smv-setup-step-2').css('display', 'none');
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('hidden');
                        break;
                    case 'step2':
                        $('[name=mt-config\\[product_gallery_setup\\]]').val('hidden');
                        break;
                }
            }
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
