<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Assets ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Execute action
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
            /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $statement */
            $statement = $connection->query("SHOW COLUMNS FROM `{$table}` LIKE 'row_id'");
            $columns = $statement->fetchAll();
            $fieldName = empty($columns) ? 'entity_id' : 'row_id';

            $ids = array_keys($assetsData);
            $select->reset()
                ->from(
                    ['t' => $table],
                    [
                        'id' => 't.' . $fieldName,
                        'sku' => 't.sku',
                    ]
                )
                ->where(
                    't.' . $fieldName . ' IN (?)',
                    $ids
                );
            $skus = $connection->fetchPairs($select, []);

            /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
            $dataHelper = $this->getDataHelper();

            $baseUrl = 'https://' . $dataHelper->getSirvDomain();

            $assetRepository = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\View\Asset\Repository::class
            );

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
                    } elseif ($asset->type == 'model') {
                        $url .= '?embed';
                        $thumbUrl = $assetRepository->createAsset('Sirv_Magento2::images/icon.3d.3.svg')->getUrl();
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
