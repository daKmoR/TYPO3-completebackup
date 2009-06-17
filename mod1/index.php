<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Thomas Allmer <at@delusionworld.com>
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
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_t3lib . 'class.t3lib_scbase.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
// DEFAULT initialization of a module [END]

/**
 * Module 'Complete Backup' for the 'completebackup' extension.
 *
 * @author	Thomas Allmer <at@delusionworld.com>
 * @package	TYPO3
 * @subpackage	tx_completebackup
 */
class  tx_completebackup_module1 extends t3lib_SCbase {
	var $pageinfo;

	/**
	* Initializes the Module
	* @return	void
	*/
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		/*
		if (t3lib_div::_GP('clear_all_cache'))	{
			$this->include_once[] = PATH_t3lib.'class.t3lib_tcemain.php';
		}
		*/
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		
		// Access check! The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
	
		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{
		
			$this->doc = t3lib_div::makeInstance("bigDoc");
			$this->doc->backPath = $BACK_PATH;
			$this->content .= $this->doc->startPage('Complete Backup');
			$this->content .= $this->doc->header('Complete Backup');
			
			if( !isset($TYPO3_CONF_VARS['EXT']['extConf']['completebackup']) )
				die('did you click the Update button while Installing the Extension?');
			$this->conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['completebackup']);
			
			if( $this->conf['filename'] == '')
				$this->conf['filename'] = $this->getSafeFilename( $TYPO3_CONF_VARS['SYS']['sitename'] );				
			
			$this->conf['notDefaultList'] = explode( ',', $this->conf['notDefaultList'] );
			
			$mode = '';
			$mode = $_REQUEST['completebackup']['mode'];
			
			if ( $mode )
				$this->content .= $this->createBackup();
			else
				$this->content .= $this->showMenu();
				
