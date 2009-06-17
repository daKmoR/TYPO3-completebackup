<?php

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
					$this->error = 'No manual config provided and no config file found at ' . $this->options->configFile;
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
				
			if( !$this->error && isset($_REQUEST['deleteBackup']) && $_REQUEST['deleteBackup'] == 'on' ) {
				//$this->deleteBackup();
			}

			if( !$this->error && isset($_REQUEST['deleteDeploy']) && $_REQUEST['deleteDeploy'] == 'on' ) {
				//$this->deleteDeploy();
			}
				
		}
		
		public function updateAdminPw($password) {
			require_once $this->options->extractPath . $this->options->configFile;
			
			if( !$link = @mysql_connect($typo_db_host, $typo_db_username, $typo_db_password) ) {
				$this->error = 'Could not connect: ' . mysql_error();
				return false;
			}
			if( !mysql_select_db($typo_db) ) {
				$this->error = 'Could not create database: ' . mysql_error();
				return false;
			}
			
			return mysql_query('UPDATE `' . $typo_db . '`.`be_users` SET `password` = MD5( \'' . $password . '\' ) WHERE `be_users`.`uid` = 1 LIMIT 1;');
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
			if( is_file($this->options->configFile) ) {
				require $this->options->extractPath . $this->options->configFile;
				if( $link = @mysql_connect($typo_db_host, $typo_db_username, $typo_db_password) ) {
					if( @mysql_select_db($typo_db) ) {
						mysql_close($link);
						return 'done';
					}
					mysql_close($link);
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
			if( is_file($path) && !$this->options->override ) {
				$this->error = 'Config File (' . $this->options->configFile . ') exists you need to select override if you want to replace it';
				return false;
			}
			$config  = '<?php' . PHP_EOL;
			$config .= '  $typo_db_username = \'' . $_REQUEST['typo_db_username'] . '\';' . PHP_EOL;
			$config .= '  $typo_db_password = \'' . $_REQUEST['typo_db_password'] . '\';' . PHP_EOL;
			$config .= '  $typo_db_host = \'' . $_REQUEST['typo_db_host'] . '\';' . PHP_EOL;
			$config .= '  $typo_db = \'' . $_REQUEST['typo_db'] . '\';' . PHP_EOL;
			$config .= '?>' . PHP_EOL;
			if( $size = @file_put_contents( $this->options->configFile, $config ) ) {
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
				if( !mysql_query($query) ) {
					return false;
				}
			}
			return true;
		}
		
		public function deploySql($sqlPath) {
			require_once $this->options->extractPath . $this->options->configFile;
			
			if( !$link = @mysql_connect($typo_db_host, $typo_db_username, $typo_db_password) ) {
				$this->error = 'Could not connect: ' . mysql_error();
				return false;
			}
			if( !mysql_select_db($typo_db) ) {
				if( !mysql_query('CREATE DATABASE `' . $typo_db . '`', $link) ) {
					$this->error = 'Could not create database: ' . mysql_error();
					return false;
				} else {
					mysql_select_db($typo_db);
				}
			}
			
			if( $this->mysqlBigImport($sqlPath) ) {
				return true;
			} else {
				$this->error = 'sql could not be imported: ' . mysql_error();
				return false;
			}
		}
		
		public function deployFileSystem($fileSystemPath) {
			if( Tar::extract($fileSystemPath, $this->options->extractPath) ) {
				return true;
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
		input { border: 1px solid #ccc;s }
		
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
	
	<form action="" method="post">
		
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
					echo '<li class="' . $class . '"><input type="checkbox" name="updateAdminPw" ' . $checked . ' /> Change Admin Password to <input type="text" style="width: 100px;" name="adminPw" id="adminPwInput" value="' . $value . '" /><span id="newPassword" onclick="newPassword();"> (random Password)</span>' . $msg . '</li>';
					
					// BaseUrl
					$checked = ( isset($_REQUEST['updateDomain']) || !isset($_REQUEST['submitted']) ) ? 'checked="checked"' : '';
					$class = 'error'; $msg = '';
					if( is_file($deploy->options->baseUrlFile) ) {
						if( is_writeable($deploy->options->baseUrlFile) ) {
							$class = 'ready';
						} else {
							$class = 'error';
							$msg = '[error: file not writeable]';
						}
					} elseif ($fileSystemStatus != 'alreadyDeployed') {
						$class = 'warning';
						$msg = '[warning: file not found]';
					} else {
						$class = 'error';
						$msg = '[error: file not found, but filesystem already deployed]';
					}
					echo '<li class="' . $class . '"><input type="checkbox" name="updateDomain" ' . $checked . ' /> Update BaseUrl to <input type="text" style="width: 200px;" name="domain" /> <span style="font-size: 13px; color: #888;">(' . $deploy->options->baseUrlFile . ')</span>' . $msg . ' </li>';
					
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
				<label for="overrideConfig">Override:</label> <input type="checkbox" name="overrideConfig" id="overrideConfig" /> Do you want to override a found config file (<?php echo $deploy->options->configFile; ?>)? <br />
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
			<li id="f2">Needs the config file (<?php echo $deploy->options->configFile; ?>) from the FileSystem or a Manual Sql Configuration on this page</li>
			<li id="f3">Filename deploy.php</li>
			<li id="f4">They will be overwritten</li>
			<li id="f5">If you still select it, it will be completely overwritten</li>
		</ol>
	</div>	
	
</div>




	<script type="text/javascript">
		var configSql = document.getElementById('configSql');
		var configSqlFieldset = document.getElementById('configSqlFieldset');
		
		configSql.style.display = 'block';
		toggleDisplay();
		if( document.getElementById('adminPwInput').value == '' )
			newPassword();
		
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
			var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			var pass = '';
			var i;
			for( var x = 0; x < length; x++) {
				i = Math.floor(Math.random() * 62);
				pass += chars.charAt(i);
			}
			return pass;
		}
		
		function newPassword() {
			var input = document.getElementById('adminPwInput');
			input.value = randomPassword(8);
		}
	</script>

</body>
</html>


<?php
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

/**
 * a class to support .tar(.gz) extraction as a static function
 * slightly based on tar and untar from http://php-classes.sourceforge.net/
 * just use 
 *   Tar::extract('myTar.tar');
 *   Tar::extract('myTarGz.tar.gz', 'path/wher/to/extract/');
 *
 * @copyright Copyright belongs to the respective authors
 * @author	Thomas Allmer <at@delusionworld.com>
 * @author Dennis Wronka <reptiler@users.sourceforge.net>
 * @license	MIT-style license
 */
class Tar {

	/**
	* saves some tar data to the given location and set some default rights
	*
	* @param array $dataInfo
	* @param array $data
	* @param string $extractTo path where to extract
	* @param int $right the default rights set to
	* @return boolean
	*/
	public static function saveDataInfo($dataInfo, $data, $extractTo = '', $rights = 0777) {
		$posCount = 0;
		$name = '';
		while( substr($dataInfo,$posCount,1) != chr(0) ) {
			$name .= substr($dataInfo, $posCount, 1);
			$posCount++;
		}
		if( !empty($name) ) {
			if( substr($name, -1) == '/') {
				@mkdir($extractTo . $name);
			} else {
				$dataSize = strlen($data) - 1;
				while( (substr($data, $dataSize, 1) == chr(0)) && ($dataSize >- 1) ) {
					$dataSize--;
				}
				$dataSize++;
				$fileData = '';
				for( $datacount = 0; $datacount < $dataSize; $datacount++ ) {
					$fileData .= substr($data, $datacount, 1);
				}
				file_put_contents($extractTo . $name, $fileData);
			}
			
			if( file_exists($extractTo . $name) ) {
				return chmod($extractTo . $name, $rights);
			}
			
		}
		return false;
	}

	/**
	* extracts a given file to a given location
	*
	* @param string $filePath what file to use
	* @param string $extractTo path where to extract
	* @param int $right the default rights set to
	* @return boolean
	*/
	public static function extract($filePath, $extractTo = '', $rights = 0777) {
		$tarFile = gzopen($filePath, 'r');
		if( $tarFile === false ) return false;
		$dataInfo = '';
		$data = '';
		while( !feof($tarFile) ) {
			$readData = gzread($tarFile, 512);
			if( substr($readData, 257, 5) == 'ustar') {
				if( !empty($dataInfo) ) {
					Tar::saveDataInfo($dataInfo, $data, $extractTo, $rights);
					$dataInfo = $readData;
					$data = '';
				} else {
					$dataInfo = $readData;
				}
			} else {
				$data .= $readData;
			}
		}
		if( !empty($dataInfo) ) {
			Tar::saveDataInfo($dataInfo, $data, $extractTo, $rights);
		}
		
		return gzclose($tarFile);
	}
	
}
	
?>