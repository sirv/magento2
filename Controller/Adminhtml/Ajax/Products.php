<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Products ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Products extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Product model factory
     *
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productModelFactory = null;

    /**
     * Sync helper factory
     *
     * @var \Sirv\Magento2\Helper\Sync\BackendFactory
     */
    protected $syncHelperFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @param \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->productModelFactory = $productModelFactory;
        $this->syncHelperFactory = $syncHelperFactory;
    }

    /**
     * Cache action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $action = isset($postData['dataAction']) ? $postData['dataAction'] : 'view';
        $group = isset($postData['group']) ? $postData['group'] : 'with_image';
        $pageNum = isset($postData['pageNum']) ? (int)$postData['pageNum'] : 0;
        $pageSize = isset($postData['pageSize']) ? (int)$postData['pageSize'] : 100;
        $pId = isset($postData['pId']) ? (int)$postData['pId'] : 0;

        $result = [
            'success' => false,
            'data' => []
        ];
        $data = [];

        switch ($action) {
            case 'view':
                $data = $this->getProductsData($group, $pageNum, $pageSize, $pId);
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

    /**
     * Method to get cached image data
     *
     * @param string $group
     * @param int $page
     * @param int $size
     * @param int $pId
     * @return array
     */
    public function getProductsData($group, $page, $size, $pId)
    {
        /** @var \Magento\Catalog\Model\Product $productModel */
        $productModel = $this->productModelFactory->create();
        /** @var  \Magento\Catalog\Model\ResourceModel\Product $resource */
        $resource = $productModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();

        /** @var \Magento\Framework\DB\Select $select */
        $select = clone $connection->select();
        $productTable = $resource->getTable('catalog_product_entity');
        $mediaToEntityTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);

        /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $statement */
        $statement = $connection->query("SHOW COLUMNS FROM `{$mediaToEntityTable}` LIKE 'entity_id'");
        $columns = $statement->fetchAll();
        $fieldName = empty($columns) ? 'row_id' : 'entity_id';

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();
        /** @var \Magento\Framework\App\CacheInterface $cache */
        $cache = $dataHelper->getAppCache();
        $cacheId = 'sirv_cache_' . $group . '_data_' . $size . '_' . $pId;
        $cacheData = $cache->load($cacheId);
        if (false === $cacheData) {
            $cacheData = [];
            $conditionSelect = clone $connection->select();
            $conditionSelect->reset()
                ->from(
                    ['mtet' => $mediaToEntityTable]
                )
                ->where('`pt`.`' . $fieldName . '` = `mtet`.`' . $fieldName . '`');

            switch ($group) {
                case 'without_image':
                    $where = 'NOT EXISTS ?';
                    break;
                case 'with_image':
                    // no break
                default:
                    $where = 'EXISTS ?';
                    break;
            }

            $select->reset()
                ->from(
                    ['pt' => $productTable],
                    ['id' => 'pt.' . $fieldName]
                )
                ->where($where, new \Zend_Db_Expr("({$conditionSelect})"))
                ->order('pt.' . $fieldName . ' ASC');

            /** @var array $result */
            $result = $connection->fetchCol($select, []);
            $cacheData['total'] = count($result);
            $cacheData['map'] = array_chunk($result, $size);
            $cache->save($dataHelper->getSerializer()->serialize($cacheData), $cacheId, [], null);
        } else {
            $cacheData = $dataHelper->getUnserializer()->unserialize($cacheData);
        }
        if (!is_array($cacheData)) {
            $cacheData = ['total' => 0, 'map' => []];
        }

        $data = [];
        if (isset($cacheData['map'][$page])) {
            $selectEntityTypeId = clone $connection->select();
            $selectEntityTypeId->reset()
                ->from(
                    ['t5' => $resource->getTable('eav_entity_type')],
                    ['entity_type_id']
                )
                ->where('`entity_type_code` = ?', 'catalog_product');

            $selectAttributeId = clone $connection->select();
            $selectAttributeId->reset()
                ->from(
                    ['t4' => $resource->getTable('eav_attribute')],
                    ['attribute_id']
                )
                ->where('`attribute_code` = ?', 'media_gallery')
                ->where('`entity_type_id` = ?', new \Zend_Db_Expr("({$selectEntityTypeId})"));

            $selectMediaGallery = clone $connection->select();
            $selectMediaGallery->reset()
                ->from(
                    ['t1' => $resource->getTable('catalog_product_entity_media_gallery_value')],
                    [
                        't1_entity_id' => 't1.entity_id',
                        't2_value' => 'GROUP_CONCAT(`t2`.`value`)'
                    ]
                )
                ->joinInner(
                    ['t2' => $resource->getTable('catalog_product_entity_media_gallery')],
                    '`t2`.`value_id` = `t1`.`value_id` AND `t2`.`attribute_id` = ' . new \Zend_Db_Expr("({$selectAttributeId})"),
                    []
                )
                ->group('t1.entity_id');

            $select->reset()
                ->from(
                    ['pt' => $productTable],
                    [
                        'id' => 'pt.' . $fieldName,
                        'sku' => 'pt.sku',
                        'media_gallery' => 'mg.t2_value'
                    ]
                )
                ->joinLeft(
                    ['mg' => new \Zend_Db_Expr("({$selectMediaGallery})")],
                    '`pt`.`entity_id` = `mg`.`t1_entity_id`',
                    []
                )
                ->where(
                    'pt.' . $fieldName . ' IN (?)',
                    $cacheData['map'][$page]
                );

            $data = $connection->fetchAll($select, []);

            /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
            $syncHelper = $this->syncHelperFactory->create();
            $baseUrl = $syncHelper->getMediaBaseUrl();
            $mediaDirAbsPath = $syncHelper->getMediaDirAbsPath();
            $productMediaRelPath = $syncHelper->getProductMediaRelPath();

            foreach ($data as &$product) {
                $product['gallery'] = [];
                $gallery = empty($product['media_gallery']) ? [] : explode(',', $product['media_gallery']);
                unset($product['media_gallery']);

                foreach ($gallery as $filePath) {
                    if (empty($filePath) || 'no_selection' == $filePath) {
                        continue;
                    }
                    $absPath = $mediaDirAbsPath . $productMediaRelPath . $filePath;

                    $fileModificationTime = 0;
                    $fileWidth = 0;
                    $fileHeight = 0;
                    $fileSize = 0;

                    if (is_file($absPath)) {
                        $fileModificationTime = filemtime($absPath);
                        list($fileWidth, $fileHeight,) = getimagesize($absPath);
                        $fileSize = filesize($absPath);
                    }
                    $units = 'B';
                    if ($fileSize > 1024) {
                        $fileSize = $fileSize / 1024;
                        $units = 'KB';
                        if ($fileSize > 1024) {
                            $fileSize = $fileSize / 1024;
                            $units = 'MB';
                        }
                    }

                    $url = $thumbUrl = $baseUrl . $productMediaRelPath . $filePath;

                    $product['gallery'][] = [
                        'name' => basename($filePath),
                        'url' => $url,
                        'thumburl' => $thumbUrl,
                        'mtime' => $fileModificationTime,
                        'width' => $fileWidth,
                        'height' => $fileHeight,
                        'size' => round($fileSize, 2) . ' ' . $units,
                    ];
                }
            }
        }

        return [
            'group' => $group,
            'page' => $page,
            'total' => $cacheData['total'],
            'next' => isset($cacheData['map'][$page + 1]),
            'items' => $data,
            'count' => count($data)
        ];
    }
}
