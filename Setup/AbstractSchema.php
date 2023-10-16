<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * DB schema installs/upgrades
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
abstract class AbstractSchema
{
    /**
     * Config table name
     */
    const SIRV_CONFIG_TABLE = 'sirv_config';

    /**
     * Cache table name
     */
    const SIRV_CACHE_TABLE = 'sirv_cache';

    /**
     * Assets table name
     */
    const SIRV_ASSETS_TABLE = 'sirv_assets';

    /**
     * Messages table name
     */
    const SIRV_MESSAGES_TABLE = 'sirv_messages';

    /**
     * Alt text cache table name
     */
    const SIRV_ALT_TEXT_CACHE_TABLE = 'sirv_alt_text_cache';

    /**
     * Create config table
     *
     * @param SchemaSetupInterface $setup
     * @param bool $skipIfExists
     * @return void
     */
    protected function createConfigTable(SchemaSetupInterface $setup, $skipIfExists = true)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            if ($skipIfExists) {
                return;
            }
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )->addColumn(
            'scope',
            Table::TYPE_TEXT,
            8,
            ['nullable' => false, 'default' => 'default'],
            'Config Scope'
        )->addColumn(
            'scope_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => 0],
            'Config Scope ID'
        )->addColumn(
            'name',
            Table::TYPE_TEXT,
            64,
            ['nullable' => false],
            'Name'
        )->addColumn(
            'value',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Value'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['scope', 'scope_id', 'name'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['scope', 'scope_id', 'name'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv configuration'
        );

        $connection->createTable($table);
    }

    /**
     * Create cache table
     *
     * @param SchemaSetupInterface $setup
     * @param bool $skipIfExists
     * @return void
     */
    protected function createCacheTable(SchemaSetupInterface $setup, $skipIfExists = true)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

        if ($setup->tableExists(self::SIRV_CACHE_TABLE)) {
            if ($skipIfExists) {
                return;
            }
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Primary key'
        )->addColumn(
            'path',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Relative file path'
        )->addColumn(
            'path_type',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => 0],
            'Path type'
        )->addColumn(
            'status',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => 0],
            'Sync status'
        )->addColumn(
            'attempt',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => 0],
            'Sync attempt'
        )->addColumn(
            'modification_time',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => 0],
            'Modification time'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['path'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['path'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv cache'
        );

        $connection->createTable($table);

        $connection->query(
            "ALTER TABLE `{$tableName}` MODIFY `path` varchar(255) BINARY NOT NULL DEFAULT '' COMMENT 'Relative file path'"
        );
    }

    /**
     * Create assets table
     *
     * @param SchemaSetupInterface $setup
     * @param bool $skipIfExists
     * @return void
     */
    protected function createAssetsTable(SchemaSetupInterface $setup, $skipIfExists = true)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_ASSETS_TABLE);

        if ($setup->tableExists(self::SIRV_ASSETS_TABLE)) {
            if ($skipIfExists) {
                return;
            }
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'product_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false, 'primary' => true],
            'Product ID'
        )->addColumn(
            'contents',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Contents'
        )->addColumn(
            'timestamp',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => 0],
            'Last checked time'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['product_id'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['product_id'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv assets'
        );

        $connection->createTable($table);
    }

    /**
     * Create messages table
     *
     * @param SchemaSetupInterface $setup
     * @param bool $skipIfExists
     * @return void
     */
    protected function createMessagesTable(SchemaSetupInterface $setup, $skipIfExists = true)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_MESSAGES_TABLE);

        if ($setup->tableExists(self::SIRV_MESSAGES_TABLE)) {
            if ($skipIfExists) {
                return;
            }
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'path',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Relative file path'
        )->addColumn(
            'message',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Message'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['path'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['path'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv messages'
        );

        $connection->createTable($table);
    }

    /**
     * Create alt text cache table
     *
     * @param SchemaSetupInterface $setup
     * @param bool $skipIfExists
     * @return void
     */
    protected function createAltTextCacheTable(SchemaSetupInterface $setup, $skipIfExists = true)
    {
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_ALT_TEXT_CACHE_TABLE);

        if ($setup->tableExists(self::SIRV_ALT_TEXT_CACHE_TABLE)) {
            if ($skipIfExists) {
                return;
            }
            $connection->dropTable($tableName);
        }

        $table = $connection->newTable(
            $tableName
        )->addColumn(
            'path',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Synced image path'
        )->addColumn(
            'value',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Image alt text'
        )->addIndex(
            $setup->getIdxName(
                $tableName,
                ['path'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['path'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'Sirv messages'
        );

        $connection->createTable($table);
    }

    /**
     * Upgrade config table
     *
     * @param SchemaSetupInterface $setup
     * @return bool
     */
    protected function upgradeConfigTable(SchemaSetupInterface $setup)
    {
        if (!$setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            return false;
        }

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);

        if (!$connection->tableColumnExists($tableName, 'scope')) {
            $connection->addColumn(
                $tableName,
                'scope',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 8,
                    'nullable' => false,
                    'default' => 'default',
                    'comment' => 'Config Scope',
                    'after' => 'id'
                ]
            );
        }

        if (!$connection->tableColumnExists($tableName, 'scope_id')) {
            $connection->addColumn(
                $tableName,
                'scope_id',
                [
                    'type' => Table::TYPE_INTEGER,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Config Scope ID',
                    'after' => 'scope'
                ]
            );
        }

        $indexList = $connection->getIndexList($tableName);
        //NOTE: find and remove outdated indexes
        $indexToDelete = [];
        foreach ($indexList as $indexData) {
            if (!is_array($indexData['COLUMNS_LIST'])) {
                continue;
            }
            if (in_array('name', $indexData['COLUMNS_LIST']) && (count($indexData['COLUMNS_LIST']) == 1)) {
                $indexToDelete[] = $indexData['KEY_NAME'];
            }
        }
        foreach ($indexToDelete as $indexName) {
            $connection->dropIndex($tableName, $indexName);
        }
        //NOTE: add new index
        $indexName = $setup->getIdxName(
            $tableName,
            ['scope', 'scope_id', 'name'],
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
        if (!isset($indexList[$indexName])) {
            $connection->addIndex(
                $tableName,
                $indexName,
                ['scope', 'scope_id', 'name'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            );
        }

        return true;
    }

    /**
     * Upgrade cache table
     *
     * @param SchemaSetupInterface $setup
     * @return bool
     */
    protected function upgradeCacheTable(SchemaSetupInterface $setup)
    {
        if (!$setup->tableExists(self::SIRV_CACHE_TABLE)) {
            return false;
        }

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

        //NOTE: remove outdated table
        if ($connection->tableColumnExists($tableName, 'last_checked')) {
            $connection->dropTable($tableName);
            return false;
        }

        if ($connection->tableColumnExists($tableName, 'url')) {
            $connection->changeColumn(
                $tableName,
                'url',
                'path',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => false,
                    'default' => '',
                    'comment' => 'Relative file path',
                ]
            );
            $connection->query(
                "ALTER TABLE `{$tableName}` MODIFY `path` varchar(255) BINARY NOT NULL DEFAULT '' COMMENT 'Relative file path'"
            );
        }

        if (!$connection->tableColumnExists($tableName, 'path_type')) {
            $connection->addColumn(
                $tableName,
                'path_type',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'unsigned' => true,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Path type',
                    'after' => 'path'
                ]
            );
        }

        if (!$connection->tableColumnExists($tableName, 'status')) {
            $connection->addColumn(
                $tableName,
                'status',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'unsigned' => true,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Sync status',
                    'after' => 'path_type'
                ]
            );
        }

        if (!$connection->tableColumnExists($tableName, 'attempt')) {
            $connection->addColumn(
                $tableName,
                'attempt',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'unsigned' => true,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Sync attempt',
                    'after' => 'status'
                ]
            );
        }

        $indexList = $connection->getIndexList($tableName);

        $indexName = $setup->getIdxName(
            $tableName,
            ['url'],
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
        if (isset($indexList[$indexName])) {
            $connection->dropIndex($tableName, $indexName);
        }

        $indexName = $setup->getIdxName(
            $tableName,
            ['path'],
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
        if (!isset($indexList[$indexName])) {
            $connection->addIndex(
                $tableName,
                $indexName,
                ['path'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            );
        }

        return true;
    }

    /**
     * Upgrade assets table
     *
     * @param SchemaSetupInterface $setup
     * @return bool
     */
    protected function upgradeAssetsTable(SchemaSetupInterface $setup)
    {
        if (!$setup->tableExists(self::SIRV_ASSETS_TABLE)) {
            return false;
        }

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_ASSETS_TABLE);

        if (!$connection->tableColumnExists($tableName, 'timestamp')) {
            $connection->addColumn(
                $tableName,
                'timestamp',
                [
                    'type' => Table::TYPE_INTEGER,
                    'unsigned' => true,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Last checked time',
                    'after' => 'contents'

                ]
            );
        }

        return true;
    }
}
