<?php

class RetentionScience_Waves_Model_Export_Product extends RetentionScience_Waves_Model_Export_Abstract {

    protected $_limit = 1000;

    protected $_exportedFields = array(
        'record_id',
        'name',
        'manufacturer',
        'model',
        'quantity',
        'price',
        'active',
        'image_list',
        'item_url',
        'parent_record_id',
        'attribute_1',
        'categories',
        'visibility',
    );

    protected $_bulkUploadFile = 'items';

    protected $_simpleActive = array();

    protected $_simpleProducts = array('simple', 'virtual', 'downloadable');

    protected $_compositeProducts = array('configurable', 'grouped', 'bundle');

    protected $_manageStock;

    protected $_mediaUrl;

    protected $_productModel;

    protected $_configurableLinks = array();

    protected $_groupedLinks = array();

    protected $_bundleLinks = array();

    protected $_configurableActive = array();

    protected $_groupedActive = array();

    protected $_bundleActive = array();

    protected function getEntityData() {
        $this->_manageStock = Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
        $this->_mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product';
        $this->_productModel = Mage::getModel('catalog/product');

        $tableName = $this->getTableName('catalog/product');
        $query = 'SELECT `entity_id`, `type_id`, `sku` FROM `' . $tableName . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . '
            ORDER BY FIELD(`type_id`, "simple", "virtual", "downloadable", "configurable", "grouped", "bundle")
            , `entity_id` ASC LIMIT ' . $this->_start . ', ' . $this->_limit;
        $this->_data = $this->fetchAll($query);
        $this->_processedRecords += count($this->_data);
        $this->_entityIds = array();
        if(! empty($this->_data)) {
            $sortedData = array();
            foreach($this->_data AS $record) {
                if(empty($record['entity_id'])) {
                    continue;
                }
                $this->_entityIds[] = $record['entity_id'];
                $sortedData[$record['entity_id']] = $record;
            }
            $this->_data = $sortedData;
        }
    }

    protected function getAdditionalData() {
        /* PRODUCT DATA */
        $this->fillAttributeData('catalog_product', 'name');
        $this->fillAttributeData('catalog_product', 'manufacturer');
        $this->fillAttributeData('catalog_product', 'price');
        $this->fillAttributeData('catalog_product', 'special_price');
        $this->fillAttributeData('catalog_product', 'image');
        $this->fillAttributeData('catalog_product', 'status', NULL, FALSE);
        $this->fillAttributeData('catalog_product', 'url_key');
        $this->fillAttributeData('catalog_product', 'visibility', NULL, FALSE);
        /* STOCK DATA */
        $this->fillTableData($this->getTableName('cataloginventory/stock_item'), 'product_id', array(
            'qty'                       => 'stock_qty',
            'use_config_manage_stock'   => 'stock_use_config_manage_stock',
            'manage_stock'              => 'stock_manage_stock',
            'is_in_stock'               => 'stock_is_in_stock',
        ));
        /* Media Gallery */
        $this->fillMediaGallery();
        /* Categories */
        $this->fillCategories();
        /* Configurable Links */
        $this->fillConfigurableLinks();
        /* Grouped Links */
        $this->fillGroupedLInks();
        /* Bundle links */
        $this->fillBundleLinks();
    }

