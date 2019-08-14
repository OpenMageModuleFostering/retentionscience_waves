<?php

class RetentionScience_Waves_Model_Source_Credentials extends Mage_Core_Model_Config_Data {

    protected function _afterSave()
    {
        static $_credentials = array();
        $_credentials[$this->getPath()] = $this->getValue();
        if(count($_credentials) == 3) {
            // Updating AWS credentials
            $api = Mage::getModel('waves/connection_retentionScienceApi', array(
                'username' => $_credentials[RetentionScience_Waves_Helper_Data::WAVES_SETTINGS_API_USER],
                'password' => $_credentials[RetentionScience_Waves_Helper_Data::WAVES_SETTINGS_API_PASSWORD],
                'testmode' => (bool) $_credentials[RetentionScience_Waves_Helper_Data::WAVES_SETTINGS_TEST_MODE]
            ));
            try {
                $aws_credentials = $api->get_aws_credentials();
                $aws_credentials = Zend_Json::decode($aws_credentials);
                $site_id = $api->get_site_id();
                $site_id = Zend_Json::decode($site_id);
                
                $valid_aws_credentials = (isset($aws_credentials) AND is_array($aws_credentials) AND isset($aws_credentials['status']) AND $aws_credentials['status'] === 'success');
                $valid_site_id = (isset($site_id) AND is_array($site_id) AND isset($site_id['status']) AND $site_id['status'] === 'success');
            
                if(! $valid_aws_credentials) {
                    throw new Exception('Error 8a with API call. Please check credentials and try again.');
                }
                
                if(! $valid_site_id) {
                    throw new Exception('Error 8b with API call. Please check credentials and try again.');
                }
                       
            } catch(Exception $e) {
                Mage::helper('waves')->disable();
                Mage::getSingleton('core/session')->addError('Unable to connect to Retention Science API. Module is disabled. ' . $e->getMessage());
            }
            

            if($valid_aws_credentials && $valid_site_id) {
                $msg = "Saving helper details: site_id " . $site_id['id'];
                $msg .= ", access_key_id " . $aws_credentials['access_key_id'];
                $msg .= ", secret_access_key " . $aws_credentials['secret_access_key'];
                $msg .= ", log_group " . $aws_credentials['log_group'];
                $msg .= ", log_stream " . $aws_credentials['log_stream'];
                $msg .= ", session_token " . $aws_credentials['session_token'];

                Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage($msg);
                
                Mage::helper('waves')->setAWSAccessKeyId($aws_credentials['access_key_id']);
                Mage::helper('waves')->setAWSSecretAccessKey($aws_credentials['secret_access_key']);
                Mage::helper('waves')->setAWSLogStream($aws_credentials['log_stream']);
                Mage::helper('waves')->setAWSLogGroup($aws_credentials['log_group']);
                Mage::helper('waves')->setAWSSessionToken($aws_credentials['session_token']);
                Mage::helper('waves')->setSiteId($site_id['id']);
            }
        }
    }

}