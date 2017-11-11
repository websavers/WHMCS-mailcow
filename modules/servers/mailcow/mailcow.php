<?php
/**
 * MailCow Provisioning Module by Websavers Inc
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Require any libraries needed for the module to function.
require_once 'lib/MailcowAPI.php';
use Mailcow\MailcowAPI;
use WHMCS\Input\Sanitize;
use WHMCS\Database\Capsule;

// Also, perform any initialization required by the service's library.

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Provisioning_Module_Meta_Data_Parameters
 *
 * @return array
 */
function mailcow_MetaData()
{
    return array(
        'DisplayName' => 'MailCow',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '80', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '443', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}

/**
 * Define product configuration options.
 *
 * The values you return here define the configuration options that are
 * presented to a user when configuring a product for use with the module. These
 * values are then made available in all module function calls with the key name
 * configoptionX - with X being the index number of the field from 1 to 24.
 *
 * You can specify up to 24 parameters, with field types:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each and their possible configuration parameters are provided in
 * this sample function.
 *
 * @return array
 */
function mailcow_ConfigOptions(){

    return array(
        'Default Mailbox Quota' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '1024',
            'Description' => 'When specifying per-account limits, this storage limit will be applied to each mailbox. Enter in megabytes',
        )
    );
}

/**
 * Provision a new instance of a product/service.
 *
 * Attempt to provision a new instance of a given product/service. This is
 * called any time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this can be any of:
 * * When a new order is placed
 * * When an invoice for a new order is paid
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function mailcow_CreateAccount(array $params)
{

    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->addDomain($params);
    
    } catch (Exception $e) {

      return $e->getMessage();
    }

    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->addDomainAdmin($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */

function mailcow_SuspendAccount(array $params)
{
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->disableDomain($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }
    
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->disableDomainAdmin($params['domain'], $params['username']);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */

function mailcow_UnsuspendAccount(array $params)
{
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->activateDomain($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }
    
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->activateDomainAdmin($params['domain'], $params['username']);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
 
function mailcow_TerminateAccount(array $params)
{
    try {
      
      //Remove Mailboxes
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeAllMailboxes($params['domain']);
      
      //Remove Resources
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeAllResources($params['domain']);
      
      //Remove Domain
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeDomain($params);
      
      //Remove Domain Admin
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeDomainAdmin($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Change the password for an instance of a product/service.
 *
 * Called when a password change is requested. This can occur either due to a
 * client requesting it via the client area or an admin requesting it from the
 * admin side.
 *
 * This option is only available to client end users when the product is in an
 * active status.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
 
function mailcow_ChangePassword(array $params)
{
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->changePasswordDomainAdmin($params['domain'], $params['username'], $params['password']);
      
      logModuleCall(
          'mailcow',
          __FUNCTION__,
          print_r($params, true),
          print_r($result, true),
          null
      );
      
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Upgrade or downgrade an instance of a product/service.
 *
 * Called to apply any change in product assignment or parameters. It
 * is called to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.
 *
 * This same function is called for upgrades and downgrades of both
 * products and configurable options.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function mailcow_ChangePackage(array $params)
{
    try {
        
        $mailcow = new MailcowAPI($params);
        $result = $mailcow->editDomain($params);
        
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            print_r($result, true),
            null
        );
        
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Test connection with the given server parameters.
 *
 * Allows an admin user to verify that an API connection can be
 * successfully made with the given configuration parameters for a
 * server.
 *
 * When defined in a module, a Test Connection button will appear
 * alongside the Server Type dropdown when adding or editing an
 * existing server.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return array
 */
function mailcow_TestConnection(array $params)
{
    try {
        // Call the service's connection test function.

        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $success = false;
        $errorMsg = $e->getMessage();
    }

    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}

 
/**
 * @param $params
 * @return string
 */
function mailcow_AdminLink(array $params)
{
    $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $secure = ($params["serversecure"]) ? 'https' : 'http';
    if (empty($address)) {
        return '';
    }

    $form = sprintf(
        '<form action="%s://%s/index.php" method="post" target="_blank">' .
        '<input type="hidden" name="login_user" value="%s" />' .
        '<input type="hidden" name="pass_user" value="%s" />' .
        '<input type="submit" value="%s">' .
        '</form>',
        $secure,
        Sanitize::encode($address),
        Sanitize::encode($params["serverusername"]),
        Sanitize::encode($params["serverpassword"]),
        'Login to panel'
    );

    return $form;
}

/**
 * @param $params
 * @return string
 */
function mailcow_ClientArea(array $params) {
  
  $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
  $secure = ($params["serversecure"]) ? 'https' : 'http';
  if (empty($address)) {
      return '';
  }

  $form = sprintf(
      '<form action="%s://%s/index.php" method="post" target="_blank">' .
      '<input type="hidden" name="login_user" value="%s" />' .
      '<input type="hidden" name="pass_user" value="%s" />' .
      '<input type="submit" class="btn btn-primary btn-large" value="%s">' .
      '</form>',
      $secure,
      Sanitize::encode($address),
      Sanitize::encode($params["username"]),
      Sanitize::encode($params["password"]),
      'Open Control Panel'
  );

  return $form;
    
}

/**
 * Admin Area Client Login link
 */
function mailcow_LoginLink(array $params){ /** Not working Need to use JS to submit form **/
/*
  return "<a href='https://{$params['serverhostname']}/index.php?login_user={$params['username']}&pass_user={$params['password']}' 
    class='btn btn-primary' 
    target='_blank'>
    <i class='fa fa-login'></i> Login as User</a>";
*/
}


/**
 * @param $params
 * @return string
 */
function mailcow_UsageUpdate($params) {

    $query = Capsule::table('tblhosting')
        ->where('server', $params["serverid"]);

    $domains = array();
    /** @var stdClass $hosting */
    foreach ($query->get() as $hosting) {
        $domains[] = $hosting->domain;
    }
    
    try {
      
      $mailcow = new MailcowAPI($params);
      $domainsUsage = $mailcow->getUsageStats($domains);
      
      logModuleCall(
          'mailcow',
          __FUNCTION__,
          $params,
          print_r($domainsUsage, true),
          null
      );
      
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    
    //logActivity(print_r($domainsUsage, true)); ////DEBUG
    
    foreach ( $domainsUsage as $domainName => $usage ) {

        Capsule::table('tblhosting')
            ->where('server', $params["serverid"])
            ->where('domain', $domainName)
            ->update(
                array(
                    "diskusage" => $usage['diskusage'],
                    "disklimit" => $usage['disklimit'],
                    //"bwusage" => $usage['bwusage'],
                    //"bwlimit" => $usage['bwlimit'],
                    "lastupdate" => Capsule::table('tblhosting')->raw('now()'),
                )
            );
    }

    return 'success';
}