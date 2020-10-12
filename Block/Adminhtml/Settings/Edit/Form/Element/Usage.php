<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form usage element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Usage extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        $this->setType('usage');
    }

    /**
     * Get the element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $formWidget = $this->getForm()->getParent();
        $layout = $formWidget->getLayout();
        $usageBlock = $layout->createBlock(\MagicToolbox\Sirv\Block\Adminhtml\Usage::class);

        return $usageBlock->toHtml();
    }
}
