<?php

namespace Nano\helpers;

use Nano\core\App;

class FileSystem {

	static function readJSON ( string $path ): mixed {
		$content = file_get_contents( $path );
		return json_decode( $content, true );
	}

	/**
	 * List content of a folder.
	 * // TODO : Allow extension filtering
	 * // TODO : Allow only file or only folders
	 * // TODO : Allow recursive with a max
	 * @param string $path Absolute path to the folder to scan.
	 * @param bool $includeHidden Will exclude every file or folder starting with a dot.
	 * @param string $fileType "all" | "directories" | "files"
	 * @return false|array
	 */
	static function listFolder ( string $path, bool $includeHidden = false, string $fileType = "all" ): false|array {
		$path = rtrim( $path, "/" )."/";
		if ( !file_exists( $path ) )
			return false;
		$files  = scandir( $path );
		$output = [];
		foreach ( $files as $fileName ) {
			if ( $fileName === "." || $fileName === ".." )
				continue;
			if ( !$includeHidden && stripos( $fileName, "." ) === 0 )
				continue;
			$filePath = $path.$fileName;
			$isDir = is_dir($filePath);
			$isFile = is_file($filePath);
			if ( $fileType === "directories" && !$isDir )
				continue;
			else if ( $fileType === "files" && !$isFile )
				continue;
			$f = new FileObject();
			$f->name = $fileName;
			$f->path = $filePath;
			$f->isDirectory = $isDir;
			$f->isFile = $isFile;
			$output[] = $f;
		}
		return $output;
	}


	/**
	 * Will search recursively for a file inside a folder.
	 * Will return first found directory which contains this file
	 *
	 * @param string $rootPath Directory to start search from. Absolute path.
	 * @param string $searchedFile File name to find.
	 * @param array $excludedFolders Folders to exclude search from.
	 *
	 * @return false|string Will return false if not found, otherwise will
	 */
	static function recursiveSearchRoot ( string $rootPath, string $searchedFile, array $excludedFolders = [] ): false|string {
		$files = scandir( $rootPath );
		foreach ( $files as $file ) {
			$filePath = rtrim( $rootPath, "/" ) . "/" . $file;
			// Do not process hidden files or current / parent directories
			if ( stripos( $file, "." ) === 0 )
				continue;
			// Do not process excluded files
			if ( in_array( $file, $excludedFolders ) )
				continue;
			// We found the root folder
			if ( $file == $searchedFile )
				return $rootPath;
			// We found a browsable folder
			if ( is_dir( $filePath ) ) {
				$r = FileSystem::recursiveSearchRoot( $filePath, $searchedFile, $excludedFolders );
				if ( $r !== false ) {
					return $r;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively remove a directory and its files.
	 * Can remove directly targeted files.
	 * Warning, it will be executed recursively, be careful of big architectures.
	 * Allowed only in App::$dataPath for security
	 * Symlinks will be unlinked. Symlinks to folders will not be followed for recursive deletion.
	 * Symlinks outside of data can be unlinked.
	 *
	 * @param string $path Absolute path of directory or file to remove.
	 * @param bool $allowOutsideData Dangerously allow recursive deletion outside of the data directory.
	 * @return bool|string string if error, true if ok, false if no effect
	 */
	static function recursiveRemove ( string $path, bool $allowOutsideData = false ): bool|string {
		// Check empty paths
		if ( empty( $path ) )
			return "empty-path";
		// Check if path is a symlink
		// In that case we want to unlink, not remove the destination
		if ( is_link($path) )
			return unlink( $path );
		// Convert to real path
		$path = realpath( $path );
		if ( $path === false )
			return false;
		// Check if in data
		if ( !$allowOutsideData ) {
			$pathRoot = rtrim( $path, "/" )."/";
			$appPathRoot = rtrim(App::$dataPath, "/")."/";
			if ( !str_starts_with( $pathRoot, $appPathRoot ) )
				return "path-not-in-data";
		}
		// Recursive delete directory
		if ( is_dir( $path ) ) {
			$files = scandir( $path );
			if ( $files === false )
				return "cannot-scan-directory";
			foreach ( $files as $file ) {
				if ( $file == "." || $file == ".." )
					continue;
				$filePath = rtrim( $path, "/" )."/".$file;
				$r = FileSystem::recursiveRemove( $filePath, $allowOutsideData );
				if (is_string($r) || $r === false)
					return $r;
			}
			return rmdir( $path );
		}
		// Remove direct file
		if ( is_file( $path ) ) {
			return unlink( $path );
		}
		return false;
	}

	/**
	 * Copy recursively a directory and its files to a destination.
	 * Destination directories will be created if not existing.
	 * Will follow symlinks.
	 *
	 * FIXME : to finish and test
	 *
	 * @param string $from Absolute path of copied file or directory
	 * @param string $to Absolute path of destination directory
	 * @param bool $allowReplace If true, will allow overriding destination files and directories
	 * @return bool|string string if error, true if ok
	 */
	static function recursiveCopy ( string $from, string $to, bool $allowReplace = false ): bool|string {
		// Check empty paths
		if ( empty( $from ) || empty( $to ) )
			return "empty-path";
		// Resolve paths
		$from = rtrim(realpath( $from ), "/");
		$to = rtrim(realpath( $to ), "/");
		// Source does not exists
		if ( !file_exists( $from ) )
			return "file-not-found";
		// Check destination
		// fixme : we still can target a $to that is a child of a file
		if ( file_exists( $to ) && is_file( $to ) )
			return "destination-should-be-a-directory";
		// Create new empty directory
		if ( !is_dir( $to ) && !mkdir( $to, 0777, true ) )
			return "cannot-create-directory";
		// Copy file
		if ( is_file($from) ) {
			return copy( $from, $to."/".basename($from) );
		}
		// Copy directory
		if ( is_dir($from)) {
			$files = scandir( $from );
			if ( $files === false )
				return false;
			foreach ( $files as $file ) {
				if ( $file == "." || $file == ".." )
					continue;
				$destinationPath = $to."/".$file;
				if ( file_exists($destinationPath) ) {
					if ( $allowReplace )
						FileSystem::recursiveRemove( $destinationPath );
					else
						continue;
				}
				self::recursiveCopy( $from."/".$file, $destinationPath, $allowReplace );
			}
			return true;
		}
		return false;
	}
}
