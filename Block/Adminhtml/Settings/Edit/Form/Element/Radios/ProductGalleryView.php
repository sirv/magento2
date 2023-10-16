<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form product gallery view element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ProductGalleryView extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios\Switcher
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData('data-role', ['original' => 'product-gallery-view-switcher', 'smv' => 'product-gallery-view-switcher']);
        return parent::getElementHtml();
    }

    /**
     * Get the after element HTML
     *
     * @return mixed
     */
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        $html .= '
            <script type="text/x-magento-init">
                {
                    "[data-role=product-gallery-view-switcher]": {
                        "Sirv_Magento2/js/product-gallery-view-switcher": {}
                    }
                }
            </script>';

        return $html;
    }
}
