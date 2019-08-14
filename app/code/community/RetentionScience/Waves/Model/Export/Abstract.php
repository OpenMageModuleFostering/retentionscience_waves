<?php

abstract class RetentionScience_Waves_Model_Export_Abstract {

    protected $_resource;

    protected $_readConnection;

    protected $_eavConfig;

    protected $_eavAttributes = array();

    protected $_exportedFields = array();

    protected $_data = array();

    protected $_start;

    protected $_limit = 100;

    protected $_totalRecords;

    protected $_processedRecords;

    protected $_entityIds;

    protected $_timestamp;

    protected $_bulkFile;

    protected $_store_id = 0;

    protected $_idsToProcess;

    CONST RECONNECT_DELAY = 10;

    CONST RECONNECT_LIMIT = 3;

    public function setIdsToProcess($ids) {
        $this->_idsToProcess = $ids;
    }

    protected function getResource() {
        if(is_null($this->_resource)) {
            $this->_resource = Mage::getSingleton('core/resource');
        }
        return $this->_resource;
    }

    protected function getReadConnection() {
        if(is_null($this->_readConnection)) {
            $this->_readConnection = $this->getResource()->getConnection('core_read');
        }
        return $this->_readConnection;
    }

    protected function fetchAll($query) {
        return $this->query('fetchAll', $query);
    }

    protected function fetchOne($query) {
        return $this->query('fetchOne', $query);
    }

    protected function query($method, $query, $reconnections = 1) {
        try {
            if($method == 'fetchAll') {
                return $this->getReadConnection()->fetchAll($query);
            } else {
                return $this->getReadConnection()->fetchOne($query);
            }
        } catch(Exception $e) {
            if($reconnections < self::RECONNECT_LIMIT) {
                trigger_error('Mysql Error: ' . $e->getMessage(), E_USER_NOTICE);
                sleep(self::RECONNECT_DELAY);
                $this->getReadConnection()->closeConnection();
                $this->getReadConnection()->getConnection();
                return $this->query($method, $query, ++ $reconnections);
            } else {
                throw $e;
            }
        }
    }

    protected function getTableName($entity) {
        return $this->getResource()->getTableName($entity);
    }

    public function getBulkFile() {
        if(is_null($this->_bulkFile)) {
            $this->_bulkFile = Mage::getBaseDir('tmp') . DIRECTORY_SEPARATOR . 'rs_bulk_' . $this->_bulkUploadFile . '_' . $this->_timestamp . '.bulk';
        }
        return $this->_bulkFile;
    }

    public function getExportModelTitle() {
        return $this->_bulkUploadFile;
    }

    public function getProcessedRecords() {
        return $this->_processedRecords;
    }

    public function getTotalRecordsCalculated() {
        return $this->_totalRecords;
    }

    protected function getEavConfig() {
        if(is_null($this->_eavConfig)) {
            $this->_eavConfig = Mage::getSingleton('eav/config');
        }
        return $this->_eavConfig;
    }

    protected function getEavAttribute($entity, $attribute) {
        if(! isset($this->_eavAttributes[$entity . '_' . $attribute])) {
            $this->_eavAttributes[$entity . '_' . $attribute] = $this->getEavConfig()->getAttribute($entity, $attribute);
        }
        return $this->_eavAttributes[$entity . '_' . $attribute];
    }

