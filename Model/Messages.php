<?php

namespace Sirv\Magento2\Model;

/**
 * Messages model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Messages extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('Sirv\Magento2\Model\ResourceModel\Messages');
    }

    /**
     * Clearing object's data
     *
     * @return $this
     */
    protected function _clearData()
    {
        $this->_data = [];
        return $this;
    }
}
