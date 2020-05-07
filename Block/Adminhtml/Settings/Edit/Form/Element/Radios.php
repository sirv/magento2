<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form radio buttons element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Radios extends \Magento\Framework\Data\Form\Element\Radios
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = parent::getElementHtml();
        $class = $this->getData('in_a_row') ? 'admin__field-option-inline' : 'admin__field-option-not-inline';
        $html = preg_replace('#\badmin__field-option\b#', '$0 ' . $class, $html);

        return $html;
    }
}
