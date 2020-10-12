<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form textarea element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Textarea extends \Magento\Framework\Data\Form\Element\Textarea
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
}
