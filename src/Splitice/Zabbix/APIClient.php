<?php
/**
 * Created by PhpStorm.
 * User: splitice
 * Date: 10-06-2015
 * Time: 1:23 PM
 */

namespace Splitice\Zabbix;


class APIClient {
// The private instance of this class
    static $instance;

    /**
     * Private class variables
     */
    protected $url              = '';
    protected $username         = '';
    protected $password         = '';
    protected $auth_hash        = NULL;
    public $debug               = false;
    public $timeout = 10;
    private $ch;

    public function __construct($url, $username, $password){
        if (substr($url,-1) != '/')
            $url .= '/';
        $url .= API::ZABBIX_API_URL;

        $this->url = $url;

        $this->ch = curl_init();

        $this->username = $username;
        $this->password = $password;
    }

    function is_authed(){
        return $this->auth_hash != null;
    }

    private function _debug($msg){
        if($this->debug){
            echo "$msg\n";
        }
    }

    function __destruct()
    {
        $this->__callAPI("user.logout", array());
        $this->auth_hash = NULL;
    }

    /**
     * Generic API Call function, with no method or property validation
     */
    public function fetch($object, $method, $properties = array()) {
        return $this->__callAPI($object.'.'.$method, $properties);
    }

    /**
     * Alias to fetch, but simply returns TRUE or FALSE.  This is typically for doing updates
     * and other "set/update" type commands
     */
    public function query($object, $method, $properties = array()) {
        return $this->fetch($object, $method, $properties) == FALSE ? FALSE : TRUE;
    }

    /**
     * Force return value to be an array
     */
    public function fetch_array($object, $method, $properties = array()) {
        $return = $this->fetch($object, $method, $properties);
        if (is_array($return))
            return $return;
        else
            return array($return);
    }

    /**
     * Force return value to be a string
     */
    public function fetch_string($object, $method, $properties = array()) {
        $return = $this->fetch($object, $method, $properties);
        if (is_array($return))
            return $this->__return_first_string($return);
        else
            return $return;
    }

    /**
     * Force return value to be the first element of an array (first "row" or record)
     */
    public function fetch_row($object, $method, $properties = array()) {
        $return = $this->fetch($object, $method, $properties);
        if (is_array($return))
            foreach ($return as $item)
                return $item;
        else
            return $return;
    }

    /**
     * Force return value to be the first column of the first row of an array, in the form of an array
     */
    public function fetch_column($object, $method, $properties = array()) {
        $return = $this->fetch($object, $method, $properties);
        if (!is_array($return))
            return array($return);
        else {
            $output = array();
            foreach ($return as $item) {
                $output[] = array_shift($item);
            }
            return $output;
        }
    }

    /**
     * Recursive function to get the first non array element of a multidimensional array
     */
    private function __return_first_string($array) {
        foreach($array as $item) {
            if (is_array($item))
                return $this->__return_first_string($item);
            else
                return $item;
        }
    }

    /**
     * Builds a JSON-RPC request, designed just for Zabbix
     */
    private function __buildJSONRequest($method, $params = array()) {
        // This is our default JSON array
        $request = array(
            'method' => $method,
            'id' => 1,  // NOTE: this needs to be fixed I think?
            'params' => ( is_array($params) ? $params : array() ),
            'jsonrpc' => "2.0"
        );
        if($method != 'user.login'){
            $request['auth'] = $this->auth_hash;
        }
        // Return our request, in JSON format
        return json_encode($request);
    }

    /**
     * The private function that performs the call to a remote RPC/API call
     */
    private function __callAPI($method, $params = array()) {
        // Make sure we're logged in, or trying to login...
        if ($this->auth_hash == NULL && $method != 'user.login')
            return false;  // If we're not logged in, no wasting our time here

        // Try to retrieve this...
        $data = $this->__jsonRequest(
            $this->url,
            $this->__buildJSONRequest( $method, $params )
        );

        if ($this->debug)
            echo "Got response from API: ($data)\n";

        if($data === false){
            throw new ZabbixAPIException(curl_error($this->ch));
        }

        // Convert return data (JSON) to PHP array
        $decoded_data = json_decode($data, true);

        if ($this->debug)
            echo "Response decoded: (".print_r($decoded_data,true)."\n";

        // Return the data if it's valid
        if ( isset($decoded_data['id']) && $decoded_data['id'] == 1 && !empty($decoded_data['result']) ) {
            return $decoded_data['result'];
        } else {
            // If we had a actual error, put it in our instance to be able to be retrieved/queried
            if (!empty($decoded_data['error'])) {
                if($decoded_data['error']['code'] == -32602){
                    $this->auth_hash = false;
                }
                throw new ZabbixAPIException("Zabbix API Error: " . var_export($decoded_data['error'], true));
            }
            return false;
        }
    }

    /**
     * Private login function to perform the login
     */
    public function login() {
        // Try to login to our API
        $data = $this->__callAPI('user.login', array( 'password' => $this->password, 'user' => $this->username ));

        $this->_debug("__login() Got response from API: ($data)");

        if (isset($data) && strlen($data) == 32) {
            $this->auth_hash = $data;
            return true;
        } else {
            $this->auth_hash = NULL;
            return false;
        }
    }

    /**
     * Note: Headers must be in the string form, in an array...
     *   eg. $headers  =  array('Content-Type: application/json-rpc', 'Another-Header: value goes here');
     */
    private function __jsonRequest($url, $data = '', $headers = array()){
        // These are required for submitting JSON-RPC requests
        $headers[]  = 'Content-Type: application/json-rpc';
        // Well, ok this one isn't, but it's good to conform (sometimes)
        $headers[]  = 'User-Agent: ZabbixAPI v'.API::PHPAPI_VERSION;

        $opts = array(
            CURLOPT_RETURNTRANSFER => true,     // Allows for the return of a curl handle
            //CURLOPT_VERBOSE => true,          // outputs verbose curl information (like --verbose with curl on the cli)
            //CURLOPT_HEADER => true,           // In a verbose output, outputs headers
            CURLOPT_CONNECTTIMEOUT => 2, 		// We arent on the moon
            CURLOPT_TIMEOUT => $this->timeout,              // Maximum number of seconds to allow curl to process the entire request
            CURLOPT_SSL_VERIFYHOST => false,    // Incase we have a fake SSL Cert...
            CURLOPT_SSL_VERIFYPEER =>false,     //    Ditto
            CURLOPT_FOLLOWLOCATION => true,     // Incase there's a redirect in place (moved zabbix url), follow it automatically
            CURLOPT_FRESH_CONNECT => true,       // Ensures we don't use a cached connection or response
            CURLOPT_ENCODING => "gzip",
            CURLOPT_URL => $url
        );

        // If we have headers set, put headers into our curl options
        if(is_array($headers) && count($headers)){
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        // This is a POST, not GET request
        $opts[CURLOPT_CUSTOMREQUEST] = "POST";
        $opts[CURLOPT_POSTFIELDS] = ( is_array($data) ? http_build_query($data) : $data );

        // This is useful, incase we're remotely attempting to consume Zabbix's API to compress our data, save some bandwidth
        $opts[CURLOPT_ENCODING] = 'gzip';

        // If we're in debug mode
        if ($this->debug) {
            echo "CURL URL: $url\n<br>";
            echo "CURL Options: ".print_r($opts, true);
        }

        // Go go gadget!  Do your magic!
        curl_setopt_array($this->ch, $opts);
        $ret = @curl_exec($this->ch);

        return $ret;
    }
}