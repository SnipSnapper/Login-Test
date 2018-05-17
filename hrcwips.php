<?php

#
# HRPrincipalInterface
#
# Version 1.8
# Date: 2017.07.01
# Author: Rene Arends, Hogeschool Rotterdam
# Change log:
#   - Cwips server domain change login.hro.nl -> login.hr.nl.
#
# Version 1.7
# Date: 2014.07.30
# Author: Jeffry Sleddens, Hogeschool Rotterdam
# Change log:
#   - General cleanup.
#
# Version 1.6
# Date: 2012.05.04
# Change log:
#   - Replced RCL parameter with allow parameter.
#
# Version: 1.5 beta
# Date: 2006.11.07
# Author: Martijn van Deventer, Hogeschool Rotterdam
# Change log:
#   - Gracefull failing and redundantie when cwips server not available.
#   - Changed getSSOServerUrl() to check for server availablility.
#   - Changed getSSOServerUrl() to check for server availablility.
#   - Changed ssoAuthenticateEx() to give a retry page if ssoServerUrl not
#     available when redirecting.
#
# Version: 1.2
# Date: 2006.08.07
# Author: Johan Thijs, JOES Internet Services
# Change log:
#   - Changed getServiceUrl() to become cross platform (windows/unix).
#   - Added debug mode -> developers can use this mode to generate output in
#     a browser to aid debugging of problems.
#   - Changed ssoAuthenticateEx() to use the debug option.
#
# Version: 1.1
# Date: 2006.05.01
# Author: Martijn van Deventer, Hogeschool Rotterdam
# 
#
# Dit is de applicatie interface voor het Centraal Web Identificatie en
# Personalidatie Systeem (CWIPS). Via deze interface zal het mogelijk worden
# via een Centrale server van de Hogeschool Rotterdam authenticatie en
# autorisatie van een applicatie te regelen. Ook zal Single Sign On mogelijk
# worden.
#
# Het Centraal Login Systeem van de hogeschool zal ontwikkeld worden in het
# kader van het AAA project 2006. Het systeem is nu gebaseerd op een
# aangepaste JA-SIG CAS (Yale CAS) maar zal waarschijnlijk binnen enkele
# maanden vervangen worden door A-SELECT. Er zal echter getracht worden deze
# interface te behouden zodat de individuele applicaties niet aangepast hoeven
# te worden wanneer de implementatie veranderd.
#
# Het is niet gegarandeerd dat deze code foutloos is en zeker niet dat de
# code compleet is. Ik houd me aanbevolen voor eventuele verbeteringen.
#
# Zaken die (nog) missen:
#   - Degelijke foutafhandeling.
#
#
# Dependencies: PHP curl extension, openssl???.
#


error_reporting(E_ALL ^ E_NOTICE);

global $DEFAULT_SSO_SERVER_URLS;
$DEFAULT_SSO_SERVER_URLS = array(
  "20" => "https://login.hr.nl/v1",
  "30" => "https://login2.hr.nl/v1"
);

define('HPC_SUCCESS',0);
define('HPC_ERR_NO_RESPONSE',1);
define('HPC_ERR_BAD_CREDENTIALS',2);
define('HPC_ERR_INVALID_CREDENTIALS',3);


class HRPrincipalInterface {

  var $SSO_SERVER_URL;
  var $SSO_SERVER_URLS = array();

  # Required credibility level, default "upt"
  var $paramAllow;


  # SSO paramter, possible values:
  #   false: No SSO allowed, always force a log in
  #   force: Only use SSo, do not show login form
  #   preferred: SSO prefered, but if not possible show login form
  #   empty: Use the default
  var $paramSSO;

  var $username = null;
  var $fullname = null;
  var $email = null;
  var $strIdentities = '||';  // heeft de vorm '|role1|role2|role3|'
  
  var $errNo = 0;
  var $bolDebug = false;

  function __construct() {
    # Default constructor
    $this->SSO_SERVER_URLS = $GLOBALS["DEFAULT_SSO_SERVER_URLS"];
    $this->paramAllow='upt';
    $this->paramSSO='';
  }


  function setDebugMode($bolEnableDebugMode) {
    $this->bolDebug = $bolEnableDebugMode;
  }  

  function setAuthenticationLevel($sAllow) {
    # Deprecated!
    $this->paramAllow = $sAllow;
  }

  function setAllowedAuthentication($sAllow) {
    $this->paramAllow = $sAllow;
  }

  function setSSOMode($strSSO) {
    $this->paramSSO = $strSSO;
  }

  function addSSOServerUrl($strSSOServerUrl, $strTicketSuffix) {
    $this->SSO_SERVER_URLS[$strTicketSuffix] = $strSSOServerUrl;
  }

  function ssoAuthenticate() {
    return($this->ssoAuthenticateEx($this->paramAllow, $this->paramSSO));
  }
  
