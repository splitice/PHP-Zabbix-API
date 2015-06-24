<?php
namespace Splitice\Zabbix;
/**
 * Zabbix PHP API (via the JSON-RPC Zabbix API)
 *
 * This Classes API is compatible with the class by Andrew Farley @ http://andrewfarley.com
 */
class API {
    /**
     * Private constants, not intended to be changed or edited
     */
    CONST ZABBIX_API_URL = 'api_jsonrpc.php';
    CONST PHPAPI_VERSION = '1.0';

    /**
     * The private instance of this class
     *
     * @var APIClient
     */
    static $instance;

    private static $lastError;
    
    // we don't permit an explicit call of the constructor! ($api = new ZabbixAPI())
    protected function __construct() { }
    // we don't permit cloning of this static class ($x = clone $v)
    protected function __clone() { }
    
    /**
     * Public facing functions
     */
     
     /**
      * Login, this will attempt to login to Zabbix with the specified username and password
      */
    public static function login($url, $username, $password) {
        self::$instance = new APIClient($url, $username, $password);
        return self::$instance;
    }
    
    public static function debugEnabled($value) {
        if ($value === TRUE)
            self::$instance->debug = true;
        else
            self::$instance->debug = false;
    }
    
    /**
     * Generic API Call function, with no method or property validation
     */
    public static function fetch($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->fetch($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }
    
    /**
     * Alias to fetch, but simply returns TRUE or FALSE.  This is typically for doing updates
     * and other "set/update" type commands
     */
    public static function query($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->query($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }

    /**
     * Force return value to be an array
     */
    public static function fetch_array($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->fetch_array($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }
    
    /**
     * Get the last error that
     */
    public static function getLastError() {
        return self::$lastError;
    }
    
    /**
     * Force return value to be a string
     */
    public static function fetch_string($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->fetch_string($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }

    /**
     * Force return value to be the first element of an array (first "row" or record)
     */
    public static function fetch_row($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->fetch_row($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }
    
    /**
     * Force return value to be the first column of the first row of an array, in the form of an array
     */
    public static function fetch_column($object, $method, $properties = array()) {
        self::$lastError = null;
        try {
            return self::$instance->fetch_column($object, $method, $properties);
        }catch(\Exception $ex){
            self::$lastError = $ex->getMessage();
        }
    }
}