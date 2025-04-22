<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Catalog images cache info ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CatalogImagesCacheInfo extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $action = isset($postData['dataAction']) ? $postData['dataAction'] : '';

        $result = [
            'success' => false,
            'data' => []
        ];
        $data = [];

        switch ($action) {
            case 'get_dir_list':
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                /** @var \Magento\Framework\Filesystem $filesystem */
                $filesystem = $objectManager->get(\Magento\Framework\Filesystem::class);
                /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory */
                $mediaDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

                $cacheDir = 'catalog/product/cache';

                $list = [];
                if ($mediaDirectory->isDirectory($cacheDir)) {
                    $pathes = $mediaDirectory->read($cacheDir);
                    foreach ($pathes as $path) {
                        if ($mediaDirectory->isDirectory($path)) {
                            $list[] = $path;
                        }
                    }
                }

                $data = ['list' => $list];
                $result['success'] = true;
                break;
            case 'get_images_cache_info':
                $path = $postData['dir'] ?? '';
                if (empty($path)) {
                    $data['error'] = 'Directory name is empty!';
                    break;
                }

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                /** @var \Magento\Framework\Filesystem $filesystem */
                $filesystem = $objectManager->get(\Magento\Framework\Filesystem::class);
                /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory */
                $mediaDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

                if (!$mediaDirectory->isDirectory($path)) {
                    $data['error'] = 'It is not a directory:' . $path;
                    break;
                }

                $mediaDirAbsPath = rtrim($mediaDirectory->getAbsolutePath(), '\\/') . '/';
                /** @var \Magento\Framework\Shell $shell */
                $shell = $objectManager->get(\Magento\Framework\Shell::class);
                $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
                $count = 0;
                $absPath = $mediaDirAbsPath . trim($path, '\\/') . '/';

                try {
                    $command = 'find ' . $absPath . ' -type f | wc -l';
                    $output = $shell->execute($command);
                    if (preg_match('#^(\d++)$#', $output, $match)) {
                        $count = (int)$output;
                    } else {
                        throw new \Exception('Unexpected result when executing command: ' . $command);
                    }
                } catch (\Exception $e) {
                    try {
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($absPath, $flags),
                            \RecursiveIteratorIterator::CHILD_FIRST
                        );
                        $_count = 0;
                        foreach ($iterator as $item) {
                            if ($item->isFile()) {
                                $_count++;
                            }
                        }
                        $count = $_count;
                    } catch (\Exception $e) {
                        throw new \Magento\Framework\Exception\FileSystemException(
                            new \Magento\Framework\Phrase($e->getMessage()),
                            $e
                        );
                    }
                }

                $data = ['count' => $count];
                $result['success'] = true;
                break;
            case 'update_images_cache_info':
                $count = $postData['count'] ?? 0;

                $c = $count = (int)$count;
                $countLabel = '';
                while ($c >= 1000) {
                    $r = $c % 1000;
                    $countLabel = ',' . ($r == 0 ? '000' : ($r < 10 ? '00' : ($r < 100 ? '0' : '')) . $r) . $countLabel;
                    $c = floor($c / 1000);
                }
                $countLabel = $c . $countLabel;

                $timestamp = time();

                $data = [
                    'count' => $count,
                    'countLabel' => $countLabel,
                    'timestamp' => $timestamp
                ];

                /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
                $dataHelper = $this->getDataHelper();
                $dataHelper->saveBackendConfig(
                    'sirv_catalog_images_cache_info',
                    $dataHelper->getSerializer()->serialize($data)
                );

                $data['date'] = date('F j, Y', $timestamp);
                $result['success'] = true;
                break;
            default:
                $data['error'] = __('Unknown action: "%1"', $action);
                break;
        }

        $result['data'] = $data;

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;
    }
}
