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
	
	function add($pattern, $path) {
		$files = glob($pattern);
		foreach($files as $file) {
			if( is_file($file) ) {
				$this->addFile($file, $path);
			} elseif( is_dir($file) ) {
				$this->addDir($file, $path);
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

?>