    protected function fillBundleLinks() {
        if(empty($this->_entityIds)) {
            return;
        }
        $bundleTable = $this->getTableName('bundle/selection');
        $rows = $this->fetchAll('
            SELECT `parent_product_id` AS `parent_id`, `product_id` AS `entity_id` FROM `' . $bundleTable . '`
            WHERE `product_id` IN (' . implode(', ', $this->_entityIds) . ')
        ');
        if(! empty($rows)) {
            foreach($rows AS $row) {
                $entityId = $row['entity_id'];
                $parentId = $row['parent_id'];
                $this->_bundleLinks[$entityId] = $parentId;
            }
        }
    }

    protected function fillGroupedLInks() {
        if(empty($this->_entityIds)) {
            return;
        }
        $linkTable = $this->getTableName('catalog/product_link');
        $rows = $this->fetchAll('
            SELECT `linked_product_id` AS `entity_id`, `product_id` AS `parent_id` FROM `' . $linkTable . '`
            WHERE `linked_product_id` IN (' . implode(', ', $this->_entityIds) . ')
              AND `link_type_id` = ' . Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED . '
        ');
        if(! empty($rows)) {
            foreach($rows AS $row) {
                $entityId = $row['entity_id'];
                $parentId = $row['parent_id'];
                $this->_groupedLinks[$entityId] = $parentId;
            }
        }
    }

    protected function fillConfigurableLinks() {
        if(empty($this->_entityIds)) {
            return;
        }
        $configurableLinkTable = $this->getTableName('catalog/product_super_link');
        $rows = $this->fetchAll('
            SELECT `product_id` AS `entity_id`, `parent_id` FROM `' . $configurableLinkTable . '`
            WHERE `product_id` IN (' . implode(', ', $this->_entityIds) . ')
        ');
        if(! empty($rows)) {
            foreach($rows AS $row) {
                $entityId = $row['entity_id'];
                $parentId = $row['parent_id'];
                $this->_configurableLinks[$entityId] = $parentId;
            }
        }
    }

    protected function fillCategories() {
        if(empty($this->_entityIds)) {
            return;
        }
        $categoryProductTable = $this->getTableName('catalog/category_product');
        $rows = $this->fetchAll('
            SELECT `category_id`, `product_id` AS `entity_id` FROM `' . $categoryProductTable . '`
            WHERE `product_id` IN (' . implode(', ', $this->_entityIds) . ') ORDER BY `position` ASC
        ');
        if(! empty($rows)) {
            foreach($rows AS $row) {
                $entityId = $row['entity_id'];
                $categoryId = $row['category_id'];
                if(! isset($this->_data[$entityId]['categories'])) {
                    $this->_data[$entityId]['categories'] = array();
                }
                $this->_data[$entityId]['categories'][] = $categoryId;
            }
        }
    }

    protected function fillMediaGallery() {
        if(empty($this->_entityIds)) {
            return;
        }
        $mediaGalleryTable = $this->getTableName('catalog/product_attribute_media_gallery');
        $mediaGalleryValueTable = $this->getTableName('catalog/product_attribute_media_gallery_value');
        $query = 'SELECT mgt.`entity_id`, mgt.`value` FROM `' . $mediaGalleryTable . '` AS mgt '
            . 'INNER JOIN `' . $mediaGalleryValueTable . '` AS mgvt ON mgt.`value_id` = mgvt.`value_id` '
            . 'WHERE mgt.`entity_id` IN (' . implode(', ', $this->_entityIds) . ') AND mgvt.`store_id` = ' . $this->_store_id . ' AND mgvt.`disabled` = 0';
        $results = $this->fetchAll($query);
        if(! empty($results)) {
            foreach($results AS $row) {
                $entityId = $row['entity_id'];
                $image = $row['value'];
                if(! isset($this->_data[$entityId]['gallery'])) {
                    $this->_data[$entityId]['gallery'] = array();
                }
                $this->_data[$entityId]['gallery'][] = $image;
            }
        }
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('catalog/product') . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
    }

    /* Fields */

    protected function getPrimaryKey($data) {
        return $data['entity_id'];
    }
    
    protected function getRecordId($data) {
        return $this->getPrimaryKey($data);
    }

    protected function getName($data) {
        return isset($data['name']) ? $data['name'] : '';
    }

    protected function getManufacturer($data) {
        return isset($data['manufacturer']) ? $data['manufacturer'] : '';
    }

    protected function getModel($data) {
        return isset($data['sku']) ? $data['sku'] : '';
    }

    protected function getQuantity($data) {
        return isset($data['stock_qty']) ? $data['stock_qty'] : 0;
    }

    // @TODO: Ask Andrew about special price date range
    protected function getPrice($data) {
        return isset($data['special_price']) ? $data['special_price'] : (isset($data['price']) ? $data['price'] : '');
    }

    /**
     * Possible values:
     * 1 - hidden
     * 2 - visible in catalog
     * 3 - visible in search
     * 4 - visible in both: catalog and search
     */
    protected function getVisibility($data) {
        return $data['visibility'];
    }

    protected function getActive($data) {
        $active = 1;
        $entityId = $data['entity_id'];
        if(isset($data['status'])) {
            if($data['status'] != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                $active = 0;
            }
        } else {
            $active = 0;
        }
        if($active) {
            if(in_array($data['type_id'], $this->_compositeProducts)) {
                switch($data['type_id']) {
                    case 'configurable':
                        return isset($this->_configurableActive[$entityId]) ? $this->_configurableActive[$entityId] : 0;
                        break;
                    case 'grouped':
                        return isset($this->_groupedActive[$entityId]) ? $this->_groupedActive[$entityId] : 0;
                        break;
                    case 'bundle':
                        return isset($this->_bundleActive[$entityId]) ? $this->_bundleActive[$entityId] : 0;
                        break;
                }
                return $active;
            } else {
                // If no data - product is not salable
                if(! isset($data['stock_use_config_manage_stock'])) {
                    if($this->_manageStock) {
                        return 0;
                    } else {
                        return 1;
                    }
                }
                // From Mage_CatalogInventory_Model_Resource_Stock
                if(
                    ($data['stock_use_config_manage_stock'] == 0 AND $data['stock_manage_stock'] == 1 AND $data['stock_is_in_stock'] == 1)
                    OR
                    ($data['stock_use_config_manage_stock'] == 0 AND $data['stock_manage_stock'] == 0)
                    OR (
                        $this->_manageStock ? (
                            $data['stock_use_config_manage_stock'] == 1 AND $data['stock_is_in_stock'] == 1
                        ) : (
                            $data['stock_use_config_manage_stock'] == 1
                        )
                    )
                ) {
                    $active = 1;
                } else {
                    $active = 0;
                }
                $this->setIsSimpleActive($data['entity_id'], $active);
            }
        }
        return $active;
    }

    // space-delimited string of image URLs
    protected function getImageList($data) {
        $imageList = array();
        if(isset($data['image'])) {
            $imageList[] = $this->_mediaUrl . $data['image'];
        }
        if(! empty($data['gallery']) AND is_array($data['gallery'])) {
            foreach($data['gallery'] AS $image) {
                $imageList[] = $this->_mediaUrl . $image;
            }
        }
        return implode(' ', $imageList);
    }

    protected function getItemUrl($data) {
        Mage::getSingleton('core/url')->setStoreId($this->_store_id);
        Mage::unregister('custom_entry_point');
        Mage::register('custom_entry_point', TRUE);
        $this->_productModel->setData($data);
        $this->_productModel->setStoreId($this->getStoreId());
        return $this->_productModel->getProductUrl();
    }

    protected function getParentRecordId($data) {
        // In the future, we may choose to include grouped / bundle hierarchy
        $simpleParentIds = TRUE;
        $groupedParentIds = FALSE;
        $bundleParentIds = FALSE;
        
        $entityId = $data['entity_id'];
        $parentIds = array();
        
        if($simpleParentIds && isset($this->_configurableLinks[$entityId])) {
            $parentIds[] = $this->_configurableLinks[$entityId];
            $isActive = isset($this->_configurableActive[$this->_configurableLinks[$entityId]]) ? $this->_configurableActive[$this->_configurableLinks[$entityId]] : 0;
            $this->_configurableActive[$this->_configurableLinks[$entityId]] = $isActive ? 1 : $this->getIsSimpleActive($entityId);
        }
        if($groupedParentIds && isset($this->_groupedLinks[$entityId])) {
            $parentIds[] = $this->_groupedLinks[$entityId];
            $isActive = isset($this->_groupedActive[$this->_groupedLinks[$entityId]]) ? $this->_groupedActive[$this->_groupedLinks[$entityId]] : 0;
            $this->_groupedActive[$this->_groupedLinks[$entityId]] = $isActive ? 1 : $this->getIsSimpleActive($entityId);
        }
        if($bundleParentIds && isset($this->_bundleLinks[$entityId])) {
            $parentIds[] = $this->_bundleLinks[$entityId];
            $isActive = isset($this->_bundleActive[$this->_bundleLinks[$entityId]]) ? $this->_bundleActive[$this->_bundleLinks[$entityId]] : 0;
            $this->_bundleActive[$this->_bundleLinks[$entityId]] = $isActive ? 1 : $this->getIsSimpleActive($entityId);
        }
        return implode(',', $parentIds);
    }

    protected function getAttribute1($data) {
        return $data['type_id'];
    }

    // comma-delimited string of category ids
    protected function getCategories($data) {
        if(isset($data['categories'])) {
            return implode(',', $data['categories']);
        }
        return '';
    }

    protected function setIsSimpleActive($productId, $active) {
        $this->_simpleActive[$productId] = $active;
    }

    protected function getIsSimpleActive($productId) {
        if(isset($this->_simpleActive[$productId])) {
            return $this->_simpleActive[$productId];
        } else {
            return 0;
        }
    }

}