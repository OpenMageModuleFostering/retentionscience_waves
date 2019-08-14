<?php

class RetentionScience_Waves_Block_Waves extends Mage_Core_Block_Template {

    public function customerLoggedIn(){
        $customerLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
        if($customerLoggedIn){
            return true;
        }
        return false;
    }
    public function getSiteId(){
        return Mage::helper('waves')->getSiteId();
    }
    public function isEnabled(){
        return Mage::helper('waves')->isEnabled() AND Mage::helper('waves')->getStoreId() == Mage::app()->getStore()->getId();

    }
    public function isAjaxAddToCartEnable(){
        return Mage::helper('waves')->isAjaxCartEnabled();
    }
    public function customerId(){
        $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
        $user_record_id = md5(trim(strtolower($email)));
        return $user_record_id;
    }

    public function getCacheKeyInfo() {
        return array('block_id' => $this->getBlockId(), 'template' => $this->getTemplate(), 'product_id' => $this->getProductId());
    }

    public function getProductId() {
        if($_product = Mage::registry('current_product')) {
            return $_product->getId();
        }
        if($this->getData('product_id')) {
            return $this->getData('product_id');
        }
        return FALSE;
    }

    public function getOrders() {
        $orderIds = $this->getOrderIds();
        $ret = array();
        if(! empty($orderIds) AND is_array($orderIds)) {
            foreach($orderIds AS $orderId) {
                $ret[] = Mage::getModel('sales/order')->load($orderId);
            }
        }
        return $ret;
    }

}