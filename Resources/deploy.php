<?php

class TarGz extends Tar {

	function open( $filePath, $mode='w' ) {
		$this->tarFilePath = $filePath;
		$this->tarFile = @gzopen($filePath, $mode);
		return is_resource($this->tarFile);
	}
	
	function write($content) {
		return gzwrite($this->tarFile, $content);
	}

	function close() {
		return gzclose($this->tarFile);
	}
	
}

class Tar {

	var $fileList = array();
	var $path = '';
	var $tarFile;
	var $cut = 0;
	var $rights = 0777;
	
	function open( $filePath, $mode='w' ) {
		$this->tarFilePath = $filePath;
		$this->tarFile = @fopen($filePath, $mode);
		return is_resource($this->tarFile);
	}
	
	function addFile( $path, $file = '') {
		if ( is_file($path) ) {
			$this->fileList[$file] = $path;
			return true;
		}
		return false;
	}
	
	function setFileList( $fileList ) {
		if( is_array($fileList) ) {
			$this->fileList = $fileList;
			return true;
		}
		return false;
	}
	
	function removeFile( $what ) {
		if( isset($this->fileList[$what]) ) {
			unset( $this->fileList[$what] );
			return true;
		} elseif( ($key = array_search($what, $this->fileList)) !== false ) {
			unset( $this->fileList[$key] );
			return true;
		}
		return false;
	}
	
	function _addDir($path, $_dir = '', $depth = 10) {
		if( file_exists($path) ) {
			$this->fileList[$_dir] = $path;
			$d = dir($path);
			while (false !== ($dir = $d->read()) ) {
				if( ( $dir != "." && $dir != ".." ) ) {
					if ( is_dir($d->path . $dir) ) {
						if ( $depth >= 1 ) {
							$this->_addDir($d->path . $dir . '/', substr($d->path, $this->cut, strlen($d->path)) . $dir . '/', $depth-1);
						}
					} else {
						$this->addFile( $d->path . $dir, substr($d->path, $this->cut, strlen($d->path)) . $dir );
					}
				}
			}
			$d->close();
		}
		return false;
	}
	
	function addDir($path, $dir) {
		if( $path != '' && substr($path, -1) != DIRECTORY_SEPARATOR ) {
			$path .= '/';
		}		
		if( $dir != '' && substr($dir, -1) != DIRECTORY_SEPARATOR ) {
			$dir .= '/';
		}
		$this->cut = strlen( dirname($path) ) + 1;
		
		$this->_addDir($path, $dir);
	}
	
	function add($pattern) {
		$files = glob($pattern);
		foreach($files as $file) {
			if( is_file($file) ) {
				$this->addFile($file, basename($file) );
			} elseif( is_dir($file) ) {
				$this->addDir($file, dirname($file) );
			}
		}
	}
	
	function getFileHeader($file, $filePath) {
		$permissions = '00000000';
		$userid = '00000000';
		$groupid = '00000000';
		
		$eightBit = '';
		while( strlen($eightBit) < 8 )
			$eightBit .= chr(0);

		while( strlen($file) < 100 )
			$file .= chr(0);
			
		$ustar = 'ustar  ' . chr(0);
		
		if ( is_dir($filePath) ) {
			$fileSize = '0' . chr(0);
		}	else {
			$fileSize = sprintf('%o', filesize($filePath) ) . chr(0);
		}
		while( strlen($fileSize) < 12 )
			$fileSize = '0' . $fileSize;
		
		$modtime = sprintf('%o', filectime($filePath) ) . chr(0);
		$checksum = '        ';
		if( is_dir($filePath) ) {
			$indicator = 5;
		} else {
			$indicator = 0;
		}

		$linkName = '';
		while( strlen($linkName) < 100 )
			$linkName .= chr(0);
		
		$user = '';
		while( strlen($user) < 32 )
			$user .= chr(0);
		
		$group = '';
		while( strlen($group) < 32 )
			$group .= chr(0);
		
		$devmajor = $eightBit;
		$devminor = $eightBit;
		
		$prefix = '';
		while( strlen($prefix) < 155 )
			$prefix .= chr(0);
		
		$header = $file.$permissions.$userid.$groupid.$fileSize.$modtime.$checksum.$indicator.$linkName.$ustar.$user.$group.$devmajor.$devminor.$prefix;
		while( strlen($header) < 512 )
			$header .= chr(0);
		
		$checksum = 0;
		for ($y=0; $y < strlen($header); $y++)
			$checksum += ord($header[$y]);
			
		$checksum = sprintf('%o', $checksum) . chr(0) . ' ';
		while( strlen($checksum) < 8 )
			$checksum = '0' . $checksum;
			
		$header = $file.$permissions.$userid.$groupid.$fileSize.$modtime.$checksum.$indicator.$linkName.$ustar.$user.$group.$devmajor.$devminor.$prefix;
		while( strlen($header) < 512 )
			$header .= chr(0);

		return $header;
	}
	
	function write($content) {
		return fwrite($this->tarFile, $content);
	}
	
	function save($offset = 0, $timeLimit = 25) {
		if( !$this->tarFile ) return false;
		
		$startTime = microtime(true);
		ksort($this->fileList);
		
		$tmpList = $this->fileList;
		foreach( $this->fileList as $file => $filePath ) {
		
			if( $offset == 0 ) {
				$header = $this->getFileHeader($file, $filePath);
				$this->write($header);
			}
			
			if( !is_dir($filePath) ) {
				$contentfile = fopen($filePath, 'r');
				fseek($contentfile, $offset);
				
				while (!feof($contentfile)) {
					$data = fread($contentfile, 51200);
					if( strlen($data) != 51200 ) {
						while( strlen($data) % 512 != 0 )
							$data .= chr(0);
					}
						
					$this->write($data);
					
					if( (microtime(true) - $startTime) > $timeLimit ) {
						$newOffset = ftell($contentfile);
						if( $newOffset == filesize($filePath) ) {
							$newOffset = 0;
							unset($tmpList[$file]);
						}
						return array('offset' => $newOffset, 'list' => $tmpList);
					}
				}
				
			}
			unset($tmpList[$file]);
			
		}
		return true;
	}
	
	function close() {
		return fclose($this->tarFile);
	}
	
	public function getFileName($dataInfo) {
		$posCount = 0;
		$name = '';
		while( substr($dataInfo,$posCount,1) != chr(0) ) {
			$name .= substr($dataInfo, $posCount, 1);
			$posCount++;
		}
		return $name;
	}
	
	/**
	* saves some tar data to the given location and set some default
	*
	* @param array $dataInfo
	* @param array $data
	* @param string $extractTo path where to extract
	* @return boolean
	*/
	public function saveData($name, $data, $extractTo = '', $flag = FILE_APPEND ) {
		if( empty($name) || empty($data) ) return false;
		
		while( substr($data, -1, 1) == chr(0) )
			$data = substr($data, 0, strlen($data)-1);
			
		@file_put_contents($extractTo . $name, $data);
			
		if( file_exists($extractTo . $name) ) {
			return chmod($extractTo . $name, $this->rights);
		}
	}

	/**
	* extracts a given file to a given location
	*
	* @param string $filePath what file to use
	* @param string $extractTo path where to extract
	* @return boolean
	*/
	public function extract($extractTo = '', $offset = 0, $name = '', $timeLimit = 25) {
		if( !$this->tarFile ) return false;

		$startTime = microtime(true);

		gzseek( $this->tarFile, $offset );
		
		$data = '';
		while( !feof($this->tarFile) ) {
			$readData = gzread($this->tarFile, 512);
			if( substr($readData, 257, 5) == 'ustar') {
				if( !empty($name) && substr($name, -1) == '/' )
					@mkdir($extractTo . $name);
					
				if( !empty($data) )
					$this->saveData($name, $data, $extractTo);
			
				$name = $this->getFileName($readData);
				$data = '';
				
			} else {
				$data .= $readData;
			}

			if( (microtime(true) - $startTime) > $timeLimit ) {
				if( !empty($data) )
					$this->saveData($name, $data, $extractTo);
				return array('offset' => gztell($this->tarFile), 'file' => $name);
			}
			
		}
		if( !empty($data) )
			$this->saveData($name, $data, $extractTo);
		
		return gzclose($this->tarFile);
	}	
	
}

/**
 * a deploy script for a tared fileSystem and a sql dump (support gz for both)
 *
 * @license MIT-style license
 * @author Thomas Allmer <at@delusionworld.com>
 * @copyright Copyright belongs to the respective authors
 */ 
class Deployer extends Options {

	public $options = array(
		'searchPath' => '',
		'extractPath' => './',
		'configFile' => 'typo3conf/hostconf.php',
		'baseUrlFile' => 'fileadmin/templates/main/ts/constants.ts',
		'override' => false
	);
	
	var $fileSystem = false;
	var $sql = false;
	var $error = false;
	var $updateAdminPwStatus = false;

	public function Deployer($options = null) {
		$this->setOptions($options);
		if( isset($_REQUEST['overrideConfig']) && $_REQUEST['overrideConfig'] == 'on' ) {
			$this->options->override = true;
		}

		$this->fileSystem = glob($this->options->searchPath . '*.tar.gz*');
		$this->sql = glob($this->options->searchPath . '*.sql*');
		if( count($this->fileSystem) > 0 && count($this->sql) > 0 ) {
			$this->fileSystem = $this->fileSystem[0];
			$this->sql = $this->sql[0];
		}
		
		if( isset($_REQUEST['deployFileSystem']) && $_REQUEST['deployFileSystem'] == 'on' && $this->fileSystem ) {
			$this->deployFileSystem( $this->fileSystem );
		}
		
		if( $this->hasRequestSqlConfig() && (!is_file($this->options->extractPath . $this->options->configFile) || $this->options->override) ) {
			$this->saveConfigFile();
		}
		
		if( isset($_REQUEST['deploySql']) && $_REQUEST['deploySql'] == 'on' ) {
			
			if ( is_file($this->options->extractPath . $this->options->configFile)  ) {
				$this->deploySql( $this->sql );
			}	else {
				$this->error = 'No manual config provided and no config file found at ' . $this->options->extractPat . $this->options->configFile;
			}
			
		}
		
		if( isset($_REQUEST['updateAdminPw']) && $_REQUEST['updateAdminPw'] == 'on' ) {
			if( isset($_REQUEST['adminPw']) && $_REQUEST['adminPw'] != '' ) {
				if( $this->updateAdminPw($_REQUEST['adminPw']) ) {
					$this->updateAdminPwStatus = 'done';
				} else {
					$this->error = 'Could not update admin password: ' . mysql_error(); 
				}
			} else {
				$this->error = 'If you want to update the admin password you need to define one (no empty pw)';
			}
		}
		
		if( isset($_REQUEST['updateDomain']) && $_REQUEST['updateDomain'] == 'on' ) {
			if( isset($_REQUEST['domain']) && $_REQUEST['domain'] != '' ) {
				if( !$this->updateDomain($_REQUEST['domain']) ) {
					$this->error = 'Could not update domain'; 
				}
			} else {
				$this->error = 'If you want to update the domain you need to define one (can not be empty)';
			}
		}
			
		if( !$this->error && isset($_REQUEST['deleteBackup']) && $_REQUEST['deleteBackup'] == 'on' ) {
			$this->deleteBackup();
		}

		if( !$this->error && isset($_REQUEST['deleteDeploy']) && $_REQUEST['deleteDeploy'] == 'on' ) {
			$this->deleteDeploy();
		}
		
	}
	
	public function updateDomain($domain) {
		$buffer = array();
		$handle = @fopen($this->options->extractPath . $this->options->baseUrlFile, 'r');
		if ($handle) {
			while (!feof($handle)) {
				$buffer[] = fgets($handle, 4096);
			}
			fclose($handle);
			foreach( $buffer as $key => $line ) {
				if( stripos($line, 'baseUrl') !== false ) {
					$buffer[$key+1] = 'baseUrl = ' . $domain . PHP_EOL;
					return file_put_contents($this->options->extractPath . $this->options->baseUrlFile, $buffer);
				}
			}
		}
		return false;
	}
	
