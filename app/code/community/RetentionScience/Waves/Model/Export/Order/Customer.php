<?php

class RetentionScience_Waves_Model_Export_Order_Customer extends RetentionScience_Waves_Model_Export_Customer {

    protected $_limit = 1000;

    protected $_exportedFields = array(
        'record_id',
        'email',
        'full_name',
        'address1',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'birth_date',
        'gender',
        'account_created_on',
        'last_logon_at',
    );

    protected $_bulkUploadFile = 'users';

    protected $_exportedPks = array();

    public function setExportedPks($exportedPks) {
        $this->_exportedPks = $exportedPks;
    }

    protected function exportFields() {
        if(! empty($this->_exportedFields) AND ! empty($this->_data)) {
            foreach($this->_data AS $data) {
                $pk = $this->getPrimaryKey($data);
                if(isset($this->_exportedPks[$pk])) {
                    continue;
                }
                $record = array();
                foreach($this->_exportedFields AS $field) {
                    $record[$field] = $this->{'get' . str_replace('_', '', $field)}($data);
                }
                $this->writeBulk($pk, $record);
            }
        }
    }

    public function run($timestamp) {
        RetentionScience_Waves_Model_Export_Abstract::run($timestamp);
    }

    protected function getEntityData() {
        $tableName = $this->getTableName('sales/order');
        $query = 'SELECT `entity_id`, `customer_email` AS `email`, `customer_firstname` AS `firstname`, `customer_lastname` AS `lastname`, `created_at` FROM `' . $tableName . '` WHERE `customer_is_guest` = 1' . (empty($this->_idsToProcess) ? '' : ' AND `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . ' LIMIT ' . $this->_start . ', ' . $this->_limit;
        $this->_data = $this->getReadConnection()->fetchAll($query);
        $this->_processedRecords += count($this->_data);
        $this->_entityIds = array();
        if(! empty($this->_data)) {
            $sortedData = array();
            foreach($this->_data AS $record) {
                $this->_entityIds[] = $record['entity_id'];
                $sortedData[$record['entity_id']] = $record;
            }
            $this->_data = $sortedData;
        }
    }

    protected function getAdditionalData() {
        /* Address Data */
        $this->fillTableData($this->getTableName('sales/order_address'), 'parent_id', array(
            'street'                => 'street',
            'city'                  => 'city',
            'region_id'             => 'region_id',
            'postcode'              => 'postcode',
            'country_id'            => 'country_id',
            'telephone'             => 'telephone',
        ), '`address_type` = "billing"');
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->getReadConnection()
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('sales/order') . '` WHERE `customer_is_guest` = 1' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
    }

    protected function getLastLogOnAt($data) {
        return date("Y-m-d", Mage::getModel('core/date')->timestamp(strtotime($data['created_at'])));
    }

    protected function getPrimaryKey($data) {
        return md5(trim(strtolower($data['email'])));
    }

}