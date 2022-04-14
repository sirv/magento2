<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Copy primary images ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
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
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

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
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
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
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\Product\Gallery\ReadHandler $galleryReadHandler,
        \Magento\Catalog\Model\Product\Gallery\EntryFactory $mediaGalleryEntryFactory,
        \Magento\Framework\Api\ImageContentFactory $imageContentFactory,
        \Magento\Catalog\Model\Product\Gallery\GalleryManagement $mediaGalleryManagement

    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->assetsModelFactory = $assetsModelFactory;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->productRepository = $productRepository;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->mediaGalleryEntryFactory = $mediaGalleryEntryFactory;
        $this->imageContentFactory = $imageContentFactory;
        $this->mediaGalleryManagement = $mediaGalleryManagement;
    }

    /**
     * Copy primary images action
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
                        'NOT EXISTS ?', new \Zend_Db_Expr("({$conditionSelect})")
                    );

                /** @var array $productsWithoutMedia */
                $productsWithoutMedia = $connection->fetchAll($select);

                $data['total'] = $totalProductsCount;
                $data['products'] = $productsWithoutMedia;
                $result['success'] = true;
            break;
            case 'copy_primary_images':
                $products = isset($postData['products']) ? $postData['products'] : [];

                $dataHelper = $this->getDataHelper();
                $productAssetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
                $productAssetsFolder = trim($productAssetsFolder);
                $productAssetsFolder = trim($productAssetsFolder, '/');
                if (empty($productAssetsFolder)) {
                    $data['error'] = 'Assets folder is not set!';
                    break;
                }

                $baseUrl = 'https://' . $dataHelper->getSirvDomain();

                $storeCode = 0;
                $this->storeManager->setCurrentStore($storeCode);

                /** @var \Magento\Framework\Filesystem\Directory\WriteInterface $mediaDirectory */
                $mediaDirectory = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                //NOTE: absolute path to pub/media folder
                $mediaDirAbsPath = rtrim($mediaDirectory->getAbsolutePath(), '\\/');
                if (!$mediaDirectory->isDirectory('tmp/sirv')) {
                    $mediaDirectory->create('tmp/sirv');
                }

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

                    $url = $baseUrl . '/' . $assetsFolder . '.view?info';
                    $contents = $this->downloadContents($url);
                    $contents = json_decode($contents);
                    $assets = is_object($contents) && isset($contents->assets) && is_array($contents->assets) ? $contents->assets : [];
                    if (empty($assets)) {
                        $product['message'] = 'Product has no assets on Sirv';
                        continue;
                    }

                    $url = false;
                    foreach ($assets as $asset) {
                        if ($asset->type == 'image') {
                            $url = $baseUrl . '/' . $assetsFolder . '/' . $asset->name;
                            $fileName = basename($asset->name);
                            $fileName = $this->getCorrectFileName($fileName);
                            $tmpAbsPath = $mediaDirAbsPath . '/tmp/sirv/' . $fileName;
                            break;
                        }
                    }
                    if (empty($url)) {
                        foreach ($assets as $asset) {
                            if ($asset->type == 'spin') {
                                $spinInfoUrl = $baseUrl . '/' . $assetsFolder . '/' . $asset->name . '?info';
                                $contents = $this->downloadContents($spinInfoUrl);
                                $contents = json_decode($contents);
                                $layer = is_object($contents) && isset($contents->layers) ? reset($contents->layers) : false;
                                $fileName = $layer ? reset($layer) : false;
                                if ($fileName) {
                                    $url = preg_replace('#/[^/]++$#', '/', $spinInfoUrl) . $fileName;
                                    $fileName = $this->getCorrectFileName($fileName);
                                    $tmpAbsPath = $mediaDirAbsPath . '/tmp/sirv/' . $fileName;
                                    break;
                                }
                            }
                        }
                        if (empty($url)) {
                            $product['message'] = 'Product has no image asset on Sirv';
                            continue;
                        }
                    }

                    if ($fileContents = file_get_contents($url)) {
                        $bytes = file_put_contents($tmpAbsPath, $fileContents);
                        if ($bytes) {
                            $productModel = $this->productRepository->get($product['sku']);
                            $this->galleryReadHandler->execute($productModel);
                            if (!$productModel->hasGalleryAttribute()) {
                                $product['message'] = 'Product has no gallery attribute';
                                unlink($tmpAbsPath);
                                continue;
                            }
                            $entry = $this->mediaGalleryEntryFactory->create();
                            $entry->setFile($fileName);
                            $entry->setMediaType('image');
                            $entry->setDisabled(false);
                            $entry->setLabel('');
                            $entry->setPosition(0);
                            $entry->setTypes(['image', 'small_image', 'thumbnail']);
                            $imageContent = $this->imageContentFactory->create();
                            $imageContent
                                ->setType(mime_content_type($tmpAbsPath))
                                ->setName($fileName)
                                ->setBase64EncodedData(base64_encode(file_get_contents($tmpAbsPath)));
                            $entry->setContent($imageContent);

                            $this->mediaGalleryManagement->create($product['sku'], $entry);

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
     * Download contents
     *
     * @param string $url
     * @return string
     */
    protected function downloadContents($url)
    {
        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]
        );

        $contents = curl_exec(self::$curlHandle);
        $error = curl_errno(self::$curlHandle);
        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);

        if ($error || $code != 200) {
            $contents = [
                'curl' => [
                    'code' => $code,
                    'error' => $error
                ]
            ];
            $contents = json_encode($contents);
        }

        return $contents;
    }
}
