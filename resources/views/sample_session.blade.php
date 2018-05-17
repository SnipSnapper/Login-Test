<?php
/*
 * Simple "Centraal Web Identificatie en Personalisatie Systeem" voorbeeld login pagina
 * Version: 1.2
 * date: 06-09-2012
 * author: Martijn van Deventer, Hogeschool Rotterdam
 *
 * update: fix voor php 5.4, Jeffry Sleddens
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
  session_start();

  $wasAlreadyAuthenticated = false;
  if (!isset($_SESSION['isAuthenticated'])) { // is nog niet ingelogd? probeer in te loggen
    require_once('php-cwips/hrcwips.php');
    $principal = new HRPrincipalInterface();
    if ($principal->ssoAuthenticate() === true) {
      $_SESSION['isAuthenticated'] = true;
      // login succesvol, set relevante sessie variabelen
      $_SESSION['username'] = $principal->getUserName();        // username (0447323, devmf, etc. Dus geen .cluster erachter!!)
      $_SESSION['displayName'] = $principal->getDisplayName();  // DisplayName (Deventer, MFC van)
      $_SESSION['email'] = $principal->getEMail();              // email adres
      $_SESSION['primairCluster'] = $principal->getPrimaryOU(); // primaire afdeling (LDAP afdeling container waarin gebruiker zich bevind)
      $_SESSION['roles'] = $principal->getIdentities();         // Alle rollen die deze gebruiker bezit
    }
  } else {
    $wasAlreadyAuthenticated = true;
  }

  if(isset($_SESSION['isAuthenticated'])) { // is ingelogd?
    // login succesvol, doe wat er gebeuren moet
    $username = $_SESSION['username'];
    $displayName = $_SESSION['displayName'];
    $email = $_SESSION['email'];
    $primairCluster = $_SESSION['primairCluster'];
    $roles = $_SESSION['roles'];
  }

?>
<html>
  <head>
    <title>Simple login client van het Centraal Web Identificatie en Personalisatie Systeem</title>
  </head>
  <body>
    <h1>Voorbeeld met sessie</h1>
    <?php if (isset($_SESSION['isAuthenticated'])) { ?>
        <h3><?php print(($wasAlreadyAuthenticated)?'Je was al op deze applicatie':'Successvol'); ?> ingelogd</h3>
        <p>
          Gebruikersnaam is <b><?php print($username); ?></b>.<br />
          Primaire cluster/afdeling is <b><?php print($primairCluster); ?></b>.<br />
          naam is <b><?php print($displayName); ?></b>.<br />
          the user's email is <b><?php print($email); ?></b>.<br />
        </p>
        <p><h3 style="margin:0">rollen:</h3>
          <?php foreach($roles as $value) print($value.'<br />'); ?>
        </p>
    <?php } else { ?>
        <h3>Inloggen niet gelukt!</h3>
    <?php } ?>
    <p>
      <a href="<?php print($_SERVER['PHP_SELF']); ?>">refresh</a>
      (aangezien je nu op de test "applicatie" bent ingelogd zal de applicatie dmv sessie variabelen dat onthouden)
    </p>
    <p style="position:absolute;bottom:10px">
      <a href="index.php">terug naar index pagina</a>
    </p>
  </body>
</html>
