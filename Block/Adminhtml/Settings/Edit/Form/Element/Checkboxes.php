<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form checkboxes element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Checkboxes extends \Magento\Framework\Data\Form\Element\Checkboxes
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = parent::getElementHtml();

        if ($tooltip = $this->getTooltip()) {
            $html .= '<div class="tooltip" data-mage-init=\'{"sirvTooltip":{}}\'><span class="help"><span></span></span>';
            $html .= '<div class="tooltip-content">' . $tooltip . '</div></div>';
        }

        return $html;
    }

    /**
     * Get disabled attribute value
     *
     * @param mixed $value
     * @return mixed
     */
    public function getDisabled($value)
    {
        if ($this->getData('disabled')) {
            return 'disabled';
        }

        return false;
    }
}