	public function updateAdminPw($password) {
		if( !is_resource($this->link) ) {
			$this->connect();
		}
		
		return mysql_query('UPDATE `be_users` SET `password` = MD5( \'' . $password . '\' ) WHERE `uid` = 1;', $this->link);
	}
	
	public function getUpdateAdminPwStatus() {
		return $this->updateAdminPwStatus;
	}
	
	public function hasRequestSqlConfig() {
		if( isset($_REQUEST['typo_db_username']) && $_REQUEST['typo_db_username'] != '' &&
				isset($_REQUEST['typo_db_password']) &&
				isset($_REQUEST['typo_db_host']) && $_REQUEST['typo_db_host'] != '' &&
				isset($_REQUEST['typo_db']) && $_REQUEST['typo_db'] != '' ) {
			return true;
		}
		return false;
	}
	
	public function getSqlStatus() {
		if( is_file($this->options->extractPath . $this->options->configFile) ) {
			if( !is_resource($this->link) ) {
				$this->connect(false);
			}
			if( is_resource($this->link) ) {
				require $this->options->extractPath . $this->options->configFile;
				if( @mysql_select_db($typo_db, $this->link) ) {
					return 'done';
				}
				return 'saveDeploy';
			} else {
				return 'noConnection';
			}
		}
		if( !is_file($this->sql) ) {
			return 'noBackupFile';
		}
		return 'noConfig';
	}
	
	public function saveConfigFile() {
		if( is_file($this->options->extractPath . $this->options->configFile) && !$this->options->override ) {
			$this->error = 'Config File (' . $this->options->extractPath . $this->options->configFile . ') exists you need to select override if you want to replace it';
			return false;
		}
		$config  = '<?php' . PHP_EOL;
		$config .= '  $typo_db_username = \'' . $_REQUEST['typo_db_username'] . '\';' . PHP_EOL;
		$config .= '  $typo_db_password = \'' . $_REQUEST['typo_db_password'] . '\';' . PHP_EOL;
		$config .= '  $typo_db_host = \'' . $_REQUEST['typo_db_host'] . '\';' . PHP_EOL;
		$config .= '  $typo_db = \'' . $_REQUEST['typo_db'] . '\';' . PHP_EOL;
		$config .= '?>' . PHP_EOL;
		if( $size = @file_put_contents( $this->options->extractPath . $this->options->configFile, $config ) ) {
			return $size;
		} else {
			$this->error = 'Could not write Config File; The folders are there right?';
			return false;
		}
	}
	
	function mysqlBigImport( $sqlPath ) {
		$handle = gzopen($sqlPath, 'r');
		$queries = array();
		$buffer = '';
		while (!gzeof($handle)) {
			$line = gzgets($handle, 100000);
			$line = trim($line);
			if( !ereg('^--', $line) && !$line == '' ) {
				$buffer .= ($buffer != '') ? ' ' . $line : $line;
				if( substr($line, -1) == ';' ) {
					$queries[] .= $buffer;
					$buffer = '';
				}
			}
		}
		
		foreach( $queries as $query ) {
			if( !mysql_query($query, $this->link) ) {
				return false;
			}
		}
		return true;
	}
	
	public function connect($selectDb = true) {
		require_once $this->options->extractPath . $this->options->configFile;
		if( !$this->link = @mysql_connect($typo_db_host, $typo_db_username, $typo_db_password) ) {
			$this->error = 'Could not connect: ' . mysql_error();
			return false;
		}
		
		if( $selectDb ) {
			if( !mysql_select_db($typo_db, $this->link) ) {
				if( !mysql_query('CREATE DATABASE `' . $typo_db . '`', $this->link) ) {
					$this->error = 'Could not create database: ' . mysql_error();
					return false;
				} else {
					return mysql_select_db($typo_db, $this->link);
				}
			}
		}
		
		return true;
	}
	
	public function disconnect() {
		if( is_resource( $this->link ) ) {
			return mysql_close($this->link);
		}
		return false;
	}
	
	public function deploySql($sqlPath) {
		if( !is_resource($this->link) ) {
			$this->connect();
		}
		if( is_resource($this->link) ) {
			if( !$this->mysqlBigImport($sqlPath) ) {
				$this->error = 'sql could not be imported: ' . mysql_error();
				return false;
			}
			return true;
		}
		$this->error = 'no connection: ' . mysql_error();
		return false;
	}
	
	public function deployFileSystem($fileSystemPath, $offset = 0) {
	
		$file = '';
		if( isset($_REQUEST['file']) )
			$file = $_REQUEST['file'];	
	
		$fileSystem = new TarGz();
		$open = $fileSystem->open($fileSystemPath, 'r');
		
		if( $open ) {
			$result = $fileSystem->extract($this->options->extractPath, $offset, $file);
			if( is_array($result) )
				echo json_encode($result);
			else
				echo 'done';
		}
		
		$this->error = 'Could not deploy the FileSystem';
		return false;
	}
	
	public function deleteDeploy() {
		unlink(__FILE__);
	}		
	
	public function deleteBackup() {
		unlink($this->fileSystem);
		unlink($this->sql);
	}
	
	public function getSqlLink() {
		return $this->getLink($this->sql);
	}		
	
	public function getZipLink() {
		return $this->getLink($this->fileSystem);
	}
	
	public function getLink($name, $link = null) {
		$link = isset($link) ? $link : $name;
		return '<a href="' . $link . '">' . $name . '</a>';
	}
	
	public function getFileSystemStatus() {
		if( !$this->hasAllTypo3RootFiles() ) {
			if( !$this->hasAnyTypo3RootFile() && is_writable($this->options->extractPath) && is_file($this->fileSystem) ) {
				return 'saveDeploy';
			} elseif( $this->hasAnyTypo3RootFile() && is_writable($this->options->extractPath) && is_file($this->fileSystem) ) {
				return 'overrideDeploy';
			} elseif( !is_file($this->fileSystem) ) {
				return 'noBackupFile';
			} elseif( !is_writable($this->options->extractPath) ) {
				return 'directoryNotWriteable';
			}
		} else {
			return 'alreadyDeployed';
		}
		return false;
	}
	
	private function hasAnyTypo3RootFile() {
		if( is_dir($this->options->extractPath . 'typo3conf') || is_dir($this->options->extractPath . 't3lib') 
			|| is_dir($this->options->extractPath . 'typo3') || is_dir($this->options->extractPath . 'fileadmin')
			|| is_dir($this->options->extractPath . 'typo3temp') || is_dir($this->options->extractPath . 'uploads')
			|| is_file($this->options->extractPath . 'index.php') || is_file($this->options->extractPath . '.htaccess') || is_file($this->options->extractPath . 'clear.gif') ) {
				return true;
		}
		return false;
	}

	private function hasAllTypo3RootFiles() {
		if( is_dir($this->options->extractPath . 'typo3conf') && is_dir($this->options->extractPath . 't3lib') 
			&& is_dir($this->options->extractPath . 'typo3') && is_dir($this->options->extractPath . 'fileadmin')
			&& is_dir($this->options->extractPath . 'typo3temp') && is_dir($this->options->extractPath . 'uploads')
			&& is_file($this->options->extractPath . 'index.php') && is_file($this->options->extractPath . '.htaccess') && is_file($this->options->extractPath . 'clear.gif') ) {
				return true;
		}
		return false;
	}
	
}

$deploy = new Deployer();

if( $_REQUEST['mode'] == 'ajax' ) {
	echo $deploy->deployFileSystem($deploy->fileSystem, $_REQUEST['offset']);
	die();
}

