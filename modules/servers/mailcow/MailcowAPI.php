<?php

namespace Mailcow;

require_once __DIR__ . '/lib/Curl/Curl.php';
use Mailcow\Curl as MailcowCurl;

class MailcowAPI{
  
  /**
   * Not truly an API because it simply creates a session like a normal client by
   * simulating login and submits POST requests to server like a logged in user
   */
  
  private $curl;
  private $cookie;
  public $baseurl;
  public $aliases = 500;
  public $MAILBOXQUOTA;
  
  public function __construct($server_hostname, $server_login, $server_password, $mquota = 30720){
    
    $this->baseurl = 'https://' . $server_hostname;
    $this->MAILBOXQUOTA = $mquota;

    $this->curl = new MailcowCurl();
    $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
    $this->curl->setCookieFile('');
    $this->curl->setCookieJar(dirname(__FILE__) . '/cookiejar.txt');
    
    $data = array(
      'login_user' => $server_login,
      'pass_user' => html_entity_decode($server_password),
    );
    
    //Create session
    $this->curl->post($this->baseurl, $data);
    //$this->cookie = $this->curl->getCookie('PHPSESSID');
        
  }
  
  /**
   * Domain functions
   */
  
  public function addDomain($domain, $num_mailboxes){
    
    return $this->_addOrEditDomain($domain, $num_mailboxes, 'create');
    
  }
  
  public function editDomain($domain, $num_mailboxes){
    
    return $this->_addOrEditDomain($domain, $num_mailboxes, 'edit');
    
  }
  
  public function disableDomain($domain, $num_mailboxes){
    
    return $this->_addOrEditDomain($domain, $num_mailboxes, 'disable');
    
  }
  
  public function activateDomain($domain, $num_mailboxes){
    
    return $this->_addOrEditDomain($domain, $num_mailboxes, 'activate');
    
  }
  
  public function removeDomain($domain){
    
    return $this->_removeDomain($domain);
      
  }
  
  private function _removeDomain($domain){
    
    $data = array( /** Those commented out hopefully aren't necessary to submit... **/
      'domain' => urlencode($domain),
      'mailbox_delete_domain' => '',
    );
    
    $this->curl->post($this->baseurl . '/mailbox.php', $data);
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      return $this->errorCheck( $this->curl->response );
      
    }
    
  }
  
  private function _addOrEditDomain($domain, $num_mailboxes, $action){
    
    $data = array( /** Those commented out hopefully aren't necessary to submit... **/
      'domain' => urlencode($domain),
      'description' => 'None',
      'aliases' => $this->aliases,
      'mailboxes' => $num_mailboxes,
      'maxquota' => $this->MAILBOXQUOTA, //per mailbox
      'quota' => $this->MAILBOXQUOTA * $num_mailboxes, //for domain
      //'backupmx' => 'on',
      //'relay_all_recipients' => 'on',
    );
    
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
      
      $this->_restartSogo(); //on successful creation, restart SOGo
      
      //error output for this is different; can't use errorCheck function      
      return $this->curl->response;
      
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
     
     if ($this->curl->error) {
       
       return array( 
         'error' => $this->curl->errorCode,
         'error_message' => $this->curl->errorMessage,
       );
       
     } else {
       
       return $this->errorCheck( $this->curl->response );
       
     }
       
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
        $data['edit_domain_admin'] = '';
        break;
        
    }
    
    $this->curl->post($this->baseurl . '/admin.php', $data);
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      return $this->errorCheck( $this->curl->response );
      
    }
    
  }
  
  public function getUsageStats($domains){
    
    $domains_d = json_decode( $this->curl->get( $this->baseurl . '/api/v1/domain/all', array() ) );
    $mailboxes_d = json_decode( $this->curl->get( $this->baseurl . '/api/v1/mailbox/all', array() ) );
    
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