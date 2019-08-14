<?php

class RetentionScience_Waves_Model_Export_Category extends RetentionScience_Waves_Model_Export_Abstract {

    protected $_limit = 1000;

    protected $_exportedFields = array(
        'record_id',
        'name',
        'description',
        'parent_record_id',
    );

    protected $_bulkUploadFile = 'categories';

    protected function getEntityData() {
        $tableName = $this->getTableName('catalog/category');
        $query = 'SELECT `entity_id`, `parent_id` FROM `' . $tableName . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . ' ORDER BY `level` ASC LIMIT ' . $this->_start . ', ' . $this->_limit;
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
        /* CATEGORY DATA */
        $this->fillAttributeData('catalog_category', 'name');
        $this->fillAttributeData('catalog_category', 'description');
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->getReadConnection()
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('catalog/category') . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
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

    protected function getDescription($data) {
        return isset($data['description']) ? $data['description'] : '';
    }

    protected function getParentRecordId($data) {
        return isset($data['parent_id']) ? $data['parent_id'] : NULL;
    }

}