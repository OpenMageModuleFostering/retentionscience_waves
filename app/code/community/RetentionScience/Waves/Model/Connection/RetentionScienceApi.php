<?php

class RetentionScience_Waves_Model_Connection_RetentionScienceApi {

    const API_TEST_URL = 'https://api.retentionsandbox.com';
    const API_URL = 'https://api.retentionscience.com';
    const API_PORT = 443;
    const API_VERSION = '1';

    private $password;
    private $time_out = 60;
    private $username;
    private $testmode;

    // class methods
    public function __construct($username = null, $password = null, $testmode=false) {
        if(is_array($username) AND isset($username['username']) AND isset($username['password'])) {
            $password = $username['password'];
            $testmode = isset($username['testmode']) ? $username['testmode'] : FALSE;
            $username = $username['username'];
        }
        if($username !== null) $this->set_username($username);
        if($password !== null) $this->set_password($password);
        if($testmode) $this->set_testmode($testmode);
    }

    private function perform_call($url, $params = array(), $authenticate = false, $use_post = true) {
        // redefine
        $url = (string) $url;
        $aParameters = (array) $params;
        $authenticate = (bool) $authenticate;
        $use_post = (bool) $use_post;

        if ($this->get_testmode()) {
            $url = self::API_TEST_URL .'/' . $url;
        } else {
            $url = self::API_URL .'/' . $url;
        }

        // validate needed authentication
        if($authenticate && ($this->get_username() == '' || $this->get_password() == '')) {
            throw new RetentionScienceException('No username or password was set.');
        }

        // build GET URL if not using post
        if(!empty($params) && !$use_post){
            $url .= '?'. http_build_query( $params );
        }

        // set options
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_PORT] = self::API_PORT;
        
        $options[CURLOPT_USERAGENT] = $this->get_user_agent();
        // follow on only if allowed - 20120221
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')){
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = (int) $this->time_out;

        // HTTP basic auth
        if($authenticate) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->get_username() .':'. $this->get_password();
        }

        // build post params if $use_post
        if(!empty($params) && $use_post) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $params;
        }

        // curl init
        $curl = curl_init();
        // set options
        curl_setopt_array($curl, $options);
        
        // execute
        $response = curl_exec($curl);
        $headers = curl_getinfo($curl);
        // fetch errors and status code
        $errorNumber = curl_errno($curl);
        $errorMessage = curl_error($curl);
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($errorNumber != 0) {
            $response = 'cURL ERROR: [' . $errorNumber . "] " . $errorMessage;
        }
        // close
        curl_close($curl);
        return array('response_code' => $http_status_code,
            'response' => $response);
    }

    private function handle_response($response){
        // decode the returned json
        if ($response['response_code'] == 200 || $response['response_code'] == 201 ){
            return $response['response'];
        } else {
            throw new RetentionScienceException($response['response_code'] . ' - ' . $response['response']);
        }
    }


    // Getters
    private function get_password(){
        return (string) $this->password;
    }
    private function get_user_agent(){
        return (string) 'Retention Science PHP API Client / v'. self::API_VERSION;
    }
    private function get_username(){
        return (string) $this->username;
    }
    private function get_testmode(){
        return (boolean) $this->testmode;
    }

    // Setters
    private function set_username($username){
        $this->username = (string) $username;
    }
    private function set_password($password){
        $this->password = (string) $password;
    }
    private function set_testmode($testmode){
        $this->testmode = (boolean) $testmode;
    }


    /* log_credentials resource */
    public function get_aws_credentials() {
        $url = 'magento/log_credentials';
        $response = $this->perform_call($url, array(), true, false);
        return $this->handle_response($response);
    }
    
    /* site_id */
    public function get_site_id() {
        $url = 'sites/id';
        $response = $this->perform_call($url, array(), true, false);
        return $this->handle_response($response);
    }

    /* Data Sync */
    public function sync_data($file_hash) {
        $url = 'bulk_import/import';

        $upload_files = array('import_type' => 'magento');
        foreach ($file_hash as $type => $file) {
            if(! function_exists('curl_file_create')) {
                $upload_files[$type] = "@$file";
            } else {
                $upload_files[$type] = curl_file_create($file);
            }
        }

        $response = $this->perform_call($url, $upload_files, true, true);
        return $this->handle_response($response);
    }

}

class RetentionScienceException extends Exception { }