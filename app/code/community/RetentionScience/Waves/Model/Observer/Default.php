<?php

class RetentionScience_Waves_Model_Observer_Default {
	
	public function setWavesOnOrderSuccessPageView($observer) {

        $orderIds = $observer->getEvent()->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }
        $block = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('waves_waves');
        if ($block) {
            $block->setOrderIds($orderIds);
        }

	}

}