<?php

class RetentionScience_Waves_Model_Export_Order extends RetentionScience_Waves_Model_Export_Abstract {

    protected $_limit = 1000;

    protected $_exportedFields = array(
        'record_id',
        'user_record_id',
        'total_price',
        'discount_amount',
        'shipping_amount',
        'tax_amount',
        'ordered_at',
        'payment_method',
        'order_status',
    );

    protected $_bulkUploadFile = 'orders';

    protected function getEntityData() {
        $tableName = $this->getTableName('sales/order');
        $query = 'SELECT `entity_id`, `state`, `customer_email`, `base_subtotal`, `base_discount_amount`, `shipping_amount`, `base_tax_amount`, `created_at` FROM `' . $tableName . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . ' ORDER BY `entity_id` ASC LIMIT ' . $this->_start . ', ' . $this->_limit;
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
        /* Get Payment Method Code */
        $this->fillTableData($this->getTableName('sales/order_payment'), 'parent_id', array(
            'method'                => 'payment_method',
        ));
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('sales/order') . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
    }

    /* Fields */

    protected function getPrimaryKey($data) {
        return $data['entity_id'];
    }
    
    protected function getRecordId($data) {
        return $this->getPrimaryKey($data);
    }

    protected function getUserRecordId($data) {
        return md5(trim(strtolower($data['customer_email'])));
    }

    protected function getTotalPrice($data) {
        return number_format($data['base_subtotal'], 2, '.', '');
    }

    protected function getDiscountAmount($data) {
        return number_format($data['base_discount_amount'], 2, '.', '');
    }

    protected function getShippingAmount($data) {
        return number_format($data['shipping_amount'], 2, '.', '');
    }

    protected function getTaxAmount($data) {
        return number_format($data['base_tax_amount'], 2, '.', '');
    }

	protected function getOrderedAt($data) {
		return date("Y-m-d H:i:s", strtotime($data['created_at']));
	}

    protected function getPaymentMethod($data) {
        if(isset($data['payment_method'])) {
            $instance = Mage::helper('payment')->getMethodInstance($data['payment_method']);
            if($instance) {
                return $instance->getTitle();
            }
        }
    }

    protected function getOrderStatus($data) {
        if(isset($data['state'])) {
            return $data['state'];
        } else {
            return '';
        }
    }

}