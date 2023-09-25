<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Data installs
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Eav setup factory
     *
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @param \Magento\Framework\Module\ModuleResource $moduleResource
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Magento\Framework\Module\ModuleResource $moduleResource,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDirReader = $modulesReader;
        $this->moduleResource = $moduleResource;
        $this->eavSetupFactory = $eavSetupFactory;
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
                        if (in_array($name, ['add_img_width_height', 'use_placeholders', 'use_placeholder_with_smv'])) {
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
                    $data[] = [
                        'name' => 'installation_date',
                        'value' => time()
                    ];
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

        //NOTE: install 'Extra Sirv Assets' attribute
        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $id = $eavSetup->getAttributeId(
            \Magento\Catalog\Model\Product::ENTITY,
            'extra_sirv_assets'
        );
        if (!$id) {
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'extra_sirv_assets',
                [
                     'type' => 'text',
                     'backend' => '',
                     'frontend' => '',
                     'label' => 'Extra Sirv Assets',
                     'input' => 'textarea',
                     'class' => '',
                     'source' => '',
                     'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                     'visible' => true,
                     'required' => false,
                     'user_defined' => false,
                     'default' => '',
                     'searchable' => false,
                     'filterable' => false,
                     'comparable' => false,
                     'visible_on_front' => false,
                     'used_in_product_listing' => false,
                     'unique' => false,
                     'apply_to' => ''
                 ]
            );
        }

        $setup->endSetup();
    }
}
