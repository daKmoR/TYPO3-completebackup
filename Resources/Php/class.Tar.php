<?php
class Tar {

	var $fileList = array();
	var $path = '';
	var $gz = false;
	var $tarFile;
	var $cut = 0;
	
	function Tar( $gz = true ) {
		$this->setGz( $gz );
	}
	
	function setGz( $gz ) {
		if( isset($gz) ) {
			if( $gz ) {
				$this->gz = true;
			} else {
				$this->gz = false;
			}
		}
	}
	
	function open( $filePath, $gz = NULL ) {
		$this->setGz( $gz );
		if( $this->gz === true ) {
			$this->tarFile = @gzopen($filePath, 'w');
		} else {
			$this->tarFile = @fopen($filePath, 'w');
		}
		return is_resource($this->tarFile);
	}
	
	function addFile( $path, $file = '') {
		if ( is_file($path) ) {
			$this->fileList[$file] = $path;
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
	
	function _addDir($path, $_dir = '', $depth = 3) {
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
		$this->cut = strlen( dirname(realpath($path)) ) + 1;
		
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
	
	function close() {
		if( !$this->tarFile ) {
			return false;
		}
		ksort($this->fileList);

		
		$eightBit = '';
		while( strlen($eightBit) < 8 ) {
			$eightBit .= chr(0);
		}
		
		foreach( $this->fileList as $file => $filePath ) {
		
			while( strlen($file) < 100 ) {
				$file .= chr(0);
			}
			$ustar = 'ustar  ' . chr(0);
			
			$permissions = '00000000';
			$userid = '00000000';
			$groupid = '00000000';
			
			if ( is_dir($filePath) ) {
				$fileSize = '0' . chr(0);
			}	else {
				$fileSize = sprintf('%o', filesize($filePath) ) . chr(0);
			}
			while( strlen($fileSize) < 12 ) {
				$fileSize = '0' . $fileSize;
			}
			
			$modtime = sprintf('%o', filectime($filePath) ) . chr(0);
			$checksum = '        ';
			if( is_dir($filePath) ) {
				$indicator = 5;
			} else {
				$indicator = 0;
			}

			$linkName = '';
			while( strlen($linkName) < 100 ) {
				$linkName .= chr(0);
			}
			
			$user = '';
			while( strlen($user) < 32 ) {
				$user .= chr(0);
			}
			
			$group = '';
			while( strlen($group) < 32 ) {
				$group .= chr(0);
			}
			
			$devmajor = $eightBit;
			$devminor = $eightBit;
			
			$prefix = '';
			while( strlen($prefix) < 155 ) {
				$prefix .= chr(0);
			}
			
			$header = $file.$permissions.$userid.$groupid.$fileSize.$modtime.$checksum.$indicator.$linkName.$ustar.$user.$group.$devmajor.$devminor.$prefix;
			while( strlen($header) < 512 ) {
				$header .= chr(0);
			}
			
			$checksum = 0;
			for ($y=0; $y < strlen($header); $y++) {
				$checksum += ord($header[$y]);
			}
			$checksum = sprintf('%o', $checksum) . chr(0) . ' ';
			while( strlen($checksum) < 8 ) {
				$checksum = '0' . $checksum;
			}
			$header = $file.$permissions.$userid.$groupid.$fileSize.$modtime.$checksum.$indicator.$linkName.$ustar.$user.$group.$devmajor.$devminor.$prefix;
			while( strlen($header) < 512 ) {
				$header .= chr(0);
			}
			if( $this->gz === true ) {
				gzwrite($this->tarFile, $header);
			} else {
				fwrite($this->tarFile, $header);
			}
			
			if( $indicator == 0 ) {
				$contentfile = fopen($filePath, 'r');
				$data = '';
				if( filesize($filePath) > 0 ) {
					$data = fread( $contentfile, filesize($filePath) );
				}
				while( strlen($data) % 512 != 0 ) {
					$data .= chr(0);
				}
				if( $this->gz === true ) {
					gzwrite($this->tarFile, $data);
				} else {
					fwrite($this->tarFile, $data);
				}
			}
			
		} // for(...)
		if( $this->gz === true ) {
			return gzclose($this->tarFile);
		} else {
			return fclose($this->tarFile);
		}
		return false;
	}
	
}

?>