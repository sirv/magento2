<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Copy alt text ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CopyAltText extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Sync helper factory
     *
     * @var \Sirv\Magento2\Helper\Sync\BackendFactory
     */
    protected $syncHelperFactory = null;

    /**
     * Alt text cache model factory
     *
     * @var \Sirv\Magento2\Model\AltTextCacheFactory
     */
    protected $altTextCacheModelFactory = null;

    /**
     * Product model factory
     *
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productModelFactory = null;

    /**
     * How many products to process with one request
     *
     * @var integer
     */
    protected $pageSize = 100;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
     * @param \Sirv\Magento2\Model\AltTextCacheFactory $altTextCacheModelFactory
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory,
        \Sirv\Magento2\Model\AltTextCacheFactory $altTextCacheModelFactory,
        \Magento\Catalog\Model\ProductFactory $productModelFactory
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->syncHelperFactory = $syncHelperFactory;
        $this->altTextCacheModelFactory = $altTextCacheModelFactory;
        $this->productModelFactory = $productModelFactory;
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
            'error' => false,
            'data' => [],
        ];
        $data = [];

        switch ($action) {
            case 'get_alt_text_data':
                /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
                $syncHelper = $this->syncHelperFactory->create();
                $syncData = $syncHelper->getSyncData();

                /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
                $altTextCacheModel = $this->altTextCacheModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\AltTextCache $resource */
                $resource = $altTextCacheModel->getResource();
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $resource->getConnection();
                /** @var \Magento\Framework\DB\Select $select */
                $select = clone $connection->select();

                $altTextCacheTable = $resource->getTable('sirv_alt_text_cache');
                $select->reset()
                    ->from(
                        ['atct' => $altTextCacheTable],
                        ['total' => 'COUNT(`atct`.`path`)']
                    );
                /** @var int $cachedCount */
                $cachedCount = (int)$connection->fetchOne($select);

                $select->reset()
                    ->from(
                        ['atct' => $altTextCacheTable],
                        ['total' => 'COUNT(`atct`.`path`)']
                    )
                    ->where('`atct`.`value` = ?', '');
                /** @var int $cachedEmptyCount */
                $cachedEmptyCount = (int)$connection->fetchOne($select);

                $data['total'] = $syncData['synced'];
                $data['cached'] = $cachedCount;
                $data['empty'] = $cachedEmptyCount;

                $result['success'] = true;
                break;
            case 'clear_cache':
                /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
                $altTextCacheModel = $this->altTextCacheModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\AltTextCache $resource */
                $resource = $altTextCacheModel->getResource();
                $resource->deleteAll();

                $result['success'] = true;
                break;
            case 'init_temp_data':
                /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
                $altTextCacheModel = $this->altTextCacheModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\AltTextCache $resource */
                $resource = $altTextCacheModel->getResource();
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $resource->getConnection();

                $tempTableName = $resource->getTable('sirv_product_ids_temp');
                if ($connection->isTableExists('sirv_product_ids_temp')) {
                    $connection->truncateTable($tempTableName);
                } else {
                    $table = $connection->newTable(
                        $tempTableName
                    )->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['unsigned' => true, 'nullable' => false, 'primary' => true],
                        'Product id'
                    )->setComment(
                        'Temporary catalog product ids table'
                    );

                    $connection->createTable($table);
                }

                $cpeTable = $resource->getTable('catalog_product_entity');

                /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $statement */
                $statement = $connection->query("SHOW COLUMNS FROM `{$cpeTable}` LIKE 'row_id'");
                $columns = $statement->fetchAll();
                $fieldName = empty($columns) ? 'entity_id' : 'row_id';

                $cpemgvTable = $resource->getTable('catalog_product_entity_media_gallery_value_to_entity');

                /** @var \Magento\Framework\DB\Select $select */
                $select = clone $connection->select();
                $select->reset()
                    ->from(
                        ['cpe' => $cpeTable],
                        ['id' => 'cpe.' . $fieldName]
                    )
                    ->joinInner(
                        ['cpemgv' => $cpemgvTable],
                        '`cpe`.`' . $fieldName . '` = `cpemgv`.`' . $fieldName . '`',
                        []
                    )
                    ->group('cpe.' . $fieldName)
                    ->order('cpe.' . $fieldName . ' ASC');

                $query = $select->insertIgnoreFromSelect($tempTableName, ['id']);
                $connection->query($query, []);

                $select->reset()
                    ->from(
                        ['t' => $tempTableName],
                        ['total' => 'COUNT(*)']
                    );
                /** @var int $productsCount */
                $productsCount = (int)$connection->fetchOne($select);

                //$dataHelper = $this->getDataHelper();
                //$data['page'] = $dataHelper->getConfig('alt_text_sync_page') ?: 0;
                $data['page'] = 0;
                $data['lastPage'] = ceil($productsCount / $this->pageSize) - 1;

                $result['success'] = true;
                break;
            case 'sync_alt_text_data':
                $page = $postData['page'] ?? 0;
                $lastPage = $postData['lastPage'] ?? 0;
                $altTextRule = $postData['altTextRule'] ?? '{alt-text}';
                $altTextCounter = 0;
                $emptyAltTextCounter = 0;

                $attrMatches = [];
                preg_match_all('#{attribute:([a-zA-Z0-9_]++)}#', $altTextRule, $attrMatches, PREG_SET_ORDER);

                /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
                $altTextCacheModel = $this->altTextCacheModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\AltTextCache $resource */
                $resource = $altTextCacheModel->getResource();
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $resource->getConnection();
                /** @var \Magento\Framework\DB\Select $select */
                $select = clone $connection->select();

                $productIdsTable = $resource->getTable('sirv_product_ids_temp');
                $select->reset()
                    ->from(
                        ['pit' => $productIdsTable],
                        ['id' => 'pit.id']
                    )
                    ->limit($this->pageSize, $page * $this->pageSize);

                /** @var array $pids */
                $pids = $connection->fetchCol($select);

                /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
                $syncHelper = $this->syncHelperFactory->create();

                $productMediaRelPath = $syncHelper->getProductMediaRelPath();

                $dataHelper = $this->getDataHelper();
                $sirvClient = $dataHelper->getSirvClient();

                foreach ($pids as $productId) {
                    /** @var \Magento\Catalog\Model\Product $product */
                    $product = $this->productModelFactory->create()->load($productId);
                    $productSku = $product->getSku();
                    $altText = str_replace(
                        ['{product-id}', '{product-sku}'],
                        [$productId, $productSku],
                        $altTextRule
                    );
                    foreach ($attrMatches as $match) {
                        $attrValue = $product->getData($match[1]);
                        if (is_string($attrValue)) {
                            $attrValue = trim($attrValue);
                            if (empty($attrValue)) {
                                $attrValue = false;
                            } else {
                                $attrTextValue = $product->getAttributeText($match[1]);
                                if (is_string($attrTextValue)) {
                                    $attrTextValue = trim($attrTextValue);
                                    if (!empty($attrTextValue)) {
                                        $attrValue = $attrTextValue;
                                    }
                                }
                            }
                        } else {
                            $attrValue = false;
                        }

                        if ($attrValue) {
                            $altText = str_replace('{attribute:' . $match[1] . '}', $attrValue, $altText);
                        } else {
                            $pattern = '{attribute:' . $match[1] . '}';
                            $altText = preg_replace(
                                [
                                    '#/' . $pattern . '/#',
                                    '#^' . $pattern . '/|/' . $pattern . '$|' . $pattern . '#'
                                ],
                                [
                                    '/',
                                    ''
                                ],
                                $altText
                            );
                        }
                    }

                    $images = $product->getMediaGallery('images');
                    foreach ($images as $image) {
                        if ($image['media_type'] == 'image') {
                            $title = $image['label'];
                        } elseif ($image['media_type'] == 'external-video') {
                            $title = $image['video_title'];
                        } else {
                            continue;
                        }

                        $relPath = $productMediaRelPath . '/' . ltrim($image['file'], '\\/');
                        $isSynced = \Sirv\Magento2\Helper\Sync::IS_SYNCED == $syncHelper->getSyncStatus($relPath);
                        if ($isSynced) {
                            $altTextCacheModel->clearInstance()->load($relPath, 'path');
                            $value = $altTextCacheModel->getValue();
                            if ($value === null) {
                                $_altText = str_replace(
                                    '{alt-text}',
                                    $title,
                                    $altText
                                );
                                if (empty($_altText)) {
                                    $altTextCacheModel->setPath($relPath);
                                    $altTextCacheModel->setValue($_altText);
                                    $altTextCacheModel->save();
                                    $emptyAltTextCounter++;
                                    continue;
                                }
                                if ($sirvClient->setFileDescription(
                                    $syncHelper->getRelUrl($relPath),
                                    $_altText
                                )) {
                                    $altTextCacheModel->setPath($relPath);
                                    $altTextCacheModel->setValue($_altText);
                                    $altTextCacheModel->save();
                                    $altTextCounter++;
                                } else {
                                    //$errorMessage = $sirvClient->getErrorMsg();
                                    //$responseCode = $sirvClient->getResponseCode();
                                }
                            }
                        }
                    }
                }

                $page++;
                $data['page'] = $page;
                $data['copied'] = $altTextCounter;
                $data['empty'] = $emptyAltTextCounter;
                $data['completed'] = $page > $lastPage;
                //$dataHelper->saveConfig('alt_text_sync_page', $data['completed'] ? 0 : $page);

                $result['success'] = true;
                break;
            case 'clean_temp_data':
                /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
                $altTextCacheModel = $this->altTextCacheModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\AltTextCache $resource */
                $resource = $altTextCacheModel->getResource();
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $resource->getConnection();

                if ($connection->isTableExists('sirv_product_ids_temp')) {
                    $tempTableName = $resource->getTable('sirv_product_ids_temp');
                    $connection->dropTable($tempTableName);
                }

                //$dataHelper = $this->getDataHelper();
                //$dataHelper->saveConfig('alt_text_sync_page', 0);

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
