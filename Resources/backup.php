<?php
	/**
	 * Module 'Complete Backup Server' for the 'completebackup' extension.
	 *
	 *   This is a very basic example of a Backup Server. It's only purpose is to
	 *   get called with a few parameters (zip, sql, service, deleteAfter, additionalInfo).
	 *   The files zip sql should be downloaded and if deleteAfter is set the service url with
	 *   the parameter mode=clearBackupCache and key=<md5 hash from additionalInfo> should be 
	 *   called to delete the backupFiles from the TYPO3 installation invoked the service.
	 *
	 * @author	Thomas Allmer <at@delusionworld.com>
	 */
	$path = './';  // where to put the downloaded files?
	
	// you may want to use wget or some other system tool in a real backup server
	if( isset($_REQUEST['zip']) && $_REQUEST['zip']  != '' ) {
		$zipName = basename($_REQUEST['zip']);
		file_put_contents($path . $zipName, file_get_contents($_REQUEST['zip']) );
	}
	
	if( isset($_REQUEST['sql']) && $_REQUEST['sql']  != '' ) {
		$sqlName = basename($_REQUEST['sql']);
		file_put_contents($path . $sqlName, file_get_contents($_REQUEST['sql']) );
	}

	// if the backupfiles should be deleted afterward you can use the service url provided
	if( $_REQUEST['service'] && $_REQUEST['deleteAfter'] && $_REQUEST['additionalInfo'] ) {
		$params = array('mode' => 'clearBackupCache', 'key' => md5($_REQUEST['additionalInfo']) );
		$url = $_REQUEST['service'];
		$url .= (strpos($url, '?') === false) ? '?' : '&';
		$url .= http_build_query($params, '', '&');
		echo file_get_contents($url);
	}
?>