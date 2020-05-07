<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form text element
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Text extends \Magento\Framework\Data\Form\Element\Text
{
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
