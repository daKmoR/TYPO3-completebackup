<?php
class AdvZipArchive extends ZipArchive {
	/**
	 * Adds a directory recursively.
	 * @param string $filename The path to the file to add.
	 * @param string $localname Local name inside ZIP archive.
	 */
	public function addDir($filename, $localname) {
		$this->addEmptyDir($localname);
		$iter = new RecursiveDirectoryIterator($filename);

		foreach ($iter as $fileinfo) {
			if ( !$fileinfo->isFile() && !$fileinfo->isDir() )
				continue;

			$method = $fileinfo->isFile() ? 'addFile' : 'addDir';
			$this->$method($fileinfo->getPathname(), $localname . '/' .
				$fileinfo->getFilename());
		}
	}
}
?>