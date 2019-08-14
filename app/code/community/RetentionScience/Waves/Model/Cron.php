<?php

class RetentionScience_Waves_Model_Cron {

    public function export() {
        if (Mage::helper('waves')->isEnabled()){
            Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("Cron export() running. Kicked off from the crontab");
        }
        

        $event = new Varien_Object();

        $event->setData(array(
            'type' => 'batch',
            'groups' => 'all',
        ));

        $event->setSource('magento_cron');

        Mage::dispatchEvent('waves_init_export', array(
            'event' => $event,
        ));

    }

    public function exportManual() {

        if(Mage::helper('waves')->getRunManual()) {
            if (Mage::helper('waves')->isEnabled()){
                Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("Cron exportManual() running. Kicked off from export button push");
            }

            Mage::helper('waves')->setRunManual(0);

            $event = new Varien_Object();

            $event->setData(array(
                'type' => 'batch',
                'groups' => 'all',
            ));

            $event->setSource('magento_cron_manual');

            Mage::dispatchEvent('waves_init_export', array(
                'event' => $event,
            ));
        }

    }

}