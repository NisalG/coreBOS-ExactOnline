<?php

class ExactPaymentConditions extends ExactApi{
	
	// This class will get the Payment Conditions from
	// Exact and fill a dropdown field generated by the
	// ExactOnline module on install (in postinstall event
	// in ExactOnline.php).
	
	public function __construct() {
		require_once('vtlib/Vtiger/Module.php');
	}
	
	public function getItemGUID($division, $code) {
		$itemGUID = $this->sendGetRequest('logistics/Items', $division, 'ID', array('Code'=>$code));
		return $itemGUID['d']['results'][0]['ID'];
	}
	
	public function getPaymentConds($division) {
		// This function gets all the Payment Conditions from Exact
		// We'll set up a cron job in coreBOS to sync them
		// from Exact to coreBOS and add them to a field in
		// Invoice module.
		$PaymentCondsArray = $this->sendGetRequest('cashflow/PaymentConditions',$division,'Code,Description');
		// Prepare the values for the dropdown in corebos
		$dropdownValues = array();
		foreach ($PaymentCondsArray['d']['results'] as $value) {
			$dropdownValues[] = $value['Code']." - ".trim($value['Description']);
		}
		// Return the array so we can add it to the field in Invoices
		return $dropdownValues;
	}
	
	public function updatePaymentConds($division) {
		global $adb;
		$adb->query('TRUNCATE vtiger_exact_payment_cond');
		$adb->query('TRUNCATE vtiger_exact_acc_payment_cond');
		$adb->query('TRUNCATE vtiger_exact_so_payment_cond');
		// Get the payment conditions from Exact
		$PaymentCondArray = $this->getPaymentConds($division);
		array_unshift($PaymentCondArray, '--None--');
		foreach ($PaymentCondArray as $key => $value) {
			$key = $key + 1;
			$adb->pquery('INSERT INTO vtiger_exact_payment_cond (exact_payment_cond, sortorderid, presence) VALUES (?,?,?)', array($value, $key, 0));
			$adb->pquery('INSERT INTO vtiger_exact_acc_payment_cond (exact_acc_payment_cond, sortorderid, presence) VALUES (?,?,?)', array($value, $key, 0));
			$adb->pquery('INSERT INTO vtiger_exact_so_payment_cond (exact_so_payment_cond, sortorderid, presence) VALUES (?,?,?)', array($value, $key, 0));
		}
	}
}

?>