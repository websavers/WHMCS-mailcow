<?php

namespace Mailcow;

require_once __DIR__ . '/Curl/Curl.php';
require_once __DIR__ . '/Curl/ArrayUtil.php';
require_once __DIR__ . '/Curl/CaseInsensitiveArray.php';
//use mailcow\lib\Curl as MailcowCurl;

class MailcowAPI{
  
  /**
   * Not truly an API because it simply creates a session like a normal client by
   * simulating login and submits POST requests to server like a logged in user
   */
  
  private $curl;
  private $cookie;
  private $csrf_token;
  
  public $baseurl;
  public $aliases = 500;
  public $MAILBOXQUOTA = 1024;
  public $UNL_MAILBOXES = 500;
  
  public function __construct($params){
    
    //Module setting for mailbox quota
    if ( !empty($params['configoption1']) ){
      $this->MAILBOXQUOTA = $params['configoption1'];
    }
    
    $this->baseurl = 'https://' . $params['serverhostname'];

    $this->curl = new Curl();
    $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
    $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false); //ignore certificate issues
    $this->curl->setCookieFile('');
    $this->curl->setCookieJar(dirname(__FILE__) . '/cookiejar.txt');
    
    $data = array(
      'login_user' => $params['serverusername'],
      'pass_user' => html_entity_decode($params['serverpassword']),
    );
    
