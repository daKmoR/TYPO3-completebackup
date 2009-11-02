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
		
			if( !isset($TYPO3_CONF_VARS['EXT']['extConf']['completebackup']) )
				die('did you click the Update button while Installing the Extension?');
			$this->conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['completebackup']);
			
			if( $this->conf['filename'] == '')
				$this->conf['filename'] = $this->getSafeFilename( $TYPO3_CONF_VARS['SYS']['sitename'] );				
			
			$this->conf['notDefaultList'] = explode( ',', $this->conf['notDefaultList'] );
			
			$mode = '';
			$mode = $_REQUEST['completebackup']['mode'];
			
			switch( $mode ) {
				case 'createBackup':
					$this->content .= $this->createBackup();
					break;
				case 'ajax':
					$list = ( isset($_REQUEST['completebackup']['list']) ) ? $_REQUEST['completebackup']['list'] : NULL;
					$offset = ( isset($_REQUEST['completebackup']['offset']) ) ? $_REQUEST['completebackup']['offset'] : 0;
					$name = ( isset($_REQUEST['completebackup']['name']) ) ? $_REQUEST['completebackup']['name'] : 'nameError';
					echo $this->createFileSystemBackup($name, $list, $offset);
					die();
				case 'notify':
					echo $this->notifyServer( $_REQUEST['completebackup']['file'], $_REQUEST['completebackup']['sql'] );
					die();
				default:
					$this->content .= $this->showMenu();
			}
				
			$this->printContent();

		} else // If no access or if ID == zero
			$this->content = 'you don\'t belong here... (no access)';
			
	}
	
	function createBackup() {
		// override config with the request params
		$getConf = isset($_REQUEST['completebackup']['conf']) ? $_REQUEST['completebackup']['conf'] : array();
		$this->conf['notifyServer'] = (isset($getConf['notifyServer']) && $getConf['notifyServer'] == 'on') ? 1 : 0;
		$this->conf['deleteFilesByServer'] = (isset($getConf['deleteFilesByServer']) && $getConf['deleteFilesByServer'] == 'on') ? 1 : 0;
		$this->conf['cleanDb'] = (isset($getConf['cleanDb']) && $getConf['cleanDb'] == 'on') ? 1 : 0;
		$this->conf['cleanFileSystem'] = (isset($getConf['cleanFileSystem']) && $getConf['cleanFileSystem'] == 'on') ? 1 : 0;
		$this->conf['fileSystemBackup'] = (isset($getConf['fileSystemBackup']) && $getConf['fileSystemBackup'] == 'on') ? 1 : 0;
		$this->conf['dataBaseBackup'] = (isset($getConf['dataBaseBackup']) && $getConf['dataBaseBackup'] == 'on') ? 1 : 0;
		
		$name = date('Y_m_d-Hm') . '_' . $this->conf['filename'];
		$fileSystemName = ($this->conf['compressFileSystem']) ? $name . '.tar.gz' : $name . '.tar';
		$sqlName = ($this->conf['compressDb']) ? $name . '.sql.gz' : $name . '.sql';

		$content = '';
		$content .= '<h3>Backup Process:</h3> <ul>';
		if( $this->conf['cleanFileSystem'] ) {
			if( $this->cleanFileSystem() ) {
				$content .= '<li>The FileSystem got cleaned [removed typo3conf/*_CACHED_*][cleaned typo3temp]</li>';
			}
		}
		if( $this->conf['fileSystemBackup'] ) {
			$content .= '<li>The Backup for the FileSystem';
			if( t3lib_extMgm::isLoaded('mpm') ) {
				$content .= '<div id="FileSystemFiles" style="display: none; position: absolute;">' . json_encode($_REQUEST['completebackup']['files']) . '</div>';
				$content .= '<div id="FileSystemName" style="display: none; position: absolute;">' . $fileSystemName . '</div>';
				$content .= '<span id="FileSystemBackup">[<img src="gfx/spinner.gif" alt="spinner" /> gets prepared]</span>';
			} else {
				if( $this->createFileSystemBackup($fileSystemName) ) {
					$content .= '<span> has been created </span>';
				}
			}
			$content .= '[<a href="../' . $this->conf['backupPath'] . $fileSystemName . '">' . $fileSystemName . '</a>]</li>';
		}
		
		if( $this->conf['cleanDb'] ) {
			if( $this->cleanDb() ) {
				$content .= '<li>The Database got cleaned [following tables got truncated ' . print_r($this->conf['truncateTables'], 1) . ']</li>';
			}
		}
		if( $this->conf['dataBaseBackup'] ) {
			if( $this->createSqlBackup($sqlName) ) {
				$content .= '<li>The Backup for the Database has been created [<a href="../' . $this->conf['backupPath'] . $sqlName . '">' . $sqlName . '</a>]</li>';
			}
		}
		
		if ( $this->conf['notifyServer'] && $this->conf['serverUrl'] != '' ) {
			$file = $this->getPageDIR() . '/../' . $this->conf['backupPath'] . $fileSystemName;
			$sql = $this->getPageDIR() . '/../' . $this->conf['backupPath'] . $sqlName;
			if( t3lib_extMgm::isLoaded('mpm') && $this->conf['fileSystemBackup'] ) {
				$content .= '<li id="notifyServer">The Server (' . $this->conf['serverUrl'] . ') <span id="notifyServerReady">will be notified once all files are ready</span> (It will fetch the backupfiles)
						<span id="notifyServerFile" style="display: none;">' . $file . '</span>
						<span id="notifyServerSql" style="display: none;">' . $sql . '</span>
					</li>';
				if ( $this->conf['deleteFilesByServer'] ) {
					$content .= '<li>The Server will delete the BackupFiles afterward. (Status: <span id="notifyServerResponse">wait for files to be ready</span>)</li>';
				}
			} else {
				$serverStatus = $this->notifyServer( $file, $sql );
				$content .= '<li>The Server (' . $this->conf['serverUrl'] . ') has been notified (It will fetch the backupfiles)</li>';
				if ( $this->conf['deleteFilesByServer'] ) {
					$content .= '<li>The Server will delete the BackupFiles afterward. (Status: ' . $serverStatus . ')</li>';
				}
			}
			
		}
		
		$content .= '</ul>';
		
		return $content;
	}
	
	function createSqlBackup($name) {
		require_once t3lib_extMgm::extPath('completebackup') . 'Resources/Php/class.MySQLDump.php';
		$dumper = new MySQLDump( TYPO3_db, PATH_site . $this->conf['backupPath'] . $name, $this->conf['compressDb']);
		return $dumper->doDump();
	}
	
	function cleanDb() {
		$this->conf['truncateTables'] = array('cache_extensions', 'cache_hash', 'cache_imagesizes', 'cache_md5params', 'cache_pages', 'cache_pagesection', 'cache_sys_dmail_stat', 'cache_typo3temp_log');
		if( t3lib_extMgm::isLoaded('realurl') )
			$this->conf['truncateTables'] = array_merge( $this->conf['truncateTables'], array('tx_realurl_chashcache', 'tx_realurl_pathcache', 'tx_realurl_urldecodecache', 'tx_realurl_urlencodecache') );
			
		foreach( $this->conf['truncateTables'] as $table ) {
			$GLOBALS['TYPO3_DB']->sql(TYPO3_db, 'TRUNCATE TABLE ' . $table );
		}
		return true;
	}
	
	function cleanFileSystem() {
		$files = glob(PATH_typo3conf . '*_CACHED_*');
		foreach($files as $file)
			@unlink( $file );
		
		return $this->cleanDir(PATH_site . 'typo3temp/', true, false, false, array('.', '..', 'index.html') );
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
	
	function notifyServer($fileSystemPath, $sqlPath) {
		$params = array('zip' => $fileSystemPath, 'sql' => $sqlPath, 'service' => $this->getPageDIR() . '/../?eID=completebackup');
		if( $this->conf['additionalInfo'] != '' )
			$params['additionalInfo'] = $this->conf['additionalInfo'];
		if( $this->conf['deleteFilesByServer'] )
			$params['deleteAfter'] = 1;
			
		$url = $this->conf['serverUrl'];
		$url .= (strpos($this->conf['serverUrl'], '?') === false) ? '?' : '&';
		$url .=  http_build_query($params, '', '&');
		
		return file_get_contents( $url );
	}
	
	function createFileSystemBackup($name, $list = NULL, $offset = 0) {
		$files = $_REQUEST['completebackup']['files'];
		
		require_once t3lib_extMgm::extPath('completebackup') . 'Resources/Php/class.Tar.php';
		if( !$this->conf['compressFileSystem'] ) 
			$fileSystem = new Tar();
		else
			$fileSystem = new TarGz();
			
		if( isset($list) ) 
			$open = $fileSystem->open(PATH_site . $this->conf['backupPath'] . $name, 'a');
		else
			$open = $fileSystem->open(PATH_site . $this->conf['backupPath'] . $name, 'w');
			
		if( $open ) {
			if( !isset($list) ) {
				foreach( $files as $file => $state ) {
					if ( is_dir(PATH_site . $file) )
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
				
			}	else {
				$fileSystem->setFileList( $list );
			}
			
			$msg = '';
			$result = $fileSystem->save($offset, $this->conf['timeout']);
			
			if( is_array($result) ) {
				$myResult = array('completebackup[offset]' => $result['offset'], 'completebackup[list]' => $result['list'], 'completebackup[name]' => $name);
				$msg = json_encode($myResult);
			} else
				$msg = 'done';
			$fileSystem->close();

			return $msg;
		}
		return false;
	}
	
	function showMenu() {
		$content = '';
		$content .= '
			<h3>Just hit the Button</h3>
			<form action="" method="post">
				<div>
				<button style="font-size: 40px; padding: 10px 40px;">DO THE JOB</button><br /> <br />
				<div style="margin: 15px 0 5px; color: #ff3333; font-weight: bold;">You usually don\'t want to mess with the options below</div>
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
					' . $this->getCheckBox('completebackup[conf][cleanFileSystem]', $this->conf['cleanFileSystem']) . ' clean the FileSystem <br />' . PHP_EOL . ' 
					' . $this->getCheckBox('completebackup[conf][cleanDb]', $this->conf['cleanDb']) . ' clean the Database <br />' . PHP_EOL . ' 
				</fieldset>
				</div>
			</form>
		';
		
		return $content;
	}
	
	function getFilterCheckBox($name, $value = '', $array = array() ) {
		$content = '<input type="checkbox" name="' . $name . '" ';
		$content .=	in_array($value, $array) ? '' : 'checked="checked"';
		$content .= ' /> ';
		return $content;
	}
	
	function getCheckBox($name, $checked) {
		$content = '<input type="checkbox" name="' . $name . '" ';
		$content .= $checked ? 'checked="checked"' : '';
		$content .= ' /> ';
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
		$content = $this->header('Complete Backup');
		$content .= $this->content;
		$content .= $this->footer();
		echo $content;
	}
	
	function header() {
		$header = '<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Complete Backup</title>
	<link rel="stylesheet" type="text/css" href="stylesheet.css" />
	<link rel="stylesheet" type="text/css" href="sysext/t3skin/stylesheets/stylesheet_post.css" />
	<style type="text/css">
		a { color: #ff0000; }
		#FileSystemBackup { color: #ff0000; }
	</style>
		';
	
		if( t3lib_extMgm::isLoaded('mpm') ) {
			require_once( t3lib_extMgm::extPath('mpm') . 'res/MPM/Classes/class.Mpr.php' );
			$localMPR = new MPR( array(
				'pathToMpr' => t3lib_extMgm::extRelPath('mpr') . 'res/MPR/',
				'cachePath' => '../typo3temp/mpm/cache/',
				'cacheAbsPath'    => PATH_site . 'typo3temp/mpm/cache/',
				'jsMinPath' => PATH_typo3 . 'contrib/jsmin/jsmin.php',
				'cache' => false,
				'externalFiles' => true,
				'compressJs' => 'none',
				'compressCss' => 'minify',
				'pathPreFix'   => PATH_site . 'typo3conf/'
			));
			
			$scriptTag = $localMPR->getScriptTagInlineCss( file_get_contents( PATH_site . 'typo3conf/ext/completebackup/mod1/completebackup.js') );
			
			$header .= '
	<script type="text/javascript">
		var MPR = {};
		MPR.path = \'../typo3conf/ext/mpr/res/MPR/\';
	</script>
	';
			$header .= $scriptTag;		
		} else {
			$topMsg = 'You need the Extension MPM to use ajax for compression as a usual TYPO3 installation takes way to long for a single PHP execution.';
		}
		
	$header .= '
	<script type="text/javascript" src="../typo3conf/ext/completebackup/mod1/completebackup.js"></script>

</head>
<body>
	' . $topMsg . '
<div class="typo3-bigDoc">
		';
		return $header;
	}
	
	function footer() {
		return '</div></body></html>';
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

?>
