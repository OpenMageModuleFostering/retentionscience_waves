<?php

class RetentionScience_Waves_Model_Export_Order_Items extends RetentionScience_Waves_Model_Export_Customer {

    protected $_limit = 1000;

    protected $_exportedFields = array(
        'order_record_id',
        'item_record_id',
        'name',
        'quantity',
        'price',
        'final_price',
        'attribute_1',
        'categories',
    );

    protected $_bulkUploadFile = 'order_items';

    protected function getEntityData() {
        $tableName = $this->getTableName('sales/order_item');
        $query = 'SELECT `item_id` AS `entity_id`, `order_id` AS `order_record_id`, `product_id`, `name`, `qty_ordered`, `price`, `sku` FROM `' . $tableName . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . ' ORDER BY `item_id` ASC LIMIT ' . $this->_start . ', ' . $this->_limit;
        $this->_data = $this->getReadConnection()->fetchAll($query);
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
        $this->fillProductCategories();
    }

    protected function fillProductCategories() {
        if(empty($this->_entityIds)) {
            return;
        }
        // Add categories to products
        $products = array();
        $productIds = array();
        if(! empty($this->_data)) {
            foreach($this->_data AS $row) {
                $productId = $row['product_id'];
                if(! in_array($productId, $productIds)) {
                    $productIds[] = $productId;
                }
                if(! isset($products[$productId])) {
                    $products[$productId] = array();
                }
            }
        }
        if(! empty($productIds)) {
            $categoryProductTable = $this->getTableName('catalog/category_product');
            $cats = $this->getReadConnection()->fetchAll('
                SELECT `category_id`, `product_id` AS `entity_id` FROM `' . $categoryProductTable . '`
                WHERE `product_id` IN (' . implode(', ', $productIds) . ') ORDER BY `position` ASC
            ');
            if(! empty($cats)) {
                foreach($cats AS $cat) {
                    $entityId = $cat['entity_id'];
                    $categoryId = $cat['category_id'];
                    $products[$entityId][] = $categoryId;
                }
            }
        }
        // Fill data
        if(! empty($this->_data)) {
            foreach($this->_data AS $row) {
                $entityId = $row['entity_id'];
                $productId = $row['product_id'];
                if(isset($products[$productId])) {
                    $this->_data[$entityId]['categories'] = $products[$productId];
                }
            }
        }
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->getReadConnection()
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('sales/order_item') . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
    }

    /* Fields */

    protected function getPrimaryKey($data) {
        return $data['entity_id'];
    }

    protected function getOrderRecordId($data) {
        return $data['order_record_id'];
    }

    protected function getItemRecordId($data) {
        return $this->getPrimaryKey($data);
    }

    protected function getName($data) {
        return $data['name'];
    }

    protected function getQuantity($data) {
        return (int) $data['qty_ordered'];
    }

    protected function getPrice($data) {
        return number_format($data['price'], 2, '.', '');
    }

    protected function getFinalPrice($data) {
        return number_format($data['price'] * $data['qty_ordered'], 2, '.', '');
    }

    protected function getAttribute1($data) {
        return $data['sku'];
    }

    protected function getCategories($data) {
        if(isset($data['categories'])) {
            return implode(',', $data['categories']);
        } else {
            return '';
        }
    }

}