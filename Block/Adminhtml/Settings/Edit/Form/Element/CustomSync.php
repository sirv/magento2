<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Custom sync element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CustomSync extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * Constructor
     *
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setType('custom_sync');
    }

    /**
     * Get element HTML
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

        $html .= '<input id="' . $this->getHtmlId() . '_product_id" value="" placeholder="Product ID" class="disabled" disabled="disabled"/>&nbsp;';
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
        $id = $this->getHtmlId();
        $formWidget = $this->getForm()->getParent();
        $ajaxUrl = $formWidget->getUrl('sirv/ajax/customsync');

        $buttonConfig = [
            'id' => $id,
            'label' => 'Sync',
            'title' => 'Sync specified product media',
            'class' => 'sirv-button action-secondary',
            'onclick' => 'return false',
            'disabled' => 'disabled',
            'data_attribute' => [
                 'mage-init' => [
                    'Sirv_Magento2/js/customsync' => [
                        'ajaxUrl' => $ajaxUrl
                    ]
                 ]
             ]
        ];

        $block = $formWidget->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class, $id);
        $block->setData($buttonConfig);

        return $block->toHtml();
    }
}
