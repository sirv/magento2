<?php

namespace Sirv\Magento2\Model;

/**
 * Assets model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Assets extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: define resource model
        $this->_init('Sirv\Magento2\Model\ResourceModel\Assets');
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
