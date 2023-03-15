/**
 * OTP code widget
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

    $.widget('sirv.otpCode', {

        options: {},
        otpCodeDigitClass: 'otp-code-digit-field',
        otpCodeDigitSelector: '.otp-code-digit-field',
        otpCodeSelector: '#mt-otp_code',

        /** @inheritdoc */
        _create: function () {
            this.element.attr('value', '');
            $(this.otpCodeDigitSelector).each(function(i, el) {
                el.setAttribute('value', '');
            });
            $(this.otpCodeDigitSelector).on('keyup', $.proxy(this._eventHandler, this));
            $(this.otpCodeDigitSelector).first().focus();
        },

        /**
         * Handle the event
         * @param {Object} e - event object
         */
        _eventHandler: function (e) {
            var el,
                keyCode,
                digits,
                validateOptCode,
                k;

            el = e.target || e.srcElement;
            keyCode = e.keyCode || e.which;
            //NOTE: digits 0-9
            digits = {
                48: 0,
                49: 1,
                50: 2,
                51: 3,
                52: 4,
                53: 5,
                54: 6,
                55: 7,
                56: 8,
                57: 9
            };
            validateOptCode = $.proxy(function () {
                let otpCode = '', isValid = true;
                $(this.otpCodeDigitSelector).each(function(i, elm) {
                    if (isValid) {
                        if (elm.value.match(/^[0-9]$/)) {
                            otpCode += elm.value;
                        } else {
                            isValid = false;
                            $(elm).focus();
                        }
                    }
                });
                return isValid ? otpCode : false;
            }, this);

            //NOTE: digits key
            for (k in digits) {
                if (k == keyCode) {
                    el.value = digits[k];
                    if ($(el.nextSibling).hasClass(this.otpCodeDigitClass)) {
                        $(el.nextSibling).focus();
                    } else {
                        let otpCode = validateOptCode();
                        if (otpCode) {
                            $('body').trigger('processStart');
                            $(this.otpCodeSelector).val(otpCode);
                            $(this.otpCodeSelector).parents('form').submit();
                        }
                    }
                    return;
                }
            }

            //NOTE: "ArrowUp" or "ArrowRight" key
            if ([38, 39].includes(keyCode)) {
                if ($(el.nextSibling).hasClass(this.otpCodeDigitClass)) {
                    $(el.nextSibling).focus();
                } else {
                    $(this.otpCodeDigitSelector).first().focus();
                }
                return;
            }

            //NOTE: "ArrowLeft" or "ArrowDown" key
            if ([37, 40].includes(keyCode)) {
                if ($(el.previousSibling).hasClass(this.otpCodeDigitClass)) {
                    $(el.previousSibling).focus();
                } else {
                    $(this.otpCodeDigitSelector).last().focus();
                }
                return;
            }

            //NOTE: "Enter" key
            if (keyCode == 13) {
                let otpCode = validateOptCode();
                if (otpCode) {
                    $('body').trigger('processStart');
                    $(this.otpCodeSelector).val(otpCode);
                    $(this.otpCodeSelector).parents('form').submit();
                }
                return;
            }

            //NOTE: "Tab" key
            if (keyCode == 9) {
                return;
            }

            //NOTE: invalide key
            el.value = '';
        }
    });

    return $.sirv.otpCode;
});
