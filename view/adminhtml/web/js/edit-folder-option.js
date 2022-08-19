/**
 * Edit folder option widget
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

    $.widget('sirv.editFolderOption', {

        /** @inheritdoc */
        _create: function () {
            this.element.on('click', $.proxy(this._makeOptionEditable, this));
        },

        /**
         * Make option editable
         * @protected
         */
        _makeOptionEditable: function () {
            this.element.parent('.admin__control-value-text').css('display', 'none')
                .next('.admin__control-text').css('display', 'inline-block');

            return false;
        }
    });

    return $.sirv.editFolderOption;
});
