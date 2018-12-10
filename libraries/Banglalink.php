<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
#library
class Banglalink{

    protected $_api;
    protected $_sender;
    protected $_username;
    protected $_password;

    protected $sms = [];
    protected $mobiles = [];
    protected $config;
    protected $debug = false;
    protected $template = false;
    protected $autoParse = false;
    protected $responseDetails = false;
    protected $numberPrefix = '';
    protected $prefix = '/sendSMS';
    protected $sendingUrl = '/sendSMS';
    protected $sendingParameters = [];
 
    function __construct($params = array()){
		$this->_ci =& get_instance();
		$this->_ci->config->load('banglalink'); 
		$this->_initialize($params);
    }
    
    /**
     * initialize to config variable
     *
     * @param array $params
     * @return $config['variable'] with  $_variable (underscore)
     */
	public function _initialize($params = array()){
		$this->_response = '';
		foreach ($params as $key => $val){
			$this->{'_'.$key} = (isset($this->{'_'.$key}) ? $val : $this->_ci->config->item($key));
		}
	}
 
    /**
     * set config to variable
     *
     * @param array $config
     * @return $this
     */
	public function set_config($config = array()){ 
		if (!empty($config)){
			$this->_initialize($config);
		}
    }

    /**
     * Set Number Prefix
     *
     * @param string $prefix
     * @return $this
     */
    public function numberPrefix( $prefix = '88' ){
        $this->numberPrefix = $prefix;
        return $this;
    }
    /**
     * Set Message
     *
     * @param string $message
     * @param null $to
     *
     * @return $this
     */
    public function message( $message = '', $to = null ){
        $this->sms[] = $message;
        if ( !is_null($to) ){
            $this->to($to);
        }
        return $this;
    }
    /**
     * Set Phone Numbers
     *
     * @param $to
     *
     * @return $this
     */
    public function to($to){
        if( is_array($to) ){
            $this->mobiles = array_merge($this->mobiles, $to);
        }else{
            $this->mobiles[] = $to;
        }
        return $this;
    }
    /**
     * Send Method
     *
     * @param array $array
     *
     * @return mixed
     */
    public function send( $array = [] ){
        return $this->makingSmsFormatAndSendingSMS($array);
    }
    /**
     * Formatting Given Data
     *
     * @param array $array
     *
     * @return array
     */
    protected function makingSmsFormatAndSendingSMS( $array = [] ){
        if ( $array ) {
            $this->sms = array_merge($this->sms, $this->splitSmsAndNumbers($array));
        } else {
            $this->sms = $this->splitSmsAndNumbers($array);
        }
        if ( count($this->sms) == 1 ) {
            $this->sms = $this->sms[ 0 ];
            return $this->singleSMSOrTemplate();
        } else {
            return $this->makeMultiSmsMultiUser();
        }
    }
    /**
     * Formatting sms and mobiles property
     *
     * @param $array
     *
     * @return array
     */
    private function splitSmsAndNumbers( $array ){
        if ( $array && !isset($array[ 'message' ]) && !isset($array[ 'to' ]) ) {
            $this->sms = array_merge($this->sms, array_values($array));
            $arrayKeys = array_keys($array);
            if ( $arrayKeys[ 0 ] != 0 ) {
                $this->mobiles = array_merge($this->mobiles, $arrayKeys);
            }
        } else {
            $this->sms = array_merge($this->sms, $array);
        }
        $sms = $mobiles = [];
        if ( is_array($this->sms) ) {
            foreach ( $this->sms as $key => $message ) {
                if ( is_array($message) && isset($message[ 'message' ]) && isset($message[ 'to' ]) ) {
                    $sms[]     = $message[ 'message' ];
                    $mobiles[] = $message[ 'to' ];
                } elseif ( $key === 'to' ) {
                    $mobiles[] = $message;
                } else {
                    $sms[] = $message;
                }
            }
        }
        if ( $mobiles ) {
            $this->mobiles = array_merge($this->mobiles, $mobiles);
        }
        return $sms;
    }
    /**
     * Rendering template
     *
     * @return array|bool
     */
    private function singleSMSOrTemplate(){
            
            if ( $this->template ) {
                $template          = $this->sms;
                $putDataInTemplate = $sms = $mobiles = [];
                if ( is_array($this->mobiles) ) {
                    foreach ( $this->mobiles as $mobile => $message ) {
                        try {
                            $putData                      = vsprintf($template, $message);
                            $sms[]                        = $putData;
                            $mobiles[]                    = $mobile;
                            $putDataInTemplate[ $mobile ] = $putData;
                        } catch (Exception $exception ) {
                            $putData                            = vsprintf($template, $message[ 1 ]);
                            $sms[]                              = $putData;
                            $mobiles[]                          = $message[ 0 ];
                            $putDataInTemplate[ $message[ 0 ] ] = $putData;
                        }
                    }
                }
                if ( $sms ) {
                    $this->sms = $sms;
                }
                if ( $mobiles ) {
                    $this->mobiles = $mobiles;
                }
                return $this->makeMultiSmsMultiUser();
            }else{
                $this->mobiles = implode(',', array_keys($this->mobiles));
                return $this->makeSingleSmsToUser();
            }
            return false;
        
    }
    /**
     * Sending Single SMS
     *
     * @return array
     */
    protected function makeSingleSmsToUser(){
        $this->gettingParameters($this->sms, $this->numberPrefix . $this->mobiles);
        return $this->sendToServer();
    }
    /**
     * Prepare Sending parameters
     *
     * @param $sms
     * @param $mobiles
     *
     * @return $this
     */
    private function gettingParameters( $sms, $mobiles )
    {
        $this->sendingParameters = [
            'userID'  => $this->_username,
            'passwd'  => $this->_password,
            'sender'  => $this->_sender,
            'message' => $sms,
            'msisdn'  => $mobiles,
        ];
        return $this;
    }
    /**
     * Getting response from api
     *
     * @return mixed
     */
    private function sendToServer(){
        if ($this->debug) {
            return $this->sendingParameters;
        }
        
        #make query string from here and send to server
        $builtUrl = $this->_api.$this->sendingUrl.$this->prefix.'?'.http_build_query($this->sendingParameters);

        if($this->responseDetails){
            #show detailed responce
            $res = @file_get_contents($builtUrl);

            preg_match_all('!\d+!', $res, $result);
            return [
                'success' => $result[ 0 ][ 0 ],
                'failed'  => $result[ 0 ][ 1 ]
            ];

            // if($res == true){
            //     return $res;
            // }else{
            //     return 'Not sent!';
            // }
        }
        return @file_get_contents($builtUrl);
        
    }
    /**
     * Sending Multiple SMS
     *
     * @return array
     */
    protected function makeMultiSmsMultiUser(){
        $response = [];
        $count    = 1;
        if ( is_array($this->sms) ) {
            foreach ( $this->sms as $key => $message ) {
                if ( isset($this->mobiles[ $key ]) ) {
                    $number = $this->numberPrefix . $this->mobiles[ $key ];
                    $this->gettingParameters($message, $number);
                    $response[ 'res-' . $count++ . '-' . $number ] = $this->sendToServer();
                }
            }
        }
        return $response;
    }
    /**
     * Set Sender Details
     *
     * @param $sender
     * @return $this
     */
    public function sender( $sender ){
        $this->_sender = $sender;
        return $this;
    }
    /**
     * Set Debug
     *
     * @param bool $debug
     *
     * @return $this
     */
    public function debug( $debug = true ){
        $this->debug = $debug;
        return $this;
    }
    /**
     * Set Auto Parse
     *
     * @param bool $autoParse
     *
     * @return $this
     */
    public function autoParse( $autoParse = true ){
        $this->autoParse = $autoParse;
        return $this;
    }
    /**
     * Set Response Details
     *
     * @param bool $responseDetails
     * @return $this
     */
    public function details( $responseDetails = true ){
        $this->responseDetails = $responseDetails;
        return $this;
    }
    /**
     * Set Template
     *
     * @param bool $template
     * @return $this
     */
    public function template( $template = true ){
        $this->template = $template;
        return $this;
    }
}
