/**
 * Button widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/backend/button',
    'loader',
    'Magento_Ui/js/modal/confirm'
], function ($, button, loader, uiConfirm) {
    'use strict';

    $.widget('sirv.button', $.ui.button, {

        options: {
            showLoader: false,
            needConfirmation: false,
            confirmationMessage: 'Are you sure?',
            confirmationButtonText: 'OK'
        },

        isButtonDisabled: false,

        /** @inheritdoc */
        _create: function () {
            this._super();
            this._addClass('sirv-ui-button');
        },

        /**
         * Do button disabled
         * @protected
         */
        doButtonDisabled: function () {
            this.isButtonDisabled = true;
            $(this.element).addClass('disabled');
        },

        /**
         * Do button enabled
         * @protected
         */
        doButtonEnabled: function () {
            this.isButtonDisabled = false;
            $(this.element).removeClass('disabled');
        },

        /**
         * Button click handler
         * @protected
         */
        _click: function () {
            var _super = $.proxy(this._super, this),
                actionFnc = $.proxy(function() {
                    if (this.options.showLoader) {
                        $('body').trigger('processStart');
                    }
                    _super();
                }, this);

            if (this.isButtonDisabled) {
                return false;
            }

            if (this.options.needConfirmation) {
                uiConfirm({
                    content: $.mage.__(this.options.confirmationMessage),
                    actions: {
                        confirm: function (event) {
                            actionFnc();
                        }
                    },
                    buttons: [{
                        text: $.mage.__('Cancel'),
                        class: 'action-secondary action-dismiss',
                        click: function (event) {
                            this.closeModal(event);
                        }
                    }, {
                        text: $.mage.__(this.options.confirmationButtonText),
                        class: 'action-primary action-accept',
                        click: function (event) {
                            this.closeModal(event, true);
                        }
                    }]
                });

                return false;
            }

            actionFnc();

            return false;
        }
    });

    return $.sirv.button;
});
