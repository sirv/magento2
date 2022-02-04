<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Data installs
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
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
     * Module configuration file reader
     *
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleDirReader;

    /**
     * Module resource
     *
     * @var \Magento\Framework\Module\ModuleResource
     */
    protected $moduleResource;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @param \Magento\Framework\Module\ModuleResource $moduleResource
     * @return void
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Magento\Framework\Module\ModuleResource $moduleResource
    ) {
        $this->moduleDirReader = $modulesReader;
        $this->moduleResource = $moduleResource;
    }

    /**
     * Installs data
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $setup->getConnection();

            $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
            $names = $connection->fetchCol($connection->select()->from($tableName, 'name'));

            if (empty($names)) {
                $moduleEtcPath = $this->moduleDirReader->getModuleDir(
                    \Magento\Framework\Module\Dir::MODULE_ETC_DIR,
                    'Sirv_Magento2'
                );

                $useErrors = libxml_use_internal_errors(true);
                $xml = simplexml_load_file($moduleEtcPath . '/settings.xml');
                libxml_use_internal_errors($useErrors);

                $data = [];
                if ($xml) {
                    $fields = $xml->xpath('/settings/group/fields/field');
                    foreach ($fields as $field) {
                        if (!isset($field->install)) {
                            continue;
                        }

                        $name = (string)$field->name;
                        $value = (string)$field->value;
                        //NOTE: change defaults for new users
                        if ($name == 'add_img_width_height' || $name == 'use_placeholders') {
                            $value = 'true';
                        }

                        $data[] = [
                            'name' => $name,
                            'value' => $value
                        ];
                    }
                    unset($xml);
                }

                if (!empty($data)) {
                    /* $connection->truncateTable($tableName); */
                    $connection->insertMultiple($tableName, $data);
                }
            }
        }

        //NOTE: fix for very old module with 'data_version' < 2.0.0
        if ($setup->tableExists(self::SIRV_CACHE_TABLE)) {
            $dataVersion = $this->moduleResource->getDataVersion('MagicToolbox_Sirv');
            if (!empty($dataVersion) && version_compare($dataVersion, '2.0.0', '<')) {
                $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

                if ($connection->tableColumnExists($tableName, 'path_type')) {
                    $bind = ['path_type' => \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH];
                    $where = [
                        '`path_type` = ?' => \Sirv\Magento2\Helper\Sync::UNKNOWN_PATH,
                        new \Zend_Db_Expr("`path` LIKE '/catalog/category/%' OR `path` LIKE '/catalog/product/%'"),
                    ];
                    $connection->update($tableName, $bind, $where);

                    $bind = ['path_type' => \Sirv\Magento2\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH];
                    $where = [
                        '`path_type` = ?' => \Sirv\Magento2\Helper\Sync::UNKNOWN_PATH,
                        new \Zend_Db_Expr("`path` LIKE '/_/_/%' OR `path` LIKE '/watermark/%' OR `path` LIKE '/placeholder/%'"),
                    ];
                    $connection->update($tableName, $bind, $where);
                }

                if ($connection->tableColumnExists($tableName, 'status')) {
                    $bind = ['status' => \Sirv\Magento2\Helper\Sync::IS_SYNCED];
                    $where = ['status = ?' => \Sirv\Magento2\Helper\Sync::IS_UNDEFINED];
                    $connection->update($tableName, $bind, $where);
                }
            }
        }

        $setup->endSetup();
    }
}