    //Create session
    $this->curl->post($this->baseurl, $data);
    if ($this->curl->error) {  
      throw new \Exception('Error creating session: ' . $this->curl->errorMessage);
    }
    else{
      $this->csrf_token = $this->getToken($this->error_checking('init', $data));
      //$this->cookie = $this->curl->getCookie('PHPSESSID'); //May not be essential
      $this->curl->setHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8'); 
    }
        
  }
  
  /**
   * Domain functions
   */
  
  public function addDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'create');
    
  }
  
  public function editDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'edit');
    
  }
  
  public function disableDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'disable');
    
  }
  
  public function activateDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'activate');
    
  }
  
  public function removeDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'remove');
      
  }
  
  private function _manageDomain($domain, $product_config, $action){
    
    $attr = array(
      'description' => '',
      'aliases' => $this->aliases,
      //'backupmx' => 0,
      //'relay_all_recipients' => 0,
    );
    
    /** Number of Mailbox Based Limits **/
    if ( !empty($product_config['Email Accounts']) ){
      $attr['mailboxes'] = $product_config['Email Accounts'];
      $attr['maxquota'] = $this->MAILBOXQUOTA; //per mailbox
      $attr['quota'] = $this->MAILBOXQUOTA * $product_config['Email Accounts']; //for total domain
    }
        
    /** Domain Storage Based Limits **/
    if ( !empty($product_config['Disk Space']) ){
      $attr['mailboxes'] = $this->UNL_MAILBOXES;
      $attr['maxquota'] = $product_config['Disk Space']; //per mailbox
      $attr['quota'] = $product_config['Disk Space']; //for total domain
    }
        
    $uri = '/api/v1/edit/domain';
    
    switch ($action){
      
      case 'create':       
        $uri = '/api/v1/add/domain';
        $attr['domain'] = $domain;
        $attr['active'] = 1;
        break;
      case 'edit':
      case 'disable':
        $data['items'] = json_encode(array($domain));
        $attr['active'] = 0;
        break;
      case 'activate':
        $data['items'] = json_encode(array($domain));
        $attr['active'] = array('0','1');
        break;
      case 'remove':
        $uri = '/api/v1/delete/domain';
        $data['items'] = json_encode(array($domain));
        break;
        
    }
    /* For some reason they include it twice. Once as part of attr and once outside */
    $attr['csrf_token'] = $this->csrf_token;
    $data['csrf_token'] = $this->csrf_token; 
    
    $data['attr'] = json_encode($attr);
        
    $this->curl->post($this->baseurl . $uri, $data);
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
            
    if ( $action == 'create' && empty($this->curl->error) ){
      $this->_restartSogo(); //on successful creation, restart SOGo
      return $this->curl->response; //can't use errorCheck function after running _restartSogo() as errors are shown differently.
    }
    else{
      return $result;
    }
      
  }
  
  
  /**
   * Domain Administrator Functions
   */
   
   public function addDomainAdmin($params){
    
     return $this->_manageDomainAdmin($params['domain'], $params['username'], $params['password'], 'create');
     
   }
   
   public function editDomainAdmin($domain, $username){
     
     return $this->_manageDomainAdmin($domain, $username, null, 'edit');
     
   }
   
   public function disableDomainAdmin($domain, $username){
     
     return $this->_manageDomainAdmin($domain, $username, null, 'disable');
     
   }
   
   public function activateDomainAdmin($domain, $username){
     
     return $this->_manageDomainAdmin($domain, $username, null, 'activate');
     
   }
   
   public function changePasswordDomainAdmin($domain, $username, $password){
     
     return $this->_manageDomainAdmin($domain, $username, $password, 'changepass');
     
   }
   
   public function removeDomainAdmin($params){
     
     return $this->_manageDomainAdmin($params['domain'], $params['username'], null, 'remove');
     
   }
  
  private function _manageDomainAdmin($domain, $username, $password, $action){
        
    $uri = '/api/v1/edit/domain-admin'; //default
    
    switch ($action){
      
      case 'create':
        $uri = '/api/v1/add/domain-admin';
        $attr['domains'] = $domain;
        $attr['username'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        $attr['active'] = 1;
        break;
      case 'edit':
      case 'changepass':
        $data['items'] = json_encode(array($username));
        $attr['domains'] = $domain;
        $attr['username_new'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        $attr['active'] = 1;
        break;
      case 'disable':
        $data['items'] = array($username);
        $attr['active'] = 0;
        break;
      case 'activate':
        $data['items'] = json_encode(array($username));
        $attr['active'] = array('0','1');
        $attr['username_new'] = $username;
        $attr['domains'] = $domain;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        break;
      case 'remove':
        $data['items'] = json_encode(array($username));
        $uri = '/api/v1/delete/domain-admin';
        break;
        
    }
    
    $attr['csrf_token'] = $this->csrf_token; 
    $data['csrf_token'] = $this->csrf_token;
    $data['attr'] = json_encode($attr);
        
    $this->curl->post($this->baseurl . $uri, $data);
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
    return $result;
    
  }
  
  public function removeAllMailboxes($domain){
    
    if ( isset($domain) && !empty($domain) ){
      
      $mailboxes = $this->_getMailboxes();
      
      foreach ($mailboxes as $mbinfo){
        if ( $mbinfo->domain === $domain ){
            $this->_removeMailbox($mbinfo->username);
        }
      }
      
    }
    else{
      return "Error: Domain not provided.";
    }
    
  }
  
  public function removeAllResources($domain){
    
    if ( isset($domain) && !empty($domain) ){
      
      $resources = $this->_getResources();
      
      foreach ($resources as $rinfo){
        if ( $rinfo->domain === $domain ){
            $this->_removeResource($rinfo->name);
        }
      }
      
    }
    else{
      return "Error: Domain not provided.";
    }
    
  }
  
  private function _removeMailbox($mailbox){
    
    $data = array(
      'username' => $mailbox,
      'mailbox_delete_mailbox' => '',
    );
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    return $this->error_checking();
    
  }
  
  private function _removeResource($name){
    
    $data = array(
      'name' => $name,
      'mailbox_delete_resource' => '',
    );
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    return $this->error_checking();
    
  }
  
  public function getUsageStats($domains){
    
    $domains_d = $this->_getDomains();
    $mailboxes_d = $this->_getMailboxes();
        
    $usagedata = array();
    
    foreach ($domains_d as $dominfo){
      //Init disk usage to 0 and set quota to actual value. 
      $usagedata[$dominfo->domain_name] = array( 
          'disklimit' => ($dominfo->max_quota_for_domain)/(1024*1024), 
          'diskusage' => 0,
      );
    }

    foreach ($mailboxes_d as $mbinfo){
      //Increase disk usage for domain by this mailboxes' usage
      $usagedata[$mbinfo->domain]['diskusage'] += ($mbinfo->quota_used)/(1024*1024);
    }
    
    return $usagedata;
    
  }
  
  private function _getDomains(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/get/domain/all', array() ) );
    
  }
  
  private function _getMailboxes(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/get/mailbox/all', array() ) );
    
  }
  
  private function _getResources(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/resource/all', array() ) );
    
  }
  
  private function _restartSogo(){
    
    $this->curl->get( $this->baseurl . '/inc/call_sogo_ctrl.php', array('ACTION' => 'stop') );
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      $this->curl->get( $this->baseurl . '/inc/call_sogo_ctrl.php', array('ACTION' => 'start') );
      
      if ($this->curl->error) {
        
        return array( 
          'error' => $this->curl->errorCode,
          'error_message' => $this->curl->errorMessage,
        );
        
      } else {
                
        return $this->curl->response;
        
      }
      
    }
    
  }
  
  private function error_checking($action = null, $data_sent = null){
    
    // first check for standard HTTP errors like 404
    if ($this->curl->error){
      throw new \Exception($this->curl->errorCode . ': ' . $this->curl->errorMessage);
    } 
    // then check for mailcow errors
    else {
      $json = $this->curl->response;
      
      logModuleCall(
          'mailcow',
          $action,
          print_r($data_sent, true),
          print_r($json, true),
          null
      );

      if ($json->type == "error" || $json->type == "danger"){
        throw new \Exception($json->msg);
      }
    }
    
    return $this->curl->response;
    
  }
  
  private function getToken($html){
    
    $token_pattern = "/var csrf_token = '([A-Za-z\d]+)';/sim";
    
    if ( preg_match($token_pattern, $html, $matches) ){
      return strip_tags( $matches[1] );
    }
    
  }
  
} /* Close MailCow_API class */

?>