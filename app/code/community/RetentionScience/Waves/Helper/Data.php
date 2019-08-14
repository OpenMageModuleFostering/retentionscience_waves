<?php

class RetentionScience_Waves_Helper_Data extends Mage_Core_Helper_Abstract
{

    CONST WAVES_SETTINGS_ENABLED = 'waves/retentionscience_settings/enable';

    CONST WAVES_SETTINGS_API_USER = 'waves/retentionscience_settings/api_user';

    CONST WAVES_SETTINGS_API_PASSWORD = 'waves/retentionscience_settings/api_pass';

    CONST WAVES_SETTINGS_SITE_ID = 'waves/retentionscience_settings/site_id';

    CONST WAVES_SETTINGS_TEST_MODE = 'waves/retentionscience_settings/testmode';

    CONST WAVES_SETTINGS_AJAX_CART_ENABLED = 'waves/retentionscience_settings/ajaxaddtocartenable';

    CONST WAVES_SETTINGS_STORE_ID = 'waves/retentionscience_settings/store_id';

    CONST WAVES_SETTINGS_BULK_COMPRESSION_ENABLED = 'waves/rs_sync_advanced/rs_use_bulk_compression';

    CONST WAVES_SETTINGS_AWS_ACCESS_KEY_ID = 'waves/rs_log_settings/aws_access_key_id';

    CONST WAVES_SETTINGS_AWS_SECRET_ACCESS_KEY = 'waves/rs_log_settings/aws_secret_access_key';

    CONST WAVES_SETTINGS_AWS_LOG_STREAM = 'waves/rs_log_settings/aws_log_stream';

    CONST WAVES_SETTINGS_AWS_LOG_GROUP = 'waves/rs_log_settings/aws_log_group';

    CONST WAVES_SETTINGS_AWS_SESSION_TOKEN = 'waves/rs_log_settings/aws_session_token';

    CONST WAVES_CACHE_KEY_RSCOREDATA = 'waves_rscoredata';

    CONST WAVES_CACHE_LIFETIME = 600;

    protected $_regionNames;

    protected $_AWS_ACCESS_KEY_ID;

    protected $_AWS_SECRET_ACCESS_KEY;

    protected $_AWS_SESSION_TOKEN;

    protected $_AWS_LOG_GROUP;

    protected $_AWS_LOG_STREAM;
    
    protected $_RS_SITE_ID;

    public function getRegionNameById($regionId) {
        if(is_null($this->_regionNames)) {
            $collection = Mage::getModel('directory/region')->getCollection();
            foreach($collection AS $region) {
                $this->_regionNames[$region->getId()] = $region->getName();
            }
        }
        return isset($this->_regionNames[$regionId]) ? $this->_regionNames[$regionId] : '';
    }

