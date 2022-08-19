/**
 * Sirv assets cache widget
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

    $.widget('sirv.assetsCache', {

        options: {
            ttl: null,
            currentTime: 0,
            url: null,
            timestamps: []
        },

        /** @inheritdoc */
        _create: function () {
            var ttl = this.options.ttl,
                currentTime = this.options.currentTime,
                url = this.options.url,
                timestamps = this.options.timestamps,
                timestamp,
                data = [];

            if (url && ttl !== null) {
                ttl = parseInt(ttl) * 60;
                currentTime = parseInt(currentTime);
                for (var id in timestamps) {
                    timestamp = parseInt(timestamps[id]);
                    if (timestamp + ttl < currentTime) {
                        data.push(id);
                    }
                }
                if (data.length) {
                    this._refreshAssets(data);
                }
            }
        },

        /**
         * Refresh assets
         * @param {Array} ids - product IDs
         * @protected
         */
        _refreshAssets: function (ids) {
            $.ajax({
                url: this.options.url,
                data: {isAjax: true, ids: ids},
                type: 'get',
                dataType: 'json',
                context: this,
                showLoader: false,
                success: function (response, textStatus, jqXHR) {
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    var errorMessage = null;
                    if (typeof errorThrown == 'string') {
                        errorMessage = errorThrown;
                    } else if (typeof errorThrown == 'object') {
                        errorMessage = errorThrown.message;
                    }
                    console && console.error && errorMessage && console.warn(errorMessage);
                }
            });
        }
    });

    return $.sirv.assetsCache;
});
