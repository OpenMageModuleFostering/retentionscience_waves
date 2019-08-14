<?php

class RetentionScience_Waves_Model_Observer extends Varien_Object {

    const EXPORT_TYPE_BATCH = 'batch';

    const EXPORT_TYPE_RECORD = 'record';

    const LOG_GROUP = 'Magento';

    const LOG_STREAM = 'default';

    protected $_delayedUpload = TRUE;

    protected $_delayedFiles = array();

    protected $_logGroup;

    protected $_logStream;

    protected $_rsAPI;

    protected $_allowedGroups = array(
        'categories' => 'waves/export_category',
        'users' => 'waves/export_customer',
        'orders' => 'waves/export_order',
        'items' => 'waves/export_product',
        'order_items' => 'waves/export_order_items',
    );

    protected $_allowedTypes = array(
        self::EXPORT_TYPE_BATCH,
//        self::EXPORT_TYPE_RECORD,
    );

    protected $_reserved_memory;

    protected $_signals;

    public function __construct() {
        parent::__construct();
        $this->_logStream = Mage::helper('waves')->getAWSLogStream();
        $this->_logGroup = Mage::helper('waves')->getAWSLogGroup();
        $this->registerShutdownFunction();
        $this->setErrorHandler();
        if(function_exists('pcntl_signal')) {
            $this->_signals = array(
                SIGINT => 'SIGINT',
                SIGTERM => 'SIGTERM',
                SIGHUP => 'SIGHUP',
            );
        }
        $this->registerPosixHandlers();
    }

    public function performExport($observer) {
        $this->setEvent($observer->getEvent());
        if(! Mage::helper('waves')->isEnabled()) {
            $this->getEvent()->setStatus('error');
            $this->getEvent()->setMessage('Retention Science Module is inactive');
            return;
        }
        if(! $this->validate()) {
            return;
        }
        $this->export();
    }

    protected function exportBatch() {
        $timestamp = microtime(TRUE);
        $groups = $this->getGroups();
        // Export groups
        foreach($groups AS $groupName) {
            $exportModel = Mage::getModel($this->_allowedGroups[$groupName]);
            $exportModel->setStoreId(Mage::helper('waves')->getStoreId());
            $this->setExportModel($exportModel);
            try {
                $this->logStart();
                $this->setListenErrors(TRUE);
                $exportModel->run($timestamp);
                $this->logSuccess();
                if(file_exists($exportModel->getBulkFile())) {
                    if($this->_delayedUpload) {
                        $this->_delayedFiles[$exportModel->getExportModelTitle()] = $exportModel->getBulkFile();
                    } else {
                        $this->uploadBulkFile($exportModel->getExportModelTitle(), $exportModel->getBulkFile());
                    }
                }
            } catch(Exception $e) {
                // Catch exception
                $errno = $e->getCode();
                $errstr = $e->getMessage();
                $errfile = $e->getFile();
                $errline = $e->getLine();
                // Do stuff
                $this->logException($errno, $errstr, $errfile, $errline);
            }

            if(! $this->_delayedUpload) {
                if(file_exists($exportModel->getBulkFile())) {
                    @ unlink($exportModel->getBulkFile());
                }

                if(file_exists($exportModel->getBulkFile() . '.gz')) {
                    @ unlink($exportModel->getBulkFile() . '.gz');
                }
            }

            $this->setListenErrors(FALSE);
            $this->getLog()->sendLogs();
        }
        if($this->_delayedUpload AND ! empty($this->_delayedFiles)) {
            $this->uploadBulkFile($this->_delayedFiles);
            foreach($this->_delayedFiles AS $_file) {
                if(file_exists($_file)) {
                    @ unlink($_file);
                }

                if(file_exists($_file . '.gz')) {
                    @ unlink($_file . '.gz');
                }
            }
        }
        // Set status
        if(! $this->getStatus()) {
            $this->getEvent()->setStatus('success');
            $this->getEvent()->setMessage('Exported ' . implode(', ', $groups));
        }
    }

    protected function uploadBulkFile($files, $filename = NULL) {
        if(! is_array($files)) {
            $files = array($files => $filename);
        }

        $uploadFiles = array();

        foreach($files AS $group => $filename) {
            // Maybe we have no records for some type of export
            if(! file_exists($filename)) {
                continue;
            }
            if(! is_readable($filename)) {
                throw new Exception('File "' . $filename . '" is not readable');
            }
            if(! is_writable($filename)) {
                throw new Exception('File "' . $filename . '" is not writable');
            }
            if(Mage::helper('waves')->isBulkCompressionEnabled()) {
                $gzfilename = $filename . '.gz';
                $fp = fopen($filename, 'rb');
                $gzfp = gzopen($gzfilename, 'wb');
                while(! feof($fp)) {
                    gzwrite($gzfp, fread($fp, 1024 * 512));
                }
                gzclose($gzfp);
                fclose($fp);
                $filename = $gzfilename;
            }
            $uploadFiles[$group] = $filename;
        }

        if(is_null($this->_rsAPI)) {
            $this->_rsAPI = Mage::getModel('waves/connection_retentionScienceApi', array(
                'username' => Mage::helper('waves')->getApiUser(),
                'password' => Mage::helper('waves')->getApiPassword(),
                'testmode' => Mage::helper('waves')->isTestMode(),
            ));
        }
        $this->_rsAPI->sync_data($uploadFiles);
    }

