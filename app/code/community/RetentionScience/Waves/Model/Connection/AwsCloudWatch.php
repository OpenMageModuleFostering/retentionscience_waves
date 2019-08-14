<?php

require_once 'aws-sdk-php' . DIRECTORY_SEPARATOR . 'aws-autoloader.php';

use Aws\CloudWatchLogs\CloudWatchLogsClient;

class RetentionScience_Waves_Model_Connection_AwsCloudWatch extends Varien_Object {

    const AWS_DEFAULT_REGION = 'us-east-1';

    const AWS_VERSION = '2014-03-28';

    const MAX_LOG_EVENTS = 10000;

    const MAX_BATCH_SIZE = 1048576;

    const EVENT_ADDITIONAL_SIZE = 26;

    protected $_logs = array();

    protected $_logSize = array();

    protected $_logBatches = array();

    public function __construct() {
        parent::__construct();

        $this->setRegion(self::AWS_DEFAULT_REGION);
        $this->setAccessKeyId(Mage::helper('waves')->getAWSAccessKeyId());
        $this->setSecretAccessKey(Mage::helper('waves')->getAWSSecretAccessKey());
        $this->setSessionToken(Mage::helper('waves')->getAWSSessionToken());

        $this->setClient(CloudWatchLogsClient::factory(array(
            'region' => $this->getRegion(),
            'version' => self::AWS_VERSION,
            'credentials' => array(
                'key' => $this->getAccessKeyId(),
                'secret' => $this->getSecretAccessKey(),
                'token' => $this->getSessionToken(),
            ),
        )));

        // Bug fix for errors during class loading
        try {
            $result = new Guzzle\Service\Resource\Model;
            $this->getClient()->putLogEvents(array(
                'logGroupName' => 'test',
                'logStreamName' => 'test',
                'logEvents' => array(
                    array(
                        'timestamp' => 1000,
                        'message' => 'test',
                    ),
                ),
            ));
        } catch(Exception $e) {}
    }

    public function reinitClient() {
        $this->setAccessKeyId(Mage::helper('waves')->getAWSAccessKeyId());
        $this->setSecretAccessKey(Mage::helper('waves')->getAWSSecretAccessKey());
        $this->setSessionToken(Mage::helper('waves')->getAWSSessionToken());

        $this->setClient(CloudWatchLogsClient::factory(array(
            'region' => $this->getRegion(),
            'version' => self::AWS_VERSION,
            'credentials' => array(
                'key' => $this->getAccessKeyId(),
                'secret' => $this->getSecretAccessKey(),
                'token' => $this->getSessionToken(),
            ),
        )));
    }

    /**
     *
     * Log one message
     * Example:
     * $this->log_message("hello testing");
     * @param string $msg
     */
    public function logMessage($msg) {
                
        $siteId = Mage::helper('waves')->getSiteId();
        $prefix = date('Y-m-d H:i:s'). ' UTC - [site_id ' . $siteId . '] ';

        $this->log(array(
            'send' => TRUE,
            'logGroupName' => Mage::helper('waves')->getAWSLogGroup(),
            'logStreamName' => Mage::helper('waves')->getAWSLogStream(),
            'message' => $prefix . $msg
        ));
    }

