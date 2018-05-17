<?php
/*
 * Simple "Centraal Web Identificatie en Personalisatie Systeem" voorbeeld login pagina
 * Version: 1.1
 * date: 1-05-2006
 * author: Martijn van Deventer, Hogeschool Rotterdam
 *
 *  Het Centraal Web  Identificatie en Personalisatie Systeem (CWIPS)  van hogeschool zal ontwikkeld worden in het kader van het AAA project 2006.
 *  Het systeem is nu in BETA en gebaseerd op JA-SIG CAS (Yale CAS) maar zal waarschijnlijk binnen enkele maanden vervangen
 *  worden door A-SELECT. Er zal echter getracht worden de PHP interface in dit voorbeeld te behouden.
 *
 *  Dependencies: PHP curl extension, openssl???.
  *
 *  Simpel voorbeeld, kinderachtige code, om als voorbeeld te dienen. 
 *
 *  Er worden geen sessies gebruikt, dus zal er elke keer een nieuwe ticket aangevraagd moeten worden bij de CWIPS server,
 *  hetgeen onnodig performance vraagt van de CWIPS server en de gebruiker veel vertraging oplevert. 
 *  Dit maakt deze code dus NIET geschikt voor een productie systeem!
 *
 *  Een refresh van deze page zal niet werken, aangezien de ticket zich nog in de URL bevindt en dat ticket is maar eenmalig en een korte termijn geldig.
  */
  require_once(app_path() . '\cwips\hrcwips.php');
  session_start();
  global $principal;
  $principal = new HRPrincipalInterface();
  //$principal->initFromRawData()$_SESSION[]
  
  //======================================================================================================
  // hulp functies 
  // Ben je al op deze applicatie ingelogd?
  function IsAuthenticated() {
    global $principal;
    if (isset($_SESSION['cwipsPrincipalData'])) {
      $principal->initFromRawData($_SESSION['cwipsPrincipalData']);
      return true;
    }
    return false;
  }
  
  // Vraagt aan CWIPS of de gebruiker daar al is ingelogd
  // Zo niet, dan wordt de gebruiker niet gevraagd om in te loggen, maar zal direct terugkeren naar deze applicatie, de 
  // gebruiker zal hier niets van merken
  // Als de gebruiker wel al ingelogd was zal er een ticket teruggegeven worden en kunnen we de gebruiker ook authenticeren
  // geeft de loginnaam terug waarmee de gebruiker op CWIPS is ingelogd of null als niet ingelogd
  function IsCWIPSAuthenticated() {
    $cwipsPrincipal = new HRPrincipalInterface();
    $cwipsPrincipal->setSSOMode('force');  // forceer Single Sign On
    $result = $cwipsPrincipal->ssoAuthenticate();
    if ($result == false) return null;
    
    return $cwipsPrincipal->getUsername();
  }
  
  // Probeert de gebruiker te authenticeren via een lager authenticationLevel (Required Credibility Level (RCL))
  // Als RCL <= 65 dan zal geprobeerd worden via NTLM te authenticeren, lukt dat niet
  // dan wordt de gebruiker teruggestuurd naar deze applicatie zonder dat hem/haar gevraagd
  // wordt om in te loggen
  // NTLM wil zeggen dat we de browser opdracht geven om te bewijzen dat op de desktop (pc) 
  // is ingelogd met een geldige gebruiker. De CWIPS server zal dit alleen proberen wanneer
  // er gebruik gemaakt wordt van een HR image en de PC in het HR netwerk staat.
  // De default RCL=80 en dat wil zeggen minimaal met username/password. De RCL is een onwetenschappelijk 
  // zekerheids percentage of de persoon werkelijk is wie hij zegt dat hij is.
  function TryHRDesktopAuthentication() {
    global $principal;
    $result = $principal->ssoAuthenticateEx(65, 'force');
    return $result;
  }
  
  // Authenticeer de gebruiker bij CWIPS, als hij al ingelogd was, prima, zo niet laat de gebruiker dan al inloggen
  // en geef een ticket terug.
  function Authenticate() {
    global $principal;
    $principal->setSSOMode('preferred');
    $result = $principal->ssoAuthenticate();
    if ($result === true)
    {
      $_SESSION['cwipsPrincipalData'] = $principal->getRawData();
    }
    return $result;
  }
  
  // Forceert authenticatie, ook al was de gebruiker al ingelogd op CWIPS, dan nog wordt er
  // opnieuw ingelogd.
  function ForceAuthentication() {

    global $principal;
    $principal->setSSOMode('false'); // SSO mag niet plaatsvinden, dus opnieuw inloggen
    $result = $principal->ssoAuthenticate();
    if ($result === true) {
      $_SESSION['cwipsPrincipalData'] = $principal->getRawData();
    }
    return $result;
  }
  
  // logged uit van deze applicatie, je blijft wel ingelogd op CWIPS
  function LogoutApp() {
    session_unset();
    session_destroy();
  }
  
  // logt je uit de applicatie EN uit CWIPS, sso is hierna niet meer mogelijk
  // Als returnUrl wordt opgegeven zal de gebruiker daarna geredirect worden naar die url
  function LogoutFull($returnUrl) {
    global $principal;
    session_unset();
    session_destroy();
    $principal->ssoLogout($returnUrl);
  }
  // einde hulpfuncties
  //======================================================================================================
  // dummy content functies
  function getContent($principal) {
    $content = '';
    if ($principal->hasIdentity('public')) $content .= 'Dit is algemeen nieuws, iedereen mag dit lezen';
    if ($principal->hasIdentity('hr')) $content .= '<h5>Hogeschool algemeen</h5><p>Dit is niews voor alle mensen binnen de hogeschool</p>';
    if ($principal->hasIdentity('stud')) $content .= '<h5>Student</h5><p>Dit is nieuws voor alle studenten</p>';
    if ($principal->hasIdentity('rivio')) $content .= '<h5>rivio</h5><p>Dit is nieuws voor rivio, alle rivio personen mogen dit lezen</p>';
    if ($principal->hasIdentity('obp')) $content .= '<h5>Ondersteunend Personeel</h5><p>Dit is nieuws alleen voor de medewerkers van de ondersteunende diensten</p>';
    if ($principal->hasIdentity('dop')) $content .= '<h5>Onderwijsgevend Personeel</h5><p>Een bericht voor all docenten</p>';
    // volgende niet helemaal waterdicht, het zou kunnen dat je dop bent van een cluster en obp van een rivio, je zou het bericht dan niet moetn zien...
    if (($principal->hasIdentity('rivio'))&&($principal->hasIdentity('dop'))) $content .= '<h5>Docenten rivio</h5><p>Een bericht voor de RIVIO docenten</p>'; 
    return $content;
  }
  
  function GetActions() {
    $content = '<h4>Acties:</h4>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=check">is gebruiker al ingelod op CWIPS?</a><br/>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=ssoDesktop">probeer in te loggen via HR desktop</a> (Single Sign On, zal ook werken als er al ingelogd was op CWIPS)<br/>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=login">login</a> (Probeer Single Sign On als mogelijk, anders de gebruiker laten inloggen)<br/>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=forceLogin">forceer opnieuw login</a> (Opnieuw inloggen, Single Sign On niet toegestaan)<br/>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=logoutApp">uitloggen van applicatie</a><br/>';
    $content .= '<a href="'.$_SERVER['PHP_SELF'].'?action=logout">uitloggen van applicatie en CWIPS</a><br/>';
    return $content;
  }
  //======================================================================================================
  
  // actie functies
  function ActionDefault() {
    global $principal;
    
    if (!IsAuthenticated()) {
      $content = '<h3>Welkom gast (je bent niet ingelogd)</h3>';
    } else {
      $content = '<h3>Welkom '.$principal->getUsername().'</h3>';
    }
    
    return $content;
  }
  
  function ActionCheck() {
    global $principal;
    
    if (IsAuthenticated()) $content = "<h3>Je bent al geauthenticeerd voor deze applicatie";
    else $content = '<h3>Je bent niet geathenticeerd voor deze applicatie';
    
    $cwipsUsername = IsCWIPSAuthenticated();
    if ($cwipsUsername != null) {
      $content .= ', en op CWIPS ben je ingelogd als '.$cwipsUsername.'</h3>Je kan dus ook voor deze applicatie geauthenticeerd worden zonder in te hoeven loggen';
    } else {
      $content .= ' en op CWIPS ben je niet ingelogd</h3>';
    }
    
    return $content;
  }
  
  function ActionSSODesktop() {
    global $principal;
    if (TryHRDesktopAuthentication()) {
      $content = '<h3>Welkom '.$principal->getUsername().'. Single Sign On via de HR desktop succesvol of je was al ingelogd op CWIPS</h3>';
    } else {
      $content = '<h3>Single Sign On via de HR desktop is niet gelukt...</h3>';
    }
    
    return $content;
  }
  
  function ActionLogin() {
    global $principal;
    
    if (Authenticate()) {
      $content = '<h3>Welkom '.$principal->getUsername().'. Je bent nu geauthenticeerd voor deze applicatie.</h3>';
    } else {
      $content = '<h3>Inloggen niet gelukt...</h3>';
    }
    
    return $content;
  }
  
  function ActionForceLogin() {
    global $principal;
    
    if (ForceAuthentication()) {
      $content = '<h3>Welkom '.$principal->getUsername().'. Je bent nu geauthenticeerd voor deze applicatie.</h3>';
    } else {
      $content = '<h3>Inloggen niet gelukt...</h3><hr />';
    }
    
    return $content;
  }
  
  function ActionLogoutApp() {
    global $principal;

    LogoutApp();
    $content = '<h3>Welkom gast (je bent nu niet meer ingelogd op deze applicatie)</h3>';
        
    return $content;
  }
  
  function ActionLogout() {
    if (IsAuthenticated()) {  
      LogoutFull('');
    }
    
    $content = '<h3>Welkom gast (je bent nu niet meer ingelogd op deze applicatie en ook niet meer op CWIPS)</h3>';
    
    return $content;
  }
  
  
  $content = '';
  $action = null;
  if (isset($_GET['action'])) $action = $_GET['action'];
  
  switch ($action) {
    case "check":
      $content = ActionCheck();
    break;
    case "ssoDesktop":
      $content = ActionSSODesktop();
    break;
    case "login":
      $content = ActionLogin();
    break;
    case "forceLogin":
      $content = ActionForceLogin();
    break;
    case "logoutApp":
      $content = ActionLogoutApp();
    break;
    case "logout":
      $content = ActionLogout();
    break;
    default:
      $content = ActionDefault();
  }
  
?>
<html>
  <head>
    <title>Simple login client van het Centraal Web Identificatie en Personalisatie Systeem</title>
  </head>
  <body>
    <h1>Gevanceerder voorbeeld</h1>
    <p>
      <?php print(GetActions()); ?>
      <p style="border-top:1px solid #181747">
        <h4>Actie resultaat</h4>
      </p>
      <?php print($content); ?>  
      <p style="border-top:1px solid #181747">
        <h4>Personalisatie voorbeeld</h4>
        <h5>Nieuws:</h5>
        <?php print(GetContent($principal)); ?>
      </p>
    </p>
    
    <p style="margin-top:20px;border-top:1px solid #181747;">
      <a href="index.php">terug naar index pagina</a>|<a href="<?php print($_SERVER['PHP_SELF']); ?>">refresh</a>
    </p>
  </body>
</html>
