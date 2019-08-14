<?php

class RetentionScience_Waves_Model_Container_Waves extends Enterprise_PageCache_Model_Container_Abstract {

    public function applyWithoutApp(&$content)
    {
        return false;
    }

    protected function _getCacheId()
    {
        $id = 'WAVES_' . microtime() . '_' . rand(0,99);
        return $id;
    }

    protected function _renderBlock()
    {
        $class = $this->_placeholder->getAttribute('block');
        $block = new $class;
        $block->setBlockId($this->_placeholder->getAttribute('block_id'));
        $block->setLayout(Mage::app()->getLayout());
        $block->setTemplate($this->_placeholder->getAttribute('template'));
        $block->setProductId($this->_placeholder->getAttribute('product_id']));
        return $block->toHtml();
    }

}