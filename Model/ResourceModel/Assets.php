<?php

namespace Sirv\Magento2\Model\ResourceModel;

/**
 * Assets resource model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Assets extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Primary key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: resource initialization
        $this->_init('sirv_assets', 'product_id');
    }

    /**
     * Delete rows by ids
     *
     * @param array $ids
     * @return $this
     */
    public function deleteByIds($ids)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['product_id IN (?)' => $ids]);

        return $this;
    }

    /**
     * Delete all rows
     *
     * @return $this
     */
    public function deleteAll()
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->truncateTable($this->getMainTable());

        return $this;
    }
}