?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Deploy a TYPO3 Backup</title>
	
	<style type="text/css">
		body { font-family: 'Trebuchet MS', Arial; background: #efefef; }
		#wrap { width: 960px; margin: 0 auto; }
		h1 { text-transform: uppercase; text-align: center; font-size: 60px; }
		h3 { text-align: center; color: #D4310A; font-size: 12px; }
		button { font-size: 40px; padding: 10px 40px; text-transform: uppercase; }
		a { color: #094F9B; text-decoration: none; }
			a:hover { color: #FF0000; }
		sup a { color: #666; text-decoration: none; }
		fieldset { border: 1px solid #ccc; padding: 3px; margin: 20px; }
		legend { cursor: pointer; margin: 0 15px }
		label { width: 90px; display: block; float: left; }
		input { border: 1px solid #ccc; }
		.info { font-size: 10px; margin-left: 5px; padding: 0 0 0 20px; background: url(data:image/gif;base64,R0lGODlhEAAQAPQAAP///wAAAPDw8IqKiuDg4EZGRnp6egAAAFhYWCQkJKysrL6+vhQUFJycnAQEBDY2NmhoaAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAAFdyAgAgIJIeWoAkRCCMdBkKtIHIngyMKsErPBYbADpkSCwhDmQCBethRB6Vj4kFCkQPG4IlWDgrNRIwnO4UKBXDufzQvDMaoSDBgFb886MiQadgNABAokfCwzBA8LCg0Egl8jAggGAA1kBIA1BAYzlyILczULC2UhACH5BAkKAAAALAAAAAAQABAAAAV2ICACAmlAZTmOREEIyUEQjLKKxPHADhEvqxlgcGgkGI1DYSVAIAWMx+lwSKkICJ0QsHi9RgKBwnVTiRQQgwF4I4UFDQQEwi6/3YSGWRRmjhEETAJfIgMFCnAKM0KDV4EEEAQLiF18TAYNXDaSe3x6mjidN1s3IQAh+QQJCgAAACwAAAAAEAAQAAAFeCAgAgLZDGU5jgRECEUiCI+yioSDwDJyLKsXoHFQxBSHAoAAFBhqtMJg8DgQBgfrEsJAEAg4YhZIEiwgKtHiMBgtpg3wbUZXGO7kOb1MUKRFMysCChAoggJCIg0GC2aNe4gqQldfL4l/Ag1AXySJgn5LcoE3QXI3IQAh+QQJCgAAACwAAAAAEAAQAAAFdiAgAgLZNGU5joQhCEjxIssqEo8bC9BRjy9Ag7GILQ4QEoE0gBAEBcOpcBA0DoxSK/e8LRIHn+i1cK0IyKdg0VAoljYIg+GgnRrwVS/8IAkICyosBIQpBAMoKy9dImxPhS+GKkFrkX+TigtLlIyKXUF+NjagNiEAIfkECQoAAAAsAAAAABAAEAAABWwgIAICaRhlOY4EIgjH8R7LKhKHGwsMvb4AAy3WODBIBBKCsYA9TjuhDNDKEVSERezQEL0WrhXucRUQGuik7bFlngzqVW9LMl9XWvLdjFaJtDFqZ1cEZUB0dUgvL3dgP4WJZn4jkomWNpSTIyEAIfkECQoAAAAsAAAAABAAEAAABX4gIAICuSxlOY6CIgiD8RrEKgqGOwxwUrMlAoSwIzAGpJpgoSDAGifDY5kopBYDlEpAQBwevxfBtRIUGi8xwWkDNBCIwmC9Vq0aiQQDQuK+VgQPDXV9hCJjBwcFYU5pLwwHXQcMKSmNLQcIAExlbH8JBwttaX0ABAcNbWVbKyEAIfkECQoAAAAsAAAAABAAEAAABXkgIAICSRBlOY7CIghN8zbEKsKoIjdFzZaEgUBHKChMJtRwcWpAWoWnifm6ESAMhO8lQK0EEAV3rFopIBCEcGwDKAqPh4HUrY4ICHH1dSoTFgcHUiZjBhAJB2AHDykpKAwHAwdzf19KkASIPl9cDgcnDkdtNwiMJCshACH5BAkKAAAALAAAAAAQABAAAAV3ICACAkkQZTmOAiosiyAoxCq+KPxCNVsSMRgBsiClWrLTSWFoIQZHl6pleBh6suxKMIhlvzbAwkBWfFWrBQTxNLq2RG2yhSUkDs2b63AYDAoJXAcFRwADeAkJDX0AQCsEfAQMDAIPBz0rCgcxky0JRWE1AmwpKyEAIfkECQoAAAAsAAAAABAAEAAABXkgIAICKZzkqJ4nQZxLqZKv4NqNLKK2/Q4Ek4lFXChsg5ypJjs1II3gEDUSRInEGYAw6B6zM4JhrDAtEosVkLUtHA7RHaHAGJQEjsODcEg0FBAFVgkQJQ1pAwcDDw8KcFtSInwJAowCCA6RIwqZAgkPNgVpWndjdyohACH5BAkKAAAALAAAAAAQABAAAAV5ICACAimc5KieLEuUKvm2xAKLqDCfC2GaO9eL0LABWTiBYmA06W6kHgvCqEJiAIJiu3gcvgUsscHUERm+kaCxyxa+zRPk0SgJEgfIvbAdIAQLCAYlCj4DBw0IBQsMCjIqBAcPAooCBg9pKgsJLwUFOhCZKyQDA3YqIQAh+QQJCgAAACwAAAAAEAAQAAAFdSAgAgIpnOSonmxbqiThCrJKEHFbo8JxDDOZYFFb+A41E4H4OhkOipXwBElYITDAckFEOBgMQ3arkMkUBdxIUGZpEb7kaQBRlASPg0FQQHAbEEMGDSVEAA1QBhAED1E0NgwFAooCDWljaQIQCE5qMHcNhCkjIQAh+QQJCgAAACwAAAAAEAAQAAAFeSAgAgIpnOSoLgxxvqgKLEcCC65KEAByKK8cSpA4DAiHQ/DkKhGKh4ZCtCyZGo6F6iYYPAqFgYy02xkSaLEMV34tELyRYNEsCQyHlvWkGCzsPgMCEAY7Cg04Uk48LAsDhRA8MVQPEF0GAgqYYwSRlycNcWskCkApIyEAOwAAAAAAAAAAAA==) no-repeat left center; }
		
		#legend { font-size: 11px; color: #666; position: absolute; bottom: 0; border-top: 1px solid #ccc; width: 960px; margin: 0 auto; }
		#actions ul { list-style-type: none; margin: 0; padding: 0; }
			#actions ul li { margin: 4px; padding-bottom: 2px; }
		
		.ready { background: #BAE3BC; border: 1px solid #099D11; }
		.warning { background: #FEFF99; border: 1px solid #E3DC18; }
		.error { background: #E18585; border: 1px solid #D4310A; }
		.done { background: #E5E6E6; border: 1px solid #B0B0B0; }
		
		#newPassword { color: #094F9B; cursor: pointer; font-size: 12px;  }
		#newPassword:hover { color: #FF0000; }
	</style>
</head>
<body>
<div id="wrap">
	<h1>Deploy a TYPO3 Backup</h1>
	<?php 
		if( $deploy->error ) {
			echo '<h3>' . $deploy->error . '</h3>';
		}
	?>
	
	<form id="bigForm" action="" method="post">
		
		<div id="actions">
			<ul>
				<?php 
					$fileSystemStatus = $deploy->getFileSystemStatus();
					$msg = ''; $class = 'error'; 
					$checked = ( isset($_REQUEST['deployFileSystem']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					if( $fileSystemStatus == 'saveDeploy' ) {
						$class = 'ready';
						$msg = '[found: ' . $deploy->getZipLink() . '][directory writeable]';
					} elseif( $fileSystemStatus == 'overrideDeploy' ) {
						$class = 'warning';
						$msg = '[found: ' . $deploy->getZipLink() . '][some TYPO3 files found<sup><a href="#f4">4</a></sup>][directory writeable]';
					} elseif( $fileSystemStatus == 'noBackupFile' || $fileSystemStatus == 'directoryNotWriteable' || !$fileSystemStatus ) {
						$class = 'error'; $checked = '';
						$msg = '[error: ' . $fileSystemStatus . ']';
					} elseif( $fileSystemStatus == 'alreadyDeployed' ) {
						$class = 'done'; $checked = '';
						$msg = '[already deployed<sup><a href="#f5">5</a></sup>]';
					}
					echo '<li class="' . $class . '"><input type="checkbox" name="deployFileSystem" ' . $checked . ' /> Deploy the FileSystem<sup><a href="#f1">1</a></sup>' . $msg . '</li>';
					
					// SQL
					$sqlStatus = $deploy->getSqlStatus();
					$msg = ''; $class = 'error';
					$checked = ( isset($_REQUEST['deploySql']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					if( $sqlStatus == 'saveDeploy' ) {
						$class = 'ready'; 
						$msg = '[found: ' . $deploy->getSqlLink() . ']';
					} elseif ( $sqlStatus == 'noBackupFile' ) {
						$class = 'error'; $checked = '';
						$msg = '[error: noBackupFile]';
					} elseif( $sqlStatus == 'noConfig' ) {
						$class = 'warning';
						$msg = '[found: ' . $deploy->getSqlLink() . '][warning: noConfigFile]';
					} elseif( $sqlStatus == 'noConfig' && $fileSystemStatus == 'alreadyDeployed' ) {
						$class = 'error';
						$msg = '[found: ' . $deploy->getSqlLink() . '][error: noConfigFile, but filesystem already deployed]';
					} elseif( $sqlStatus == 'noConnection' ) {
						$class = 'error'; $checked = '';
						$msg = '[found: ' . $deploy->getSqlLink() . '][error: ' . mysql_error() . ']';
					} elseif( $sqlStatus == 'done' ) {
						$class = 'done'; $checked = '';
						$msg = '[Database already found][check if you want to override]';
					}
					echo '<li class="' . $class . '"><input type="checkbox" name="deploySql" ' . $checked . ' /> Deploy the Database<sup><a href="#f2">2</a></sup>' . $msg . '</li>';
					
					// admin PW
					$checked = ( isset($_REQUEST['changeAdminPw']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					$class = 'error'; $msg = '';
					if( $sqlStatus == 'saveDeploy' || $sqlStatus == 'done' ) {
						$class = 'ready';
					} elseif( $sqlStatus == 'noConfig' ) {
						$class = 'warning';
						$msg = '[warning: noConfigFile]';
					} elseif( $sqlStatus == 'noConfig' && $fileSystemStatus == 'alreadyDeployed' ) {
						$class = 'error';
						$msg = '[found: ' . $deploy->getSqlLink() . '][error: noConfigFile, but filesystem already deployed]';
					} elseif( $sqlStatus == 'noConnection' ) {
						$class = 'error'; $checked = '';
						$msg = '[error: ' . mysql_error() . ']';
					}
					if( $deploy->getUpdateAdminPwStatus() == 'done' ) {
						$class = 'done'; $checked = '';
						$msg = '[password updated]';
					}
					$value = isset($_REQUEST['adminPw']) ? $_REQUEST['adminPw'] : '';
					echo '<li class="' . $class . '"><input type="checkbox" name="updateAdminPw" ' . $checked . ' /> Change Admin Password to <input type="text" style="width: 100px;" name="adminPw" id="adminPwInput" value="' . $value . '" /><span id="newPassword"> (random Password)</span>' . $msg . '</li>';
					
					// BaseUrl
					$checked = ( isset($_REQUEST['updateDomain']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					$class = 'error'; $msg = '';
					$value = isset($_REQUEST['domain']) ? $_REQUEST['domain'] : '';
					if( is_file($deploy->options->extractPath . $deploy->options->baseUrlFile) ) {
						if( $buffer = file_get_contents($deploy->options->extractPath . $deploy->options->baseUrlFile) ) {
							preg_match_all("#baseUrl\s*=\s*(.+)#", $buffer, $out, PREG_PATTERN_ORDER);
							if( trim($out[1][0]) == trim($value) ) {
								if( isset($_REQUEST['updateDomain']) && $_REQUEST['updateDomain'] == 'on' ) {
									$class = 'done';
									$checked = '';
									$msg = '[domain updated]';
								} else {
									$class = 'ready';
								}
							} elseif( is_writeable($deploy->options->extractPath . $deploy->options->baseUrlFile) ) {
								$class = 'ready';
								if( $value == '' ) {
									$value = $out[1][0];
								}
							} else {
								$class = 'error';
								$msg = '[error: file not writeable]';
							}
						} else {
							$class = 'error';
							$msg = '[error: could not open file]';
						}
					} elseif ($fileSystemStatus != 'alreadyDeployed') {
						$class = 'warning';
						$value = Helper::getPageDIR() . '/';
						$msg = '[warning: file not found]';
					} else {
						$class = 'error';
						$msg = '[error: file not found, but filesystem already deployed]';
					}
					echo '<li class="' . $class . '"><input type="checkbox" name="updateDomain" ' . $checked . ' /> Update BaseUrl to <input type="text" style="width: 300px;" name="domain" value="' . $value . '" /> <span style="font-size: 13px; color: #888;">(' . $deploy->options->baseUrlFile . ')</span>' . $msg . ' </li>';
					
					// DELETE FILES
					$checked = ( isset($_REQUEST['deleteBackup']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					if( is_file($deploy->fileSystem) || is_file($deploy->sql) ) {
						if( is_writeable($deploy->fileSystem) && is_writeable($deploy->sql) ) {
							echo '<li class="ready"><input type="checkbox" name="deleteBackup" ' . $checked . ' /> Delete used backup files</li>';
						} else {
							echo '<li class="error"><input type="checkbox" name="deleteBackup" ' . $checked . ' /> Delete used backup files [Files not writeable]</li>';
						}
					} else {
						echo '<li class="done"><input type="checkbox" name="deleteBackup" /> Delete used backup files [deleted]</li>';
					}
					
					$checked = ( isset($_REQUEST['deleteDeploy']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					if( is_file(__FILE__) ) {
						if( is_writeable(__FILE__) ) {
							echo '<li class="ready"><input type="checkbox" name="deleteDeploy" ' . $checked . ' /> Delete this File<sup><a href="#f3">3</a></sup></li>';
						} else {
							echo '<li class="error"><input type="checkbox" name="deleteDeploy" ' . $checked . ' /> Delete this File<sup><a href="#f3">3</a></sup> [File not writeable]</li>';
						}
					} else {
						echo '<li class="done"><input type="checkbox" name="deleteDeploy" /> Delete this File<sup><a href="#f3">3</a></sup> [deleted]</li>';
					}
				?>
			</ul>
			<p style="text-align: center;"><button type="submit" name="submitted">do the job</button></p>
		</div>
	
		<fieldset id="configSqlFieldset">
			<legend onclick="toggleDisplay();">Manual Sql Configuration</legend>
			<div id="configSql">
				<label for="overrideConfig">Override:</label> <input type="checkbox" name="overrideConfig" id="overrideConfig" /> Do you want to override a found config file (<?php echo $deploy->options->extactPath . $deploy->options->configFile; ?>)? <br />
				<label for="typo_db_username">Username:</label> <input type="text" name="typo_db_username" id="typo_db_username" /> <br />
				<label for="typo_db_password">Password:</label> <input type="text" name="typo_db_password" id="typo_db_password" /> <br />
				<label for="typo_db_host">Host:</label> <input type="text" name="typo_db_host" id="typo_db_host" /> <br />
				<label for="typo_db">Database:</label>	<input type="text" name="typo_db" id="typo_db" /> <br />
			</div>
		</fieldset>
		
	</form>
	
	<div id="legend">
		<ol>
			<li id="f1">Extract the files</li>
			<li id="f2">Needs the config file (<?php echo $deploy->options->extactPath . $deploy->options->configFile; ?>) from the FileSystem or a Manual Sql Configuration on this page</li>
			<li id="f3">Filename deploy.php</li>
			<li id="f4">They will be overwritten</li>
			<li id="f5">If you still select it, it will be completely overwritten</li>
		</ol>
	</div>	
	
</div>

	<script type="text/javascript">
	/* <![CDATA[ */
//MooTools, <http://mootools.net>, My Object Oriented (JavaScript) Tools. Copyright (c) 2006-2009 Valerio Proietti, <http://mad4milk.net>, MIT Style License.

var MooTools={version:"1.2.3",build:"4980aa0fb74d2f6eb80bcd9f5b8e1fd6fbb8f607"};var Native=function(k){k=k||{};var a=k.name;var i=k.legacy;var b=k.protect;
var c=k.implement;var h=k.generics;var f=k.initialize;var g=k.afterImplement||function(){};var d=f||i;h=h!==false;d.constructor=Native;d.$family={name:"native"};
if(i&&f){d.prototype=i.prototype;}d.prototype.constructor=d;if(a){var e=a.toLowerCase();d.prototype.$family={name:e};Native.typize(d,e);}var j=function(n,l,o,m){if(!b||m||!n.prototype[l]){n.prototype[l]=o;
}if(h){Native.genericize(n,l,b);}g.call(n,l,o);return n;};d.alias=function(n,l,p){if(typeof n=="string"){var o=this.prototype[n];if((n=o)){return j(this,l,n,p);
}}for(var m in n){this.alias(m,n[m],l);}return this;};d.implement=function(m,l,o){if(typeof m=="string"){return j(this,m,l,o);}for(var n in m){j(this,n,m[n],l);
}return this;};if(c){d.implement(c);}return d;};Native.genericize=function(b,c,a){if((!a||!b[c])&&typeof b.prototype[c]=="function"){b[c]=function(){var d=Array.prototype.slice.call(arguments);
return b.prototype[c].apply(d.shift(),d);};}};Native.implement=function(d,c){for(var b=0,a=d.length;b<a;b++){d[b].implement(c);}};Native.typize=function(a,b){if(!a.type){a.type=function(c){return($type(c)===b);
};}};(function(){var a={Array:Array,Date:Date,Function:Function,Number:Number,RegExp:RegExp,String:String};for(var h in a){new Native({name:h,initialize:a[h],protect:true});
}var d={"boolean":Boolean,"native":Native,object:Object};for(var c in d){Native.typize(d[c],c);}var f={Array:["concat","indexOf","join","lastIndexOf","pop","push","reverse","shift","slice","sort","splice","toString","unshift","valueOf"],String:["charAt","charCodeAt","concat","indexOf","lastIndexOf","match","replace","search","slice","split","substr","substring","toLowerCase","toUpperCase","valueOf"]};
for(var e in f){for(var b=f[e].length;b--;){Native.genericize(a[e],f[e][b],true);}}})();var Hash=new Native({name:"Hash",initialize:function(a){if($type(a)=="hash"){a=$unlink(a.getClean());
}for(var b in a){this[b]=a[b];}return this;}});Hash.implement({forEach:function(b,c){for(var a in this){if(this.hasOwnProperty(a)){b.call(c,this[a],a,this);
}}},getClean:function(){var b={};for(var a in this){if(this.hasOwnProperty(a)){b[a]=this[a];}}return b;},getLength:function(){var b=0;for(var a in this){if(this.hasOwnProperty(a)){b++;
}}return b;}});Hash.alias("forEach","each");Array.implement({forEach:function(c,d){for(var b=0,a=this.length;b<a;b++){c.call(d,this[b],b,this);}}});Array.alias("forEach","each");
function $A(b){if(b.item){var a=b.length,c=new Array(a);while(a--){c[a]=b[a];}return c;}return Array.prototype.slice.call(b);}function $arguments(a){return function(){return arguments[a];
};}function $chk(a){return !!(a||a===0);}function $clear(a){clearTimeout(a);clearInterval(a);return null;}function $defined(a){return(a!=undefined);}function $each(c,b,d){var a=$type(c);
((a=="arguments"||a=="collection"||a=="array")?Array:Hash).each(c,b,d);}function $empty(){}function $extend(c,a){for(var b in (a||{})){c[b]=a[b];}return c;
}function $H(a){return new Hash(a);}function $lambda(a){return($type(a)=="function")?a:function(){return a;};}function $merge(){var a=Array.slice(arguments);
a.unshift({});return $mixin.apply(null,a);}function $mixin(e){for(var d=1,a=arguments.length;d<a;d++){var b=arguments[d];if($type(b)!="object"){continue;
}for(var c in b){var g=b[c],f=e[c];e[c]=(f&&$type(g)=="object"&&$type(f)=="object")?$mixin(f,g):$unlink(g);}}return e;}function $pick(){for(var b=0,a=arguments.length;
b<a;b++){if(arguments[b]!=undefined){return arguments[b];}}return null;}function $random(b,a){return Math.floor(Math.random()*(a-b+1)+b);}function $splat(b){var a=$type(b);
return(a)?((a!="array"&&a!="arguments")?[b]:b):[];}var $time=Date.now||function(){return +new Date;};function $try(){for(var b=0,a=arguments.length;b<a;
b++){try{return arguments[b]();}catch(c){}}return null;}function $type(a){if(a==undefined){return false;}if(a.$family){return(a.$family.name=="number"&&!isFinite(a))?false:a.$family.name;
}if(a.nodeName){switch(a.nodeType){case 1:return"element";case 3:return(/\S/).test(a.nodeValue)?"textnode":"whitespace";}}else{if(typeof a.length=="number"){if(a.callee){return"arguments";
}else{if(a.item){return"collection";}}}}return typeof a;}function $unlink(c){var b;switch($type(c)){case"object":b={};for(var e in c){b[e]=$unlink(c[e]);
}break;case"hash":b=new Hash(c);break;case"array":b=[];for(var d=0,a=c.length;d<a;d++){b[d]=$unlink(c[d]);}break;default:return c;}return b;}var Browser=$merge({Engine:{name:"unknown",version:0},Platform:{name:(window.orientation!=undefined)?"ipod":(navigator.platform.match(/mac|win|linux/i)||["other"])[0].toLowerCase()},Features:{xpath:!!(document.evaluate),air:!!(window.runtime),query:!!(document.querySelector)},Plugins:{},Engines:{presto:function(){return(!window.opera)?false:((arguments.callee.caller)?960:((document.getElementsByClassName)?950:925));
},trident:function(){return(!window.ActiveXObject)?false:((window.XMLHttpRequest)?5:4);},webkit:function(){return(navigator.taintEnabled)?false:((Browser.Features.xpath)?((Browser.Features.query)?525:420):419);
},gecko:function(){return(document.getBoxObjectFor==undefined)?false:((document.getElementsByClassName)?19:18);}}},Browser||{});Browser.Platform[Browser.Platform.name]=true;
Browser.detect=function(){for(var b in this.Engines){var a=this.Engines[b]();if(a){this.Engine={name:b,version:a};this.Engine[b]=this.Engine[b+a]=true;
break;}}return{name:b,version:a};};Browser.detect();Browser.Request=function(){return $try(function(){return new XMLHttpRequest();},function(){return new ActiveXObject("MSXML2.XMLHTTP");
});};Browser.Features.xhr=!!(Browser.Request());Browser.Plugins.Flash=(function(){var a=($try(function(){return navigator.plugins["Shockwave Flash"].description;
},function(){return new ActiveXObject("ShockwaveFlash.ShockwaveFlash").GetVariable("$version");})||"0 r0").match(/\d+/g);return{version:parseInt(a[0]||0+"."+a[1],10)||0,build:parseInt(a[2],10)||0};
})();function $exec(b){if(!b){return b;}if(window.execScript){window.execScript(b);}else{var a=document.createElement("script");a.setAttribute("type","text/javascript");
a[(Browser.Engine.webkit&&Browser.Engine.version<420)?"innerText":"text"]=b;document.head.appendChild(a);document.head.removeChild(a);}return b;}Native.UID=1;
var $uid=(Browser.Engine.trident)?function(a){return(a.uid||(a.uid=[Native.UID++]))[0];}:function(a){return a.uid||(a.uid=Native.UID++);};var Window=new Native({name:"Window",legacy:(Browser.Engine.trident)?null:window.Window,initialize:function(a){$uid(a);
if(!a.Element){a.Element=$empty;if(Browser.Engine.webkit){a.document.createElement("iframe");}a.Element.prototype=(Browser.Engine.webkit)?window["[[DOMElement.prototype]]"]:{};
}a.document.window=a;return $extend(a,Window.Prototype);},afterImplement:function(b,a){window[b]=Window.Prototype[b]=a;}});Window.Prototype={$family:{name:"window"}};
new Window(window);var Document=new Native({name:"Document",legacy:(Browser.Engine.trident)?null:window.Document,initialize:function(a){$uid(a);a.head=a.getElementsByTagName("head")[0];
a.html=a.getElementsByTagName("html")[0];if(Browser.Engine.trident&&Browser.Engine.version<=4){$try(function(){a.execCommand("BackgroundImageCache",false,true);
});}if(Browser.Engine.trident){a.window.attachEvent("onunload",function(){a.window.detachEvent("onunload",arguments.callee);a.head=a.html=a.window=null;
});}return $extend(a,Document.Prototype);},afterImplement:function(b,a){document[b]=Document.Prototype[b]=a;}});Document.Prototype={$family:{name:"document"}};
new Document(document);Array.implement({every:function(c,d){for(var b=0,a=this.length;b<a;b++){if(!c.call(d,this[b],b,this)){return false;}}return true;
},filter:function(d,e){var c=[];for(var b=0,a=this.length;b<a;b++){if(d.call(e,this[b],b,this)){c.push(this[b]);}}return c;},clean:function(){return this.filter($defined);
},indexOf:function(c,d){var a=this.length;for(var b=(d<0)?Math.max(0,a+d):d||0;b<a;b++){if(this[b]===c){return b;}}return -1;},map:function(d,e){var c=[];
for(var b=0,a=this.length;b<a;b++){c[b]=d.call(e,this[b],b,this);}return c;},some:function(c,d){for(var b=0,a=this.length;b<a;b++){if(c.call(d,this[b],b,this)){return true;
}}return false;},associate:function(c){var d={},b=Math.min(this.length,c.length);for(var a=0;a<b;a++){d[c[a]]=this[a];}return d;},link:function(c){var a={};
for(var e=0,b=this.length;e<b;e++){for(var d in c){if(c[d](this[e])){a[d]=this[e];delete c[d];break;}}}return a;},contains:function(a,b){return this.indexOf(a,b)!=-1;
},extend:function(c){for(var b=0,a=c.length;b<a;b++){this.push(c[b]);}return this;},getLast:function(){return(this.length)?this[this.length-1]:null;},getRandom:function(){return(this.length)?this[$random(0,this.length-1)]:null;
},include:function(a){if(!this.contains(a)){this.push(a);}return this;},combine:function(c){for(var b=0,a=c.length;b<a;b++){this.include(c[b]);}return this;
},erase:function(b){for(var a=this.length;a--;a){if(this[a]===b){this.splice(a,1);}}return this;},empty:function(){this.length=0;return this;},flatten:function(){var d=[];
for(var b=0,a=this.length;b<a;b++){var c=$type(this[b]);if(!c){continue;}d=d.concat((c=="array"||c=="collection"||c=="arguments")?Array.flatten(this[b]):this[b]);
}return d;},hexToRgb:function(b){if(this.length!=3){return null;}var a=this.map(function(c){if(c.length==1){c+=c;}return c.toInt(16);});return(b)?a:"rgb("+a+")";
},rgbToHex:function(d){if(this.length<3){return null;}if(this.length==4&&this[3]==0&&!d){return"transparent";}var b=[];for(var a=0;a<3;a++){var c=(this[a]-0).toString(16);
b.push((c.length==1)?"0"+c:c);}return(d)?b:"#"+b.join("");}});Function.implement({extend:function(a){for(var b in a){this[b]=a[b];}return this;},create:function(b){var a=this;
b=b||{};return function(d){var c=b.arguments;c=(c!=undefined)?$splat(c):Array.slice(arguments,(b.event)?1:0);if(b.event){c=[d||window.event].extend(c);
}var e=function(){return a.apply(b.bind||null,c);};if(b.delay){return setTimeout(e,b.delay);}if(b.periodical){return setInterval(e,b.periodical);}if(b.attempt){return $try(e);
}return e();};},run:function(a,b){return this.apply(b,$splat(a));},pass:function(a,b){return this.create({bind:b,arguments:a});},bind:function(b,a){return this.create({bind:b,arguments:a});
},bindWithEvent:function(b,a){return this.create({bind:b,arguments:a,event:true});},attempt:function(a,b){return this.create({bind:b,arguments:a,attempt:true})();
},delay:function(b,c,a){return this.create({bind:c,arguments:a,delay:b})();},periodical:function(c,b,a){return this.create({bind:b,arguments:a,periodical:c})();
}});Number.implement({limit:function(b,a){return Math.min(a,Math.max(b,this));},round:function(a){a=Math.pow(10,a||0);return Math.round(this*a)/a;},times:function(b,c){for(var a=0;
a<this;a++){b.call(c,a,this);}},toFloat:function(){return parseFloat(this);},toInt:function(a){return parseInt(this,a||10);}});Number.alias("times","each");
(function(b){var a={};b.each(function(c){if(!Number[c]){a[c]=function(){return Math[c].apply(null,[this].concat($A(arguments)));};}});Number.implement(a);
})(["abs","acos","asin","atan","atan2","ceil","cos","exp","floor","log","max","min","pow","sin","sqrt","tan"]);String.implement({test:function(a,b){return((typeof a=="string")?new RegExp(a,b):a).test(this);
},contains:function(a,b){return(b)?(b+this+b).indexOf(b+a+b)>-1:this.indexOf(a)>-1;},trim:function(){return this.replace(/^\s+|\s+$/g,"");},clean:function(){return this.replace(/\s+/g," ").trim();
},camelCase:function(){return this.replace(/-\D/g,function(a){return a.charAt(1).toUpperCase();});},hyphenate:function(){return this.replace(/[A-Z]/g,function(a){return("-"+a.charAt(0).toLowerCase());
});},capitalize:function(){return this.replace(/\b[a-z]/g,function(a){return a.toUpperCase();});},escapeRegExp:function(){return this.replace(/([-.*+?^${}()|[\]\/\\])/g,"\\$1");
},toInt:function(a){return parseInt(this,a||10);},toFloat:function(){return parseFloat(this);},hexToRgb:function(b){var a=this.match(/^#?(\w{1,2})(\w{1,2})(\w{1,2})$/);
return(a)?a.slice(1).hexToRgb(b):null;},rgbToHex:function(b){var a=this.match(/\d{1,3}/g);return(a)?a.rgbToHex(b):null;},stripScripts:function(b){var a="";
var c=this.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi,function(){a+=arguments[1]+"\n";return"";});if(b===true){$exec(a);}else{if($type(b)=="function"){b(a,c);
}}return c;},substitute:function(a,b){return this.replace(b||(/\\?\{([^{}]+)\}/g),function(d,c){if(d.charAt(0)=="\\"){return d.slice(1);}return(a[c]!=undefined)?a[c]:"";
});}});Hash.implement({has:Object.prototype.hasOwnProperty,keyOf:function(b){for(var a in this){if(this.hasOwnProperty(a)&&this[a]===b){return a;}}return null;
},hasValue:function(a){return(Hash.keyOf(this,a)!==null);},extend:function(a){Hash.each(a||{},function(c,b){Hash.set(this,b,c);},this);return this;},combine:function(a){Hash.each(a||{},function(c,b){Hash.include(this,b,c);
},this);return this;},erase:function(a){if(this.hasOwnProperty(a)){delete this[a];}return this;},get:function(a){return(this.hasOwnProperty(a))?this[a]:null;
},set:function(a,b){if(!this[a]||this.hasOwnProperty(a)){this[a]=b;}return this;},empty:function(){Hash.each(this,function(b,a){delete this[a];},this);
return this;},include:function(a,b){if(this[a]==undefined){this[a]=b;}return this;},map:function(b,c){var a=new Hash;Hash.each(this,function(e,d){a.set(d,b.call(c,e,d,this));
},this);return a;},filter:function(b,c){var a=new Hash;Hash.each(this,function(e,d){if(b.call(c,e,d,this)){a.set(d,e);}},this);return a;},every:function(b,c){for(var a in this){if(this.hasOwnProperty(a)&&!b.call(c,this[a],a)){return false;
}}return true;},some:function(b,c){for(var a in this){if(this.hasOwnProperty(a)&&b.call(c,this[a],a)){return true;}}return false;},getKeys:function(){var a=[];
Hash.each(this,function(c,b){a.push(b);});return a;},getValues:function(){var a=[];Hash.each(this,function(b){a.push(b);});return a;},toQueryString:function(a){var b=[];
Hash.each(this,function(f,e){if(a){e=a+"["+e+"]";}var d;switch($type(f)){case"object":d=Hash.toQueryString(f,e);break;case"array":var c={};f.each(function(h,g){c[g]=h;
});d=Hash.toQueryString(c,e);break;default:d=e+"="+encodeURIComponent(f);}if(f!=undefined){b.push(d);}});return b.join("&");}});Hash.alias({keyOf:"indexOf",hasValue:"contains"});
var Event=new Native({name:"Event",initialize:function(a,f){f=f||window;var k=f.document;a=a||f.event;if(a.$extended){return a;}this.$extended=true;var j=a.type;
var g=a.target||a.srcElement;while(g&&g.nodeType==3){g=g.parentNode;}if(j.test(/key/)){var b=a.which||a.keyCode;var m=Event.Keys.keyOf(b);if(j=="keydown"){var d=b-111;
if(d>0&&d<13){m="f"+d;}}m=m||String.fromCharCode(b).toLowerCase();}else{if(j.match(/(click|mouse|menu)/i)){k=(!k.compatMode||k.compatMode=="CSS1Compat")?k.html:k.body;
var i={x:a.pageX||a.clientX+k.scrollLeft,y:a.pageY||a.clientY+k.scrollTop};var c={x:(a.pageX)?a.pageX-f.pageXOffset:a.clientX,y:(a.pageY)?a.pageY-f.pageYOffset:a.clientY};
if(j.match(/DOMMouseScroll|mousewheel/)){var h=(a.wheelDelta)?a.wheelDelta/120:-(a.detail||0)/3;}var e=(a.which==3)||(a.button==2);var l=null;if(j.match(/over|out/)){switch(j){case"mouseover":l=a.relatedTarget||a.fromElement;
break;case"mouseout":l=a.relatedTarget||a.toElement;}if(!(function(){while(l&&l.nodeType==3){l=l.parentNode;}return true;}).create({attempt:Browser.Engine.gecko})()){l=false;
}}}}return $extend(this,{event:a,type:j,page:i,client:c,rightClick:e,wheel:h,relatedTarget:l,target:g,code:b,key:m,shift:a.shiftKey,control:a.ctrlKey,alt:a.altKey,meta:a.metaKey});
}});Event.Keys=new Hash({enter:13,up:38,down:40,left:37,right:39,esc:27,space:32,backspace:8,tab:9,"delete":46});Event.implement({stop:function(){return this.stopPropagation().preventDefault();
},stopPropagation:function(){if(this.event.stopPropagation){this.event.stopPropagation();}else{this.event.cancelBubble=true;}return this;},preventDefault:function(){if(this.event.preventDefault){this.event.preventDefault();
}else{this.event.returnValue=false;}return this;}});function Class(b){if(b instanceof Function){b={initialize:b};}var a=function(){Object.reset(this);if(a._prototyping){return this;
}this._current=$empty;var c=(this.initialize)?this.initialize.apply(this,arguments):this;delete this._current;delete this.caller;return c;}.extend(this);
a.implement(b);a.constructor=Class;a.prototype.constructor=a;return a;}Function.prototype.protect=function(){this._protected=true;return this;};Object.reset=function(a,c){if(c==null){for(var e in a){Object.reset(a,e);
}return a;}delete a[c];switch($type(a[c])){case"object":var d=function(){};d.prototype=a[c];var b=new d;a[c]=Object.reset(b);break;case"array":a[c]=$unlink(a[c]);
break;}return a;};new Native({name:"Class",initialize:Class}).extend({instantiate:function(b){b._prototyping=true;var a=new b;delete b._prototyping;return a;
},wrap:function(a,b,c){if(c._origin){c=c._origin;}return function(){if(c._protected&&this._current==null){throw new Error('The method "'+b+'" cannot be called.');
}var e=this.caller,f=this._current;this.caller=f;this._current=arguments.callee;var d=c.apply(this,arguments);this._current=f;this.caller=e;return d;}.extend({_owner:a,_origin:c,_name:b});
}});Class.implement({implement:function(a,d){if($type(a)=="object"){for(var e in a){this.implement(e,a[e]);}return this;}var f=Class.Mutators[a];if(f){d=f.call(this,d);
if(d==null){return this;}}var c=this.prototype;switch($type(d)){case"function":if(d._hidden){return this;}c[a]=Class.wrap(this,a,d);break;case"object":var b=c[a];
if($type(b)=="object"){$mixin(b,d);}else{c[a]=$unlink(d);}break;case"array":c[a]=$unlink(d);break;default:c[a]=d;}return this;}});Class.Mutators={Extends:function(a){this.parent=a;
this.prototype=Class.instantiate(a);this.implement("parent",function(){var b=this.caller._name,c=this.caller._owner.parent.prototype[b];if(!c){throw new Error('The method "'+b+'" has no parent.');
}return c.apply(this,arguments);}.protect());},Implements:function(a){$splat(a).each(function(b){if(b instanceof Function){b=Class.instantiate(b);}this.implement(b);
},this);}};var Chain=new Class({$chain:[],chain:function(){this.$chain.extend(Array.flatten(arguments));return this;},callChain:function(){return(this.$chain.length)?this.$chain.shift().apply(this,arguments):false;
},clearChain:function(){this.$chain.empty();return this;}});var Events=new Class({$events:{},addEvent:function(c,b,a){c=Events.removeOn(c);if(b!=$empty){this.$events[c]=this.$events[c]||[];
this.$events[c].include(b);if(a){b.internal=true;}}return this;},addEvents:function(a){for(var b in a){this.addEvent(b,a[b]);}return this;},fireEvent:function(c,b,a){c=Events.removeOn(c);
if(!this.$events||!this.$events[c]){return this;}this.$events[c].each(function(d){d.create({bind:this,delay:a,"arguments":b})();},this);return this;},removeEvent:function(b,a){b=Events.removeOn(b);
if(!this.$events[b]){return this;}if(!a.internal){this.$events[b].erase(a);}return this;},removeEvents:function(c){var d;if($type(c)=="object"){for(d in c){this.removeEvent(d,c[d]);
}return this;}if(c){c=Events.removeOn(c);}for(d in this.$events){if(c&&c!=d){continue;}var b=this.$events[d];for(var a=b.length;a--;a){this.removeEvent(d,b[a]);
}}return this;}});Events.removeOn=function(a){return a.replace(/^on([A-Z])/,function(b,c){return c.toLowerCase();});};var Options=new Class({setOptions:function(){this.options=$merge.run([this.options].extend(arguments));
if(!this.addEvent){return this;}for(var a in this.options){if($type(this.options[a])!="function"||!(/^on[A-Z]/).test(a)){continue;}this.addEvent(a,this.options[a]);
delete this.options[a];}return this;}});var Element=new Native({name:"Element",legacy:window.Element,initialize:function(a,b){var c=Element.Constructors.get(a);
if(c){return c(b);}if(typeof a=="string"){return document.newElement(a,b);}return document.id(a).set(b);},afterImplement:function(a,b){Element.Prototype[a]=b;
if(Array[a]){return;}Elements.implement(a,function(){var c=[],g=true;for(var e=0,d=this.length;e<d;e++){var f=this[e][a].apply(this[e],arguments);c.push(f);
if(g){g=($type(f)=="element");}}return(g)?new Elements(c):c;});}});Element.Prototype={$family:{name:"element"}};Element.Constructors=new Hash;var IFrame=new Native({name:"IFrame",generics:false,initialize:function(){var f=Array.link(arguments,{properties:Object.type,iframe:$defined});
var d=f.properties||{};var c=document.id(f.iframe);var e=d.onload||$empty;delete d.onload;d.id=d.name=$pick(d.id,d.name,c?(c.id||c.name):"IFrame_"+$time());
c=new Element(c||"iframe",d);var b=function(){var g=$try(function(){return c.contentWindow.location.host;});if(!g||g==window.location.host){var h=new Window(c.contentWindow);
new Document(c.contentWindow.document);$extend(h.Element.prototype,Element.Prototype);}e.call(c.contentWindow,c.contentWindow.document);};var a=$try(function(){return c.contentWindow;
});((a&&a.document.body)||window.frames[d.id])?b():c.addListener("load",b);return c;}});var Elements=new Native({initialize:function(f,b){b=$extend({ddup:true,cash:true},b);
f=f||[];if(b.ddup||b.cash){var g={},e=[];for(var c=0,a=f.length;c<a;c++){var d=document.id(f[c],!b.cash);if(b.ddup){if(g[d.uid]){continue;}g[d.uid]=true;
}e.push(d);}f=e;}return(b.cash)?$extend(f,this):f;}});Elements.implement({filter:function(a,b){if(!a){return this;}return new Elements(Array.filter(this,(typeof a=="string")?function(c){return c.match(a);
}:a,b));}});Document.implement({newElement:function(a,b){if(Browser.Engine.trident&&b){["name","type","checked"].each(function(c){if(!b[c]){return;}a+=" "+c+'="'+b[c]+'"';
if(c!="checked"){delete b[c];}});a="<"+a+">";}return document.id(this.createElement(a)).set(b);},newTextNode:function(a){return this.createTextNode(a);
},getDocument:function(){return this;},getWindow:function(){return this.window;},id:(function(){var a={string:function(d,c,b){d=b.getElementById(d);return(d)?a.element(d,c):null;
},element:function(b,e){$uid(b);if(!e&&!b.$family&&!(/^object|embed$/i).test(b.tagName)){var c=Element.Prototype;for(var d in c){b[d]=c[d];}}return b;},object:function(c,d,b){if(c.toElement){return a.element(c.toElement(b),d);
}return null;}};a.textnode=a.whitespace=a.window=a.document=$arguments(0);return function(c,e,d){if(c&&c.$family&&c.uid){return c;}var b=$type(c);return(a[b])?a[b](c,e,d||document):null;
};})()});if(window.$==null){Window.implement({$:function(a,b){return document.id(a,b,this.document);}});}Window.implement({$$:function(a){if(arguments.length==1&&typeof a=="string"){return this.document.getElements(a);
}var f=[];var c=Array.flatten(arguments);for(var d=0,b=c.length;d<b;d++){var e=c[d];switch($type(e)){case"element":f.push(e);break;case"string":f.extend(this.document.getElements(e,true));
}}return new Elements(f);},getDocument:function(){return this.document;},getWindow:function(){return this;}});Native.implement([Element,Document],{getElement:function(a,b){return document.id(this.getElements(a,true)[0]||null,b);
},getElements:function(a,d){a=a.split(",");var c=[];var b=(a.length>1);a.each(function(e){var f=this.getElementsByTagName(e.trim());(b)?c.extend(f):c=f;
},this);return new Elements(c,{ddup:b,cash:!d});}});(function(){var h={},f={};var i={input:"checked",option:"selected",textarea:(Browser.Engine.webkit&&Browser.Engine.version<420)?"innerHTML":"value"};
var c=function(l){return(f[l]||(f[l]={}));};var g=function(n,l){if(!n){return;}var m=n.uid;if(Browser.Engine.trident){if(n.clearAttributes){var q=l&&n.cloneNode(false);
n.clearAttributes();if(q){n.mergeAttributes(q);}}else{if(n.removeEvents){n.removeEvents();}}if((/object/i).test(n.tagName)){for(var o in n){if(typeof n[o]=="function"){n[o]=$empty;
}}Element.dispose(n);}}if(!m){return;}h[m]=f[m]=null;};var d=function(){Hash.each(h,g);if(Browser.Engine.trident){$A(document.getElementsByTagName("object")).each(g);
}if(window.CollectGarbage){CollectGarbage();}h=f=null;};var j=function(n,l,s,m,p,r){var o=n[s||l];var q=[];while(o){if(o.nodeType==1&&(!m||Element.match(o,m))){if(!p){return document.id(o,r);
}q.push(o);}o=o[l];}return(p)?new Elements(q,{ddup:false,cash:!r}):null;};var e={html:"innerHTML","class":"className","for":"htmlFor",defaultValue:"defaultValue",text:(Browser.Engine.trident||(Browser.Engine.webkit&&Browser.Engine.version<420))?"innerText":"textContent"};
var b=["compact","nowrap","ismap","declare","noshade","checked","disabled","readonly","multiple","selected","noresize","defer"];var k=["value","type","defaultValue","accessKey","cellPadding","cellSpacing","colSpan","frameBorder","maxLength","readOnly","rowSpan","tabIndex","useMap"];
b=b.associate(b);Hash.extend(e,b);Hash.extend(e,k.associate(k.map(String.toLowerCase)));var a={before:function(m,l){if(l.parentNode){l.parentNode.insertBefore(m,l);
}},after:function(m,l){if(!l.parentNode){return;}var n=l.nextSibling;(n)?l.parentNode.insertBefore(m,n):l.parentNode.appendChild(m);},bottom:function(m,l){l.appendChild(m);
},top:function(m,l){var n=l.firstChild;(n)?l.insertBefore(m,n):l.appendChild(m);}};a.inside=a.bottom;Hash.each(a,function(l,m){m=m.capitalize();Element.implement("inject"+m,function(n){l(this,document.id(n,true));
return this;});Element.implement("grab"+m,function(n){l(document.id(n,true),this);return this;});});Element.implement({set:function(o,m){switch($type(o)){case"object":for(var n in o){this.set(n,o[n]);
}break;case"string":var l=Element.Properties.get(o);(l&&l.set)?l.set.apply(this,Array.slice(arguments,1)):this.setProperty(o,m);}return this;},get:function(m){var l=Element.Properties.get(m);
return(l&&l.get)?l.get.apply(this,Array.slice(arguments,1)):this.getProperty(m);},erase:function(m){var l=Element.Properties.get(m);(l&&l.erase)?l.erase.apply(this):this.removeProperty(m);
return this;},setProperty:function(m,n){var l=e[m];if(n==undefined){return this.removeProperty(m);}if(l&&b[m]){n=!!n;}(l)?this[l]=n:this.setAttribute(m,""+n);
return this;},setProperties:function(l){for(var m in l){this.setProperty(m,l[m]);}return this;},getProperty:function(m){var l=e[m];var n=(l)?this[l]:this.getAttribute(m,2);
return(b[m])?!!n:(l)?n:n||null;},getProperties:function(){var l=$A(arguments);return l.map(this.getProperty,this).associate(l);},removeProperty:function(m){var l=e[m];
(l)?this[l]=(l&&b[m])?false:"":this.removeAttribute(m);return this;},removeProperties:function(){Array.each(arguments,this.removeProperty,this);return this;
},hasClass:function(l){return this.className.contains(l," ");},addClass:function(l){if(!this.hasClass(l)){this.className=(this.className+" "+l).clean();
}return this;},removeClass:function(l){this.className=this.className.replace(new RegExp("(^|\\s)"+l+"(?:\\s|$)"),"$1");return this;},toggleClass:function(l){return this.hasClass(l)?this.removeClass(l):this.addClass(l);
},adopt:function(){Array.flatten(arguments).each(function(l){l=document.id(l,true);if(l){this.appendChild(l);}},this);return this;},appendText:function(m,l){return this.grab(this.getDocument().newTextNode(m),l);
},grab:function(m,l){a[l||"bottom"](document.id(m,true),this);return this;},inject:function(m,l){a[l||"bottom"](this,document.id(m,true));return this;},replaces:function(l){l=document.id(l,true);
l.parentNode.replaceChild(this,l);return this;},wraps:function(m,l){m=document.id(m,true);return this.replaces(m).grab(m,l);},getPrevious:function(l,m){return j(this,"previousSibling",null,l,false,m);
},getAllPrevious:function(l,m){return j(this,"previousSibling",null,l,true,m);},getNext:function(l,m){return j(this,"nextSibling",null,l,false,m);},getAllNext:function(l,m){return j(this,"nextSibling",null,l,true,m);
},getFirst:function(l,m){return j(this,"nextSibling","firstChild",l,false,m);},getLast:function(l,m){return j(this,"previousSibling","lastChild",l,false,m);
},getParent:function(l,m){return j(this,"parentNode",null,l,false,m);},getParents:function(l,m){return j(this,"parentNode",null,l,true,m);},getSiblings:function(l,m){return this.getParent().getChildren(l,m).erase(this);
},getChildren:function(l,m){return j(this,"nextSibling","firstChild",l,true,m);},getWindow:function(){return this.ownerDocument.window;},getDocument:function(){return this.ownerDocument;
},getElementById:function(o,n){var m=this.ownerDocument.getElementById(o);if(!m){return null;}for(var l=m.parentNode;l!=this;l=l.parentNode){if(!l){return null;
}}return document.id(m,n);},getSelected:function(){return new Elements($A(this.options).filter(function(l){return l.selected;}));},getComputedStyle:function(m){if(this.currentStyle){return this.currentStyle[m.camelCase()];
}var l=this.getDocument().defaultView.getComputedStyle(this,null);return(l)?l.getPropertyValue([m.hyphenate()]):null;},toQueryString:function(){var l=[];
this.getElements("input, select, textarea",true).each(function(m){if(!m.name||m.disabled||m.type=="submit"||m.type=="reset"||m.type=="file"){return;}var n=(m.tagName.toLowerCase()=="select")?Element.getSelected(m).map(function(o){return o.value;
}):((m.type=="radio"||m.type=="checkbox")&&!m.checked)?null:m.value;$splat(n).each(function(o){if(typeof o!="undefined"){l.push(m.name+"="+encodeURIComponent(o));
}});});return l.join("&");},clone:function(o,l){o=o!==false;var r=this.cloneNode(o);var n=function(v,u){if(!l){v.removeAttribute("id");}if(Browser.Engine.trident){v.clearAttributes();
v.mergeAttributes(u);v.removeAttribute("uid");if(v.options){var w=v.options,s=u.options;for(var t=w.length;t--;){w[t].selected=s[t].selected;}}}var x=i[u.tagName.toLowerCase()];
if(x&&u[x]){v[x]=u[x];}};if(o){var p=r.getElementsByTagName("*"),q=this.getElementsByTagName("*");for(var m=p.length;m--;){n(p[m],q[m]);}}n(r,this);return document.id(r);
},destroy:function(){Element.empty(this);Element.dispose(this);g(this,true);return null;},empty:function(){$A(this.childNodes).each(function(l){Element.destroy(l);
});return this;},dispose:function(){return(this.parentNode)?this.parentNode.removeChild(this):this;},hasChild:function(l){l=document.id(l,true);if(!l){return false;
}if(Browser.Engine.webkit&&Browser.Engine.version<420){return $A(this.getElementsByTagName(l.tagName)).contains(l);}return(this.contains)?(this!=l&&this.contains(l)):!!(this.compareDocumentPosition(l)&16);
},match:function(l){return(!l||(l==this)||(Element.get(this,"tag")==l));}});Native.implement([Element,Window,Document],{addListener:function(o,n){if(o=="unload"){var l=n,m=this;
n=function(){m.removeListener("unload",n);l();};}else{h[this.uid]=this;}if(this.addEventListener){this.addEventListener(o,n,false);}else{this.attachEvent("on"+o,n);
}return this;},removeListener:function(m,l){if(this.removeEventListener){this.removeEventListener(m,l,false);}else{this.detachEvent("on"+m,l);}return this;
},retrieve:function(m,l){var o=c(this.uid),n=o[m];if(l!=undefined&&n==undefined){n=o[m]=l;}return $pick(n);},store:function(m,l){var n=c(this.uid);n[m]=l;
return this;},eliminate:function(l){var m=c(this.uid);delete m[l];return this;}});window.addListener("unload",d);})();Element.Properties=new Hash;Element.Properties.style={set:function(a){this.style.cssText=a;
},get:function(){return this.style.cssText;},erase:function(){this.style.cssText="";}};Element.Properties.tag={get:function(){return this.tagName.toLowerCase();
}};Element.Properties.html=(function(){var c=document.createElement("div");var a={table:[1,"<table>","</table>"],select:[1,"<select>","</select>"],tbody:[2,"<table><tbody>","</tbody></table>"],tr:[3,"<table><tbody><tr>","</tr></tbody></table>"]};
a.thead=a.tfoot=a.tbody;var b={set:function(){var e=Array.flatten(arguments).join("");var f=Browser.Engine.trident&&a[this.get("tag")];if(f){var g=c;g.innerHTML=f[1]+e+f[2];
for(var d=f[0];d--;){g=g.firstChild;}this.empty().adopt(g.childNodes);}else{this.innerHTML=e;}}};b.erase=b.set;return b;})();if(Browser.Engine.webkit&&Browser.Engine.version<420){Element.Properties.text={get:function(){if(this.innerText){return this.innerText;
}var a=this.ownerDocument.newElement("div",{html:this.innerHTML}).inject(this.ownerDocument.body);var b=a.innerText;a.destroy();return b;}};}Element.Properties.events={set:function(a){this.addEvents(a);
}};Native.implement([Element,Window,Document],{addEvent:function(e,g){var h=this.retrieve("events",{});h[e]=h[e]||{keys:[],values:[]};if(h[e].keys.contains(g)){return this;
}h[e].keys.push(g);var f=e,a=Element.Events.get(e),c=g,i=this;if(a){if(a.onAdd){a.onAdd.call(this,g);}if(a.condition){c=function(j){if(a.condition.call(this,j)){return g.call(this,j);
}return true;};}f=a.base||f;}var d=function(){return g.call(i);};var b=Element.NativeEvents[f];if(b){if(b==2){d=function(j){j=new Event(j,i.getWindow());
if(c.call(i,j)===false){j.stop();}};}this.addListener(f,d);}h[e].values.push(d);return this;},removeEvent:function(c,b){var a=this.retrieve("events");if(!a||!a[c]){return this;
}var f=a[c].keys.indexOf(b);if(f==-1){return this;}a[c].keys.splice(f,1);var e=a[c].values.splice(f,1)[0];var d=Element.Events.get(c);if(d){if(d.onRemove){d.onRemove.call(this,b);
}c=d.base||c;}return(Element.NativeEvents[c])?this.removeListener(c,e):this;},addEvents:function(a){for(var b in a){this.addEvent(b,a[b]);}return this;
},removeEvents:function(a){var c;if($type(a)=="object"){for(c in a){this.removeEvent(c,a[c]);}return this;}var b=this.retrieve("events");if(!b){return this;
}if(!a){for(c in b){this.removeEvents(c);}this.eliminate("events");}else{if(b[a]){while(b[a].keys[0]){this.removeEvent(a,b[a].keys[0]);}b[a]=null;}}return this;
},fireEvent:function(d,b,a){var c=this.retrieve("events");if(!c||!c[d]){return this;}c[d].keys.each(function(e){e.create({bind:this,delay:a,"arguments":b})();
},this);return this;},cloneEvents:function(d,a){d=document.id(d);var c=d.retrieve("events");if(!c){return this;}if(!a){for(var b in c){this.cloneEvents(d,b);
}}else{if(c[a]){c[a].keys.each(function(e){this.addEvent(a,e);},this);}}return this;}});Element.NativeEvents={click:2,dblclick:2,mouseup:2,mousedown:2,contextmenu:2,mousewheel:2,DOMMouseScroll:2,mouseover:2,mouseout:2,mousemove:2,selectstart:2,selectend:2,keydown:2,keypress:2,keyup:2,focus:2,blur:2,change:2,reset:2,select:2,submit:2,load:1,unload:1,beforeunload:2,resize:1,move:1,DOMContentLoaded:1,readystatechange:1,error:1,abort:1,scroll:1};
(function(){var a=function(b){var c=b.relatedTarget;if(c==undefined){return true;}if(c===false){return false;}return($type(this)!="document"&&c!=this&&c.prefix!="xul"&&!this.hasChild(c));
};Element.Events=new Hash({mouseenter:{base:"mouseover",condition:a},mouseleave:{base:"mouseout",condition:a},mousewheel:{base:(Browser.Engine.gecko)?"DOMMouseScroll":"mousewheel"}});
})();Native.implement([Document,Element],{getElements:function(h,g){h=h.split(",");var c,e={};for(var d=0,b=h.length;d<b;d++){var a=h[d],f=Selectors.Utils.search(this,a,e);
if(d!=0&&f.item){f=$A(f);}c=(d==0)?f:(c.item)?$A(c).concat(f):c.concat(f);}return new Elements(c,{ddup:(h.length>1),cash:!g});}});Element.implement({match:function(b){if(!b||(b==this)){return true;
}var d=Selectors.Utils.parseTagAndID(b);var a=d[0],e=d[1];if(!Selectors.Filters.byID(this,e)||!Selectors.Filters.byTag(this,a)){return false;}var c=Selectors.Utils.parseSelector(b);
return(c)?Selectors.Utils.filter(this,c,{}):true;}});var Selectors={Cache:{nth:{},parsed:{}}};Selectors.RegExps={id:(/#([\w-]+)/),tag:(/^(\w+|\*)/),quick:(/^(\w+|\*)$/),splitter:(/\s*([+>~\s])\s*([a-zA-Z#.*:\[])/g),combined:(/\.([\w-]+)|\[(\w+)(?:([!*^$~|]?=)(["']?)([^\4]*?)\4)?\]|:([\w-]+)(?:\(["']?(.*?)?["']?\)|$)/g)};
Selectors.Utils={chk:function(b,c){if(!c){return true;}var a=$uid(b);if(!c[a]){return c[a]=true;}return false;},parseNthArgument:function(h){if(Selectors.Cache.nth[h]){return Selectors.Cache.nth[h];
}var e=h.match(/^([+-]?\d*)?([a-z]+)?([+-]?\d*)?$/);if(!e){return false;}var g=parseInt(e[1],10);var d=(g||g===0)?g:1;var f=e[2]||false;var c=parseInt(e[3],10)||0;
if(d!=0){c--;while(c<1){c+=d;}while(c>=d){c-=d;}}else{d=c;f="index";}switch(f){case"n":e={a:d,b:c,special:"n"};break;case"odd":e={a:2,b:0,special:"n"};
break;case"even":e={a:2,b:1,special:"n"};break;case"first":e={a:0,special:"index"};break;case"last":e={special:"last-child"};break;case"only":e={special:"only-child"};
break;default:e={a:(d-1),special:"index"};}return Selectors.Cache.nth[h]=e;},parseSelector:function(e){if(Selectors.Cache.parsed[e]){return Selectors.Cache.parsed[e];
}var d,h={classes:[],pseudos:[],attributes:[]};while((d=Selectors.RegExps.combined.exec(e))){var i=d[1],g=d[2],f=d[3],b=d[5],c=d[6],j=d[7];if(i){h.classes.push(i);
}else{if(c){var a=Selectors.Pseudo.get(c);if(a){h.pseudos.push({parser:a,argument:j});}else{h.attributes.push({name:c,operator:"=",value:j});}}else{if(g){h.attributes.push({name:g,operator:f,value:b});
}}}}if(!h.classes.length){delete h.classes;}if(!h.attributes.length){delete h.attributes;}if(!h.pseudos.length){delete h.pseudos;}if(!h.classes&&!h.attributes&&!h.pseudos){h=null;
}return Selectors.Cache.parsed[e]=h;},parseTagAndID:function(b){var a=b.match(Selectors.RegExps.tag);var c=b.match(Selectors.RegExps.id);return[(a)?a[1]:"*",(c)?c[1]:false];
},filter:function(f,c,e){var d;if(c.classes){for(d=c.classes.length;d--;d){var g=c.classes[d];if(!Selectors.Filters.byClass(f,g)){return false;}}}if(c.attributes){for(d=c.attributes.length;
d--;d){var b=c.attributes[d];if(!Selectors.Filters.byAttribute(f,b.name,b.operator,b.value)){return false;}}}if(c.pseudos){for(d=c.pseudos.length;d--;d){var a=c.pseudos[d];
if(!Selectors.Filters.byPseudo(f,a.parser,a.argument,e)){return false;}}}return true;},getByTagAndID:function(b,a,d){if(d){var c=(b.getElementById)?b.getElementById(d,true):Element.getElementById(b,d,true);
return(c&&Selectors.Filters.byTag(c,a))?[c]:[];}else{return b.getElementsByTagName(a);}},search:function(o,h,t){var b=[];var c=h.trim().replace(Selectors.RegExps.splitter,function(k,j,i){b.push(j);
return":)"+i;}).split(":)");var p,e,A;for(var z=0,v=c.length;z<v;z++){var y=c[z];if(z==0&&Selectors.RegExps.quick.test(y)){p=o.getElementsByTagName(y);
continue;}var a=b[z-1];var q=Selectors.Utils.parseTagAndID(y);var B=q[0],r=q[1];if(z==0){p=Selectors.Utils.getByTagAndID(o,B,r);}else{var d={},g=[];for(var x=0,w=p.length;
x<w;x++){g=Selectors.Getters[a](g,p[x],B,r,d);}p=g;}var f=Selectors.Utils.parseSelector(y);if(f){e=[];for(var u=0,s=p.length;u<s;u++){A=p[u];if(Selectors.Utils.filter(A,f,t)){e.push(A);
}}p=e;}}return p;}};Selectors.Getters={" ":function(h,g,j,a,e){var d=Selectors.Utils.getByTagAndID(g,j,a);for(var c=0,b=d.length;c<b;c++){var f=d[c];if(Selectors.Utils.chk(f,e)){h.push(f);
}}return h;},">":function(h,g,j,a,f){var c=Selectors.Utils.getByTagAndID(g,j,a);for(var e=0,d=c.length;e<d;e++){var b=c[e];if(b.parentNode==g&&Selectors.Utils.chk(b,f)){h.push(b);
}}return h;},"+":function(c,b,a,e,d){while((b=b.nextSibling)){if(b.nodeType==1){if(Selectors.Utils.chk(b,d)&&Selectors.Filters.byTag(b,a)&&Selectors.Filters.byID(b,e)){c.push(b);
}break;}}return c;},"~":function(c,b,a,e,d){while((b=b.nextSibling)){if(b.nodeType==1){if(!Selectors.Utils.chk(b,d)){break;}if(Selectors.Filters.byTag(b,a)&&Selectors.Filters.byID(b,e)){c.push(b);
}}}return c;}};Selectors.Filters={byTag:function(b,a){return(a=="*"||(b.tagName&&b.tagName.toLowerCase()==a));},byID:function(a,b){return(!b||(a.id&&a.id==b));
},byClass:function(b,a){return(b.className&&b.className.contains(a," "));},byPseudo:function(a,d,c,b){return d.call(a,c,b);},byAttribute:function(c,d,b,e){var a=Element.prototype.getProperty.call(c,d);
if(!a){return(b=="!=");}if(!b||e==undefined){return true;}switch(b){case"=":return(a==e);case"*=":return(a.contains(e));case"^=":return(a.substr(0,e.length)==e);
case"$=":return(a.substr(a.length-e.length)==e);case"!=":return(a!=e);case"~=":return a.contains(e," ");case"|=":return a.contains(e,"-");}return false;
}};Selectors.Pseudo=new Hash({checked:function(){return this.checked;},empty:function(){return !(this.innerText||this.textContent||"").length;},not:function(a){return !Element.match(this,a);
},contains:function(a){return(this.innerText||this.textContent||"").contains(a);},"first-child":function(){return Selectors.Pseudo.index.call(this,0);},"last-child":function(){var a=this;
while((a=a.nextSibling)){if(a.nodeType==1){return false;}}return true;},"only-child":function(){var b=this;while((b=b.previousSibling)){if(b.nodeType==1){return false;
}}var a=this;while((a=a.nextSibling)){if(a.nodeType==1){return false;}}return true;},"nth-child":function(g,e){g=(g==undefined)?"n":g;var c=Selectors.Utils.parseNthArgument(g);
if(c.special!="n"){return Selectors.Pseudo[c.special].call(this,c.a,e);}var f=0;e.positions=e.positions||{};var d=$uid(this);if(!e.positions[d]){var b=this;
while((b=b.previousSibling)){if(b.nodeType!=1){continue;}f++;var a=e.positions[$uid(b)];if(a!=undefined){f=a+f;break;}}e.positions[d]=f;}return(e.positions[d]%c.a==c.b);
},index:function(a){var b=this,c=0;while((b=b.previousSibling)){if(b.nodeType==1&&++c>a){return false;}}return(c==a);},even:function(b,a){return Selectors.Pseudo["nth-child"].call(this,"2n+1",a);
},odd:function(b,a){return Selectors.Pseudo["nth-child"].call(this,"2n",a);},selected:function(){return this.selected;},enabled:function(){return(this.disabled===false);
}});var JSON=new Hash({$specialChars:{"\b":"\\b","\t":"\\t","\n":"\\n","\f":"\\f","\r":"\\r",'"':'\\"',"\\":"\\\\"},$replaceChars:function(a){return JSON.$specialChars[a]||"\\u00"+Math.floor(a.charCodeAt()/16).toString(16)+(a.charCodeAt()%16).toString(16);
},encode:function(b){switch($type(b)){case"string":return'"'+b.replace(/[\x00-\x1f\\"]/g,JSON.$replaceChars)+'"';case"array":return"["+String(b.map(JSON.encode).clean())+"]";
case"object":case"hash":var a=[];Hash.each(b,function(e,d){var c=JSON.encode(e);if(c){a.push(JSON.encode(d)+":"+c);}});return"{"+a+"}";case"number":case"boolean":return String(b);
case false:return"null";}return null;},decode:function(string,secure){if($type(string)!="string"||!string.length){return null;}if(secure&&!(/^[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]*$/).test(string.replace(/\\./g,"@").replace(/"[^"\\\n\r]*"/g,""))){return null;
}return eval("("+string+")");}});Native.implement([Hash,Array,String,Number],{toJSON:function(){return JSON.encode(this);}});var Request=new Class({Implements:[Chain,Events,Options],options:{url:"",data:"",headers:{"X-Requested-With":"XMLHttpRequest",Accept:"text/javascript, text/html, application/xml, text/xml, */*"},async:true,format:false,method:"post",link:"ignore",isSuccess:null,emulation:true,urlEncoded:true,encoding:"utf-8",evalScripts:false,evalResponse:false,noCache:false},initialize:function(a){this.xhr=new Browser.Request();
this.setOptions(a);this.options.isSuccess=this.options.isSuccess||this.isSuccess;this.headers=new Hash(this.options.headers);},onStateChange:function(){if(this.xhr.readyState!=4||!this.running){return;
}this.running=false;this.status=0;$try(function(){this.status=this.xhr.status;}.bind(this));this.xhr.onreadystatechange=$empty;if(this.options.isSuccess.call(this,this.status)){this.response={text:this.xhr.responseText,xml:this.xhr.responseXML};
this.success(this.response.text,this.response.xml);}else{this.response={text:null,xml:null};this.failure();}},isSuccess:function(){return((this.status>=200)&&(this.status<300));
},processScripts:function(a){if(this.options.evalResponse||(/(ecma|java)script/).test(this.getHeader("Content-type"))){return $exec(a);}return a.stripScripts(this.options.evalScripts);
},success:function(b,a){this.onSuccess(this.processScripts(b),a);},onSuccess:function(){this.fireEvent("complete",arguments).fireEvent("success",arguments).callChain();
},failure:function(){this.onFailure();},onFailure:function(){this.fireEvent("complete").fireEvent("failure",this.xhr);},setHeader:function(a,b){this.headers.set(a,b);
return this;},getHeader:function(a){return $try(function(){return this.xhr.getResponseHeader(a);}.bind(this));},check:function(){if(!this.running){return true;
}switch(this.options.link){case"cancel":this.cancel();return true;case"chain":this.chain(this.caller.bind(this,arguments));return false;}return false;},send:function(k){if(!this.check(k)){return this;
}this.running=true;var i=$type(k);if(i=="string"||i=="element"){k={data:k};}var d=this.options;k=$extend({data:d.data,url:d.url,method:d.method},k);var g=k.data,b=k.url,a=k.method.toLowerCase();
switch($type(g)){case"element":g=document.id(g).toQueryString();break;case"object":case"hash":g=Hash.toQueryString(g);}if(this.options.format){var j="format="+this.options.format;
g=(g)?j+"&"+g:j;}if(this.options.emulation&&!["get","post"].contains(a)){var h="_method="+a;g=(g)?h+"&"+g:h;a="post";}if(this.options.urlEncoded&&a=="post"){var c=(this.options.encoding)?"; charset="+this.options.encoding:"";
this.headers.set("Content-type","application/x-www-form-urlencoded"+c);}if(this.options.noCache){var f="noCache="+new Date().getTime();g=(g)?f+"&"+g:f;
}var e=b.lastIndexOf("/");if(e>-1&&(e=b.indexOf("#"))>-1){b=b.substr(0,e);}if(g&&a=="get"){b=b+(b.contains("?")?"&":"?")+g;g=null;}this.xhr.open(a.toUpperCase(),b,this.options.async);
this.xhr.onreadystatechange=this.onStateChange.bind(this);this.headers.each(function(m,l){try{this.xhr.setRequestHeader(l,m);}catch(n){this.fireEvent("exception",[l,m]);
}},this);this.fireEvent("request");this.xhr.send(g);if(!this.options.async){this.onStateChange();}return this;},cancel:function(){if(!this.running){return this;
}this.running=false;this.xhr.abort();this.xhr.onreadystatechange=$empty;this.xhr=new Browser.Request();this.fireEvent("cancel");return this;}});(function(){var a={};
["get","post","put","delete","GET","POST","PUT","DELETE"].each(function(b){a[b]=function(){var c=Array.link(arguments,{url:String.type,data:$defined});
return this.send($extend(c,{method:b}));};});Request.implement(a);})();Element.Properties.send={set:function(a){var b=this.retrieve("send");if(b){b.cancel();
}return this.eliminate("send").store("send:options",$extend({data:this,link:"cancel",method:this.get("method")||"post",url:this.get("action")},a));},get:function(a){if(a||!this.retrieve("send")){if(a||!this.retrieve("send:options")){this.set("send",a);
}this.store("send",new Request(this.retrieve("send:options")));}return this.retrieve("send");}};Element.implement({send:function(a){var b=this.get("send");
b.send({data:this,url:a||b.options.url});return this;}});
/* ]]> */
	</script>

	<script type="text/javascript">
	/* <![CDATA[ */
		var inputs = $$('input');
		var deployFileSystem = inputs.filter('[name=deployFileSystem]');
	
		var myRequest = new Request({
			url: 'deploy.php',
			onSuccess: function(msg) {
				if( msg != 'done' ) {
				
					msg = JSON.decode(msg);
					if( $chk(msg.file) ) {
						newParams = {};
						newParams.data = { mode: 'ajax', action: 'deployFileSystem', offset: msg.offset, file: msg.file };
						
						deployFileSystem[0].getParent().getElement('span').set('html', 'block ' + msg.offset + ' ');	
						this.send( newParams );
					}
					
				} else {
					deployFileSystem[0].getParent().getElement('span').dispose();
					deployFileSystem.set('checked', '');
					$('bigForm').submit();
				}
			}
		});
		
		$('bigForm').addEvent('submit', function(e) {
			e.stop();
			if( deployFileSystem[0].get('checked') === true ) {
				deployFileSystem.getParent().grab( new Element('span', { html: 'block 0 ', 'class': 'info' }), 'top' );
				myRequest.send('action=deployFileSystem&mode=ajax');
			} else {
				this.submit();
			}
		});
	
		var configSql = document.getElementById('configSql');
		var configSqlFieldset = document.getElementById('configSqlFieldset');
		
		configSql.style.display = 'block';
		toggleDisplay();
		
		$('newPassword').addEvent('click', function() {
			$('adminPwInput').set('value', randomPassword(8));
		});
		
		if( $('adminPwInput').get('value') == '' ) {
			$('adminPwInput').set('value', randomPassword(8));
		}
		
		function toggleDisplay() {
			if( configSql.style.display == 'block' ) {
				configSql.style.display = 'none';
				configSqlFieldset.style.padding = '3px';
			} else {
				configSql.style.display = 'block';
				configSqlFieldset.style.padding = '10px';
			}
		}
		
		function randomPassword(length) {
			var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ23456789';
			var pass = '';
			var i;
			for( var x = 0; x < length; x++) {
				i = Math.floor(Math.random() * 62);
				pass += chars.charAt(i);
			}
			return pass;
		}
	/* ]]> */
	</script>

</body>
</html>

<?php
$deploy->disconnect();

class Helper {

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
	
}

/**
 * a simple class to use MooTools Style options in PHP
 *
 * @package MPR
 * @version $Id:
 * @copyright Copyright belongs to the respective authors
 * @author	Thomas Allmer <at@delusionworld.com>
 * @license	MIT-style license
 */
class Options {

	/**
	 * This array holds all the options you can set
	 *
	 * @var array
	 */
	public $options = array();
	
	/**
	* Description: The setOptions method should be called in the constructor of an extending class
	* @param array $options - The options array resets any default options present in the class
	* @return - $this
	*/
	protected function setOptions($options) {
		if ( is_array($options) || is_object($options) ) {
			foreach ($options as $key => $value)
				$this->options[$key] = $value;
		}
		if ( is_array($this->options) )
			$this->options = $this->arrayToObject($this->options);
		return $this;
	}
	
	/**
	* Description: Recursively returns an array as an object, for easier syntax
	* Credit: Mithras @ http://us2.php.net/manual/en/language.types.object.php#85237
	* @param array $array - The array to return as an object
	* @return - The object converted from the array
	*/
	public function arrayToObject(array $array){
		foreach ($array as $key => $value)
			if (is_array($value)) $array[$key] = $this->arrayToObject($value);
		return (object) $array;
	}

}

?>