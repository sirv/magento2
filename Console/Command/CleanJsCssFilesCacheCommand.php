<?php

namespace Sirv\Magento2\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning JavaScript and CSS files cache
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CleanJsCssFilesCacheCommand extends Command
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
        $this->setName('sirv:js-css-files-cache:clean');
        $this->setDescription('Flush JavaScript/CSS Cache (themes JavaScript and CSS files combined to one file).');
        parent::configure();
    }

    /**
     * Executes the current command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            /** @var \Magento\Framework\View\Asset\MergeService $mergeServiceModel */
            $mergeServiceModel = $this->objectManager->get(\Magento\Framework\View\Asset\MergeService::class);
            $mergeServiceModel->cleanMergedJsCss();

            /** @var \Magento\Framework\Event\Manager\Proxy $eventManager */
            $eventManager = $this->objectManager->get(\Magento\Framework\Event\ManagerInterface::class);
            $eventManager->dispatch('clean_media_cache_after');

            $output->writeln('<info>' . __('The JavaScript/CSS cache has been cleaned.') . '</info>');
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
