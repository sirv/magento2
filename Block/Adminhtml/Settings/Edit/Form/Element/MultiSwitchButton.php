<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form multi switch button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class MultiSwitchButton extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setType('button');
        $this->setExtType('multi-switch-button');
    }

    /**
     * Get the HTML for the element.
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

        $config = $this->getButtonConfig();
        $html .= $this->getSwitcherHtml($config['options']);
        $html .= '<div class="admin__field admin__field-option">' .
            '<div class="sirv-actions">' .
            $this->getButtonHtml($config) .
            '</div>' .
            '</div>';

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
     * Get switcher HTML
     *
     * @param array $options
     * @return string
     */
    protected function getSwitcherHtml($options)
    {
        $html = '';
        $id = $this->getHtmlId();
        $name = ' name="multiswitchbutton[' . $id . ']"';
        $selected = ' checked="checked"';
        foreach ($options as $actionId => $config) {
            $optionId = $id . '-' . $actionId;
            $html .= '<div class="admin__field admin__field-option">' .
                '<input type="radio"' . $name . ' value="' . $this->_escape($actionId) .
                '" class="admin__control-radio" id="' . $optionId .
                '" title="' . $config['title'] . '"' .
                $selected . ' /><label class="admin__field-label" for="' . $optionId .
                '" title="' . $config['title'] .
                 '"><span>' . $config['label'] . '</span></label></div>';
            $selected = '';
        }

        return $html;
    }

    /**
     * Get button HTML
     *
     * @param array $config
     * @return string
     */
    protected function getButtonHtml($config)
    {
        $label = isset($config['label']) ? $config['label'] : $this->getLabel();
        $title = isset($config['title']) ? $config['title'] : $label;
        $options = isset($config['options']) ? $config['options'] : [];
        $id = $this->getHtmlId();
        $buttonConfig = [
            'id' => $id,
            'label' => $label,
            'title' => $title,
            'class' => 'sirv-button action-secondary',
            'onclick' => 'return false',
            'data_attribute' => [
                 'mage-init' => [
                     'sirvMultiSwitchButton' => [
                        'id' => $id,
                        'actionsData' => $options,
                     ]
                 ]
             ]
        ];

        $formWidget = $this->getForm()->getParent();
        $block = $formWidget->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class, $id);
        $block->setData($buttonConfig);

        return $block->toHtml();
    }

    /**
     * Get button config
     *
     * @return array
     */
    protected function getButtonConfig()
    {
        return [
            'label' => 'Press mes',
            'options' => []
        ];
    }
}
