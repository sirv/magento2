<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form element for 'viewer_contents' param
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class OrderOfContent extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios
{
    /**
     * Viewer contents
     *
     * 1 Magento images/videos
     * 2 Magento images/videos + Sirv assets
     * 3 Sirv assets + Magento images/videos
     * 4 Sirv assets
     */
    const MAGENTO_ASSETS = 1;
    const MAGENTO_AND_SIRV_ASSETS = 2;
    const SIRV_AND_MAGENTO_ASSETS = 3;
    const SIRV_ASSETS = 4;

    /**
     * Format an option as HTML
     *
     * @param array $option
     * @param array $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected)
    {
        $html = parent::_optionToHtml($option, $selected);
        if (self::SIRV_ASSETS == $option['value']) {
            $html .= '<div class="note admin__field-note admin__field-sub-option-note" id="mt-viewer_contents-note">If no assets on Sirv, Magento assets will be used.</div>';
        }

        return $html;
    }
}
