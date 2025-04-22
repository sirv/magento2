/**
 * Sirv media uploader widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define([
    'jquery',
    'mage/template',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/form/element/file-uploader',
    'mage/translate',
    'jquery/file-uploader'
], function ($, mageTemplate, alert, FileUploader) {
    'use strict';

    var fileUploader = new FileUploader({
        dataScope: '',
        isMultipleFiles: false
    });

    fileUploader.initUploader();

    $.widget('sirv.mediaUploader', {

        /**
         *
         * @private
         */
        _create: function () {
            var self = this,
                count = 0,
                processed = 0;

            this.element.find('input[type=file]').fileupload({
                dataType: 'json',
                formData: {
                    'form_key': window.FORM_KEY,
                    'sirv_path': ''
                },
                dropZone: null,
                sequentialUploads: true,
                acceptFileTypes: /(\.|\/)(jpg|jpeg|png|gif|webp|tif|tiff|svg|mpg|mpeg|m4v|mp4|avi|mov|ogv|usdz|glb|dwg)$/i,
                maxFileSize: this.options.maxFileSize,

                change : function (e, data) {
                    count = data.files.length;
                    processed = 0;
                },

                /**
                 * @param {Object} e
                 * @param {Object} data
                 */
                add: function (e, data) {
                    $(this).fileupload('option', {
                        formData: {
                            'form_key': window.FORM_KEY,
                            'sirv_path': window.sirvAssetPickerPath || ''
                        }
                    });

                    /* $('body').trigger('processStart'); */
                    $('.sirv-asset-picker-container').parents('.modal-inner-wrap').loader({
                        texts: {
                            loaderText: $.mage.__('Uploading...')
                        }
                    }).trigger('processStart');

                    $.each(data.files, function (index, file) {
                        data.fileId = Math.random().toString(33).substr(2, 18);
                    });

                    $(this).fileupload('process', data).done(function () {
                        data.submit();
                    });
                },

                /**
                 * @param {Object} e
                 * @param {Object} data
                 */
                done: function (e, data) {
                    processed++;
                    if (data.result && !data.result.error) {
                    } else {
                        fileUploader.aggregateError(data.files[0].name, data.result.error);
                    }

                    if (processed == count) {
                        $('.sirv-asset-picker-container').sirvAssetPicker('updateViewForce');
                    }

                    /* $('body').trigger('processStop'); */
                    $('.sirv-asset-picker-container').parents('.modal-inner-wrap').trigger('processStop');
                },

                /**
                 * @param {Object} e
                 * @param {Object} data
                 */
                progress: function (e, data) {},

                /**
                 * @param {Object} e
                 * @param {Object} data
                 */
                fail: function (e, data) {},

                stop: fileUploader.uploaderConfig.stop
            });
        }
    });

    return $.sirv.mediaUploader;
});
