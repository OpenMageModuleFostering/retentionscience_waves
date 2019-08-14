<?php

class RetentionScience_Waves_Model_Cron {

    public function export() {

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

}