    protected function exportRecord() {
        $timestamp = microtime(TRUE);
        $groupName = $this->getGroup();
        $exportModel = Mage::getModel($this->_allowedGroups[$groupName]);
        $exportModel->setIdsToProcess($this->getIds());
        $this->setExportModel($exportModel);
        try {
            $this->logStart();
            $this->setListenErrors(TRUE);
            $exportModel->run($timestamp);
            $this->logSuccess();
        } catch(Exception $e) {
            // Catch exception
            $errno = $e->getCode();
            $errstr = $e->getMessage();
            $errfile = $e->getFile();
            $errline = $e->getLine();
            // Do stuff
            $this->logException($errno, $errstr, $errfile, $errline);
        }
        $this->setListenErrors(FALSE);
        $this->getLog()->sendLogs();
    }

    protected function log($logStreamName, $message) {
        // There are some errors during including AWS SDK files - ignore them
        $oldListen = $this->getListenErrors();
        $this->setListenErrors(FALSE);
        $logTime = Mage::getSingleton('core/date')->gmtDate() . ' UTC';
        $siteId = Mage::helper('waves')->getSiteId();
        $this->getLog()->log(array(
            'logGroupName'      => $this->_logGroup,
            'logStreamName'     => $this->_logStream,
            'message'           => $logTime . ' - [site_id ' . $siteId . '] [' . strtoupper($logStreamName) . ']: [' . $this->getGUID() . '] ' . $message,
        ));
        $this->setListenErrors($oldListen);
    }

    protected function logStart() {
        $this->setStartTime(round(microtime(true)));
        $this->setStartMemory(memory_get_usage(TRUE));
        $this->setGUID(substr(md5(rand()), 0, 8) . '-' . substr(md5(rand()), 0, 4) . '-' . substr(md5(rand()), 0, 4) . '-' . substr(md5(rand()), 0, 12));
        $start = $this->getExportModel()->getExportModelTitle() . ' export started';
        if($this->getEvent()->getSource()) {
            $start .= ' [source ' . $this->getEvent()->getSource() . ']';
        }
        $start .= ' [memstart ' . $this->getStartMemory() . ']';
        $this->log('start', $start);
    }

    protected function logSuccess() {
        $memdiff = memory_get_usage(TRUE) - $this->getStartMemory();
        $processedRecords = $this->getExportModel()->getProcessedRecords();
        $timediff = round(microtime(true)) - $this->getStartTime();
        $this->log('success', $this->getExportModel()->getExportModelTitle() . ' export ended successful [processed ' . $processedRecords . '] [memdiff ' . ($memdiff > 0 ? '+' : '') . $memdiff . '] [timediff ' . $timediff . ']');
    }

    protected function logFail() {
        $memdiff = memory_get_usage(TRUE) - $this->getStartMemory();
        $processedRecords = $this->getExportModel()->getProcessedRecords();
        $timediff = round(microtime(true)) - $this->getStartTime();
        $totalRecords = $this->getExportModel()->getTotalRecordsCalculated();
        $this->log('fail', $this->getExportModel()->getExportModelTitle() . ' export failed [processed=' . $processedRecords . '] [total ' . $totalRecords . '] [memdiff ' . ($memdiff > 0 ? '+' : '') . $memdiff . '] [timediff=' . $timediff . ']');
    }

    protected function logException($errno, $errstr, $errfile, $errline) {
        $this->logError('exception', $errno, $errstr, $errfile, $errline);
        $this->logFail();
    }

    protected function logRecoverableError($errno, $errstr, $errfile, $errline) {
        $this->logError('recoverable_error', $errno, $errstr, $errfile, $errline);
    }

    protected function logUnrecoverableError($errno, $errstr, $errfile, $errline) {
        $this->logError('unrecoverable_error', $errno, $errstr, $errfile, $errline);
        $this->logFail();
        $this->getLog()->sendLogs();
    }

    protected function logError($type, $errno, $errstr, $errfile, $errline) {
        $memdiff = memory_get_usage(TRUE) - $this->getStartMemory();
        $processedRecords = $this->getExportModel()->getProcessedRecords();
        $timediff = round(microtime(true)) - $this->getStartTime();
        $totalRecords = $this->getExportModel()->getTotalRecordsCalculated();
        $this->log($type, $this->getExportModel()->getExportModelTitle() . ' error occured [errno ' . $errno . '] [errstr ' . $errstr . '] [errfile' . $errfile . '] [errline ' . $errline . '] [processed ' . $processedRecords . '] [total ' . $totalRecords . '] [memdiff ' . ($memdiff > 0 ? '+' : '') . $memdiff . '] [timediff ' . $timediff . ']');
    }

