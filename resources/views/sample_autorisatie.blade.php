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
  
  require_once('C:\laragon\www\Login-Test/hrcwips.php');
  $principal = new HRPrincipalInterface();
  if ($principal->ssoAuthenticate() !== true) {
    // niet geauthenticeerd (dus niet succesvol ingelogd), doe hier wat er dan moet gebeuren
    // Dit zal overigens vrijwel nooit voorkomen omdat het Centraal Login Systeem een gebruiker
    // niet terug zal sturen naar deze site als de gebruiker niet sucesvol is ingelogd....
    // Zal alleen voorkomen in geval van een storing of wanneer iemand probeert te klieren/hacken
    exit('<html><body>Sorry, je bent niet ingelogd. <a href="'.$_SERVER['PHP_SELF'].'">Probeer nog een keer</a></body></html>');
  }

  // login succesvol, maar mag je deze applicatie ook gebruiken?
  
  // bepalen of gebruiker de juiste identity (rol) heeft, alle roles zijn lowercase
  // roles zijn onder andere:
  // public     Is iedereen
  // hr      Een persoon van de Hogeschool Rotterdam
  // stud    Een student
  // obp    Ondersteunend personeel (secretariaten, personeel van de diensten, directeuren, etc)
  // dop    Onderwijsgevend personeel (docenten, lab assistenten etc)
  // rivio    Iemand van het cluster rivio
  // wdk    Iemand van het cluster wdk
  // rivio:gmt    Een student van GMT
  //
  if (($principal->hasIdentity('stud'))||($principal->hasIdentity('dop'))||($principal->hasIdentity('obp'))) {
    // ok je bent een rivio persoon of een medewerker, dus je mag erin
    // doe hier wat er dan gebeuren moet
  } else {
    // je hebt hier dus geen toegang toe, wegwezen
    exit('<html><body>Sorry, je bent wel ingelogd als <b>'.$principal->getUsername().'</b>, maar je bent niet van rivio en je bent geen docent, dus heb je hier geen toegang toe.</body></html>');
  }

  // ok je bent authenticated en authorized, dus toon de page
  $username = $principal->getUserName();    // username (0447323, devmf, etc. Dus geen .cluster erachter!!)
  $displayName = $principal->getDisplayName();  // DisplayName (Deventer, MFC van)
  $email = $principal->getEMail();        // email adres
  $primairCluster = $principal->getPrimaryOU(); // primaire afdeling (LDAP afdeling container waarin gebruiker zich bevind)
  $roles = $principal->getIdentities();      // Alle rollen die deze gebruiker bezit
?>
<html>
  <head>
    <title>Simple login client van het Centraal Web Identificatie en Personalisatie Systeem</title>
  </head>
  <body>
    <h1>Simpel voorbeeld</h1>
    <h3>Successvol ingelogd</h3>
    <p>
      Gebruikersnaam is <b><?php print($username); ?></b>.<br />
      Primaire cluster/afdeling is <b><?php print($primairCluster); ?></b>.<br />
      naam is <b><?php print($displayName); ?></b>.<br />
      the user's email is <b><?php print($email); ?></b>.<br />
    </p>
    <p><h3 style="margin:0">rollen:</h3>
      <?
        foreach($roles as $value) echo $roles.'<br />';
      ?>
    </p>
    <p>
      <a href="<?php print($_SERVER['PHP_SELF']); ?>">nog een keer ;-) </a>
      (Zal een nieuwe ticket aanvragen bij de CWIPS server, aangezien je nu op de CWIPS server reeds bent ingelogd zal er niet nogmaals om authenticatie gevraagd worden)
    </p>
    <p style="position:absolute;bottom:10px">
      <a href="index.php">terug naar index pagina</a>
    </p>
  </body>
</html>
