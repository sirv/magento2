<?php

namespace Sirv\Magento2\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning static files cache
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CleanStaticFilesCacheCommand extends Command
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
        $this->setName('sirv:static-files-cache:clean');
        $this->setDescription('Flush Static Files Cache (preprocessed view files and static files).');
        parent::configure();
    }

    /**
     * Executes the current command
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            /** @var \Magento\Framework\App\State\CleanupFiles $cleanupFilesService */
            $cleanupFilesService = $this->objectManager->get(\Magento\Framework\App\State\CleanupFiles::class);
            $cleanupFilesService->clearMaterializedViewFiles();

            /** @var \Magento\Framework\Event\Manager\Proxy $eventManager */
            $eventManager = $this->objectManager->get(\Magento\Framework\Event\ManagerInterface::class);
            $eventManager->dispatch('clean_static_files_cache_after');

            $output->writeln('<info>' . __('The static files cache has been cleaned.') . '</info>');
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
