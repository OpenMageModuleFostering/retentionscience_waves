<?php

class RetentionScience_Waves_Frontend_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction(){
        $helper = Mage::helper('waves');
        if($helper->isEnabled() && $helper->getStoreId() == Mage::app()->getStore()->getId()){

            $result['siteID'] = $helper->getWebsiteId();
            $result['customerId'] = "";
            if(Mage::getSingleton('customer/session')->isLoggedIn()){
                $customerId = Mage::getSingleton('customer/session')->getCustomerId();
                $result['customerId'] = $customerId;
            }


            $allItems = Mage::getModel('checkout/cart')->getQuote()->getAllVisibleItems();
            $items = array();
            $count = 0;
            foreach ($allItems as $item){
                $items[$count]['id'] = $item->getProductId();
                $items[$count]['name'] = $item->getName();
                $items[$count]['price'] = $item->getPrice();
                $count++;
            }
            $result['items'] = json_encode($items);
            echo json_encode($result);
            die();
        }
    }

}