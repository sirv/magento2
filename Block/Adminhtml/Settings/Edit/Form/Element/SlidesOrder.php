<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Slides order
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class SlidesOrder extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setType('slides-order');
    }

    /**
     * Get the HTML for the element
     *
     * @return string
     */
    public function getElementHtml()
    {
        $values = $this->getValues() ?: [];
        $options = [];
        foreach ($values as $v) {
            $options[$v['value']] = $v['label'];
        }

        $values = $this->getValue() ?: '';
        $values = explode(',', $values);

        $disabled = $this->getData('disabled') ? ' disabled' : '';

        $html = '<div class="slides_order_items' . $disabled . '" data-mage-init=\'{"sirvSlidesOrder":{}}\'>';
        $value = [];
        $p = 0;
        foreach ($values as $v) {
            if (isset($options[$v])) {
                $value[] = $v;
                $html .= '<div class="slides_order_item" data-role="item" data-item-type="' . $v . '" data-position="' . $p . '">' .
                    '<div class="slides_order_item_wrapper">' .
                    '<span class="slides_order_item_label">' .
                    $options[$v] .
                    '</span>' .
                    '<div class="slides_order_actions">' .
                    '<div class="slides_order_action slides_order_action_draggable_handle" title="Drag and drop to sort"></div>' .
                    '<div class="slides_order_action slides_order_action_remove" data-role="delete-button" title="Delete"></div>' .
                    '</div></div></div>';
                $p++;
            }
        }

        $html .= '<div class="slides_order_item other_assets_item' . ($p ? '' : ' hidden_item') . '">' .
            '<div class="slides_order_item_wrapper">' .
            '<span class="slides_order_item_label">All other assets</span>' .
            '</div></div>';

        $html .= '<div class="slides_order_item">' .
            '<div class="slides_order_item_wrapper item_add_wrapper">' .
            '<div class="slides_order_item_actions">' .
            '<div class="slides_order_action slides_order_action_add" data-role="add-button" title="Add">' .
            '</div>' .
            '<ul class="items-menu" data-role="items-menu" tabindex="-1">' .
            '<li class="items-menu-header">Add new:</li>';

        foreach ($options as $optionValue => $optionLabel) {
            $html .= '<li><a data-role="menu-item" tabindex="-1" href="#" class="slides_order_menu_item" data-item-type="' .
                $optionValue . '" data-item-label="' . $optionLabel . '"><span>' .
                $optionLabel . '</span></a></li>';
        }

        $html .= '</ul>' .
            '</div></div></div>'.
            '</div>';

        $value = implode(',', $value);
        $html .= "\n\n".'<input id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' . $this->_getUiId() .
            ' value="' . $value . '" type="hidden" ' . $this->serialize($this->getHtmlAttributes()) . ' />';

        return $html;
    }

    /**
     * Get the attributes
     *
     * @return string[]
     */
    public function getHtmlAttributes()
    {
        return [
            'class',
            'onchange',
            'disabled',
            'readonly',
            'data-form-part',
            'data-role',
            'data-action'
        ];
    }
}
