<?php
/**
 * Recolize GmbH
 *
 * @section LICENSE
 * This source file is subject to the GNU General Public License Version 3 (GPLv3).
 *
 * @category Recolize
 * @package Recolize_RecommendationEngine
 * @author Recolize GmbH <service@recolize.com>
 * @copyright 2015 Recolize GmbH (http://www.recolize.com)
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License Version 3 (GPLv3).
 */
class Recolize_RecommendationEngine_Model_Convert_Mapper_Column extends Mage_Dataflow_Model_Convert_Mapper_Abstract
{
    /**
     * Dataflow batch model
     *
     * @var Mage_Dataflow_Model_Batch
     */
    protected $_batch;

    /**
     * Dataflow batch export model
     *
     * @var Mage_Dataflow_Model_Batch_Export
     */
    protected $_batchExport;

    /**
     * Dataflow batch import model
     *
     * @var Mage_Dataflow_Model_Batch_Import
     */
    protected $_batchImport;

    /**
     * The image attribute name.
     *
     * @var string
     */
    protected $_imageAttribute = 'image';

    /**
     * The name of the category ids attribute.
     *
     * @var string
     */
    protected $_categoryIdsAttribute = 'category_ids';

    /**
     * The name of the price attribute.
     *
     * @var string
     */
    protected $_priceAttribute = 'price';

    /**
     * Retrieve Batch model singleton
     *
     * @return Mage_Dataflow_Model_Batch
     */
    public function getBatchModel()
    {
        if (empty($this->_batch) === true) {
            $this->_batch = Mage::getSingleton('dataflow/batch');
        }

        return $this->_batch;
    }

    /**
     * Retrieve Batch export model
     *
     * @return Mage_Dataflow_Model_Batch_Export
     */
    public function getBatchExportModel()
    {
        if (empty($this->_batchExport) === true) {
            $object = Mage::getModel('dataflow/batch_export');
            $this->_batchExport = Varien_Object_Cache::singleton()->save($object);
        }

        return Varien_Object_Cache::singleton()->load($this->_batchExport);
    }

    /**
     * This method does some transformations of certain fields, e.g.
     * - add full URLs for product images
     * - replace category ids with category names
     *
     * @see Mage_Dataflow_Model_Convert_Mapper_Column::map()
     *
     * @return Recolize_RecommendationEngine_Model_Convert_Mapper_Column
     */
    public function map()
    {
        $batchModel = $this->getBatchModel();
        $batchExport = $this->getBatchExportModel();

        $batchExportIds = $batchExport
            ->setBatchId($this->getBatchModel()->getId())
            ->getIdCollection();

        foreach ($batchExportIds as $batchExportId) {
            $batchExport->load($batchExportId);

            $row = $batchExport->getBatchData();
            // Apply attribute specific transformations
            foreach ($row as $attributeName => $attributeValue) {
                if (empty($attributeValue) === true) {
                    continue;
                }

                // Generate smaller image and add full URL to export.
                if ($attributeName === $this->_imageAttribute) {
                    $row[$this->_imageAttribute] = (string) Mage::helper('catalog/image')->init(Mage::getSingleton('catalog/product'), $attributeName, $attributeValue)
                        ->constrainOnly(true)
                        ->keepAspectRatio(true)
                        ->keepFrame(false)
                        ->resize(500);
                }

                // Add category names instead of ids.
                if ($attributeName === $this->_categoryIdsAttribute) {
                    $categoryNames = array();
                    $categoryIds = explode(',', $attributeValue);
                    foreach ($categoryIds as $categoryId) {
                        /** @var Mage_Catalog_Model_Category $category */
                        $category = Mage::getModel('catalog/category')->load($categoryId);
                        if (empty($category) === false) {
                            $categoryNames[] = $category->getName();
                        }
                    }

                    $row[$attributeName] = implode(', ', $categoryNames);
                }

                // Always export prices with tax.
                if ($attributeName === $this->_priceAttribute) {
                    $product = Mage::getModel('catalog/product')->load($row['entity_id']);
                    $row[$attributeName] = Mage::helper('tax')->getPrice($product, $attributeValue);
                }
            }

            $batchExport->setBatchData($row)
                ->setStatus(2)
                ->save();

            $batchModel->parseFieldList($batchExport->getBatchData());
        }

        return $this;
    }
}