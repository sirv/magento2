/**
 * Scope switcher widget
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

    $.widget('sirv.scopeSwitcher', {

        options: {
            nameRegExp: /^mt\-config\[([^\[\]]+)\](?:\[\])?$/,
            name: '',
            isEnabled: true
        },

        /** @inheritdoc */
        _create: function () {
            this.options.name = this.element.attr('data-name').replace(this.options.nameRegExp, '$1');
            var value = this.element.find('[name^="scope-switcher\[' + this.options.name + '\]"]').attr('value');
            this.options.isEnabled = (value == 'on');

            this.element.find('.scope-switcher-status').on(
                'change',
                $.proxy(this._eventHandler, this)
            );
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         */
        _eventHandler: function (e) {
            var el = this.element.find('.scope-switcher-status');
            this._switchDisabled(!el.is(':checked'));
        },

        /**
         * Switch disabled attribute
         * @param {Bool} disabled
         */
        _switchDisabled: function (disabled) {
            this.element.find('[name^="scope-switcher\[' + this.options.name + '\]"]').attr(
                'value',
                disabled ? 'off' : 'on'
            );
            $('[name^="mt-config\[' + this.options.name + '\]"]').prop('disabled', disabled);
        }
    });

    return $.sirv.scopeSwitcher;
});
