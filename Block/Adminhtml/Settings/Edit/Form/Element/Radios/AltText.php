<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form AltText element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AltText extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios\Switcher
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData(
            'data-role',
            [
                'true' => 'alt-text-switcher',
                'false' => 'alt-text-switcher'
            ]
        );

        $html = parent::getElementHtml();
        $html .= '
            <script type="text/x-magento-init">
                {
                    "[data-role=alt-text-switcher]": {
                        "Sirv_Magento2/js/alt-text-switcher": {}
                    }
                }
            </script>';

        return $html;
    }
}
