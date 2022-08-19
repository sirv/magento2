<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form elements for first and last name
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FirstAndLastName extends \Magento\Framework\Data\Form\Element\Text
{
    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = '';
        $htmlId = $this->getHtmlId();

        $beforeElementHtml = $this->getBeforeElementHtml();
        if ($beforeElementHtml) {
            $html .= '<label class="addbefore" for="' . $htmlId . '">' . $beforeElementHtml . '</label>';
        }

        $this->setPlaceholder('Your first name');
        $this->setTitle('Your first name');
        $this->setValue($this->getFirstName());
        $value = $this->getValue();
        $name = preg_replace('#\[[^\]]++\]#', '[first_name]', $this->getName());
        $html .= '<input id="' . $this->getHtmlId() . '_first" name="' . $name . '" ' . $this->_getUiId()
            . ' value="' . $value . '" ' . $this->serialize($this->getHtmlAttributes()) . '/>';

        $this->setPlaceholder('Your last name');
        $this->setTitle('Your last name');
        $this->setValue($this->getLastName());
        $value = $this->getValue();
        $name = preg_replace('#\[[^\]]++\]#', '[last_name]', $this->getName());
        $html .= '<input id="' . $this->getHtmlId() . '_last" name="' . $name . '" ' . $this->_getUiId()
            . ' value="' . $value . '" ' . $this->serialize($this->getHtmlAttributes()) . '/>';

        $afterElementJs = $this->getAfterElementJs();
        if ($afterElementJs) {
            $html .= $afterElementJs;
        }

        $afterElementHtml = $this->getAfterElementHtml();
        if ($afterElementHtml) {
            $html .= '<label class="addafter" for="' . $htmlId . '">' . $afterElementHtml . '</label>';
        }

        if ($tooltip = $this->getTooltip()) {
            $html .= '<div class="tooltip" data-mage-init=\'{"sirvTooltip":{}}\'><span class="help"><span></span></span>';
            $html .= '<div class="tooltip-content">' . $tooltip . '</div></div>';
        }

        return $html;
    }

    /**
     * Get field extra attributes
     *
     * @return string
     */
    public function getFieldExtraAttributes()
    {
        return $this->getData('hidden') ? 'style="display: none;"' : '';
    }
}
