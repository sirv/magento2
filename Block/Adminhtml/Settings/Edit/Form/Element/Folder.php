<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form folder element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Folder extends \Magento\Framework\Data\Form\Element\Text
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

        $valuePrefix = $this->getValuePrefix();
        $value = $this->getEscapedValue();

        $html .= '<div class="admin__control-value-text">' .
            '<span class="value-prefix">' . $valuePrefix . '</span><span>' . $value . '</span>' .
            '<a class="admin__control-change-link" href="#" data-mage-init=\'{"sirvEditFolderOption":{}}\'>Change</a>' .
            '</div>';

        $html .= '<input id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' . $this->_getUiId() .
            ' value="' . $value . '" ' . $this->serialize($this->getHtmlAttributes()) . '/>';

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
