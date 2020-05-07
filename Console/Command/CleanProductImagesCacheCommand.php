<?php

namespace MagicToolbox\Sirv\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning product images cache
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CleanProductImagesCacheCommand extends Command
{
    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return void
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
        parent::__construct();
    }

    /**
     * Initialization of the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('sirv:product-images-cache:clean');
        $this->setDescription('Clean images cache (pregenerated product images files).');
        parent::configure();
    }

    /**
     * Executes the current command
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            /** @var \Magento\Catalog\Model\Product\Image $productImageModel */
            $productImageModel = $this->objectManager->get(\Magento\Catalog\Model\Product\Image::class);
            $productImageModel->clearCache();

            /** @var \Magento\Framework\Event\Manager\Proxy $eventManager */
            $eventManager = $this->objectManager->get(\Magento\Framework\Event\ManagerInterface::class);
            $eventManager->dispatch('clean_catalog_images_cache_after');

            $output->writeln('<info>' . __('The image cache was cleaned.') . '</info>');
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
