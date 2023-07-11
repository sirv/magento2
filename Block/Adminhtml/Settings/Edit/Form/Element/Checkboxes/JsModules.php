<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Checkboxes;

/**
 * Js Modules
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class JsModules extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Checkboxes
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData('data-role', [
            'all' => 'sirv-js-modules',
            'lazyimage' => 'sirv-js-modules',
            'zoom' => 'sirv-js-modules',
            'spin' => 'sirv-js-modules',
            'hotspots' => 'sirv-js-modules',
            'video' => 'sirv-js-modules',
            'gallery' => 'sirv-js-modules',
            'model' => 'sirv-js-modules'
        ]);

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
                    "[data-role=sirv-js-modules]": {
                        "Sirv_Magento2/js/sirv-js-modules": {}
                    }
                }
            </script>';

        return $html;
    }
}
