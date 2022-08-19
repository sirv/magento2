/**
 * Dismiss review widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('sirv.dismissReview', {

        options: {
            ajaxUrl: null,
            bannerSelector: '.sirv-banner-wrapper'
        },

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._dismissReview, this));
        },

        /**
         * Dismiss review
         * @protected
         */
        _dismissReview: function () {
            this.element.closest(this.options.bannerSelector).css('display', 'none');

            $.ajax({
                url: this.options.ajaxUrl,
                data: {isAjax: true},
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: false,
                success: function (response, textStatus, jqXHR) {
                    if ($.type(response) !== 'object' || $.isEmptyObject(response)) {
                        console && console.warn && console.warn('Unexpected response.');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.warn && errorMessage && console.warn(errorMessage);
                }
            });

            return false;
        }
    });

    return $.sirv.dismissReview;
});
