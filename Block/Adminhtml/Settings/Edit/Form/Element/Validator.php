<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Cache validator element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Validator extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setType('validator');
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
        $ajaxUrl = $formWidget->getUrl('sirv/ajax/validate');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $sessionManager = $objectManager->get(\Magento\Framework\Session\SessionManagerInterface::class);
        $sessionData = $sessionManager->getSirvValidateData() ?: [];

        $buttonConfig = [
            'id' => $id,
            'label' => 'Validate',
            'title' => 'Start cache validation',
            'class' => 'sirv-button action-secondary',
            'onclick' => 'return false',
            'disabled' => 'disabled',
            'data_attribute' => [
                 'mage-init' => [
                    'Sirv_Magento2/js/validator' => [
                        'ajaxUrl' => $ajaxUrl,
                        'isEmptySessionData' => empty($sessionData)
                    ]
                 ]
             ]
        ];

        $block = $formWidget->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class, $id);
        $block->setData($buttonConfig);

        return $block->toHtml();
    }
}
