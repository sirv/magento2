<?php

namespace Sirv\Magento2\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Install default configuration data and product attribute
 *
 * @copyright Copyright (c) 2018-2026 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class InstallConfigData implements DataPatchInterface
{
    /**
     * Config table name
     */
    public const SIRV_CONFIG_TABLE = 'sirv_config';

    /**
     * Cache table name
     */
    public const SIRV_CACHE_TABLE = 'sirv_cache';

    /**
     * Data setup instance
     *
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * Module configuration file reader
     *
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleDirReader;

    /**
     * Module resource model
     *
     * @var \Magento\Framework\Module\ModuleResource
     */
    protected $moduleResource;

    /**
     * Factory for EAV setup instances
     *
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @param \Magento\Framework\Module\ModuleResource $moduleResource
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @return void
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Magento\Framework\Module\ModuleResource $moduleResource,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->moduleDirReader = $modulesReader;
        $this->moduleResource = $moduleResource;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Apply patch
     *
     * @return $this
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $this->installDefaultConfig($setup);
        $this->fixOutdatedCacheData($setup);
        $this->installProductAttribute($setup);

        $setup->endSetup();

        return $this;
    }

    /**
     * Install default configuration values
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    protected function installDefaultConfig(ModuleDataSetupInterface $setup)
    {
        if (!$setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            return;
        }

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
        $names = $connection->fetchCol(
            $connection->select()->from($tableName, 'name')->where('scope_id = ?', 0)
        );
        $isFreshInstall = empty($names);
        $names = array_flip($names);

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
                if (isset($names[$name])) {
                    continue;
                }

                $value = (string)$field->value;
                //NOTE: change defaults for new users
                if ($isFreshInstall &&
                    in_array($name, ['add_img_width_height', 'use_placeholders', 'use_placeholder_with_smv'])
                ) {
                    $value = 'true';
                }

                $data[] = [
                    'scope' => 'default',
                    'scope_id' => 0,
                    'name' => $name,
                    'value' => $value
                ];
            }

            if (!isset($names['installation_date'])) {
                $data[] = [
                    'scope' => 'default',
                    'scope_id' => 0,
                    'name' => 'installation_date',
                    'value' => time()
                ];
            }

            unset($xml);
        }

        if (!empty($data)) {
            $connection->insertMultiple($tableName, $data);
        }

        if ($isFreshInstall) {
            return;
        }

        //NOTE: update value for 'excluded_from_lazy_load' and 'excluded_files' params
        $pNames = ['excluded_from_lazy_load', 'excluded_files'];
        foreach ($pNames as $pName) {
            $params = $connection->fetchAll(
                $connection->select()
                    ->from($tableName, ['id', 'value'])
                    ->where('name = ?', $pName)
            );
            foreach ($params as $param) {
                $value = trim($param['value'], "\n");
                $value = empty($value) ? [] : explode("\n", $value);
                if (in_array('/captcha*', $value)) {
                    continue;
                }
                $value[] = '/captcha*';
                $value = implode("\n", $value);
                $connection->update($tableName, ['value' => $value], ['id = ?' => $param['id']]);
            }
        }

        //NOTE: update value for 'slides_order' param
        $params = $connection->fetchAll(
            $connection->select()
                ->from($tableName, ['id', 'value'])
                ->where('name = ?', 'slides_order')
        );
        foreach ($params as $param) {
            $value = str_replace('image', 'zoom', $param['value']);
            $connection->update($tableName, ['value' => $value], ['id = ?' => $param['id']]);
        }
    }

    /**
     * Fix cache data left by very old module versions (MagicToolbox_Sirv with 'data_version' < 2.0.0)
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    protected function fixOutdatedCacheData(ModuleDataSetupInterface $setup)
    {
        if (!$setup->tableExists(self::SIRV_CACHE_TABLE)) {
            return;
        }

        $dataVersion = $this->moduleResource->getDataVersion('MagicToolbox_Sirv');
        if (empty($dataVersion) || version_compare($dataVersion, '2.0.0', '>=')) {
            return;
        }

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $setup->getConnection();

        $tableName = $setup->getTable(self::SIRV_CACHE_TABLE);

        if ($connection->tableColumnExists($tableName, 'path_type')) {
            $bind = ['path_type' => \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH];
            $where = [
                '`path_type` = ?' => \Sirv\Magento2\Helper\Sync::UNKNOWN_PATH,
                "`path` LIKE '/catalog/category/%' OR `path` LIKE '/catalog/product/%'",
            ];
            $connection->update($tableName, $bind, $where);

            $bind = ['path_type' => \Sirv\Magento2\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH];
            $where = [
                '`path_type` = ?' => \Sirv\Magento2\Helper\Sync::UNKNOWN_PATH,
                "`path` LIKE '/_/_/%' OR `path` LIKE '/watermark/%' OR `path` LIKE '/placeholder/%'",
            ];
            $connection->update($tableName, $bind, $where);
        }

        if ($connection->tableColumnExists($tableName, 'status')) {
            $bind = ['status' => \Sirv\Magento2\Helper\Sync::IS_SYNCED];
            $where = ['status = ?' => \Sirv\Magento2\Helper\Sync::IS_UNDEFINED];
            $connection->update($tableName, $bind, $where);
        }
    }

    /**
     * Install 'Extra Sirv Assets' product attribute
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    protected function installProductAttribute(ModuleDataSetupInterface $setup)
    {
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
    }

    /**
     * Get dependencies
     *
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return [];
    }
}