			// ShortCut
			if ($BE_USER->mayMakeShortcut())
				$this->content .= $this->doc->spacer(20) . $this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->MCONF["name"]));

		} else // If no access or if ID == zero
			$this->content = 'you don\'t belong here... (no access)';
			
	}
	
	function createBackup() {
		// override config with the request params
		$getConf = isset($_REQUEST['completebackup']['conf']) ? $_REQUEST['completebackup']['conf'] : array();
		$this->conf['notifyServer'] = (isset($getConf['notifyServer']) && $getConf['notifyServer'] == 'on') ? 1 : 0;
		$this->conf['deleteFilesByServer'] = (isset($getConf['deleteFilesByServer']) && $getConf['deleteFilesByServer'] == 'on') ? 1 : 0;
		$this->conf['clearDb'] = (isset($getConf['clearDb']) && $getConf['clearDb'] == 'on') ? 1 : 0;
		$this->conf['clearFileSystem'] = (isset($getConf['clearFileSystem']) && $getConf['clearFileSystem'] == 'on') ? 1 : 0;

		$name = date('Y_m_d-Hm') . '_' . $this->conf['filename'];
		$zipName = ($this->conf['compressFileSystem']) ? $name . '.tar.gz' : $name . '.tar';
		$sqlName = ($this->conf['compressDb']) ? $name . '.sql.gz' : $name . '.sql';
		$this->createZip( $zipName );
		//$this->createSql( $sqlName );
		if ( $this->conf['notifyServer'] ) 
			$serverStatus = $this->notifyServer( $this->getPageDIR() . '/../' . $this->conf['backupPath'] . $zipName, $this->getPageDIR() . '/../' . $this->conf['backupPath'] . $sqlName );
		
		$content = '';
		$content .= 'The Backupfiles: <br />';
		$content .= '<a href="../' . $this->conf['backupPath'] . $zipName . '">' . $zipName . '</a><br />';
		$content .= '<a href="../' . $this->conf['backupPath'] . $sqlName . '">' . $sqlName . '</a><br />';
		$content .= 'are created.<br />';
		
		if ( $this->conf['notifyServer'] && $this->conf['serverUrl'] != '' )
			$content .= 'The Server (' . $this->conf['serverUrl'] . ') has been notified (It will fetch the backupfiles).<br />';
		if ( $this->conf['deleteFilesByServer'] )
			$content .= 'The Server will delete the BackupFiles afterward. (Status: ' . $serverStatus . ') ';
		
		return $content;
	}
	
	function createSql($name) {
		if( $this->conf['cleanDb'] )
			$this->cleanDb();
	
		require_once t3lib_extMgm::extPath('completebackup') . 'Resources/Php/class.MySQLDump.php';
		$dumper = new MySQLDump( TYPO3_db, PATH_site . $this->conf['backupPath'] . $name, $this->conf['compressDb']);
		$dumper->doDump();
	}
	
	function cleanDb() {
		$this->conf['truncateTables'] = array('cache_extensions', 'cache_hash', 'cache_imagesizes', 'cache_md5params', 'cache_pages', 'cache_pagesection', 'cache_sys_dmail_stat', 'cache_typo3temp_log');
		if( t3lib_extMgm::isLoaded('realurl') )
			$this->conf['truncateTables'] = array_merge( $this->conf['truncateTables'], array('tx_realurl_chashcache', 'tx_realurl_pathcache', 'tx_realurl_urldecodecache', 'tx_realurl_urlencodecache') );
			
		foreach( $this->conf['truncateTables'] as $table )
			$GLOBALS['TYPO3_DB']->sql(TYPO3_db, 'TRUNCATE TABLE ' . $table );
	}
	
	function cleanFileSystem() {
		$files = glob(PATH_typo3conf . '*_CACHED_*');
		foreach($files as $file)
			@unlink( $file );
		
		$this->cleanDir(PATH_site . 'typo3temp/', true, false, false, array('.', '..', 'index.html') );
	}
	
	/** 
	* Delete all files and/or dirs in a directory
	*
	* @param $path directory to clean
	* @param $recursive delete files in subdirs
	* @param $delDirs delete subdirs
	* @param $delRoot delete root directory
	* @param $exclude files you don't want to delete
	* @return success
	*/
	function cleanDir($path, $recursive = true, $delDirs = false, $delRoot = null, $exclude = array('.', '..') ) {
		$result = true;
		if($delRoot === null) $delRoot = $delDirs;
		if(!$dir = @dir($path)) return false;
		while($file = $dir->read())	{
			if( in_array($file, $exclude) ) continue;
			$full = $dir->path . DIRECTORY_SEPARATOR . $file;
			if(is_dir($full) && $recursive)	{
				$result &= $this->cleanDir($full, $recursive, $delDirs, $delDirs, $exclude);
			} else if(is_file($full)) {
				$result &= unlink($full);
			}
		}
		$dir->close();
		if($delRoot) {
			$result &= rmdir($dir->path);
		}
		return $result;
	}
	
	function notifyServer($zipPath, $sqlPath) {
		$params = array('zip' => $zipPath, 'sql' => $sqlPath, 'service' => $this->getPageDIR() . '/../?eID=completebackup');
		if( $this->conf['additionalInfo'] != '' )
			$params['additionalInfo'] = $this->conf['additionalInfo'];
		if( $this->conf['deleteFilesByServer'] )
			$params['deleteAfter'] = 1;
			
		$url = $this->conf['serverUrl'];
		$url .= (strpos($this->conf['serverUrl'], '?') === false) ? '?' : '&';
		$url .=  http_build_query($params, '', '&');
		
		return file_get_contents( $url );
	}
	
	function createZip($name) {
		if( $this->conf['cleanFileSystem'] )
			$this->cleanFileSystem();
			
		$files = $_REQUEST['completebackup']['files'];
		
		require_once t3lib_extMgm::extPath('completebackup') . 'Resources/Php/class.Tar.php';
		$fileSystem = new Tar( $this->conf['compressFileSystem'] );
		if( $fileSystem->open(PATH_site . $this->conf['backupPath'] . $name) ) {
		
			foreach( $files as $file => $state ) {
				if ( is_dir( PATH_site . $file) )
					$fileSystem->addDir( PATH_site . $file, $file );
				else
					$fileSystem->addFile( PATH_site . $file, $file);
			}
			
			$filesToRemove = glob( PATH_site . $this->conf['backupPath'] . '*' );
			foreach ( $filesToRemove as $removeMe ) {
				if( stripos($removeMe, 'index.html') === false ) {
					$fileSystem->removeFile($removeMe);
				}
			}
			
			$fileSystem->close();
			return true;
		}
		return false;
	}
	
	function showMenu() {
		$content = '';
		$content .= '
			<h3>Just hit the Button</h3>
			<form action="" method="post">
				<button style="font-size: 50px;">Create a Backup</button><br /> <br />
				<div>You usually don\'t want to mess with the options below</div>
				<fieldset>
					<legend>Files/Folder to Backup</legend>
					<input type="hidden" name="completebackup[mode]" value="createBackup" />
		';
		
		$checked = 'checked="checked"';
		
		$files = $this->getFiles(PATH_site, 'both', 1);
		foreach( $files as $sub => $file ) {
			$name = is_array($file) ? $sub : $file;
			
			$content .= $this->getFilterCheckBox( 'completebackup[files][' . $name . ']', $name, $this->conf['notDefaultList'] );
			$content .= ' ' . $name . '<br />' . PHP_EOL;
			
		}

		$content .= '
				</fieldset>
				<fieldset>
					<legend>Options</legend>
					' . $this->getCheckBox('completebackup[conf][fileSystemBackup]', $this->conf['fileSystemBackup']) . ' Create a FileSystem Backup <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][dataBaseBackup]', $this->conf['dataBaseBackup']) . ' Create a Database Backup <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][notifyServer]', $this->conf['notifyServer']) . ' Notify Server [' . $this->conf['serverUrl'] . '] <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][deleteFilesByServer]', $this->conf['deleteFilesByServer']) . ' Delete the backupfiles after they have been fetched by the server (only works if Notify Server) <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][clearFileSystem]', $this->conf['clearFileSystem']) . ' clear File System <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][clearDb]', $this->conf['clearDb']) . ' clear DB <br />' . PHP_EOL . ' 
				</fieldset>
			</form>
		';
		
		return $content;
	}
	
	function getFilterCheckBox($name, $value = '', $array = array() ) {
		$content = '<input type="checkbox" name="' . $name . '" ';
		$content .=	in_array($value, $array) ? '' : 'checked="checked"';
		$content .= '" /> ';
		return $content;
	}
	
	function getCheckBox($name, $checked) {
		$content = '<input type="checkbox" name="' . $name . '" ';
		$content .= $checked ? 'checked="checked"' : '';
		$content .= '" /> ';
		return $content;
	}

	function getSafeFilename($filename) {
		$search = array('/ß/', '/ä/', '/Ä/', '/ö/', '/Ö/', '/ü/', '/Ü/', '([^[:alnum:]._])');
		$replace = array('ss', 'ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', '_');
		return preg_replace($search, $replace, $filename);
	}
	
	public static function getPageDIR() {
		$pageURL = 'http';
		if ($_SERVER['HTTPS'] == 'on')
			$pageURL .= 's';
		$pageURL .= '://';
		if ($_SERVER['SERVER_PORT'] != '80')
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . dirname($_SERVER['SCRIPT_NAME']);
		else
			$pageURL .= $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']);
		return $pageURL;
	}	

	/**
	 * gives you an array for the given path in the given mode:
	 *   'both' => dirs and files; 'dirs' => only dirs; 'files' => only files
	 *
	 * @param string $path
	 * @param string $mode ['both', files, 'dirs']
	 * @return array
	 * @author Thomas Allmer <at@delusionworld.com>
	 */
	public function getFiles($path, $mode = 'both', $depth = 2) {
		if (! is_dir($path)) return array();
		$d = dir($path);
		$files = array();
		while (false !== ($dir = $d->read()) ) {
			if ( ( $dir != "." && $dir != ".." ) ) {
				if (is_dir($d->path . '/' . $dir) ) {
					if ( ($depth >= 1) && ($mode != 'files') )
						$files[$dir] = $this->getFiles($d->path . '/' . $dir, $mode, $depth-1);
				} else if ($mode != 'dirs') {
					$files[] = $dir;
				}
			}
		}
		$d->close();
		ksort($files);
		
		return $files;
	}	

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/completebackup/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/completebackup/mod1/index.php']);
}

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_completebackup_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
