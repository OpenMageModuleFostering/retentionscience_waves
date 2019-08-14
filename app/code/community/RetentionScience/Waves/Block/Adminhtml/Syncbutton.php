<?php


class RetentionScience_Waves_Block_Adminhtml_Syncbutton
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $originalData = $element->getOriginalData();
        $action = $originalData['rs_button_url'];
        $link_url = $this->_getLink($action);
        return $this->_getAddRowButtonHtml($link_url);
    }


    protected function _getAddRowButtonHtml($link_url)
    {
        $title = "Run Now";
        return $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('run_now_button')
            ->setLabel($title)
            ->setOnClick("location.href='$link_url';")
            ->setDisabled(false)
            ->toHtml();
    }

    protected function _getLink($action){
        $baseurl = Mage::app()->getStore()->getBaseUrl();
        return Mage::helper('adminhtml')->getUrl("adminhtml/waves/$action");
    }

}
