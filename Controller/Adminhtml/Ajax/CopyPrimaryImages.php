<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Copy primary images ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class CopyPrimaryImages extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Assets model factory
     *
     * @var \Sirv\Magento2\Model\AssetsFactory
     */
    protected $assetsModelFactory = null;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    protected $mediaConfig;

    /**
     *
     * @var \Magento\Catalog\Model\Product\Gallery\ReadHandler
     */
    protected $galleryReadHandler;

    /**
     * @var \Magento\Catalog\Model\Product\Gallery\EntryFactory
     */
    protected $mediaGalleryEntryFactory;

    /**
     * @var \Magento\Framework\Api\ImageContentFactory
     */
    protected $imageContentFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Gallery\GalleryManagement
     */
    protected $mediaGalleryManagement;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     * @param \Magento\Catalog\Model\Product\Gallery\ReadHandler $galleryReadHandler
     * @param \Magento\Catalog\Model\Product\Gallery\EntryFactory $mediaGalleryEntryFactory
     * @param \Magento\Framework\Api\ImageContentFactory $imageContentFactory
     * @param \Magento\Catalog\Model\Product\Gallery\GalleryManagement $mediaGalleryManagement
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        \Magento\Catalog\Model\Product\Gallery\ReadHandler $galleryReadHandler,
        \Magento\Catalog\Model\Product\Gallery\EntryFactory $mediaGalleryEntryFactory,
        \Magento\Framework\Api\ImageContentFactory $imageContentFactory,
        \Magento\Catalog\Model\Product\Gallery\GalleryManagement $mediaGalleryManagement
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->assetsModelFactory = $assetsModelFactory;
        $this->filesystem = $filesystem;
        $this->mediaConfig = $mediaConfig;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->mediaGalleryEntryFactory = $mediaGalleryEntryFactory;
        $this->imageContentFactory = $imageContentFactory;
        $this->mediaGalleryManagement = $mediaGalleryManagement;
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
            case 'get_magento_data':
                /** @var \Sirv\Magento2\Model\Assets $assetsModel */
                $assetsModel = $this->assetsModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\Assets $resource */
                $resource = $assetsModel->getResource();
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

                $select->reset()
                    ->from(
                        ['pt' => $productTable],
                        ['total' => 'COUNT(`pt`.`' . $fieldName . '`)']
                    );
                /** @var int $totalProductsCount */
                $totalProductsCount = (int)$connection->fetchOne($select);

                $conditionSelect = clone $connection->select();
                $conditionSelect->reset()
                    ->from(
                        ['mtet' => $mediaToEntityTable]
                    )
                    ->where('`pt`.`' . $fieldName . '` = `mtet`.`' . $fieldName . '`');

                $select->reset()
                    ->from(
                        ['pt' => $productTable],
                        [
                            'id' => 'pt.' . $fieldName,
                            'sku' => 'pt.sku',
                        ]
                    )
                    ->where(
                        'NOT EXISTS ?',
                        new \Zend_Db_Expr("({$conditionSelect})")
                    );

                /** @var array $productsWithoutMedia */
                $productsWithoutMedia = $connection->fetchAll($select);

                $data['total'] = $totalProductsCount;
                $data['products'] = $productsWithoutMedia;
                $result['success'] = true;
                break;
            case 'get_sirv_data':
                /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
                $dataHelper = $this->getDataHelper();

                $pathTemplate = $dataHelper->getConfig('product_assets_folder') ?: '';
                $pathTemplate = trim(trim($pathTemplate), '/');
                if (empty($pathTemplate)) {
                    $data['error'] = 'Assets folder is not set!';
                    break;
                }
                $placeholdersPattern = '#{product-(?:sku(?:-(?:2|3)-char)?|id)}#';
                if (!preg_match($placeholdersPattern, $pathTemplate)) {
                    $pathTemplate = $pathTemplate . '/{product-sku}';
                }

                $parts = explode('/', $pathTemplate);
                $structure = [];
                $commonPath = [];
                $i = 0;
                foreach ($parts as $part) {
                    if (preg_match($placeholdersPattern, $part)) {
                        $structure[$i] = [
                            'path' => implode('/', $commonPath),
                            'template' => $part,
                            'unique' => preg_match('#{product-(?:sku|id)}#', $part),
                            'list' => null
                        ];
                        $commonPath = [];
                        $i++;
                    } else {
                        $commonPath[] = $part;
                    }
                }
                $structure[0]['list'] = $dataHelper->getSirvDirList($structure[0]['path']);

                $data['pathTemplate'] = $pathTemplate;
                $data['structure'] = $structure;

                $result['success'] = true;
                break;
            case 'get_dir_list':
                $srcList = $postData['list'] ?? [];

                if (!empty($srcList)) {
                    /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
                    $dataHelper = $this->getDataHelper();

                    $resultList = [];
                    foreach ($srcList as $path) {
                        $resultList = array_merge($resultList, $dataHelper->getSirvDirList($path));
                    }

                    $data = $resultList;
                }

                $result['success'] = true;
                break;
            case 'copy_primary_images':
                /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
                $dataHelper = $this->getDataHelper();

                $productAssetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
                $productAssetsFolder = trim(trim($productAssetsFolder), '/');
                if (empty($productAssetsFolder)) {
                    $data['error'] = 'Assets folder is not set!';
                    break;
                }

                $products = isset($postData['products']) ? $postData['products'] : [];

                $baseUrl = 'https://' . $dataHelper->getSirvDomain();

                /** @var \Magento\Framework\Filesystem\Directory\WriteInterface $mediaDirectory */
                $mediaDirectory = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                //NOTE: absolute path to pub/media folder
                $mediaDirAbsPath = rtrim($mediaDirectory->getAbsolutePath(), '\\/');
                if (!$mediaDirectory->isDirectory('tmp/sirv')) {
                    $mediaDirectory->create('tmp/sirv');
                }

                /** @var \Sirv\Magento2\Model\Assets $assetsModel */
                $assetsModel = $this->assetsModelFactory->create();
                /** @var \Sirv\Magento2\Model\ResourceModel\Assets $resource */
                $resource = $assetsModel->getResource();
                /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
                $connection = $resource->getConnection();

                $table = $resource->getTable('catalog_product_entity_media_gallery_value_to_entity');
                /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $statement */
                $statement = $connection->query("SHOW COLUMNS FROM `{$table}` LIKE 'entity_id'");
                $columns = $statement->fetchAll();
                $fieldName = empty($columns) ? 'row_id' : 'entity_id';

                $table = $resource->getTable('eav_entity_type');
                $select = clone $connection->select();
                $select->reset()
                    ->from(
                        ['t' => $table],
                        ['id' => 't.entity_type_id']
                    )
                    ->where('`t`.`entity_type_code` = ?', 'catalog_product');
                /** @var int $entityTypeId */
                $entityTypeId = (int)$connection->fetchOne($select);

                $table = $resource->getTable('eav_attribute');
                $select = clone $connection->select();
                $select->reset()
                    ->from(
                        ['t' => $table],
                        ['code' => 't.attribute_code', 'id' => 't.attribute_id']
                    )
                    ->where('`t`.`entity_type_id` = ?', $entityTypeId)
                    ->where(
                        '`t`.`attribute_code` IN (?)',
                        ['image', 'thumbnail', 'small_image', 'swatch_image', 'media_gallery']
                    );
                /** @var array $mediaAttributes */
                $mediaAttributes = $connection->fetchPairs($select);

                foreach ($products as &$product) {
                    $product['copied'] = false;

                    $assetsFolder = str_replace(
                        ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
                        [$product['id'], $product['sku'], substr($product['sku'], 0, 2), substr($product['sku'], 0, 3)],
                        $productAssetsFolder,
                        $found
                    );
                    if (!$found) {
                        $assetsFolder = $productAssetsFolder . '/' . $product['sku'];
                    }

                    $contents = [];
                    $assetsInfo = $dataHelper->downloadAssetsInfo($baseUrl . '/' . $assetsFolder . '.view?info');
                    $assetsInfo = json_decode($assetsInfo, true);
                    if (is_array($assetsInfo)) {
                        $contents = $dataHelper->prepareAssetsInfo($assetsInfo);
                    }

                    $assets = $contents['assets'] ?? [];
                    $featuredImage = $contents['featured_image'] ?? '';
                    if (empty($assets) || empty($featuredImage)) {
                        $product['message'] = 'Product has no assets on Sirv';
                        continue;
                    }

                    //NOTE: refresh cache
                    $assetsModel->load($product['id'], 'product_id');
                    $assetsModel->setData('product_id', $product['id']);
                    $assetsModel->setData('contents', json_encode($contents));
                    $assetsModel->setData('timestamp', time());
                    $assetsModel->save();

                    $fileName = basename(preg_replace('#\?[^?]*+$#', '', $featuredImage));

                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    $pi = pathinfo($fileName);
                    if (!isset($pi['extension']) || !in_array(strtolower($pi['extension']), ['jpg', 'jpeg', 'jfif', 'gif', 'png'])) {
                        $product['message'] = 'The image type for the file is invalid';
                        continue;
                    }

                    $fileName = $this->getCorrectFileName($fileName);
                    $tmpAbsPath = $mediaDirAbsPath . '/tmp/sirv/' . $fileName;
                    $url = $baseUrl . '/' . $featuredImage;

                    if ($fileContents = file_get_contents($url)) {
                        $bytes = file_put_contents($tmpAbsPath, $fileContents);
                        if ($bytes) {
                            $dispersionPath = $this->getDispersionPath($fileName);
                            $absPath = $mediaDirectory->getAbsolutePath($this->mediaConfig->getMediaPath(
                                $dispersionPath . '/' . $fileName
                            ));
                            $uniqueFileName = $this->getUniqueFileName($absPath);
                            $filePath = $dispersionPath . '/' . $uniqueFileName;
                            $mediaDirectory->copyFile(
                                '/tmp/sirv/' . $fileName,
                                $this->mediaConfig->getMediaPath($filePath)
                            );

                            $table = $resource->getTable('catalog_product_entity_media_gallery');
                            $connection->insert($table, [
                                'attribute_id' => $mediaAttributes['media_gallery'],
                                'value' => $filePath,
                                'media_type' => 'image',
                            ]);
                            $valueId = $connection->lastInsertId();

                            $table = $resource->getTable('catalog_product_entity_media_gallery_value_to_entity');
                            $connection->insertOnDuplicate(
                                $table,
                                ['value_id' => $valueId, $fieldName => $product['id']],
                                ['value_id', $fieldName]
                            );

                            $table = $resource->getTable('catalog_product_entity_media_gallery_value');
                            $select = clone $connection->select();
                            $select->reset()
                                ->from(['t' => $table])
                                ->where('`t`.`value_id` = ?', $valueId)
                                ->where('`t`.`store_id` = ?', 0)
                                ->where('`t`.`' . $fieldName . '` = ?', $product['id']);
                            $select->deleteFromSelect($table);
                            $connection->insert($table, [
                                'value_id' => $valueId,
                                'store_id' => 0,
                                $fieldName => $product['id'],
                                'label' => null,
                                'position' => 0,
                                'disabled' => 0,
                            ]);

                            $table = $resource->getTable('catalog_product_entity_varchar');
                            $connection->insertOnDuplicate(
                                $table,
                                [
                                    'attribute_id' => $mediaAttributes['image'],
                                    'store_id' => 0,
                                    $fieldName => $product['id'],
                                    'value' => $filePath
                                ],
                                ['attribute_id', 'store_id', $fieldName, 'value']
                            );
                            $connection->insertOnDuplicate(
                                $table,
                                [
                                    'attribute_id' => $mediaAttributes['thumbnail'],
                                    'store_id' => 0,
                                    $fieldName => $product['id'],
                                    'value' => $filePath
                                ],
                                ['attribute_id', 'store_id', $fieldName, 'value']
                            );
                            $connection->insertOnDuplicate(
                                $table,
                                [
                                    'attribute_id' => $mediaAttributes['small_image'],
                                    'store_id' => 0,
                                    $fieldName => $product['id'],
                                    'value' => $filePath
                                ],
                                ['attribute_id', 'store_id', $fieldName, 'value']
                            );
                            $connection->insertOnDuplicate(
                                $table,
                                [
                                    'attribute_id' => $mediaAttributes['swatch_image'],
                                    'store_id' => 0,
                                    $fieldName => $product['id'],
                                    'value' => 'no_selection'
                                ],
                                ['attribute_id', 'store_id', $fieldName, 'value']
                            );

                            unlink($tmpAbsPath);

                            $product['copied'] = true;
                        } else {
                            $product['message'] = 'Product image was not saved to temp path';
                        }
                    } else {
                        $product['message'] = 'Product image was not downloaded from Sirv';
                    }
                }

                $data['products'] = $products;
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
     * Get correct file name
     *
     * @param string $fileName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getCorrectFileName($fileName)
    {
        $fileName = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $fileName);
        $fileInfo = pathinfo($fileName);
        $fileInfo['extension'] = $fileInfo['extension'] ?? '';
        if ($fileInfo['extension'] == 'jfif') {
            $fileInfo['extension'] = 'jpeg';
        }

        $length = strlen($fileInfo['basename']);
        if ($length > 90) {
            $fileInfo['filename'] = substr($fileInfo['filename'], 0, 90 - $length);
            if (empty($fileInfo['filename'])) {
                throw new \InvalidArgumentException('Filename is too long; must be 90 characters or less');
            }
        }

        if (preg_match('/^_+$/', $fileInfo['filename'])) {
            $fileInfo['filename'] = 'file';
        }

        $fileInfo['basename'] = $fileInfo['filename'] .
            (empty($fileInfo['extension']) ? '' : '.' . $fileInfo['extension']);

        return $fileInfo['basename'];
    }

    /**
     * Get dispersion path
     *
     * @param string $fileName
     * @return string
     */
    protected function getDispersionPath($fileName)
    {
        $i = 0;
        $l = strlen($fileName);
        $dispersionPath = '';
        while ($i < 2 && $i < $l) {
            $dispersionPath .= '/' . ('.' == $fileName[$i] ? '_' : $fileName[$i]);
            $i++;
        }

        return $dispersionPath;
    }

    /**
     * Get new file name if the same is already exists
     *
     * @param string $destinationFile
     * @return string
     */
    protected function getUniqueFileName($destinationFile)
    {
        $fileInfo = pathinfo($destinationFile);
        $index = 1;
        while (is_file($fileInfo['dirname'] . '/' . $fileInfo['basename'])) {
            $fileInfo['basename'] = $fileInfo['filename'] . '_' . ($index++);
            $fileInfo['basename'] .= isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
        }

        return $fileInfo['basename'];
    }
}
