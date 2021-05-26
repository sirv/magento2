<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form button element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Button extends \Magento\Framework\Data\Form\Element\Button
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

        $html .= $this->getButtonHtml();

        $afterElementJs = $this->getAfterElementJs();
        if ($afterElementJs) {
            $html .= $afterElementJs;
        }

        $afterElementHtml = $this->getAfterElementHtml();
        if ($afterElementHtml) {
            $html .= '<label class="addafter" for="' . $htmlId . '">' . $afterElementHtml . '</label>';
        }

        return $html;
    }

    /**
     * Get the button HTML
     *
     * @return string
     */
    protected function getButtonHtml()
    {
        $buttonLabel = $this->getEscapedValue();

        if (!$this->getTitle()) {
            $this->setTitle($buttonLabel);
        }

        $this->setData(
            'data-mage-init',
            str_replace(
                '"',
                '&quot;',
                '{"button":{"event":"save","target":"#edit_form"}}'
            )
        );

        $this->addClass('action-default');

        return '<button id="' . $this->getHtmlId() . '" ' . $this->serialize($this->getHtmlAttributes()) . '>' .
            '<span class="ui-button-text"><span>' . $buttonLabel . '</span></span></button>';
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
