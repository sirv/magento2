<?php

namespace Sirv\Magento2\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * DB schema upgrades
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 *
 * @codeCoverageIgnore
 */
class UpgradeSchema extends AbstractSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        //NOTE: 'schema_version' from `setup_module` table
        $schemaVersion = $context->getVersion();
        if (empty($schemaVersion)) {
            //NOTE: skip upgrade when install
            return;
        }

        $setup->startSetup();

        $this->upgradeConfigTable($setup) || $this->createConfigTable($setup);
        $this->upgradeCacheTable($setup) || $this->createCacheTable($setup);
        $this->upgradeAssetsTable($setup) || $this->createAssetsTable($setup);
        $this->createMessagesTable($setup);
        $this->createAltTextCacheTable($setup);

        $setup->endSetup();
    }
}
