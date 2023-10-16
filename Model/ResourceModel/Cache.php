<?php

namespace Sirv\Magento2\Model\ResourceModel;

/**
 * Cache resource model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Cache extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: resource initialization
        $this->_init('sirv_cache', 'id');
    }

    /**
     * Delete rows by status
     *
     * @param string $status
     * @return $this
     */
    public function deleteByStatus($status)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['status = ?' => $status]);

        return $this;
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
        $connection->delete($this->getMainTable(), ['id IN (?)' => $ids]);

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