    /**
     *
     * Log event
     * Example:
     * $this->log(array(
     *      //Required
     *      'logGroupName' => 'test',
     *      // Required
     *      'logStreamName' => 'test',
     *      // Required
     *      'message' => 'qwerty',
     *      // Optional
     *      'timestamp' => round(microtime(true) * 1000),
     *      // Optional
     *      'send' => TRUE || FALSE, // If set tu true - log will be send to AWS CloudWatchLogs immediately
     * ));
     * @param array $log
     */
    public function log(array $log) {
        if(! isset($log['logGroupName']) OR ! isset($log['logStreamName']) OR ! isset($log['message'])) {
            return;
        }
        if(! isset($log['timestamp'])) {
            $log['timestamp'] = round(microtime(true) * 1000);
        }
        $newBatch = FALSE;
        if(isset($log['send']) AND $log['send']) {
            $location = array(
                'logGroupName' => $log['logGroupName'],
                'logStreamName' => $log['logStreamName'],
            );
            $location['logGroupName'] = $this->getLogGroup($location['logGroupName']);
            $location['logStreamName'] = $this->getLogStream($location['logGroupName'], $location['logStreamName']);
            $this->putLogEvents($location, array(
                array('timestamp' => $log['timestamp'], 'message' => $log['message'],),
            ));
        } else {
            $logGroupName = $log['logGroupName'];
            $logStreamName = $log['logStreamName'];
            if(! isset($this->_logs[$logGroupName])) {
                $this->_logs[$logGroupName] = array();
                $this->_logSize[$logGroupName] = array();
                $this->_logBatches[$logGroupName] = array();
            }
            if(! isset($this->_logs[$logGroupName][$logStreamName])) {
                $this->_logs[$logGroupName][$logStreamName] = array();
                $this->_logSize[$logGroupName][$logStreamName] = 0;
                $this->_logBatches[$logGroupName][$logStreamName] = 0;
            }
            $batch = $this->_logBatches[$logGroupName][$logStreamName];
            if(! isset($this->_logs[$logGroupName][$logStreamName][$batch])) {
                $this->_logs[$logGroupName][$logStreamName][$batch] = array();
            }
            if(
                (strlen($log['message']) + self::EVENT_ADDITIONAL_SIZE + $this->_logSize[$logGroupName][$logStreamName] > self::MAX_BATCH_SIZE)
                OR
                (count($this->_logs[$logGroupName][$logStreamName][$batch]) >= self::MAX_LOG_EVENTS)
            ) {
                $this->_logSize[$logGroupName][$logStreamName] = 0;
                $this->_logBatches[$logGroupName][$logStreamName] ++;
                $batch = $this->_logBatches[$logGroupName][$logStreamName];
                $newBatch = TRUE;
            }
            $this->_logSize[$logGroupName][$logStreamName] += strlen($log['message']) + self::EVENT_ADDITIONAL_SIZE;
            $this->_logs[$logGroupName][$logStreamName][$batch][] = array(
                'timestamp' => $log['timestamp'],
                'message' => $log['message'],
            );
        }
        // If batch is full - send and clean - if there would be a lot of errors - to save memory
        if($newBatch) {
            $this->sendLogs();
            gc_collect_cycles();
        }
    }

    /**
     * Send all logs to AWS CloudWatchLogs if 'send' wasn't set to TRUE in $this->log() method
     */
    public function sendLogs() {
        if(empty($this->_logs)) {
            return;
        }
        foreach($this->_logs AS $logGroupName => $logStreams) {
            $location = array(
                'logGroupName' => $logGroupName,
            );
            $location['logGroupName'] = $this->getLogGroup($location['logGroupName']);
            foreach($logStreams AS $logStreamName => $logEventsBatches) {
                $location['logStreamName'] = $this->getLogStream($location['logGroupName'], $logStreamName);
                foreach($logEventsBatches AS $logEvents) {
                    $this->putLogEvents($location, $logEvents);
                }
            }
        }
        $this->_logs = array();
    }

    public function getReadableEventLog($logGroupName, $logStreamNames = '') {
        $ret = '';
        $ret .= 'LogGroupName: ' . $logGroupName . "\n";
        try {
            if (empty($logStreamNames)) {
                $streams = $this->getClient()->describeLogStreams(array(
                    'logGroupName' => $logGroupName,
                ))->get('logStreams');
                $logStreamNames = array();
                if (!empty($streams)) {
                    foreach ($streams AS $stream) {
                        $logStreamNames[] = $stream['logStreamName'];
                    }
                }
            } else {
                $logStreamNames = array($logStreamNames);
            }
            if (empty($logStreamNames)) {
                $ret .= "\tNo streams found\n";
            } else {
                foreach ($logStreamNames AS $logStreamName) {
                    $ret .= "\tLogStreamName: {$logStreamName}\n";
                    $logEvents = $this->getClient()->getLogEvents(array(
                        'logGroupName' => $logGroupName,
                        'logStreamName' => $logStreamName,
                    ))->get('events');
                    if (empty($logEvents)) {
                        $ret .= "\t\tNo log events found\n";
                    } else {
                        foreach ($logEvents AS $logEvent) {
                            $ret .= "\t\t" . $logEvent['timestamp'] . " {$logEvent['message']}\n";
                        }
                    }
                }
            }
        } catch(Exception $e) {
            $ret .= "\t" . $e->getMessage() . "\n";
        }
        return $ret;
    }

