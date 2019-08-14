<?php

class RetentionScience_Waves_Model_Export_Customer extends RetentionScience_Waves_Model_Export_Abstract {

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

    protected $_exportedIds = array();

    public function run($timestamp) {
        parent::run($timestamp);
        $exportModel = Mage::getModel('waves/export_order_customer');
        $exportModel->setStoreId($this->getStoreId());
        $exportModel->setExportedPks($this->_exportedIds);
        $exportModel->run($timestamp);
    }

    protected function getEntityData() {
        $tableName = $this->getTableName('customer/entity');
        $query = 'SELECT `entity_id`, `email`, `created_at`, `updated_at` FROM `' . $tableName . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')') . ' LIMIT ' . $this->_start . ', ' . $this->_limit;
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
        /* CUSTOMER DATA */
        $this->fillAttributeData('customer', 'firstname', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'lastname', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'prefix', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'middlename', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'suffix', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'default_billing', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'dob', NULL, TRUE, FALSE);
        $this->fillAttributeData('customer', 'gender', NULL, TRUE, FALSE);
        /* ADDRESS DATA */
        $this->fillAttributeData('customer_address', 'street', 'default_billing', TRUE, FALSE);
        $this->fillAttributeData('customer_address', 'city', 'default_billing', TRUE, FALSE);
        $this->fillAttributeData('customer_address', 'region_id', 'default_billing', TRUE, FALSE);
        $this->fillAttributeData('customer_address', 'postcode', 'default_billing', TRUE, FALSE);
        $this->fillAttributeData('customer_address', 'country_id', 'default_billing', TRUE, FALSE);
        $this->fillAttributeData('customer_address', 'telephone', 'default_billing', TRUE, FALSE);
    }

    protected function getTotalRecords() {
        return (int) $this
                        ->fetchOne('SELECT COUNT(*) FROM `' . $this->getTableName('customer/entity') . '`' . (empty($this->_idsToProcess) ? '' : ' WHERE `entity_id` IN (' . implode(', ', $this->_idsToProcess) . ')'));
    }

    /* Fields */

    protected function getPrimaryKey($data) {
        $pk = md5(trim(strtolower($data['email'])));
        $this->_exportedIds[$pk] = TRUE;
        return $pk;
    }
    
    protected function getRecordId($data) {
        return $this->getPrimaryKey($data);
    }

    protected function getEmail($data) {
        return isset($data['email']) ? $data['email'] : '';
    }

    protected function getFullname($data) {
        $name = '';
        if ($this->getEavAttribute('customer', 'prefix')->getIsVisible() && isset($data['prefix'])) {
            $name .= $data['prefix'] . ' ';
        }
        $name .= isset($data['firstname']) ? $data['firstname'] : '';
        if ($this->getEavAttribute('customer', 'middlename')->getIsVisible() && isset($data['middlename'])) {
            $name .= ' ' . $data['middlename'];
        }
        $name .=  isset($data['lastname']) ? ' ' . $data['lastname'] : '';
        if ($this->getEavAttribute('customer', 'suffix')->getIsVisible() && isset($data['suffix'])) {
            $name .= ' ' . $data['suffix'];
        }
        return $name;
    }

    protected function getAddress1($data) {
        return isset($data['street']) ? str_replace("\n", ' ', $data['street']) : '';
    }

    protected function getCity($data) {
        return isset($data['city']) ? $data['city'] : '';
    }

    protected function getState($data) {
        return isset($data['region_id']) ? Mage::helper('waves')->getRegionNameById($data['region_id']) : '';
    }

    protected function getZip($data) {
        return isset($data['postcode']) ? $data['postcode'] : '';
    }

    protected function getCountry($data) {
        if(isset($data['country_id'])) {
            // shorten to US
            if ($data['country_id'] == 'United States') {
                return 'US';
            } elseif ($data['country_id'] == 'United Kingdom') {
                return 'UK';
            } else {
                return $data['country_id'];     
            }
         
        }
        return '';
    }
    
    protected function getPhone($data) {
        return isset($data['telephone']) ? $data['telephone'] : '';
    }

    // @return yyyy-mm-dd
    protected function getBirthdate($data) {
        if(isset($data['dob'])) {
            $birthDateTime = explode(' ', $data['dob']);
            return $birthDateTime[0];
        }
        return '';
    }

    protected function getGender($data) {
        if (isset($data['gender'])) {
            $gender = strtolower($data['gender']);
             if ($gender == 'female') {
                 return 'f';
             } elseif ($gender == 'male') {
                 return 'm';
             } else {
                 return $gender;
             }
        }
        return '';
    }

    protected function getAccountCreatedOn($data) {
        return date("Y-m-d H:i:s", strtotime($data['created_at']));
    }

    protected function getLastLogOnAt($data) {
        return date("Y-m-d H:i:s", strtotime($data['updated_at']));
    }

}