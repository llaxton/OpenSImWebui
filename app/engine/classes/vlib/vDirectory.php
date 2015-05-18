<?php
/**
 * Represents a directory on the filesystem, also provides static directory-related methods
 * 
 * @copyright  Copyright (c) 2014-2019 Alan Johnston, Velus Universe Ltd
 * @author     Alan Johnston [aj] <alan.johnston@velusuniverse.co.uk>
 * @author     Alan Johnston, Velus Universe Ltd [aj-vu] <alan.johnston@velusuniverse.co.uk>
 * @license    http://veluslib.opensource.velusuniverse.com/license
 * 
 * @package    Velus Lib
 * 
 * @version    0.0.1b
 * @changes    0.0.1b    The initial implementation [aj, 2014-12-13]
 * 
 * @link       http://veluslib.opensource.velusuniverse.com/vDirectory
 */
class vDirectory
{
	// The following constants allow for nice looking callbacks to static methods
	const create        = 'vDirectory::create';
	const makeCanonical = 'vDirectory::makeCanonical';
	
	
	/**
	 * Creates a directory on the filesystem and returns an object representing it
	 * 
	 * The directory creation is done recursively, so if any of the parent
	 * directories do not exist, they will be created.
	 * 
	 * This operation will be reverted by a filesystem transaction being rolled back.
	 * 
	 * @throws vValidationException  When no directory was specified, or the directory already exists
	 * 
	 * @param  string  $directory  The path to the new directory
	 * @param  numeric $mode       The mode (permissions) to use when creating the directory. This should be an octal number (requires a leading zero). This has no effect on the Windows platform.
	 * @return vDirectory
	 */
	static public function create($directory, $mode=0777)
	{
		if (empty($directory)) {
			throw new vValidationException('No directory name was specified');
		}
		
		if (file_exists($directory)) {
			throw new vValidationException(
				'The directory specified, %s, already exists',
				$directory
			);
		}
		
		$parent_directory = vFilesystem::getPathInfo($directory, 'dirname');
		if (!file_exists($parent_directory)) {
			vDirectory::create($parent_directory, $mode);
		}
		
		if (!is_writable($parent_directory)) {
			throw new vEnvironmentException(
				'The directory specified, %s, is inside of a directory that is not writable',
				$directory
			);
		}
		
		mkdir($directory, $mode);
		
		$directory = new vDirectory($directory);
		
		vFilesystem::recordCreate($directory);
		
		return $directory;
	}
	
	
	/**
	 * Makes sure a directory has a `/` or `\` at the end
	 * 
	 * @param  string $directory  The directory to check
	 * @return string  The directory name in canonical form
	 */
	static public function makeCanonical($directory)
	{
		if (substr($directory, -1) != '/' && substr($directory, -1) != '\\') {
			$directory .= DIRECTORY_SEPARATOR;
		}
		return $directory;
	}
	
	
	/**
	 * A backtrace from when the file was deleted 
	 * 
	 * @var array
	 */
	protected $deleted = NULL;
	
