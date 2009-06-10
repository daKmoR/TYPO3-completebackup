<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
if (!defined ('PATH_typo3conf')) 	die ('Access denied: eID only.');
require_once(PATH_tslib . 'class.tslib_pibase.php');

class tx_completebackup_eid extends tslib_pibase {
	var $prefixId      = 'tx_extensionkey_eid';		// Same as class name
	var $scriptRelPath = 'eid/class.tx_extensionkey_eid.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'extensionkey';	// The extension key.

	function eid_main() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		$this->conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['completebackup']);
		
		if( isset($_REQUEST['key']) && $_REQUEST['key'] == md5($this->conf['additionalInfo']) ) {

			if( isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'clearBackupCache' )
				$this->clearCache(PATH_typo3 . $this->conf['backupPath']);
			
			echo 'OK';
			
		} else {
			die('Your key seems to be wrong...');
		}
		
	}

	function clearCache($path) {
		$this->removeDir($path, false);
	}
	
	/**
	 * removes a directory and all it files
	 * 
	 * @param string $dir path to the directory
	 * @param boolean $DeleteMe (def. true) do you want to the delete the directory itself
	 * @return void
	 * @author Thomas Allmer <at@delusionworld.com>
	 */
	public function removeDir($dir, $DeleteMe = TRUE) {
		$dh = @opendir ($dir);
		if (!$dh) return;
		while ( false !== ( $obj = readdir ( $dh ) ) ) {
			if ( $obj == '.' || $obj == '..' || $obj == 'index.html' ) continue;
			if ( ! @unlink ( $dir . '/' . $obj ) ) $this->removeDir ( $dir . '/' . $obj, true );
		}
		closedir ($dh);
		if ($DeleteMe) 
			@rmdir ( $dir );
	}	
	
	
}
 
$extensionkey = t3lib_div::makeInstance('tx_completebackup_eid');
$extensionkey->eid_main();

?>