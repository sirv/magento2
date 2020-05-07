<?php

namespace MagicToolbox\Sirv\Model;

/**
 * Cache model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Cache extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('MagicToolbox\Sirv\Model\ResourceModel\Cache');
    }

    /**
     * Clearing object's data
     *
     * @return $this
     */
    protected function _clearData()
    {
        $this->_hasDataChanges = false;
        $this->_isDeleted = false;
        $this->_isObjectNew = null;
        $this->_origData = null;
        $this->storedData = [];
        $this->_data = [];
        return $this;
    }
}
