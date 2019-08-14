<?php

class RetentionScience_Waves_Model_Source_Stores {

    public function toOptionArray()
    {
        $stores = Mage::app()->getStores();
        $options = array(array('value' => null, 'label' => "Select a Store"));
        foreach ($stores as $store_id => $store) {
            array_push($options, array('value' => $store_id, 'label' => $store->getWebsite()->getName() . " - " . $store->getName()));
        }
        return $options;
    }

}