    protected function registerShutdownFunction() {
        // Save 5MB of reserved memory, because PHP not allows to do anything else if out of memory
        $this->_reserved_memory = str_repeat('*', 10 * 1024 * 1024);
        register_shutdown_function(array($this, 'shutdownHandler'));
    }

    protected function setErrorHandler() {
        set_error_handler(array($this, 'errorHandler'), E_ALL | E_STRICT);
    }

    protected function registerPosixHandlers() {
        if(function_exists('pcntl_signal')) {
            declare(ticks = 1);
            foreach($this->_signals AS $signo => $code) {
                pcntl_signal($signo, array($this, 'posixHandler'));
            }
        }
    }

    public function posixHandler($signo) {
        if(! $this->getListenErrors()) {
            return;
        }
        $this->logError('manual_shutodown', $signo, 'Processed was manually stopped with ' . $this->_signals[$signo], 'unknown file', 0);
        $this->logFail();
        $this->getLog()->sendLogs();
        exit;
    }

    public function errorHandler($errno, $errstr, $errfile, $errline) {
        if(! $this->getListenErrors()) {
            return;
        }
        // Thats because magento doesn't support namespaces
        if(preg_match('#Varien#', $errfile)) {
            return;
        }
        // Catch recoverable error
        // Do stuff
        $this->logRecoverableError($errno, $errstr, $errfile, $errline);
    }

    public function shutdownHandler() {
        if(! $this->getListenErrors()) {
            return;
        }
        $this->setListenErrors(FALSE);
        // Free reserved memory, because PHP not allows to do anything else if out of memory
        $this->_reserved_memory = NULL;
        // Catch non-recoverable error
        $errno   = E_CORE_ERROR;
        $errstr  = "shutdown";
        $errfile = "unknown file";
        $errline = 0;
        $error = error_get_last();
        if( $error !== NULL) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr  = $error["message"];
        }
        // Do stuff
        $this->logUnrecoverableError($errno, $errstr, $errfile, $errline);
    }

    protected function getLog() {
        $log = $this->getData('log');
        if(is_null($log)) {
            $this->setData('log', Mage::getSingleton('waves/connection_awsCloudWatch'));
        }
        return $this->getData('log');
    }

    protected function export() {
        $oldValue = Mage::app()->getStore(Mage::helper('waves')->getStoreId())->getConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL);
        Mage::app()->getStore(Mage::helper('waves')->getStoreId())->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '1');
        switch($this->getType()) {
            case self::EXPORT_TYPE_BATCH:
                $this->exportBatch();
                break;
            case self::EXPORT_TYPE_RECORD:
                $this->exportRecord();
                break;
        }
        Mage::app()->getStore(Mage::helper('waves')->getStoreId())->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, $oldValue);
    }

    protected function validate() {
        $event = $this->getEvent();
        if(! in_array($event->getType(), $this->_allowedTypes, TRUE)) {
            $event->setStatus('error');
            $event->setMessage('Not valid export type. Allowed types: ' . implode(', ', $this->_allowedTypes));
            return FALSE;
        }
        $this->setType($event->getType());
        switch($event->getType()) {
            case self::EXPORT_TYPE_BATCH:
                $groups = $event->getGroups();
                if(! empty($groups) AND $groups !== 'all') {
                    $groups = explode(',', $groups);
                    if(! empty($groups)) {
                        foreach($groups AS $group) {
                            if(! isset($this->_allowedGroups[$group])) {
                                $event->setStatus('error');
                                $event->setMessage('Not valid export groups. Allowed groups: all or any/comma-separated of(' . implode(', ', array_keys($this->_allowedGroups)) . ')');
                                return FALSE;
                            }
                        }
                        $this->setGroups($groups);
                    } else {
                        $event->setStatus('error');
                        $event->setMessage('Validation failed');
                        return FALSE;
                    }
                } else {
                    $this->setGroups(array_keys($this->_allowedGroups));
                }
                break;
            case self::EXPORT_TYPE_RECORD:
                $group = $event->getGroup();
                if(empty($group) OR ! isset($this->_allowedGroups[$group])) {
                    $event->setStatus('error');
                    $event->setMessage('Not valid export groups. You should specify one of ' . implode(', ', array_keys($this->_allowedGroups)));
                    return FALSE;
                }
                $this->setGroup($group);
                $ids = $event->getIds();
                if(! is_string($ids)) {
                    $event->setStatus('error');
                    $event->setMessage('Not specified record ids');
                    return FALSE;
                }
                $ids = explode(',', $ids);
                if(! empty($ids)) {
                    $exportIds = array();
                    foreach($ids AS $id) {
                        $id = (int) $id;
                        if($id <= 0) {
                            $event->setStatus('error');
                            $event->setMessage('Id should be more than zero');
                            return FALSE;
                        }
                        $exportIds[] = $id;
                    }
                    $this->setIds($exportIds);
                } else {
                    $event->setStatus('error');
                    $event->setMessage('Not specified record ids');
                    return FALSE;
                }
                break;
        }
        return TRUE;
    }

}