  function ssoAuthenticateEx($authAllow, $ssoMode) {
    # Log in to the SSO server in case the user was not logged in yet. When
    # logged in retrieve a service ticket and check it. Returns true on
    # success and false on failure.

    $bolDebug = $this->bolDebug;
    $strTicket = null;
    $validateResult = null;
    $loginUrl = null;
    
    if (isset($_GET['ticket'])) {
      # We've got a ticket!

      $strTicket = $_GET['ticket'];
      if (($strTicket == 'none') || ($strTicket == '')) return(false);
      return($this->validateServiceTicket($strTicket, $authAllow, $ssoMode));

    } else {
      # No ticket, redirect to login server.

      $strServiceUrl = $this->getServiceUrl();
      if ($bolDebug) {
        if ($strServiceUrl == '') exit("No service URL could be retrieved.");
      }

      # Make sure we get a valid and working URL (checks servers).
      $loginUrl = $this->getSSOServerUrl();
      if ($loginUrl === '') {
        if ($ssoMode === 'force') return(false);
        exit($this->retryResponse());
      }
      
      $loginUrl = $loginUrl . '/login?service=' . $strServiceUrl . "&allow=" . $authAllow;
      if ($ssoMode!="") $loginUrl .= "&sso=" . $ssoMode;
          
      if ($bolDebug) {
        echo("Going to redirect to <a href='$loginUrl'>$loginUrl</a>");
      } else {
        header('Location: ' . $loginUrl);
      }      
      exit();
    }
    return(false);
  }
    
  function ssoLogout($returnUrl='') {
    # Logs off at the SSO server and because of that also possibly from
    # other applications. Note that this works through a redirect and
    # because of that will terminate the PHP session.

    $logoutUrl = $this->SSO_SERVER_URL . '/logout';
    if ($returnUrl != '') {
      $logoutUrl .= '?service=' . $returnUrl;
    } else {
      $strServiceUrl = $this->getServiceUrl();
      if ($bolDebug) {
        if ($strServiceUrl == '') exit("No service URL could be retrieved.");
      }
      $logoutUrl .= '?service=' . $strServiceUrl;
    }
    if ($bolDebug){
      echo("Going to redirect to <a href='$loginUrl'>$loginUrl</a>");
    } else {
      header('Location: ' . $logoutUrl);
    }
    exit();
  }
    
  function getUsername() {
    return($this->username);
  }

  function getEMail() {
    return($this->email);
  }

  function getDisplayname() {
    return($this->fullname);
  }

  function getIdentities() {
    return(explode('|', substr($this->strIdentities, 1, -1)));
  }

  function hasIdentity($strIdentity) {
    if ($strIdentity == 'public') return(true);
    if (strpos($this->strIdentities, '|' . $strIdentity . '|') !== false) return(true);
    return(false);
  }

  function getPrimaryOU() {
    $pos = strpos($this->strIdentities, 'POU=');
    if ($pos !== false) return(substr($this->strIdentities, $pos+4, strpos($this->strIdentities, '|', $pos) - ($pos+4)));
    return(null);
  }

  function getErrorMessage() {
    switch($this->errNo) {
      case HPC_SUCCESS:
        return('Success');
        break;
      case HPC_ERR_NO_RESPONSE:
        return('Service Ticket not validated. Reason: no response from the CAS server');
        break;
      case HPC_ERR_BAD_RESPONSE:
        return('Service Ticket not validated. Reason: bad response from the CAS server');
        break;
      case HPC_ERR_BAD_CREDETIALS:
        return('Service Ticket not validated. Reason: bad username or password');
        break;
    }
  }

  function getError() {
    return($this->errNo);
  }
  
  function initFromRawData($dataMap) {
    $this->SSO_SERVER_URL = $dataMap['SSO_SERVER_URL'];
    $this->paramAllow = $dataMap['paramAllow'];
    $this->paramSSO = $dataMap['paramSSO'];
    $this->username = $dataMap['username'];
    $this->fullname = $dataMap['fullname'];
    $this->email = $dataMap['email'];
    $this->strIdentities = $dataMap['strIdentities'];
  }
  
  function getRawData() {
    $dataMap = array();
    $dataMap['SSO_SERVER_URL'] = $this->SSO_SERVER_URL;
    $dataMap['paramAllow'] = $this->paramAllow;
    $dataMap['paramSSO'] = $this->paramSSO;
    $dataMap['username'] = $this->username;
    $dataMap['fullname'] = $this->fullname;
    $dataMap['email'] = $this->email;
    $dataMap['strIdentities'] = $this->strIdentities;
    return($dataMap);
  }
    


  # Internal functions

  function pingHTTP($url, $checkFor) {
    # Requests the URL and checks if a certain text is present (true) or
    # not (false).
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    
    $text_response = curl_exec ($ch);
    curl_close($ch);
    
    if (strstr($text_response, $checkFor) !== false) return(true);
    return(false);
  }
  
