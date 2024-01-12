<?php

namespace Sirv\Magento2\Model\ResourceModel;

/**
 * Manually added assets resource model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ManuallyAddedAssets extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: resource initialization
        $this->_init('sirv_manually_added_assets', 'id');
    }

    /**
     * Delete row by id
     *
     * @param string $id
     * @return $this
     */
    public function deleteById($id)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['id = ?' => $id]);

        return $this;
    }

    /**
     * Insert asset data to DB and retrieve last id
     *
     * @param array $data
     * @return integer
     */
    public function insertAssetData($data)
    {
        $connection = $this->getConnection();
        $data = $this->_prepareDataForTable(
            new \Magento\Framework\DataObject($data),
            $this->getMainTable()
        );
        $connection->insert($this->getMainTable(), $data);

        return $connection->lastInsertId($this->getMainTable());
    }
}
