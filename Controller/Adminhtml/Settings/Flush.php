<?php

namespace Sirv\Magento2\Controller\Adminhtml\Settings;

/**
 * Settings backend controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Flush extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Assets model factory
     *
     * @var \Sirv\Magento2\Model\AssetsFactory
     */
    protected $assetsModelFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->assetsModelFactory = $assetsModelFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $action = $this->getRequest()->getParam('flush-action');
        $cleanEmptyItems = false;
        $message = '<a class="save-message" href="' . $this->getUrl('adminhtml/cache') . '">Clear your page cache</a> to see the changes.';

        switch ($action) {
            case 'empty':
                $cleanEmptyItems = true;
                // no break
            case 'notempty':
                $ids = [];
                $assetsModel = $this->assetsModelFactory->create();
                $collection = $assetsModel->getCollection();
                $collection->setPageSize(1000);
                $pageCount = $collection->getLastPageNumber();
                $currentPage = 1;
                while ($currentPage <= $pageCount) {
                    $collection->setCurPage($currentPage);
                    foreach ($collection as $item) {
                        $contents = $item->getData('contents');
                        $contents = json_decode($contents);
                        $notEmpty = is_object($contents) && isset($contents->assets) && is_array($contents->assets) && !empty($contents->assets);
                        if ($notEmpty xor $cleanEmptyItems) {
                            $ids[] = $item->getData('product_id');
                        }
                    }
                    $collection->clear();
                    $currentPage++;
                }
                if (empty($ids)) {
                    $message = 'No data found for cleaning.';
                } else {
                    $assetsModel->getResource()->deleteByIds($ids);
                    $message = count($ids) . ' item(s) cleaned. ' . $message;
                }
                break;
            case 'all':
                $assetsModel = $this->assetsModelFactory->create();
                $assetsModel->getResource()->deleteAll();
                $message = 'The asset\'s data cache was flushed. ' . $message;
                break;
            default:
                $message = 'Error: wrong action!';
        }

        $this->messageManager->addSuccess($message);

        $resultRedirect->setPath('sirv/*/edit');

        return $resultRedirect;
    }
}
