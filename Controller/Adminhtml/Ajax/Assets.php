<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Assets ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Assets extends \Sirv\Magento2\Controller\Adminhtml\Settings
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
     * @return void
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
     * Assets action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = [
            'error' => false,
            'products' => []
        ];

        /** @var \Sirv\Magento2\Model\Assets $assetsModel */
        $assetsModel = $this->assetsModelFactory->create();

        /** @var \Sirv\Magento2\Model\ResourceModel\Assets $resource */
        $resource = $assetsModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        /** @var \Magento\Framework\DB\Select $select */
        $select = clone $connection->select();

        $table = $resource->getMainTable();
        $select->reset()
            ->from(
                ['t' => $table],
                [
                    'id' => 't.product_id',
                    'data' => 't.contents',
                ]
            );
        $assetsData = $connection->fetchPairs($select, []);

        $data = [];
        if (!empty($assetsData)) {
            $table = $resource->getTable('catalog_product_entity');
            $ids = array_keys($assetsData);
            $select->reset()
                ->from(
                    ['t' => $table],
                    [
                        'id' => 't.entity_id',
                        'sku' => 't.sku',
                    ]
                )
                ->where(
                    't.entity_id IN (?)',
                    $ids
                );
            $skus = $connection->fetchPairs($select, []);

            /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
            $dataHelper = $this->getDataHelper();

            $baseUrl = 'https://' . $dataHelper->getSirvDomain();

            foreach ($assetsData as $id => $contents) {
                $data[$id] = [];
                $data[$id]['sku'] = isset($skus[$id]) ? $skus[$id] : false;
                $data[$id]['items'] = [];
                $contents = json_decode($contents);
                $dirname = is_object($contents) && isset($contents->dirname) ? $contents->dirname : '';
                $assets = is_object($contents) && isset($contents->assets) && is_array($contents->assets) ? $contents->assets : [];
                foreach ($assets as $asset) {
                    $size = $asset->size;
                    $units = 'B';
                    if ($size > 1024) {
                        $size = $size / 1024;
                        $units = 'KB';
                        if ($size > 1024) {
                            $size = $size / 1024;
                            $units = 'MB';
                        }
                    }
                    $thumbUrl = $url = $baseUrl . $dirname . '/' . $asset->name;
                    if ($asset->type == 'spin' || $asset->type == 'video') {
                        $thumbUrl .= '?thumb';
                    }
                    $data[$id]['items'][] = [
                        'name' => $asset->name,
                        'url' => $url,
                        'thumbUrl' => $thumbUrl,
                        'mtime' => $asset->mtime,
                        'size' => round($size, 2) . ' ' . $units,
                        'type' => $asset->type,
                        'width' => isset($asset->width) ? $asset->width : false,
                        'height' => isset($asset->height) ? $asset->height : false,
                    ];
                }
            }
        }

        $result['products'] = $data;

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;
    }
}
