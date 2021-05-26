<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form advanced button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AdvancedButton extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setExtType('advanced-button');
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

        $buttonContainerClass = 'admin__field admin__field-option';
        if (count($config['options']) > 1) {
            $buttonContainerClass = 'sirv-advanced-button';
            $html .= $this->getOptionsSwitcherHtml($config['options']);
        }

        $html .= '<div class="' . $buttonContainerClass . '">' .
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
     * Get options switcher HTML
     *
     * @param array $options
     * @return string
     */
    protected function getOptionsSwitcherHtml($options)
    {
        $html = '';
        $id = $this->getHtmlId();
        $name = ' name="advancedbutton[' . $id . ']"';
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
            'id' => $id . '-button',
            'label' => $label,
            'title' => $title,
            'class' => 'sirv-button action-secondary',
            'onclick' => 'return false',
            'data_attribute' => [
                 'mage-init' => [
                     'sirvAdvancedButton' => [
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
            'label' => 'Press me',
            'options' => []
        ];
    }
}