	/**
	 * The full path to the directory
	 * 
	 * @var string
	 */
	protected $directory;
	
	
	/**
	 * Creates an object to represent a directory on the filesystem
	 * 
	 * If multiple vDirectory objects are created for a single directory,
	 * they will reflect changes in each other including rename and delete
	 * actions.
	 * 
	 * @throws vValidationException  When no directory was specified, when the directory does not exist or when the path specified is not a directory
	 * 
	 * @param  string  $directory    The path to the directory
	 * @param  boolean $skip_checks  If file checks should be skipped, which improves performance, but may cause undefined behavior - only skip these if they are duplicated elsewhere
	 * @return vDirectory
	 */
	public function __construct($directory, $skip_checks=FALSE)
	{
		if (!$skip_checks) {
			if (empty($directory)) {
				throw new vValidationException('No directory was specified');
			}
			
			if (!is_readable($directory)) {
				throw new vValidationException(
					'The directory specified, %s, does not exist or is not readable',
					$directory
				);
			}
			if (!is_dir($directory)) {
				throw new vValidationException(
					'The directory specified, %s, is not a directory',
					$directory
				);
			}
		}
		
		$directory = self::makeCanonical(realpath($directory));
		
		$this->directory =& vFilesystem::hookFilenameMap($directory);
		$this->deleted   =& vFilesystem::hookDeletedMap($directory);
		
		// If the directory is listed as deleted and we are not inside a transaction,
		// but we've gotten to here, then the directory exists, so we can wipe the backtrace
		if ($this->deleted !== NULL && !vFilesystem::isInsideTransaction()) {
			vFilesystem::updateDeletedMap($directory, NULL);
		}
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @internal
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Returns the full filesystem path for the directory
	 * 
	 * @return string  The full filesystem path
	 */
	public function __toString()
	{
		return $this->getPath();
	}
	
	
	/**
	 * Removes all files and directories inside of the directory
	 * 
	 * @return void
	 */
	public function clear()
	{
		if ($this->deleted) {
			return;	
		}
		
		foreach ($this->scan() as $file) {
			$file->delete();
		}
	}
	
	
	/**
	 * Will delete a directory and all files and directories inside of it
	 * 
	 * This operation will not be performed until the filesystem transaction
	 * has been committed, if a transaction is in progress. Any non-VelusLib
	 * code (PHP or system) will still see this directory and all contents as
	 * existing until that point.
	 * 
	 * @return void
	 */
	public function delete()
	{
		if ($this->deleted) {
			return;	
		}

		if (!$this->getParent()->isWritable()) {
			throw new vEnvironmentException(
				'The directory, %s, can not be deleted because the directory containing it is not writable',
				$this->directory
			);
		}
		
		$files = $this->scan();
		
		foreach ($files as $file) {
			$file->delete();
		}
		
		// Allow filesystem transactions
		if (vFilesystem::isInsideTransaction()) {
			return vFilesystem::recordDelete($this);
		}
		
		rmdir($this->directory);
		
		vFilesystem::updateDeletedMap($this->directory, debug_backtrace());
		vFilesystem::updateFilenameMapForDirectory($this->directory, '*DELETED at ' . time() . ' with token ' . uniqid('', TRUE) . '* ' . $this->directory);
	}
	
	
	/**
	 * Gets the name of the directory
	 * 
	 * @return string  The name of the directory
	 */
	public function getName()
	{
		return vFilesystem::getPathInfo($this->directory, 'basename');
	}
	
	
	/**
	 * Gets the parent directory
	 * 
	 * @return vDirectory  The object representing the parent directory
	 */
	public function getParent()
	{
		$this->tossIfDeleted();
		
		$dirname = vFilesystem::getPathInfo($this->directory, 'dirname');
		
		if ($dirname == $this->directory) {
			throw new vEnvironmentException(
				'The current directory does not have a parent directory'
			);
		}
		
		return new vDirectory($dirname);
	}
	
	
	/**
	 * Gets the directory's current path
	 * 
	 * If the web path is requested, uses translations set with
	 * vFilesystem::addWebPathTranslation()
	 * 
	 * @param  boolean $translate_to_web_path  If the path should be the web path
	 * @return string  The path for the directory
	 */
	public function getPath($translate_to_web_path=FALSE)
	{
		$this->tossIfDeleted();
		
		if ($translate_to_web_path) {
			return vFilesystem::translateToWebPath($this->directory);
		}
		return $this->directory;
	}
	
	
	/**
	 * Gets the disk usage of the directory and all files and folders contained within
	 * 
	 * This method may return incorrect results if files over 2GB exist and the
	 * server uses a 32 bit operating system
	 * 
	 * @param  boolean $format          If the filesize should be formatted for human readability
	 * @param  integer $decimal_places  The number of decimal places to format to (if enabled)
	 * @return integer|string  If formatted, a string with filesize in b/kb/mb/gb/tb, otherwise an integer
	 */
	public function getSize($format=FALSE, $decimal_places=1)
	{
		$this->tossIfDeleted();
		
		$size = 0;
		
		$children = $this->scan();
		foreach ($children as $child) {
			$size += $child->getSize();
		}
		
		if (!$format) {
			return $size;
		}
		
		return vFilesystem::formatFilesize($size, $decimal_places);
	}
	
	
	/**
	 * Check to see if the current directory is writable
	 * 
	 * @return boolean  If the directory is writable
	 */
	public function isWritable()
	{
		$this->tossIfDeleted();
		
		return is_writable($this->directory);
	}
	
	
	/**
	 * Moves the current directory into a different directory
	 * 
	 * Please note that ::rename() will rename a directory in its current
	 * parent directory or rename it into a different parent directory.
	 * 
	 * If the current directory's name already exists in the new parent
	 * directory and the overwrite flag is set to false, the name will be
	 * changed to a unique name.
	 * 
	 * This operation will be reverted if a filesystem transaction is in
	 * progress and is later rolled back.
	 * 
	 * @throws vValidationException  When the new parent directory passed is not a directory, is not readable or is a sub-directory of this directory
	 * 
	 * @param  vDirectory|string $new_parent_directory  The directory to move this directory into
	 * @param  boolean           $overwrite             If the current filename already exists in the new directory, `TRUE` will cause the file to be overwritten, `FALSE` will cause the new filename to change
	 * @return vDirectory  The directory object, to allow for method chaining
	 */
	public function move($new_parent_directory, $overwrite)
	{
		if (!$new_parent_directory instanceof vDirectory) {
			$new_parent_directory = new vDirectory($new_parent_directory);
		}
		
		if (strpos($new_parent_directory->getPath(), $this->getPath()) === 0) {
			throw new vValidationException('It is not possible to move a directory into one of its sub-directories');	
		}
		
		return $this->rename($new_parent_directory->getPath() . $this->getName(), $overwrite);
	}
	
	
	/**
	 * Renames the current directory
	 * 
	 * This operation will NOT be performed until the filesystem transaction
	 * has been committed, if a transaction is in progress. Any non-VelusLib
	 * code (PHP or system) will still see this directory (and all contained
	 * files/dirs) as existing with the old paths until that point.
	 * 
	 * @param  string  $new_dirname  The new full path to the directory or a new name in the current parent directory
	 * @param  boolean $overwrite    If the new dirname already exists, TRUE will cause the file to be overwritten, FALSE will cause the new filename to change
	 * @return void
	 */
	public function rename($new_dirname, $overwrite)
	{
		$this->tossIfDeleted();
		
		if (!$this->getParent()->isWritable()) {
			throw new vEnvironmentException(
				'The directory, %s, can not be renamed because the directory containing it is not writable',
				$this->directory
			);
		}
		
		// If the dirname does not contain any folder traversal, rename the dir in the current parent directory
		if (preg_match('#^[^/\\\\]+$#D', $new_dirname)) {
			$new_dirname = $this->getParent()->getPath() . $new_dirname;	
		}
		
		$info = vFilesystem::getPathInfo($new_dirname);
		
		if (!file_exists($info['dirname'])) {
			throw new vProgrammerException(
				'The new directory name specified, %s, is inside of a directory that does not exist',
				$new_dirname
			);
		}
		
		if (file_exists($new_dirname)) {
			if (!is_writable($new_dirname)) {
				throw new vEnvironmentException(
					'The new directory name specified, %s, already exists, but is not writable',
					$new_dirname
				);
			}
			if (!$overwrite) {
				$new_dirname = vFilesystem::makeUniqueName($new_dirname);
			}
		} else {
			$parent_dir = new vDirectory($info['dirname']);
			if (!$parent_dir->isWritable()) {
				throw new vEnvironmentException(
					'The new directory name specified, %s, is inside of a directory that is not writable',
					$new_dirname
				);
			}
		}
		
		rename($this->directory, $new_dirname);
		
		// Make the dirname absolute
		$new_dirname = vDirectory::makeCanonical(realpath($new_dirname));
		
		// Allow filesystem transactions
		if (vFilesystem::isInsideTransaction()) {
			vFilesystem::rename($this->directory, $new_dirname);
		}
		
		vFilesystem::updateFilenameMapForDirectory($this->directory, $new_dirname);
	}
	
	
	/**
	 * performs a [http://php.net/scandir scandir()] on a directory, removing the `.` and `..` entries
	 * 
	 * If the `$filter` looks like a valid PCRE pattern - matching delimeters
	 * (a delimeter can be any non-alphanumeric, non-backslash, non-whitespace
	 * character) followed by zero or more of the flags `i`, `m`, `s`, `x`,
	 * `e`, `A`, `D`,  `S`, `U`, `X`, `J`, `u` - then
	 * [http://php.net/preg_match `preg_match()`] will be used.
	 * 
	 * Otherwise the `$filter` will do a case-sensitive match with `*` matching
	 * zero or more characters and `?` matching a single character.
	 * 
	 * On all OSes (even Windows), directories will be separated by `/`s when
	 * comparing with the `$filter`.
	 * 
	 * @param  string $filter  A PCRE or glob pattern to filter files/directories by path - directories can be detected by checking for a trailing / (even on Windows)
	 * @return array  The vFile (or vImage) and vDirectory objects for the files/directories in this directory
	 */
	public function scan($filter=NULL)
	{
		$this->tossIfDeleted();
		
		$files   = array_diff(scandir($this->directory), array('.', '..'));
		$objects = array();
		
		if ($filter && !preg_match('#^([^a-zA-Z0-9\\\\\s]).*\1[imsxeADSUXJu]*$#D', $filter)) {
			$filter = '#^' . strtr(
				preg_quote($filter, '#'),
				array(
					'\\*' => '.*',
					'\\?' => '.'
				)
			) . '$#D';
		}
		
		natcasesort($files);
		
		foreach ($files as $file) {
			if ($filter) {
				$test_path = (is_dir($this->directory . $file)) ? $file . '/' : $file;
				if (!preg_match($filter, $test_path)) {
					continue;
				}
			}
			
			$objects[] = vFilesystem::createObject($this->directory . $file);
		}
		
		return $objects;
	}
	
	
	/**
	 * performs a **recursive** [http://php.net/scandir scandir()] on a directory, removing the `.` and `..` entries
	 * 
	 * @param  string $filter  A PCRE or glob pattern to filter files/directories by path - see ::scan() for details
	 * @return array  The vFile (or vImage) and vDirectory objects for the files/directories (listed recursively) in this directory
	 */
	public function scanRecursive($filter=NULL)
	{
		$this->tossIfDeleted();
		
		$objects = $this->scan();
		
		for ($i=0; $i < sizeof($objects); $i++) {
			if ($objects[$i] instanceof vDirectory) {
				array_splice($objects, $i+1, 0, $objects[$i]->scan());
			}
		}
		
		if ($filter) {
			if (!preg_match('#^([^a-zA-Z0-9\\\\\s*?^$]).*\1[imsxeADSUXJu]*$#D', $filter)) {
				$filter = '#^' . strtr(
					preg_quote($filter, '#'),
					array(
						'\\*' => '.*',
						'\\?' => '.'
					)
				) . '$#D';
			}
			
			$new_objects  = array();
			$strip_length = strlen($this->getPath());
			foreach ($objects as $object) {
				$test_path = substr($object->getPath(), $strip_length);
				$test_path = str_replace(DIRECTORY_SEPARATOR, '/', $test_path);
				if (!preg_match($filter, $test_path)) {
					continue;	
				}	
				$new_objects[] = $object;
			}
			$objects = $new_objects;
		}
		
		return $objects;
	}
	
	
	/**
	 * Throws an exception if the directory has been deleted
	 * 
	 * @return void
	 */
	protected function tossIfDeleted()
	{
		if ($this->deleted) {
			throw new vProgrammerException(
				"The action requested can not be performed because the directory has been deleted\n\nBacktrace for vDirectory::delete() call:\n%s",
				vCore::backtrace(0, $this->deleted)
			);
		}
	}
}



/**
 * Copyright (c) Alan Johnston of Velus Universe Ltd <alan.johnston@velusuniverse.co.uk>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
