<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form new account switcher
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class NewAccount extends \MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\Radios
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $this->setData('data-role', ['yes' => 'new-account-switcher', 'no' => 'new-account-switcher']);
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
                    "[data-role=new-account-switcher]": {
                        "MagicToolbox_Sirv/js/new-account-switcher": {}
                    }
                }
            </script>';

        return $html;
    }
}
