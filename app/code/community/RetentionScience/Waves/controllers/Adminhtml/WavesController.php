<?php

class RetentionScience_Waves_Adminhtml_WavesController extends Mage_Adminhtml_Controller_Action {

    public function updateRScoreAction() {
        $rscore = Mage::helper('waves')->getRScore();
        echo Zend_Json::encode($rscore);
    }

    public function syncDataAction() {

        try {
            if (Mage::helper('waves')->getAWSAccessKeyId() != "" ){
                Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("SyncData Button - button clicked");
            }

            $event = new Varien_Object();

            $event->setData(array(
                'type' => 'batch',
                'groups' => 'all',
            ));

            $event->setSource('magento_admin');

            Mage::dispatchEvent('waves_init_export', array(
                'event' => $event,
            ));

            if (Mage::helper('waves')->getAWSAccessKeyId() != "" ){
                Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("SyncData Button - Data successfully exported");
            }

            Mage::getSingleton('core/session')->addSuccess('Data successfully exported');

        } catch(Exception $e) {

            Mage::getSingleton('core/session')->addError('Error: ' . $e->getMessage());
        }

        $this->_redirectUrl(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit/section/waves/"));

    }

}