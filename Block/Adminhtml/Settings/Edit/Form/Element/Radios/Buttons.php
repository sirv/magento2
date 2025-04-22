<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form radio buttons element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Buttons extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = parent::getElementHtml();
        $html = '<div class="control-buttons">' .  $html . '</div>';

        return $html;
    }
}