    public function cleanAll() {
        $nextToken = TRUE;
        while($nextToken) {
            $request = array();
            if(is_string($nextToken)) {
                $request['nextToken'] = $nextToken;
            }
            $result = $this->getClient()->describeLogGroups($request);
            $nextToken = $result->get('nextToken');
            $logGroups = $result->get('logGroups');
            if(! empty($logGroups)) foreach($logGroups AS $logGroup) {
                $this->getClient()->deleteLogGroup($logGroup);
            }
        }
    }

    /**
     * Get/Create log group
     * @param $logGroupName
     * @return mixed
     */
    protected function getLogGroup($logGroupName) {
        try {
            $logGroupName = preg_replace('#[^a-zA-Z0-9_\-\/\.]#', '', $logGroupName);
            return $logGroupName;
            $this->getClient()->createLogGroup(array(
                'logGroupName' => $logGroupName,
            ));
            return $logGroupName;
        } catch(Aws\CloudWatchLogs\Exception\CloudWatchLogsException $e) {
            if($e->getAwsErrorCode() === 'ResourceAlreadyExistsException') {
                return $logGroupName;
            }
            throw $e;
        }
    }

    /**
     * Get/Create log stream
     * @param $logGroupName
     * @param $logStreamName
     * @return mixed
     */
    protected function getLogStream($logGroupName, $logStreamName) {
        try {
            $logStreamName = preg_replace('#[:]#', '', $logStreamName);
              // No longer creating log stream. Expected to exist
//            $this->getClient()->createLogStream(array(
//                'logGroupName' => $logGroupName,
//                'logStreamName' => $logStreamName,
//            ));
            return $logStreamName;
        } catch(Aws\CloudWatchLogs\Exception\CloudWatchLogsException $e) {
            return $logStreamName;
            if($e->getAwsErrorCode() === 'ResourceAlreadyExistsException') {
                return $logStreamName;
            }
            throw $e;
        }
    }

    /**
     * Send log events
     * @param array $location
     * @param array $events
     */
    protected function putLogEvents(array $location, array $events = array(), $catchException = TRUE) {
        if(empty($events)) {
            return;
        }
        $sequenceToken = $this->getSequenceToken($location);
        $request = $location;
        $request['logEvents'] = $events;
        if(! is_null($sequenceToken)) {
            $request['sequenceToken'] = $sequenceToken;
        }
        try {
            $result = $this->getClient()->putLogEvents($request);
            $this->setSequenceToken($location, $result->get('nextSequenceToken'));
            return $result;
        } catch(Aws\CloudWatchLogs\Exception\CloudWatchLogsException $e) {
            if($e->getAwsErrorCode() === 'InvalidSequenceTokenException') {
                $result = $this->getClient()->describeLogStreams(array(
                    'logGroupName' => $location['logGroupName'],
                    'logStreamNamePrefix' => $location['logStreamName'],
                ));
                $logStreams = $result->get('logStreams');
                if(! empty($logStreams)) {
                    $logStream = array_shift($logStreams);
                    $this->setSequenceToken($location, $logStream['uploadSequenceToken']);
                    return $this->putLogEvents($location, $events);
                }
            } else {
                if(! $catchException) {
                    throw $e;
                }
                Mage::helper('waves')->updateAWSCredentials();
                $this->reinitClient();
                $this->putLogEvents($location, $events, FALSE);
            }
        } catch(Exception $e) {
            if(! $catchException) {
                throw $e;
            }
            Mage::helper('waves')->updateAWSCredentials();
            $this->reinitClient();
            $this->putLogEvents($location, $events, FALSE);
        }
    }

    /**
     * Get Sequence Token
     * @param array $location
     * @return mixed
     */
    protected function getSequenceToken(array $location) {
        $tokens = $this->getSequenceTokens();
        $key = $location['logGroupName'] . '::' . $location['logStreamName'];
        if(isset($tokens[$key])) {
            return $tokens[$key];
        }
    }

    /**
     * Set Sequence Token
     * @param array $location
     * @param $sequenceToken
     */
    protected function setSequenceToken(array $location, $sequenceToken) {
        $tokens = $this->getSequenceTokens();
        if(! is_array($tokens)) {
            $tokens = array();
        }
        $key = $location['logGroupName'] . '::' . $location['logStreamName'];
        $tokens[$key] = $sequenceToken;
        $this->setSequenceTokens($tokens);
    }

}
