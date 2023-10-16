<?php

namespace Sirv\Magento2\Model;

/**
 * Config model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Config extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('Sirv\Magento2\Model\ResourceModel\Config');
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
