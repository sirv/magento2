<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Custom sync ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Customsync extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Sync helper factory
     *
     * @var \Sirv\Magento2\Helper\Sync\BackendFactory
     */
    protected $syncHelperFactory;

    /**
     * Product repository
     *
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->syncHelperFactory = $syncHelperFactory;
        $this->productRepository = $productRepository;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = ['synced' => 0, 'error' => false];

        $getData = $this->getRequest()->getQueryValue();
        $productId = (int)(isset($getData['productId']) ? $getData['productId'] : 0);

        if ($productId) {
            try {
                $product = $this->productRepository->getById($productId);
            } catch (\Exception $e) {
                $product = null;
                $result['error'] = $e->getMessage();
            }
        }

        $syncedTotal = 0;
        if ($product) {
            $images = $product->getMediaGalleryImages();
            if ($images instanceof \Magento\Framework\Data\Collection) {
                /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
                $syncHelper = $this->syncHelperFactory->create();
                $mediaDirAbsPath = $syncHelper->getMediaDirAbsPath();
                $productMediaRelPath = $syncHelper->getProductMediaRelPath();

                $iterator = $images->getIterator();
                $iterator->rewind();
                while ($iterator->valid()) {
                    $image = $iterator->current();
                    if ('image' == $image->getData('media_type')) {
                        $imageFile = $image->getData('file');
                        $relPath = $productMediaRelPath . '/' . ltrim($imageFile, '\\/');
                        $absPath = $mediaDirAbsPath . $relPath;
                        if ($syncHelper->save(
                            $absPath,
                            \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH,
                            true
                        )) {
                            $syncedTotal++;
                        }
                    }
                    $iterator->next();
                }
            }
        }
        $result['synced'] = $syncedTotal;

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);
        return $resultJson;
    }
}
