<?php
/*
$Id$

  This code is part of LDAP Account Manager (http://www.sourceforge.net/projects/lam)
  Copyright (C) 2004  Roland Gruber

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
* Creates LDAP accounts for file upload.
*
* @author Roland Gruber
* @package tools
*/

/** access to configuration */
include_once('../lib/config.inc');
/** LDAP handle */
include_once('../lib/ldap.inc');
/** status messages */
include_once('../lib/status.inc');
/** account modules */
include_once('../lib/modules.inc');


// Start session
session_save_path('../sess');
@session_start();

// Redirect to startpage if user is not loged in
if (!isset($_SESSION['loggedIn'])) {
	metaRefresh("login.php");
	exit;
}

// Set correct language, codepages, ....
setlanguage();

echo $_SESSION['header'];
echo "<title>account upload</title>\n";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"../style/layout.css\">\n";

// create accounts
$accounts = unserialize($_SESSION['ldap']->decrypt($_SESSION['mass_accounts']));
if (($_SESSION['mass_counter'] < sizeof($accounts)) || !isset($_SESSION['mass_postActions']['finished'])) {
	$maxTime = get_cfg_var('max_execution_time') - 5;
	$refreshTime = get_cfg_var('max_execution_time') + 1;
	$startTime = time();
	echo "<meta http-equiv=\"refresh\" content=\"2; URL=massDoUpload.php\">\n";
	echo "</head>\n<body>\n";
	echo "<h1>" . _("LDAP upload in progress. Please wait.") . "</h1>\n";
	echo "<table align=\"center\" width=\"80%\" style=\"border-color: grey\" border=\"2\" cellspacing=\"0\" rules=\"none\">\n";
	echo "<tr><td bgcolor=\"blue\" width=\"" . ($_SESSION['mass_counter'] * 100) / sizeof($accounts) . "%\">&nbsp;</td>";
	echo "<td bgcolor=\"grey\" width=\"" . (100 - (($_SESSION['mass_counter'] * 100) / sizeof($accounts))) . "%\">&nbsp;</td></tr>\n";
	echo "</table>";
	flush();  // send HTML to browser
	// add accounts to LDAP
	while (($_SESSION['mass_counter'] < sizeof($accounts)) && ($startTime + $maxTime > time())) {
		// create accounts as long as max_execution_time is not near
		$attrs = $accounts[$_SESSION['mass_counter']];
		$dn = $attrs['dn'];
		unset($attrs['dn']);
		$success = @ldap_add($_SESSION['ldap']->server, $dn, $attrs);
		if (!$success) {
			$errorMessage = array(
				"ERROR",
				_("LAM was unable to create account %s! An LDAP error occured."),
				ldap_errno($_SESSION[ldap]->server) . ": " . ldap_error($_SESSION[ldap]->server),
				array($_SESSION['mass_counter']));
			$_SESSION['mass_errors'][] = $errorMessage;
			$_SESSION['mass_failed'][] = $_SESSION['mass_counter'];
		}
		$_SESSION['mass_counter']++;
	}
	// do post upload actions
	if ($_SESSION['mass_counter'] >= sizeof($accounts)) {
		$data = unserialize($_SESSION['ldap']->decrypt($_SESSION['mass_data']));
		$return  = doUploadPostActions($_SESSION['mass_scope'], $data, $_SESSION['mass_ids'], $failed);
		if ($return['status'] == 'finished') {
			$_SESSION['mass_postActions']['finished'] = true;
		}
		for ($i = 0; $i < sizeof($return['errors']); $i++) $_SESSION['mass_errors'][] = $return['errors'][$i];
		echo "<h1>" . _("Additional tasks for module:") . ' ' . $return['module'] . "</h1>\n";
		echo "<table align=\"center\" width=\"80%\" style=\"border-color: grey\" border=\"2\" cellspacing=\"0\" rules=\"none\">\n";
		echo "<tr><td bgcolor=\"blue\" width=\"" . $return['progress'] . "%\">&nbsp;</td>";
		echo "<td bgcolor=\"grey\" width=\"" . (100 - $return['progress']) . "%\">&nbsp;</td></tr>\n";
		echo "</table>";
		flush();
		while (!isset($_SESSION['mass_postActions']['finished']) && ($startTime + $maxTime > time())) {
			$return  = doUploadPostActions($_SESSION['mass_scope'], $data, $_SESSION['mass_ids'], $failed);
			if ($return['status'] == 'finished') {
				$_SESSION['mass_postActions']['finished'] = true;
			}
			for ($i = 0; $i < sizeof($return['errors']); $i++) $_SESSION['mass_errors'][] = $return['errors'][$i];
		}
	}
	echo "</body></html>";
}
// all accounts have been created
else {
	echo "</head>\n<body>\n";
	echo "<h1>" . _("LDAP upload has finished") . "</h1>\n";
	if (sizeof($_SESSION['mass_errors']) > 0) {
		echo "<h2>" . _("There were errors while uploading:") . "</h2>\n";
		for ($i = 0; $i < sizeof($_SESSION['mass_errors']); $i++) {
			call_user_func_array('StatusMessage', $_SESSION['mass_errors'][$i]);
		}
	}
	echo "</body></html>";
}


?>