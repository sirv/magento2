<?php

namespace Sirv\Magento2\Observer;

/**
 * Observer that syncs the product gallery after saving or deleting a product
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class SyncProductGallery implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync\Backend
     */
    protected $syncHelper = null;

    /**
     * Resource model
     *
     * @var \Magento\Catalog\Model\ResourceModel\Product\Gallery
     */
    protected $resourceModel = null;

    /**
     * Product images
     *
     * @var array
     */
    protected $productImages = [];

    /**
     * Constructor
     *
     * @param \Sirv\Magento2\Helper\Data\Backend $dataHelper
     * @param \Sirv\Magento2\Helper\Sync\Backend $syncHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\Gallery $resourceModel
     * @return void
     */
    public function __construct(
        \Sirv\Magento2\Helper\Data\Backend $dataHelper,
        \Sirv\Magento2\Helper\Sync\Backend $syncHelper,
        \Magento\Catalog\Model\ResourceModel\Product\Gallery $resourceModel
    ) {
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
        $this->syncHelper = $syncHelper;
        $this->resourceModel = $resourceModel;
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->isSirvEnabled) {
            return;
        }

        $event = $observer->getEvent();
        $product = $event->getProduct();
        if (!is_object($product)) {
            return;
        }

        $eventName = $event->getName();
        $productId = $product->getId();

        $connection = $this->resourceModel->getConnection();
        $select = clone $connection->select();
        $mediaTable = $this->resourceModel->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        $mediaToEntityTable = $this->resourceModel->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_VALUE_TO_ENTITY_TABLE);

        switch ($eventName) {
            case 'catalog_product_delete_before':
                try {
                    $select->reset()
                        ->distinct()
                        ->from(
                            ['mt' => $mediaTable],
                            ['unique_value' => 'BINARY(`mt`.`value`)']
                        )
                        ->joinInner(
                            ['mtet' => $mediaToEntityTable],
                            '`mt`.`value_id` = `mtet`.`value_id`',
                            [/*'entity_id'*/]
                        )
                        ->where('`mt`.`value` IS NOT NULL')
                        ->where('`mt`.`value` != ?', '')
                        ->where('`mtet`.`entity_id` = ?', $productId);

                    $this->productImages = $connection->fetchCol($select) ?: [];
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                }
                break;
            case 'catalog_product_delete_after':
                if (empty($this->productImages)) {
                    break;
                }
                try {
                    $select->reset()
                        ->from(
                            ['mt' => $mediaTable],
                            ['mt.value']
                        )
                        ->joinInner(
                            ['mtet' => $mediaToEntityTable],
                            '`mt`.`value_id` = `mtet`.`value_id`',
                            []
                        )
                        ->where('`mt`.`value` IN (?)', $this->productImages);

                    $linkedImages = $connection->fetchCol($select) ?: [];
                    $linkedImages = array_unique($linkedImages);
                    $linkedImages = array_flip($linkedImages);
                    $productMediaRelPath = $this->syncHelper->getProductMediaRelPath();
                    foreach ($this->productImages as $image) {
                        if (!isset($linkedImages[$image])) {
                            $relPath = $productMediaRelPath . '/' . ltrim($image, '\\/');
                            $this->syncHelper->remove($relPath);
                        }
                    }
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                }
                break;
            case 'catalog_product_save_after':
                $mediaGallery = $product->getData('media_gallery');
                if (!is_array($mediaGallery) ||
                    !isset($mediaGallery['images']) ||
                    !is_array($mediaGallery['images'])
                ) {
                    break;
                }

                $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
                $productMediaRelPath = $this->syncHelper->getProductMediaRelPath();
                foreach ($mediaGallery['images'] as &$image) {
                    $relPath = $productMediaRelPath . '/' . ltrim($image['file'], '\\/');
                    $absPath = $mediaDirAbsPath . $relPath;
                    if (empty($image['removed'])) {
                        if (!$this->syncHelper->isSynced($relPath)) {
                            $this->syncHelper->save(
                                $absPath,
                                \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH,
                                true
                            );
                        }
                    } else {
                        $select->reset()
                            ->from([$this->resourceModel->getMainTableAlias() => $this->resourceModel->getMainTable()])
                            ->where('value = ?', $image['file'])
                            ->limit(1);

                        if (!$connection->fetchOne($select)) {
                            $this->syncHelper->remove($relPath);
                        }
                    }
                }
                break;
        }
    }
}
