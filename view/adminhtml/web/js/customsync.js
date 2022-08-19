/**
 * Sync product media widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, uiAlert) {
    'use strict';

    $.widget('sirv.customsync', {

        options: {
            ajaxUrl: null
        },

        isWorking: false,
        productId: 0,

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._syncMedia, this));
            var input = $('#mt-custom_sync_product_id');
            if (input.hasClass('disabled')) {
                input.removeClass('disabled');
                input.attr('disabled', false);
            }
            if (this.element.hasClass('disabled')) {
                this.element.removeClass('disabled');
                this.element.attr('disabled', false);
            }
        },

        /**
         * Sync media
         * @protected
         */
        _syncMedia: function () {
            if (!this.isWorking) {
                this.isWorking = true;

                var productId = $('#mt-custom_sync_product_id').val();
                if (!productId.match(/^[1-9][0-9]*$/)) {
                    uiAlert({
                        title: $.mage.__('Notice'),
                        content: $.mage.__('Specify valid product ID.'),
                        actions: {always: function() {}}
                    });
                    this.isWorking = false;
                    return;
                }

                this.productId = parseInt(productId, 10);

                this._syncMediaWithAjax();
            }

            return false;
        },

        /**
         * Sync media with ajax
         * @protected
         */
        _syncMediaWithAjax: function () {
            $('body').trigger('processStart');

            $.ajax({
                url: this.options.ajaxUrl,
                data: {isAjax: true, productId: this.productId},
                type: 'get',
                dataType: 'json',
                context: this,
                success: function (response, textStatus, jqXHR) {
                    this.isWorking = false;
                    this.element.trigger('processStop');
                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        var title = response.error ? 'Warning' : 'Success',
                            content = response.error || 'Images synced: ' + response.synced;
                        uiAlert({
                            title: $.mage.__(title),
                            content: $.mage.__(content),
                            actions: {always: function() {}}
                        });
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                    this.isWorking = false;
                    this.element.trigger('processStop');
                    uiAlert({
                        title: $.mage.__('Error'),
                        content: $.mage.__(errorMessage),
                        actions: {always: function() {}}
                    });
                }
            });
        }
    });

    return $.sirv.customsync;
});
