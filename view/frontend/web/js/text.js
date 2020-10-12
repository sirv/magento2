/**
 * Text widget
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

define(['module', 'mageText'], function (module, mageText) {
    'use strict';

    var config, sirvDomain, staticDomain, fetchMode, loadContentOriginal;

    config = module.config && module.config() || {};
    sirvDomain = config.domains.sirv || '';
    staticDomain = config.domains.static || '';
    fetchMode = config.fetchMode || '';
    loadContentOriginal = mageText.load;

    if (fetchMode != 'custom') {
        return mageText;
    }

    mageText.load = function(name, req, onLoad) {
        if (!req.toUrlOriginal) {
            var toUrlOriginal = req.toUrl;
            req.toUrlOriginal = req.toUrl;
            req.toUrl = function(moduleNamePlusExt) {
                var url = toUrlOriginal.apply(this, arguments);

                if (url.replace(/^(https?:)?\/\//, '').indexOf(sirvDomain) === 0) {
                    url = url.replace(sirvDomain, staticDomain);
                }

                return url;
            };
        }

        return loadContentOriginal.apply(this, arguments);
    };

    return mageText;
});
