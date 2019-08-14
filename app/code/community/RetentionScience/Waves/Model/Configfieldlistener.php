<?php

class RetentionScience_Waves_Model_Configfieldlistener extends Varien_Object {

    
    public function logParameters($observer) {
        
        try {
        
            $post_vars = Mage::app()->getRequest()->getPost();
       
            if (array_key_exists('waves_retentionscience_settings', $post_vars['config_state'])) {
                if (Mage::helper('waves')->isEnabled()){
                    $results = print_r($post_vars, true);
                    $results = str_replace("\n", " ", $results);
                    $results = "Waves Config Saved with params: \n" . $results;
                    
                    Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage($results);
                }
            }
        } catch(Exception $e) {
            Mage::getSingleton('core/session')->addError('Error: ' . $e->getMessage());
        }
    }


}
