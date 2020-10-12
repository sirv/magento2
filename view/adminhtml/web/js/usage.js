/**
 * Usage data widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'text!sirv/template/limits.html',
    'mage/translate'
], function ($, mageTemplate, limitsTpl) {
    'use strict';

    $.widget('sirv.usage', {

        options: {
            ajaxUrl: null
        },

        isUpdating: false,

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._updateUsageData, this));
        },

        /**
         * Update usage data
         * @protected
         */
        _updateUsageData: function () {
            if (!this.isUpdating) {
                this.isUpdating = true;
                this._getUsageData();
            }
            return false;
        },

        /**
         * Get usage data
         * @protected
         */
        _getUsageData: function () {
            var loaderContext = $('.sirv-usage-wraper .admin__table-primary').parent();
            this.element.trigger('processStart', [loaderContext]);

            $.ajax({
                url: this.options.ajaxUrl,
                data: {isAjax: true},
                type: 'get',
                dataType: 'json',
                context: this,
                success: function (response, textStatus, jqXHR) {
                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        $('.sirv-usage-wraper .last-update-time').replaceWith(
                            'Last update: ' + response.current_time
                        );
                        var tmpl = mageTemplate(limitsTpl, {
                            'items': response.limits
                        });
                        $('.sirv-usage-wraper .admin__table-primary tbody').html(tmpl);
                    }
                    this.isUpdating = false;
                    this.element.trigger('processStop');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                    this.isUpdating = false;
                    this.element.trigger('processStop');
                }
            });
        }
    });

    return $.sirv.usage;
});
