<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form text element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class OtpCode extends \Magento\Framework\Data\Form\Element\Text
{
    /**
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setData('maxlength', 6);
        $this->addCustomAttribute('pattern', '[0-9]{6}');
        $this->addClass('hidden-element');
        $this->setData('data-role', 'otp-code');
    }

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

        for ($i = 0; $i < 6; $i++) {
            $html .= '<input id="otp_code_digit_' . $i . '" value="" ' .
                'class="mt-option input-text admin__control-text otp-code-digit-field" ' .
                'type="text" maxlength="1" size="1" pattern="[0-9]{1}" ' .
                'autocomplete="off" />';
        }
        $html .= '<input id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' .
            $this->_getUiId() . ' value="' . $this->getEscapedValue() . '" ' .
            $this->serialize($this->getHtmlAttributes()) . '/>';

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
     * Get the after element JS
     *
     * @return mixed
     */
    public function getAfterElementJs()
    {
        $code = parent::getAfterElementJs();
        $code .= '
            <script type="text/x-magento-init">
                {
                    "[data-role=otp-code]": {
                        "Sirv_Magento2/js/otp-code": {}
                    }
                }
            </script>';

        return $code;
    }
}