    protected function getAttributeData($entity, $attribute, $entityIdField = NULL, $useStore = TRUE) {
        $attribute = $this->getEavAttribute($entity, $attribute);
        if($attribute->getAttributeId()) {
            $backendTable = $attribute->getBackendTable();
            if(! empty($entityIdField)) {
                $_entityIds = array();
                $_mapIds = array();
                if(! empty($this->_data)) {
                    foreach($this->_data AS $record) {
                        if(! empty($record[$entityIdField])) {
                            $_entityIds[] = $record[$entityIdField];
                            $_mapIds[$record[$entityIdField]] = $record['entity_id'];
                        }
                    }
                    if(! empty($_entityIds)) {
                        if($useStore) {
                            $attributeData = $this->fetchAll(
                                '   SELECT
                                    `default_value`.`entity_id` AS `' . $entityIdField . '`,
                                    IF(`store_value`.`value` IS NULL, `default_value`.`value`, `store_value`.`value`) AS `value`
                                FROM
                                    `' . $backendTable . '` AS `default_value`
                                LEFT OUTER JOIN
                                    `' . $backendTable . '` AS `store_value`
                                    ON
                                        `store_value`.`entity_id` = `default_value`.`entity_id` AND
                                        `store_value`.`attribute_id` = `default_value`.`attribute_id` AND
                                        `store_value`.`store_id` = ' . $this->getStoreId() . '
                                WHERE
                                    `default_value`.`attribute_id` = ' . $attribute->getAttributeId() . ' AND
                                    `default_value`.`entity_id` IN (' . implode(', ', $_entityIds) . ') AND
                                    `default_value`.`store_id` = 0
                            ');
                        } else {
                            $attributeData = $this->fetchAll(
                                '   SELECT
                                    `default_value`.`entity_id` AS `' . $entityIdField . '`,
                                    `default_value`.`value`
                                FROM
                                    `' . $backendTable . '` AS `default_value`
                                WHERE
                                    `default_value`.`attribute_id` = ' . $attribute->getAttributeId() . ' AND
                                    `default_value`.`entity_id` IN (' . implode(', ', $_entityIds) . ')
                            ');
                        }
                        if(! empty($attributeData)) {
                            foreach($attributeData AS & $data) {
                                $dataAttributeId = $data[$entityIdField];
                                $entityId = $_mapIds[$dataAttributeId];
                                $data['entity_id'] = $entityId;
                            }
                        }
                        return $attributeData;
                    }
                }
            } else {
                if($useStore) {
                    return $this->fetchAll('   SELECT
                                    `default_value`.`entity_id`,
                                    IF(`store_value`.`value` IS NULL, `default_value`.`value`, `store_value`.`value`) AS `value`
                                FROM
                                    `' . $backendTable . '` AS `default_value`
                                LEFT OUTER JOIN
                                    `' . $backendTable . '` AS `store_value`
                                    ON
                                        `store_value`.`entity_id` = `default_value`.`entity_id` AND
                                        `store_value`.`attribute_id` = `default_value`.`attribute_id` AND
                                        `store_value`.`store_id` = ' . $this->getStoreId() . '
                                WHERE
                                    `default_value`.`attribute_id` = ' . $attribute->getAttributeId() . ' AND
                                    `default_value`.`entity_id` IN (' . implode(', ', $this->_entityIds) . ') AND
                                    `default_value`.`store_id` = 0
                            ');
                } else {
                    return $this->fetchAll('   SELECT
                                    `default_value`.`entity_id`,
                                    `default_value`.`value`
                                FROM
                                    `' . $backendTable . '` AS `default_value`
                                WHERE
                                    `default_value`.`attribute_id` = ' . $attribute->getAttributeId() . ' AND
                                    `default_value`.`entity_id` IN (' . implode(', ', $this->_entityIds) . ')
                            ');
                }
            }
        }
    }

    protected function fillAttributeData($entity, $attribute, $entityIdField = NULL, $getLabel = TRUE, $useStore = TRUE) {
        $data = $this->getAttributeData($entity, $attribute, $entityIdField, $useStore);
        if(! empty($data)) {
            foreach($data AS $record) {
                if($this->getEavAttribute($entity, $attribute)->getFrontendInput() === 'select' AND $getLabel) {
                    $this->_data[$record['entity_id']][$attribute] = $this
                        ->getEavAttribute($entity, $attribute)
                            ->getSource()
                                ->getOptionText($record['value']);
                } else {
                    $this->_data[$record['entity_id']][$attribute] = $record['value'];
                }
            }
        }
    }

    protected function fillTableData($tableName, $key, array $fields, $where = '') {
        $tableData = $this->fetchAll(
            'SELECT `' . $key . '` AS `entity_id`, `' . implode('`, `', array_keys($fields)) . '` FROM `' . $tableName . '`' .
            ' WHERE `' . $key . '` IN (' . implode(', ', $this->_entityIds) . ')' . ($where ? ' AND ' . $where : '')
        );
        if(! empty($tableData)) {
            foreach($tableData AS $data) {
                foreach($fields AS $key => $alias) {
                    $this->_data[$data['entity_id']][$alias] = $data[$key];
                }
            }
        }
    }

    public function run($timestamp) {        
        $time = time();
        $this->_timestamp = $timestamp;
        $this->_bulkFile = NULL;
        $this->_totalRecords = $this->getTotalRecords();
        $this->_start = 0;
        $this->_processedRecords = 0;
        if (Mage::helper('waves')->isEnabled()){
            Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("run called for file " . $this->getBulkFile() . " TotalRecords " . $this->_totalRecords);
        }
        
        while($this->_start < $this->_totalRecords) {

            $this->getEntityData();
            $this->getAdditionalData();
            $this->exportFields();
            $this->cleanupData();

            $this->_start += $this->_limit;
        }
        $this->_idsToProcess = NULL;
    }

    protected function exportFields() {
        if(! empty($this->_exportedFields) AND ! empty($this->_data)) {
            foreach($this->_data AS $data) {
                $record = array();
                foreach($this->_exportedFields AS $field) {
                    $record[$field] = $this->{'get' . str_replace('_', '', $field)}($data);
                }
                $this->writeBulk($this->getPrimaryKey($data), $record);
            }
        }
    }

    protected function writeBulk($key, $record) {
        // $record['record_id'] = $key;
        $msg = json_encode($record) . "\n";
        file_put_contents($this->getBulkFile(), $msg, FILE_APPEND);
    }

    protected function cleanupData() {
        $this->_data = NULL;
        gc_collect_cycles();
    }

    public function getStoreId() {
        return $this->_store_id;
    }

    public function setStoreId($storeId) {
        $this->_store_id = (int) $storeId;
        return $this;
    }

    abstract protected function getEntityData();

    abstract protected function getAdditionalData();

    abstract protected function getTotalRecords();

    abstract protected function getPrimaryKey($data);

}