<?php

class RetentionScience_Waves_Adminhtml_WavesController extends Mage_Adminhtml_Controller_Action {

    public function updateRScoreAction() {
        $rscore = Mage::helper('waves')->getRScore();
        echo Zend_Json::encode($rscore);
    }

    public function syncDataAction() {

        try {
            if (Mage::helper('waves')->isEnabled()){
                Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("SyncData Button - button clicked");
            }

            Mage::helper('waves')->setRunManual(1);

            Mage::getSingleton('core/session')->addSuccess('Export scheduled and will run shortly');

        } catch(Exception $e) {

            Mage::getSingleton('core/session')->addError('Error: ' . $e->getMessage());
        }

        $this->_redirectUrl(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit/section/waves/"));

    }

}