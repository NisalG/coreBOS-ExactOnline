<?php

class ExactAccounts extends ExactApi{
	
	public function __construct() {
		require_once('vtlib/Vtiger/Module.php');
		require_once('modules/ExactOnline/functions.php');
	}
	
	public function listAccounts($division, $selection, $filter = NULL) {
		// Returns an array of all accounts with the account fields
		// Provided in the '$selection' (comma separated input)
		// Start filter empty
		$filterstring = "";
		// See if there was a filter and include it in the getrequest call
		if ( isset($filter) && is_array($filter)) {
			$i = 0;
			foreach ($filter as $filterkey => $filtervalue) {
				if (++$i == 2) break;
				// If we want to filter on account code, remember we have
				// to add padding to get to an 18 character string
				$filterstring = $filterkey." eq '".$filtervalue."'";
			}
		}
		return $this->sendGetRequest('crm/Accounts', $division, $selection, $filterstring);
	}
	
	public function CreateAccount($division, $fields) {
		if ( is_array($fields) ) {
			// Don't accept an account that doesn't have a code
			if ( isset($fields['Code']) && $fields['Code'] != "" ) {
				// We should check if the account already exists first
				if ( !$this->AccountExists($division, $fields) ) {
					// It doesn't exist, so send a POST
					$this->sendPostRequest('crm/Accounts', $division, $fields);
				} else {
					// It already exists, so send a PUT
					// We need to provide the Exact 'guid' code for this, so let's retrieve it
					$ExactGUID = $this->getAccountGUID($division, $fields['Code']);
					$this->sendPutRequest('crm/Accounts', $division, $fields, $ExactGUID);
				}
			} else {
				echo "You need to provide an Account code, make sure to set array key with a capital C.";
			}
		} else {
			echo "When using function 'CreateAccount', Post fields should be in array form";
		}
	}
	
	public function AccountExists($division, $fields) {
		// Make sure we strip any non-numerical from the code we
		// want to check, since Exact will return only numbers also
		$enteredCode = preg_replace("/[^0-9,.]/", "", $fields['Code']);
		// First, GET all the accounts, this returns an array
		// To check if an account exists, we only need the code
		// Because we regard this as the unique ID
		// TODO: replace this listAll with a function that passes in the
		// request code. The Exact API look on it's own server without
		// havinf to return everything and check it here.
		$AccountsCodeArray = $this->listAccounts($division,'Code');
		// Now loop through the returned accounts
		foreach ( $AccountsCodeArray['feed']['entry'] as $entry ) {
			// Check every code from Exact and match it against the code we're feeding to
			// This method. Set a variable to true or false depending on if it's found
			// Make sure to TRIM the result from Exact, because it will be 18 characters
			// long filled with leading spaces
			$ExactAccountCode = trim($entry['content']['m:properties']['d:Code']);
			// var_dump($ExactAccountCode);
			// echo "<br>";
			// var_dump($fields['Code']);
			// echo "<br>";
			if ( $ExactAccountCode == $enteredCode ) {
				$codeExists = 1;
				// Stop the loop if it exists
				break;
			} else {
				$codeExists = 0;
			}
		}
		if ($codeExists == 1) {return TRUE;} else {return FALSE;}
	}
	
	public function getAccountGUID($division, $code) {
		// Takes a provided code and gets the correct guid for it.
		$AccountsCodeArray = $this->listAccounts($division,'Code,ID');
		// Strip any non-numerical from the code
		$code = preg_replace("/[^0-9,.]/", "", $code);
		// Loop through the accounts from Exact
		foreach ( $AccountsCodeArray['feed']['entry'] as $entry ) {
			$ExactAccountCode = trim($entry['content']['m:properties']['d:Code']);
			if ($ExactAccountCode == $code) {
				return $entry['content']['m:properties']['d:ID'];
			}
		}
	}
	
	public function sendAllAccounts($division) {
		// This function will be a helper for when the initial setup
		// Starts. It will send ALL coreBOS accounts to Exact and
		// Respects the Account numbering used in Corebos (by setting
		// the Exact 'Code' to the coreBOS account no, but removing
		// any non-numerical prefix, since Exact can't handle that).
		global $adb;
		$accountResult = $adb->pquery('SELECT accountid, account_no, accountname, phone, email1 FROM vtiger_account', array());
		while ( $Account = $adb->fetch_array($accountResult) ) {
			$addressResult = $adb->pquery('SELECT bill_city, bill_country, bill_street, bill_pobox FROM vtiger_accountbillads WHERE accountaddressid=?', array($Account['accountid']));
			while ($AccountAddress = $adb->fetch_array($addressResult)) {
				$Account['bill_city']		=	$AccountAddress['bill_city'];
				$Account['bill_country']	=	$AccountAddress['bill_country'];
				$Account['bill_street']		=	$AccountAddress['bill_street'];
				$Account['bill_pobox']		=	$AccountAddress['bill_pobox'];
			}
			// Setup the array the CreateAccount method wants
			$AccountCreateFields = array(
				'Name'				=>	$Account['accountname'],
				'Code'				=>	$Account['account_no'],
				'Phone'				=>	$Account['phone'],
				'Email'				=>	$Account['email1'],
				'City'				=>	$Account['bill_city'],
				'Country'			=>	$Account['bill_country'],
				'AddressLine1'		=>	$Account['bill_street'],
				'Postcode'			=>	$Account['bill_pobox']
			);
			// Fire method 'CreateAccount' for each account
			$this->CreateAccount($division, $AccountCreateFields);
		}
	}
	
}

?>