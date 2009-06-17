<?php
class Tar {

	var $fileList = array();
	var $path = '';
	var $gz = false;
	var $tarFile;
	
	function Tar( $gz = true ) {
		$this->setGz( $gz );
	}
	
	function setGz( $gz ) {
		if( isset($gz) && $gz ) {
			$this->gz = true;
		} else {
			$this->gz = false;
		}
	}
	
	function open( $filename, $gz = NULL ) {
		$this->setGz( $gz );
		if( $this->gz === true ) {
			$this->tarFile = @gzopen($filename, 'w');
		} else {
			$this->tarFile = @fopen($filename, 'w');
		}
		return is_resource($this->tarFile);
	}
	
	function addFile( $file, $path = '') {
		if ( is_file($file) ) {
			$this->fileList[$path] = $file;
			return true;
		}
		return false;
	}
	
	function _addDir($_dir, $path = '', $depth = 3) {
		if( file_exists($_dir) ) {
			if( $path == '' ) {
				$path = $_dir;
			}
			if( $path != '' && substr($path, -1) != '/' ) {
				$path .= '/';
			}
			
			$this->fileList[$path] = $_dir;
			$d = dir($_dir);
			while (false !== ($dir = $d->read()) ) {
				if( ( $dir != "." && $dir != ".." ) ) {
					if ( is_dir($d->path . '/' . $dir) ) {
						if ( $depth >= 1 )
							$this->_addDir($d->path . '/' . $dir, $this->path . $d->path . '/' . $dir, $depth-1);
					} else {
						$this->addFile( $d->path . '/' . $dir, $this->path . $d->path . '/' . $dir );
					}
				}
			}
			$d->close();
		}
		return false;
	}
	
	function addDir($dir, $path = '') {
		if( $path != '' && substr($path, -1) != '/' ) {
				$path .= '/';
		}
		$this->path = $path;
		$this->_addDir($dir, $path);
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
		
		foreach( $this->fileList as $file => $filename ) {
		
			while( strlen($filename) < 100 ) {
				$filename .= chr(0);
			}
			while( strlen($file) < 100 ) {
				$file .= chr(0);
			}
			$ustar = 'ustar  ' . chr(0);
			
			$permissions = '00000000';
			$userid = '00000000';
			$groupid = '00000000';
			
			if ( is_dir($filename) ) {
				$filesize = '0' . chr(0);
			}	else {
				$filesize = sprintf('%o', filesize($filename) ) . chr(0);
			}
			while( strlen($filesize) < 12 ) {
				$filesize = '0' . $filesize;
			}
			
			$modtime = sprintf('%o', filectime($filename) ) . chr(0);
			$checksum = '        ';
			if( is_dir($filename) ) {
				$indicator = 5;
			} else {
				$indicator = 0;
			}

			$linkname = '';
			while( strlen($linkname) < 100 ) {
				$linkname .= chr(0);
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
			
			$header = $filename.$permissions.$userid.$groupid.$filesize.$modtime.$checksum.$indicator.$linkname.$ustar.$user.$group.$devmajor.$devminor.$prefix;
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
			$header = $filename.$permissions.$userid.$groupid.$filesize.$modtime.$checksum.$indicator.$linkname.$ustar.$user.$group.$devmajor.$devminor.$prefix;
			while( strlen($header) < 512 ) {
				$header .= chr(0);
			}
			if( $this->gz === true ) {
				gzwrite($this->tarFile, $header);
			} else {
				fwrite($this->tarFile, $header);
			}
			
			if( $indicator == 0 ) {
				$contentfile = fopen($filename, 'r');
				$data = fread( $contentfile, filesize($filename) );
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
			gzclose($this->tarFile);
		} else {
			fclose($this->tarFile);
		}
		return true;
	}
	
}

?>