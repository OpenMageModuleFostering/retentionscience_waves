<?php

class RetentionScience_Waves_Model_Source_Cronconfig extends Mage_Core_Model_Config_Data
{

    protected function _afterSave()
    {
        // category, product, user, order
        $rs_cron_job = $this->getFieldConfig()->rs_cron_job;
        $cron_string_path = 'crontab/jobs/' . $rs_cron_job . '/schedule/cron_expr';
        $cron_model_path = 'crontab/jobs/' . $rs_cron_job . '/run/model';
        $cron_expr_string =  $this->getValue();


        try {
            Mage::getModel('core/config_data')
                ->load($cron_string_path, 'path')
                ->setValue($cron_expr_string)
                ->setPath($cron_string_path)
                ->save();
            Mage::getModel('core/config_data')
                ->load($cron_model_path, 'path')
                ->setValue((string) Mage::getConfig()->getNode($cron_model_path))
                ->setPath($cron_model_path)
                ->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }

}