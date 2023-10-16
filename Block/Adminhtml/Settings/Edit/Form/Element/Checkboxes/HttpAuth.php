<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Checkboxes;

/**
 * HTTP authentication
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class HttpAuth extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Checkboxes
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData('data-role', ['yes' => 'http-auth-switcher']);
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
                    "[data-role=http-auth-switcher]": {
                        "Sirv_Magento2/js/http-auth-switcher": {}
                    }
                }
            </script>';

        return $html;
    }
}
