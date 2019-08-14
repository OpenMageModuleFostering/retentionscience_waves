<?php

class RetentionScience_Waves_Model_Configfieldlistener extends Varien_Object {

    
    public function logParameters($observer) {
       $post_vars = Mage::app()->getRequest()->getPost();
       $results = print_r($post_vars, true);
       $results = "Waves Config Saved with params: \n" . $results;
       
       Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage($results);
    }


}
