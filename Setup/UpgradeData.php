<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Data upgrades
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Config table name
     */
    const SIRV_CONFIG_TABLE = 'sirv_config';

    /**
     * Module configuration file reader
     *
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleDirReader;

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
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDirReader = $modulesReader;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Upgrades data
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        //NOTE: 'data_version' from `setup_module` table
        $dataVersion = $context->getVersion();

        if (empty($dataVersion)) {
            //NOTE: skip upgrade when install
            return;
        }

        $setup->startSetup();

        if ($setup->tableExists(self::SIRV_CONFIG_TABLE)) {
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $setup->getConnection();

            $tableName = $setup->getTable(self::SIRV_CONFIG_TABLE);
            $names = $connection->fetchCol(
                $connection->select()->from($tableName, 'name')->where('scope_id = ?', 0)
            );
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
                    $name = (string)$field->name;
                    if (!isset($field->install) || isset($names[$name])) {
                        continue;
                    }
                    $data[] = [
                        'scope' => 'default',
                        'scope_id' => 0,
                        'name' => $name,
                        'value' => (string)$field->value
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
