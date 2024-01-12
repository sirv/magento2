<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Assets cache controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AssetsCache extends \Magento\Backend\App\Action
{
    /**
     * Assets cache helper factory
     *
     * @var \Sirv\Magento2\Helper\AssetsCacheFactory
     */
    protected $assetsCacheHelperFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory
    ) {
        parent::__construct($context);
        $this->assetsCacheHelperFactory = $assetsCacheHelperFactory;
    }

    /**
     * Update assets cache
     *
     * @return string
     */
    public function execute()
    {
        $result = [];
        $productIds = $this->getRequest()->getParam('ids');
        $assetsCacheHelper = $this->assetsCacheHelperFactory->create();

        $message = $assetsCacheHelper->updateAssetsCache($productIds);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData(['message' => $message]);

        return $resultJson;
    }
}
