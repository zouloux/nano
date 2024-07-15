<?php

namespace Nano\helpers;

class FileSystem {

	static function readJSON ( string $path ) {
		$content = file_get_contents( $path );
		return json_decode( $content, true );
	}

	/**
	 * List content of a folder.
	 * // TODO : Allow extension filtering
	 * // TODO : Allow only file or only folders
	 * // TODO : Allow recursive with a max
	 *
	 * @param string $path Absolute path to the folder to scan.
	 * @param bool $includeHidden Will exclude every file or folder starting with a dot.
	 */
	static function listFolder ( string $path, bool $includeHidden = false ) {
		$path = rtrim( $path ) . "/";
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$files  = scandir( $path );
		$output = [];
		foreach ( $files as $fileName ) {
			if ( $fileName === "." || $fileName === ".." ) {
				continue;
			}
			if ( ! $includeHidden && stripos( $fileName, "." ) === 0 ) {
				continue;
			}
			$f              = new FileObject();
			$filePath       = $path . "/" . $fileName;
			$f->name        = $fileName;
			$f->path        = $filePath;
			$f->isDirectory = is_dir( $filePath );
			$f->isFile      = is_file( $filePath );
			$output[]       = $f;
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
	static function recursiveSearchRoot ( string $rootPath, string $searchedFile, array $excludedFolders = [] ) {
		$files = scandir( $rootPath );
		foreach ( $files as $file ) {
			$filePath = rtrim( $rootPath, "/" ) . "/" . $file;
			// Do not process hidden files or current / parent directories
			if ( stripos( $file, "." ) === 0 ) {
				continue;
			}
			// Do not process excluded files
			if ( in_array( $file, $excludedFolders ) ) {
				continue;
			}
			// We found the root folder
			if ( $file == $searchedFile ) {
				return $rootPath;
			}
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
	 * Remove a directory and its files.
	 * Will be executed recursively.
	 * FIXME WARNING : Does not check if not in app or data
	 *
	 * @param string $path Absolute path of directory to remove.
	 */
	static function recursiveRemoveDirectory ( string $path ) {
		// TODO : Check if parent of App::root and halt
		$files = scandir( $path );
		foreach ( $files as $file ) {
			if ( $file == "." || $file == ".." ) {
				continue;
			}
			$filePath = rtrim( $path, "/" ) . "/" . $file;
			if ( is_dir( $filePath ) )
				FileSystem::recursiveRemoveDirectory( $filePath );
			else
				unlink( $filePath );
		}
		rmdir( $path );
	}

	/**
	 * Copy a folder and its content.
	 * One level only, no recursive copy.
	 * Output will be created if not existing.
	 * FIXME : Implement deep recursive copy
	 *
	 * @param string $from Absolute path from
	 * @param string $to Absolute path to (directory name included)
	 */
	static function copyFolder ( string $from, string $to ) {
		// Clean already existing directory
		if ( is_dir( $to ) ) {
			FileSystem::recursiveRemoveDirectory( $to );
		}
		// Create new empty directory
		mkdir( $to, 0777, true );
		// Browse source directory
		$files = scandir( $from );
		foreach ( $files as $file ) {
			$filePath = rtrim( $from, "/" ) . "/" . $file;
			if ( !is_file( $filePath ) )
				continue;
			copy( $filePath, rtrim( $to, "/" ) . "/" . $file );
		}
	}
}