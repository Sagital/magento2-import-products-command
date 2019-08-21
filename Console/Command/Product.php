<?php


namespace Sagital\ProductImporter\Console\Command;

class Product extends \Magento\CatalogImportExport\Model\Import\Product
{
    private $bunch;
    private $imagesToRemove;

    protected function getExistingImages($bunch)
    {
        $this->bunch = $bunch;

        return parent::getExistingImages($bunch);
    }

    public function addImageHashes(&$imagesBySku)
    {
        parent::addImageHashes($imagesBySku);

        // get existing images (from db)
        $existingImages = [];
        foreach ($imagesBySku as $sku => $images) {
            foreach ($images as $path => $imageInfo) {
                if (!isset($existingImages[$imageInfo['hash']])) {
                    $existingImages[$imageInfo['hash']] = [];
                }

                $existingImages[$imageInfo['hash']][] = [
                    'value_id' => $imageInfo['value_id'],
                    'path' => $path
                ];
            }
        }

        // get imported images (from file)
        $importDir = $this->_mediaDirectory->getAbsolutePath($this->getImportDir());

        $importedImages = [];
        foreach ($this->bunch as $rowData) {
            foreach ($this->getImagesFromRow($rowData)[0] as $imagesFromRow) {
                $imageNames = explode($this->getMultipleValueSeparator(), $imagesFromRow[0]);

                $imageHashes = array_flip(array_map(function ($imageName) use ($importDir) {
                    $filename = $importDir . DIRECTORY_SEPARATOR . $imageName;

                    return $this->_mediaDirectory->isReadable($filename) ? md5_file($filename) : '';
                }, $imageNames));

                $importedImages = array_merge($importedImages, $imageHashes);
            }
        }

        // guess images to remove
        $this->imagesToRemove = array_diff_key($existingImages, $importedImages);
    }

    protected function _saveMediaGallery(array $mediaGalleryData)
    {
        // remove duplicate images
        $valueIds = [];
        if (!empty($this->imagesToRemove)) {
            // from disk
            foreach ($this->imagesToRemove as $imagesToRemove) {
                foreach ($imagesToRemove as $imageToRemove) {
                    $imagePath = 'pub/media/catalog/product' . $imageToRemove['path'];

                    if ($this->_mediaDirectory->isExist($imagePath)) {
                        $this->_mediaDirectory->delete($imagePath);
                    }

                    $valueIds[] = $imageToRemove['value_id'];
                }
            }

            // from database
            $this->getConnection()->delete(
                $this->getConnection()->getTableName('catalog_product_entity_media_gallery'),
                $this->getConnection()->quoteInto('value_id IN (?)', $valueIds)
            );
        }

        return parent::_saveMediaGallery($mediaGalleryData);
    }
}