  function retryResponse() {
    return('
      <html>
        <head>
          <title>CWIPS Server problem</title>
          <meta http-equiv="refresh" content="12">
          <script type="text/javascript">
          <!--
            counter=10;
            function Count() { 
              document.getElementById("counter").innerHTML = counter--;
              if (counter == -1) {
                clearInterval(interval);
                window.location.reload();
              }
            }
            interval = window.setInterval("Count()", 1000);
          -->
          </script>
        </head>
        <body style="margin:20;text-align:center;line-height:2em;">
          CWIPS authentication service too busy, restarting or unavailable.<br/>
          Retrying in <span id="counter">10</span> seconds....<br/>
          <a href="'.$this->getSelfUrl().'">retry now</a><br/>
          We are sorry for the inconvenience.<br/>
          If the problem persists, please contact your local Servicedesk.
          <script type="text/javascript">
          <!--
            Count();
          -->
          </script>
        </body>
      </html>
    ');
  }
  
  function validateServiceTicket($strTicket, $authAllow, $ssoMode) {
    # Build the URL to validate the ticket.
    $strServiceUrl=$this->getServiceUrl();
    if ($bolDebug) {
      if ($strServiceUrl=='') exit("No service URL could be retrieved.");
    }
    
    $validateUrl = $this->getSSOServerUrl();
    if ($validateUrl==='') return(false);
    
    $validateUrl = $validateUrl . '/validate?ticket=' . $strTicket . '&service=' . $strServiceUrl . '&allow=' . $authAllow;
    if ($ssoMode!='') $validateUrl .= '&sso=' . $ssoMode;

    $ch = curl_init($validateUrl);

    # Verify the server's certificate corresponds to it's name.
    #   1: Check the existence of a common name in the SSL peer certificate. 
    #   2: Check the existence of a common name and also verify that it
    #      matches the hostname.
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    # Do not verify the certificate itself.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    # Return the CURL output into a variable.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $text_response = curl_exec ($ch);
    if ($text_response === false) {
      # Close the CURL session.
      curl_close($ch);
      return(false);
    }
    curl_close($ch);
  
    # Analyze the result depending on the version.
    if (preg_match('/^no\n/', $text_response)) {
      $this->_errNo=HPC_ERR_BAD_CREDENTIALS;
      return(false);
    }
  
    if (!preg_match('/^yes\n/', $text_response)) {
      $this->_errNo=HPC_ERR_INVALID_RESPONSE;
      return(false);
    }
  
    # ST has been validated, extract the user name and other values.
    $arr = preg_split('/\n/',$text_response);
    $this->netId=trim($arr[1]);
    $this->parseNetId($this->netId);
    return(true);
  }
  
  function getSSOServerUrl() {
    # Returns the URL of the login server. If no URL is available, return ''.

    if (isset($_GET['ticket'])) {
      $ticket = $_GET['ticket'];

      # Get the ticket suffix.
      $ticketSuffix = substr(strrchr($ticket, '-'), 1);

      # Get the URL corresponding to the suffix.
      return($this->SSO_SERVER_URLS[$ticketSuffix]);

    } else {
      foreach($this->SSO_SERVER_URLS as $url) {
        if ($this->pingHTTP($url . '/ping/', 'ping ok')) return($url);
      }
    }
    return('');
  }

  function getSelfUrl() {
    # Builds a URL for our application, including the query string.

    # Determine the protocol.
    $final_uri = ($_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
    $final_uri .= '://';

    if (empty($_SERVER['HTTP_X_FORWARDED_SERVER'])) {
      if (empty($_SERVER['SERVER_NAME'])) {
        $final_uri .= $_SERVER['HTTP_HOST'];
      } else {
        $final_uri .= $_SERVER['SERVER_NAME'];
      }
    } else {
      $final_uri .= $_SERVER['HTTP_X_FORWARDED_SERVER'];
    }

    # Add port number.
    if (($_SERVER['HTTPS']=='on' && $_SERVER['SERVER_PORT']!=443) || ($_SERVER['HTTPS']!='on' && $_SERVER['SERVER_PORT']!=80)) {
      $final_uri .= ':';
      $final_uri .= $_SERVER['SERVER_PORT'];
    }
        
    $final_uri .= $_SERVER["PHP_SELF"];
        
    # Query string parameters.
    if (!empty($_SERVER["QUERY_STRING"])) $final_uri .= '?'.$_SERVER["QUERY_STRING"];
    
    return($final_uri);
  }

  function getServiceUrl() {
    # Builds a service URL that will be sent as a query string parameter to the
    # validation service.

    # Determine the protocol.
    $final_uri = $this->getSelfUrl();
    
    # Remove the ticket if present in the CGI parameters.
    $final_uri = preg_replace('/&ticket=[^&]*/', '', $final_uri);
    $final_uri = preg_replace('/\?ticket=[^&;]*/', '?', $final_uri);
    $final_uri = preg_replace('/\?$/', '', $final_uri);
    
    # Since this url is being used in a querystring: URL encode it properly.
    $final_uri = urlencode($final_uri);
    
    if ($bolDebug) echo("getServiceUrl()=" . $final_uri . "<br/>\n");
    return($final_uri);
  }
  
  function parseNetId($strNetId) {
    if (empty($strNetId)) return;
    $parts = explode('|', $strNetId);
    $this->username = $parts[0];
    $this->fullname = $parts[1];
    $this->email = $parts[2];
    unset($parts[0]);
    unset($parts[1]);
    unset($parts[2]);
    
    $this->strIdentities = '|' . implode($parts, '|') . '|';
  }

}
