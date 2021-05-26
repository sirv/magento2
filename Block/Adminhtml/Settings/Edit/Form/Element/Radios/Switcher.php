<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios;

/**
 * Form radio buttons switcher element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Switcher extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\Radios
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = '';
        $value = $this->getValue();

        if ($values = $this->getValues()) {
            foreach ($values as $option) {
                $html .= $this->_optionToHtml($option, $value);
            }
        }

        if (!empty($html)) {
            $html = '<span class="admin__control-switcher">' . $html . '<span class="slide-button"></span></span>';
        }

        $html .= $this->getAfterElementHtml();

        if ($tooltip = $this->getTooltip()) {
            $html .= '<div class="tooltip" data-mage-init=\'{"sirvTooltip":{}}\'><span class="help"><span></span></span>';
            $html .= '<div class="tooltip-content">' . $tooltip . '</div></div>';
        }

        return $html;
    }

    /**
     * Format an option as HTML
     *
     * @param array $option
     * @param array $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected)
    {
        $html = '<input type="radio"' . $this->getRadioButtonAttributes($option);
        if (is_array($option)) {
            $html .= 'value="' . $this->_escape(
                $option['value']
            ) . '" class="admin__control-radio-switch" id="' . $this->getHtmlId() . $option['value'] . '"';
            if ($option['value'] == $selected) {
                $html .= ' checked="checked"';
            }
            $html .= ' />';
            $html .= '<label class="admin__field-label" for="' .
                $this->getHtmlId() .
                $option['value'] .
                '">' .
                $option['label'] .
                '</label>';
        } elseif ($option instanceof \Magento\Framework\DataObject) {
            $html .= 'id="' . $this->getHtmlId() . $option->getValue() . '"' . $option->serialize(
                ['label', 'title', 'value', 'class', 'style']
            );
            if (in_array($option->getValue(), $selected)) {
                $html .= ' checked="checked"';
            }
            $html .= ' />';
            $html .= '<label class="inline" for="' .
                $this->getHtmlId() .
                $option->getValue() .
                '">' .
                $option->getLabel() .
                '</label>';
        }

        return $html;
    }
}
