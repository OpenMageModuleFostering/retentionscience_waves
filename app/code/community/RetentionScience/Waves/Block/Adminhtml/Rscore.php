<?php

class RetentionScience_Waves_Block_Adminhtml_Rscore extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    public function render(Varien_Data_Form_Element_Abstract $fieldset)
    {
        return $this->getLayout()->createBlock('waves/adminhtml_rscore')->setTemplate('waves/rscore.phtml')->assign('in_config', true)->toHtml();
    }
}
