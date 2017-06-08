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
    $this->curl->setCookieFile('');
    $this->curl->setCookieJar(dirname(__FILE__) . '/cookiejar.txt');
    
    $data = array(
      'login_user' => $params['serverusername'],
      'pass_user' => html_entity_decode($params['serverpassword']),
    );
    
    //Create session
    $this->curl->post($this->baseurl, $data);
    //$this->cookie = $this->curl->getCookie('PHPSESSID');
        
  }
  
  /**
   * Domain functions
   */
  
  public function addDomain($params){
    
    return $this->_addOrEditDomain($params['domain'], $params['configoptions'], 'create');
    
  }
  
  public function editDomain($params){
    
    return $this->_addOrEditDomain($params['domain'], $params['configoptions'], 'edit');
    
  }
  
  public function disableDomain($params){
    
    return $this->_addOrEditDomain($params['domain'], $params['configoptions'], 'disable');
    
  }
  
  public function activateDomain($params){
    
    return $this->_addOrEditDomain($params['domain'], $params['configoptions'], 'activate');
    
  }
  
  public function removeDomain($domain){
    
    return $this->_removeDomain($domain);
      
  }
  
  private function _removeDomain($domain){
    
    $data = array(
      'domain' => urlencode($domain),
      'mailbox_delete_domain' => '',
    );
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    return $this->errorCheckedResponse();
    
  }
  
  private function _addOrEditDomain($domain, $product_config, $action){
    
    $data = array(
      'domain' => urlencode($domain),
      'description' => 'None',
      'aliases' => $this->aliases,
      //'backupmx' => 'on',
      //'relay_all_recipients' => 'on',
    );
    
    /** Number of Mailbox Based Limits **/
    if ( !empty($product_config['Email Accounts']) ){
      $data['mailboxes'] = $product_config['Email Accounts'];
      $data['maxquota'] = $this->MAILBOXQUOTA; //per mailbox
      $data['quota'] = $this->MAILBOXQUOTA * $product_config['Email Accounts']; //for total domain
    }
    
    //logActivity($product_config['Disk Space']); ////DEBUG
    logActivity($this->MAILBOXQUOTA); ////DEBUG
    
    /** Domain Storage Based Limits **/
    if ( !empty($product_config['Disk Space']) ){
      $data['mailboxes'] = $this->UNL_MAILBOXES;
      $data['maxquota'] = $product_config['Disk Space']; //per mailbox
      $data['quota'] = $product_config['Disk Space']; //for total domain
    }
    
    if ($action != 'disable'){
      $data['active'] = 'on';
    }
    
    //logActivity("Add a domain? $addDomain"); //DEBUG
    
    switch ($action){
      
      case 'create':
        $data['mailbox_add_domain'] = '';
        break;
      case 'edit':
      case 'disable':
      case 'activate':
        $data['mailbox_edit_domain'] = '';
        break;
        
    }
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      if ($action == 'create'){
        $this->_restartSogo(); //on successful creation, restart SOGo
        return $this->curl->response; //can't use errorCheck function after running _restartSogo() as errors are shown differently.
      }
      else{
        return $this->errorCheckedResponse();
      }
        
    }
      
  }
  
  
  /**
   * Domain Administrator Functions
   */
   
   public function addDomainAdmin($domain, $username, $password){
     
     return $this->_addOrEditDomainAdmin($domain, $username, $password, 'create');
     
   }
   
   public function editDomainAdmin($domain, $username){
     
     return $this->_addOrEditDomainAdmin($domain, $username, null, 'edit');
     
   }
   
   public function disableDomainAdmin($domain, $username){
     
     return $this->_addOrEditDomainAdmin($domain, $username, null, 'disable');
     
   }
   
   public function activateDomainAdmin($domain, $username){
     
     return $this->_addOrEditDomainAdmin($domain, $username, null, 'activate');
     
   }
   
   public function changePasswordDomainAdmin($domain, $username, $password){
     
     return $this->_addOrEditDomainAdmin($domain, $username, $password, 'changepass');
     
   }
   
   public function removeDomainAdmin($username){
     
     return $this->_removeDomainAdmin($username);
     
   }
   
   private function _removeDomainAdmin($username){
     
     $data = array( /** Those commented out hopefully aren't necessary to submit... **/
       'username' => $username,
       'delete_domain_admin' => '',
     );
     
     $this->curl->post($this->baseurl . '/admin.php', $data);
     
     return $this->errorCheckedResponse();
       
   }
  
  private function _addOrEditDomainAdmin($domain, $username, $password, $action){
    
    $data = array(
      'username' => $username,
      'domain[]' => urlencode($domain),
    );
    
    if ($action == 'create' || $action == 'changepass'){
      $data['password'] = $password;
      $data['password2'] = $password;
    }
    else{
      $data['password'] = '';
      $data['password2'] = '';
    }
    
    if ($action != 'disable'){
      $data['active'] = 'on';
    }
    
    //logActivity("Add a domain? $addDomain"); //DEBUG
    
    switch ($action){
      
      case 'create':
        $data['add_domain_admin'] = '';
        break;
      case 'edit':
      case 'disable':
      case 'activate':
      case 'changepass':
        $data['username_now'] = $username;
        $data['edit_domain_admin'] = '';
        break;
        
    }
    
    $this->curl->post($this->baseurl . '/admin.php', $data);
    
    return $this->errorCheckedResponse();
    
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
    
    return $this->errorCheckedResponse();
    
  }
  
  private function _removeResource($name){
    
    $data = array(
      'name' => $name,
      'mailbox_delete_resource' => '',
    );
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    return $this->errorCheckedResponse();
    
  }
  
  public function getUsageStats($domains){
    
    $domains_d = $this->_getDomains();
    $mailboxes_d = $this->_getMailboxes();
    
    $usagedata = array();
    
    foreach ($domains_d as $dominfo){
      //Init disk usage to 0 and set quota to actual value. 
      $usagedata[$dominfo->domain_name] = array( 'disklimit' => ($dominfo->max_quota_for_domain)/(1024*1024), 'diskusage' => 0 );
    }
    
    foreach ($mailboxes_d as $mbinfo){
      //Increase disk usage for domain by this mailboxes' usage
      $usagedata[$mbinfo->domain]['diskusage'] += ($mbinfo->quota_used)/(1024*1024);
    }
    
    return $usagedata;
    
  }
  
  private function _getDomains(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/domain/all', array() ) );
    
  }
  
  private function _getMailboxes(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/mailbox/all', array() ) );
    
  }
  
  private function _getResources(){
    
    return json_decode( $this->curl->get( $this->baseurl . '/api/v1/resource/all', array() ) );
    
  }
  
  private function _restartSogo(){
    
    $this->curl->get( $this->baseurl . '/call_sogo_ctrl.php', array('ACTION' => 'stop') );
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      $this->curl->get( $this->baseurl . '/call_sogo_ctrl.php', array('ACTION' => 'start') );
      
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
  
  private function errorCheckedResponse(){
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      return $this->errorCheck( $this->curl->response );
      
    }
    
  }
  
  /* Takes an HTML response string as input, parses for errors */
  private function errorCheck($response){
    
    $error_pattern = '/<div.*alert-danger.*<\/a>(.*)<\/div>/sim';
    $success_pattern = '/<div.*alert-success.*<\/a>(.*)<\/div>/sim';
    
    if ( preg_match($success_pattern, $response, $matches) ){
      return strip_tags( $matches[1] );
    }
    else if ( preg_match($error_pattern, $response, $matches) ){
      throw new \Exception( 'Error: ' . strip_tags($matches[1]) );
    }
    else{
      throw new \Exception( 'Error: unexpected response' );
    }
    
  }
  
} /* Close MailCow_API class */

?>