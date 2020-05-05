<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form account connect button element
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Connect extends \Magento\Framework\Data\Form\Element\Button
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

        $this->setData('data-mage-init', str_replace('"', '&quot;', '{"button":{"event":"save","target":"#edit_form"}}'));

        $this->addClass('action-default');

        return '<button id="' . $this->getHtmlId() . '" ' . $this->serialize($this->getHtmlAttributes()) . '>' .
            '<span class="ui-button-text"><span>' . $buttonLabel . '</span></span></button>';
    }
}