    public function isEnabled() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_ENABLED);
    }

    public function disable() {
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_ENABLED, 0);
        $this->setSiteId('');
        Mage::getConfig()->cleanCache();

        return $this;
    }

    public function getApiUser() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_API_USER);
    }

    public function getApiPassword() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_API_PASSWORD);
    }

    public function getSiteId() {
        if(! is_null($this->_RS_SITE_ID)) {
            return $this->_RS_SITE_ID;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_SITE_ID);
    }

    public function isTestMode() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_TEST_MODE);
    }

    public function isAjaxCartEnabled() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AJAX_CART_ENABLED);
    }

    public function getStoreId() {
        return Mage::getStoreConfig(self::WAVES_SETTINGS_STORE_ID);
    }

    public function getWebsiteId() {
        static $websiteId;
        if(is_null($websiteId)) {
            $store = Mage::getModel('core/store')->load($this->getStoreId());
            $websiteId = $store->getWebsiteId();
        }
        return $websiteId;
    }

    public function isBulkCompressionEnabled() {
        $enabled = (bool) Mage::getStoreConfig(self::WAVES_SETTINGS_BULK_COMPRESSION_ENABLED);
        if($enabled) {
            if(! function_exists('gzopen') OR ! function_exists('gzwrite') OR ! function_exists('gzclose')) {
                $enabled = FALSE;
            }
        }
        return $enabled;
    }

    public function getAWSAccessKeyId() {
        if(! is_null($this->_AWS_ACCESS_KEY_ID)) {
            return $this->_AWS_ACCESS_KEY_ID;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AWS_ACCESS_KEY_ID);
    }

    public function getAWSSecretAccessKey() {
        if(! is_null($this->_AWS_SECRET_ACCESS_KEY)) {
            return $this->_AWS_SECRET_ACCESS_KEY;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AWS_SECRET_ACCESS_KEY);
    }

    public function getAWSLogStream() {
        if(! is_null($this->_AWS_LOG_STREAM)) {
            return $this->_AWS_LOG_STREAM;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AWS_LOG_STREAM);
    }

    public function getAWSLogGroup() {
        if(! is_null($this->_AWS_LOG_GROUP)) {
            return $this->_AWS_LOG_GROUP;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AWS_LOG_GROUP);
    }

    public function getAWSSessionToken() {
        if(! is_null($this->_AWS_SESSION_TOKEN)) {
            return $this->_AWS_SESSION_TOKEN;
        }
        return Mage::getStoreConfig(self::WAVES_SETTINGS_AWS_SESSION_TOKEN);
    }

    public function setAWSSessionToken($value) {
        $this->_AWS_SESSION_TOKEN = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_AWS_SESSION_TOKEN, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }

    public function setAWSAccessKeyId($value) {
        $this->_AWS_ACCESS_KEY_ID = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_AWS_ACCESS_KEY_ID, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }

    public function setAWSSecretAccessKey($value) {
        $this->_AWS_SECRET_ACCESS_KEY = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_AWS_SECRET_ACCESS_KEY, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }

    public function setAWSLogStream($value) {
        $this->_AWS_LOG_STREAM = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_AWS_LOG_STREAM, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }

    public function setAWSLogGroup($value) {
        $this->_AWS_LOG_GROUP = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_AWS_LOG_GROUP, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }
    
    public function setSiteId($value) {
        $this->_RS_SITE_ID = $value;
        Mage::getConfig()->saveConfig(self::WAVES_SETTINGS_SITE_ID, $value);
        Mage::getConfig()->cleanCache();
        return $this;
    }

    public function getRScore($forceLoad = TRUE) {
        $cache = Mage::app()->getCache();
        if(! ($rscore = $cache->load(self::WAVES_CACHE_KEY_RSCOREDATA)) AND $forceLoad) {
            try {
                $rscore_data = Mage::getModel('waves/source_rscoredata')->getDataArray();
                $api = $this->getApi();
                $rscore_json = $api->calculate_rscore($rscore_data);
                $rscore = Zend_Json::decode($rscore_json);
                if($rscore) {
                    $cache->save(Zend_Json::encode($rscore), self::WAVES_CACHE_KEY_RSCOREDATA, array(), self::WAVES_CACHE_LIFETIME);
                }
            } catch(Exception $e) {
                // Do nothing
            }
        } else {
            $rscore = Zend_Json::decode($rscore);
        }
        return $rscore;
    }

    /**
     * @return RetentionScience_Waves_Model_Connection_RetentionScienceApi
     */
    public function getApi() {
        static $api;
        if(is_null($api)) {
            $api = Mage::getModel('waves/connection_retentionScienceApi', array(
                'username' => Mage::helper('waves')->getApiUser(),
                'password' => Mage::helper('waves')->getApiPassword(),
                'testmode' => Mage::helper('waves')->isTestMode(),
            ));
        }
        return $api;
    }

    public function updateAWSCredentials() {
        try {
            $aws_credentials = $this->getApi()->get_aws_credentials();
            $aws_credentials = Zend_Json::decode($aws_credentials);
            if(isset($aws_credentials) AND is_array($aws_credentials) AND isset($aws_credentials['status']) AND $aws_credentials['status'] === 'success') {
                Mage::helper('waves')->setAWSAccessKeyId($aws_credentials['access_key_id']);
                Mage::helper('waves')->setAWSSecretAccessKey($aws_credentials['secret_access_key']);
                Mage::helper('waves')->setAWSLogStream($aws_credentials['log_stream']);
                Mage::helper('waves')->setAWSLogGroup($aws_credentials['log_group']);
                Mage::helper('waves')->setAWSSessionToken($aws_credentials['session_token']);
            }
            return TRUE;
        } catch(Exception $e) {
            return FALSE;
        }
    }

}
