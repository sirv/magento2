<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Pinned items
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class PinnedItems extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * Constructor
     *
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
        $this->setType('pinned-items');
    }

    /**
     * Get the HTML for the element
     *
     * @return string
     */
    public function getElementHtml()
    {
        $options = [
            'videos' => [
                'label' => 'Pin video(s)',
                'options' => [
                    ['label' => 'Unpinned', 'value' => 'no'],
                    ['label' => 'Left', 'value' => 'left'],
                    ['label' => 'Right', 'value' => 'right'],
                ],
                'default' => 'no',
            ],
            'spins' => [
                'label' => 'Pin spin(s)',
                'options' => [
                    ['label' => 'Unpinned', 'value' => 'no'],
                    ['label' => 'Left', 'value' => 'left'],
                    ['label' => 'Right', 'value' => 'right'],
                ],
                'default' => 'no',
            ],
            'images' => [
                'label' => 'Pin images by file mask',
                'options' => [
                    ['label' => 'Unpinned', 'value' => 'no'],
                    ['label' => 'Left', 'value' => 'left'],
                    ['label' => 'Right', 'value' => 'right'],
                ],
                'default' => 'no',
            ],
        ];

        $values = $this->getValue() ?: [];
        $htmlId = $this->getHtmlId();
        $name = $this->getName();

        $html = '<div class="note admin__field-note-top" id="' . $htmlId . '-note">Always show thumbnail(s) beside scroller.</div>';

        foreach ($options as $id => $data) {
            $optionId = $htmlId . '-' . $id;
            $optionName = $name . '[' . $id . ']';
            $optionValue = isset($values[$id]) ? $values[$id] : $data['default'];
            $dataMageInit = ($id == 'images' ? ' data-mage-init=\'{"sirvPinnedMask":{}}\'' : '');
            $html .= '<div class="admin__field-control"' . $dataMageInit . '>';
            $html .= '<label class="addbefore">' . $data['label'] . '</label>';
            foreach ($data['options'] as $option) {
                $option['selected'] = $optionValue;
                $option['id'] = $optionId;
                $option['name'] = $optionName;
                $html .= $this->getOptionHtml($option);
            }
            $html .= '</div>';
        }

        $hideMask = (isset($values['images']) ? $values['images'] : $options['images']['default']) == 'no';
        $optionValue = isset($values['mask']) ? $values['mask'] : '';
        $disabled = '';
        if ($this->getDisabled('')) {
            $disabled .= ' disabled="disabled" ';
        }
        $html .= '<div class="admin__field-control field-mt-pinned_items-mask"';
        if ($hideMask) {
            $html .= ' style="display: none;"';
        }
        $html .= '><input id="' . $htmlId . '-mask" name="' . $name . '[mask]' . '" ' .
            $this->_getUiId() . ' value="' . $this->_escape($optionValue) . '" title="Pin by file mask" ' .
            'class="mt-option input-text admin__control-text" type="text" placeholder="e.g. *-hero.jpg" ' .
            $disabled . '/>';
        $html .= '<div class="note admin__field-note">Filenames matching this pattern will be pinned. Use * as a wildcard.</div>';
        $html .= '</div>';

        if ($tooltip = $this->getTooltip()) {
            $html .= '<div class="tooltip" data-mage-init=\'{"sirvTooltip":{}}\'><span class="help"><span></span></span>';
            $html .= '<div class="tooltip-content">' . $tooltip . '</div></div>';
        }

        return $html;
    }

    /**
     * Get the HTML for the option
     *
     * @param array $option
     * @return string
     */
    protected function getOptionHtml($option)
    {
        $radioId = $option['id'] . '-' . $option['value'];
        $html = '<div class="admin__field admin__field-option admin__field-option-inline">' .
            '<input type="radio" class="admin__control-radio" name="' .
            $option['name'] . '" ' . $this->getRadioButtonAttributes($option);

        $html .= ' value="' . $this->_escape($option['value']) . '"';
        $html .= ' id="' . $radioId . '"';
        if ($option['value'] == $option['selected']) {
            $html .= ' checked="checked"';
        }
        $html .= ' />';
        $html .= '<label class="admin__field-label" for="' .
            $radioId .
            '"><span>' .
            $option['label'] .
            '</span></label>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get attributes for radio button
     *
     * @param array $option
     * @return string
     */
    protected function getRadioButtonAttributes($option)
    {
        $html = '';
        foreach ($this->getHtmlAttributes() as $attribute) {
            if ($value = $this->getDataUsingMethod($attribute, $option['value'])) {
                $html .= ' ' . $attribute . '="' . $value . '" ';
            }
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
