<?php

namespace Sirv\Magento2\Model\ResourceModel\Assets;

/**
 * Assets collection
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Sirv\Magento2\Model\Assets', 'Sirv\Magento2\Model\ResourceModel\Assets');
    }

    /**
     * Truncate the cache table
     *
     * @return void
     */
    public function truncate()
    {
        $this->getConnection()->truncateTable($this->getMainTable());
    }
}
