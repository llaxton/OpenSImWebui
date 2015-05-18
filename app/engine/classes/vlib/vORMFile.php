<?php
/**
 * Provides file manipulation functionality for vActiveRecord classes
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
 * @link       http://veluslib.opensource.velusuniverse.com/vORMFile
 */
class vORMFile
{
	// The following constants allow for nice looking callbacks to static methods
	const addvImageMethodCall        = 'vORMFile::addvImageMethodCall';
	const addvUploadMethodCall       = 'vORMFile::addvUploadMethodCall';
	const begin                      = 'vORMFile::begin';
	const commit                     = 'vORMFile::commit';
	const configureColumnInheritance = 'vORMFile::configureColumnInheritance';
	const configureFileUploadColumn  = 'vORMFile::configureFileUploadColumn';
	const configureImageUploadColumn = 'vORMFile::configureImageUploadColumn';
	const delete                     = 'vORMFile::delete';
	const deleteOld                  = 'vORMFile::deleteOld';
	const encode                     = 'vORMFile::encode';
	const inspect                    = 'vORMFile::inspect';
	const moveFromTemp               = 'vORMFile::moveFromTemp';
	const objectify                  = 'vORMFile::objectify';
	const populate                   = 'vORMFile::populate';
	const prepare                    = 'vORMFile::prepare';
	const process                    = 'vORMFile::process';
	const processImage               = 'vORMFile::processImage';
	const reflect                    = 'vORMFile::reflect';
	const replicate                  = 'vORMFile::replicate';
	const reset                      = 'vORMFile::reset';
	const rollback                   = 'vORMFile::rollback';
	const set                        = 'vORMFile::set';
	const upload                     = 'vORMFile::upload';
	const validate                   = 'vORMFile::validate';
	
	
	/**
	 * The temporary directory to use for various tasks
	 * 
	 * @internal
	 * 
	 * @var string
	 */
	const TEMP_DIRECTORY = '__VelusLib_temp';
	
	
	/**
	 * Defines how columns can inherit uploaded files
	 * 
	 * @var array
	 */
	static private $column_inheritence = array();
	
	/**
	 * Methods to be called on vUpload before the file is uploaded
	 * 
	 * @var array
	 */
	static private $vUpload_method_calls = array();
	
	/**
	 * Columns that can be filled by file uploads
	 * 
	 * @var array
	 */
	static private $file_upload_columns = array();
	
	/**
	 * Methods to be called on the vImage instance
	 * 
	 * @var array
	 */
	static private $vImage_method_calls = array();
	
	/**
	 * Columns that can be filled by image uploads
	 * 
	 * @var array
	 */
	static private $image_upload_columns = array();
	
	/**
	 * Keeps track of the nesting level of the filesystem transaction so we know when to start, commit, rollback, etc
	 * 
	 * @var integer
	 */
	static private $transaction_level = 0;
	
	
	/**
	 * Adds an vImage method call to the image manipulation for a column if an image file is uploaded
	 * 
	 * Any call to vImage::saveChanges() will be called last. If no explicit
	 * method call to vImage::saveChanges() is made, it will be called
	 * implicitly with default parameters.
	 * 
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The vImage method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addvImageMethodCall($class, $column, $method, $parameters=array())
	{
		$class = vORM::getClass($class);
		
		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new vProgrammerException(
				'The column specified, %s, has not been configured as a file or image upload column',
				$column
			);
		}
		
		if (empty(self::$vImage_method_calls[$class])) {
			self::$vImage_method_calls[$class] = array();
		}
		if (empty(self::$vImage_method_calls[$class][$column])) {
			self::$vImage_method_calls[$class][$column] = array();
		}
		
		self::$vImage_method_calls[$class][$column][] = array(
			'method'     => $method,
			'parameters' => $parameters
		);
	}
	
	
	/**
	 * Adds an vUpload method call to the vUpload initialization for a column
	 * 
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The vUpload method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addvUploadMethodCall($class, $column, $method, $parameters=array())
	{
		if ($method == 'enableOverwrite') {
			throw new vProgrammerException(
				'The method specified, %1$s, is not compatible with how %2$s stores and associates files with records',
				$method,
				'vORMFile'
			); 	
		}
		
		$class = vORM::getClass($class);
		
		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new vProgrammerException(
				'The column specified, %s, has not been configured as a file or image upload column',
				$column
			);
		}
		
		if (empty(self::$vUpload_method_calls[$class])) {
			self::$vUpload_method_calls[$class] = array();
		}
		if (empty(self::$vUpload_method_calls[$class][$column])) {
			self::$vUpload_method_calls[$class][$column] = array();
		}
		
		self::$vUpload_method_calls[$class][$column][] = array(
			'method'     => $method,
			'parameters' => $parameters
		);
	}
	
	
	/**
	 * Begins a transaction, or increases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function begin()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0 && vFilesystem::isInsideTransaction()) {
			return;
		}
		
		self::$transaction_level++;
		
		if (!vFilesystem::isInsideTransaction()) {
			vFilesystem::begin();
		}
	}
	
	
	/**
	 * Commits a transaction, or decreases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function commit()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return;
		}
		
		self::$transaction_level--;
		
		if (!self::$transaction_level) {
			vFilesystem::commit();
		}
	}
	
	
	/**
	 * Composes text using vText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static private function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('vText', FALSE)) {
			return call_user_func_array(
				array('vText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * Sets a column to be a file upload column
	 * 
	 * Configuring a column to be a file upload column means that whenever
	 * vActiveRecord::populate() is called for an vActiveRecord object, any
	 * appropriately named file uploads (via `$_FILES`) will be moved into
	 * the directory for this column.
	 * 
	 * Setting the column to a file path will cause the specified file to
	 * be copied into the directory for this column.
	 * 
	 * @param  mixed             $class      The class name or instance of the class
	 * @param  string            $column     The column to set as a file upload column
	 * @param  vDirectory|string $directory  The directory to upload/move to
	 * @return void
	 */
	static public function configureFileUploadColumn($class, $column, $directory)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a file upload column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		if (!is_object($directory)) {
			$directory = new vDirectory($directory);
		}
		
		if (!$directory->isWritable()) {
			throw new vEnvironmentException(
				'The file upload directory, %s, is not writable',
				$directory->getPath()
			);
		}
		
		$camelized_column = vGrammar::camelize($column, TRUE);
		
		vORM::registerActiveRecordMethod(
			$class,
			'upload' . $camelized_column,
			self::upload
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'set' . $camelized_column,
			self::set
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			self::encode
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			self::prepare
		);
		
		vORM::registerReflectCallback($class, self::reflect);
		vORM::registerInspectCallback($class, $column, self::inspect);
		vORM::registerReplicateCallback($class, $column, self::replicate);
		vORM::registerObjectifyCallback($class, $column, self::objectify);
		
		$only_once_hooks = array(
			'post-begin::delete()'    => self::begin,
			'pre-commit::delete()'    => self::delete,
			'post-commit::delete()'   => self::commit,
			'post-rollback::delete()' => self::rollback,
			'post::populate()'        => self::populate,
			'post-begin::store()'     => self::begin,
			'post-validate::store()'  => self::moveFromTemp,
			'pre-commit::store()'     => self::deleteOld,
			'post-commit::store()'    => self::commit,
			'post-rollback::store()'  => self::rollback,
			'post::validate()'        => self::validate
		);
		
		foreach ($only_once_hooks as $hook => $callback) {
			if (!vORM::checkHookCallback($class, $hook, $callback)) {
				vORM::registerHookCallback($class, $hook, $callback);
			}
		}
		
		if (empty(self::$file_upload_columns[$class])) {
			self::$file_upload_columns[$class] = array();
		}
		
		self::$file_upload_columns[$class][$column] = $directory;
	}
	
	
	/**
	 * Takes one file or image upload columns and sets it to inherit any uploaded/set files from another column
	 * 
	 * @param  mixed  $class                The class name or instance of the class
	 * @param  string $column               The column that will inherit the uploaded file
	 * @param  string $inherit_from_column  The column to inherit the uploaded file from
	 * @return void
	 */
	static public function configureColumnInheritance($class, $column, $inherit_from_column)
	{
		$class = vORM::getClass($class);
		
		if (empty(self::$file_upload_columns[$class][$column])) {
			throw new vProgrammerException(
				'The column specified, %s, has not been configured as a file upload column',
				$column
			);
		}
		
		if (empty(self::$file_upload_columns[$class][$inherit_from_column])) {
			throw new vProgrammerException(
				'The column specified, %s, has not been configured as a file upload column',
				$column
			);
		}
		
		if (empty(self::$column_inheritence[$class])) {
			self::$column_inheritence[$class] = array();
		}
		
		if (empty(self::$column_inheritence[$class][$inherit_from_column])) {
			self::$column_inheritence[$class][$inherit_from_column] = array();
		}
		
		self::$column_inheritence[$class][$inherit_from_column][] = $column;
	}
	
	
	/**
	 * Sets a column to be an image upload column
	 * 
	 * This method works exactly the same as ::configureFileUploadColumn()
	 * except that only image files are accepted.
	 * 
	 * To alter an image, including the file type, use ::addvImageMethodCall().
	 * 
	 * @param  mixed             $class       The class name or instance of the class
	 * @param  string            $column      The column to set as a file upload column
	 * @param  vDirectory|string $directory   The directory to upload to
	 * @return void
	 */
	static public function configureImageUploadColumn($class, $column, $directory)
	{
		self::configureFileUploadColumn($class, $column, $directory);
		
		$class = vORM::getClass($class);
		
		$camelized_column = vGrammar::camelize($column, TRUE);
		
		vORM::registerActiveRecordMethod(
			$class,
			'process' . $camelized_column,
			self::process
		);
		
		if (empty(self::$image_upload_columns[$class])) {
			self::$image_upload_columns[$class] = array();
		}
		
		self::$image_upload_columns[$class][$column] = TRUE;
		
		self::addvUploadMethodCall(
			$class,
			$column,
			'setMimeTypes',
			array(
				array(
					'image/gif',
					'image/jpeg',
					'image/pjpeg',
					'image/png'
				),
				self::compose('The file uploaded is not an image')
			)
		);
	}
	
	
	/**
	 * Deletes the files for this record
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function delete($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			
			// Remove the current file for the column
			if ($values[$column] instanceof vFile) {
				$values[$column]->delete();
			}
			
			// Remove the old files for the column
			foreach (vActiveRecord::retrieveOld($old_values, $column, array(), TRUE) as $file) {
				if ($file instanceof vFile) {
					$file->delete();
				}
			}
		}
	}
	
	
	/**
	 * Deletes old files for this record that have been replaced by new ones
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function deleteOld($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		// Remove the old files for the column
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			$current_file = $values[$column];
			foreach (vActiveRecord::retrieveOld($old_values, $column, array(), TRUE) as $file) {
				if ($file instanceof vFile && (!$current_file instanceof vFile || $current_file->getPath() != $file->getPath())) {
					$file->delete();
				}
			}
		}
	}
	
	
	/**
	 * Encodes a file for output into an HTML `input` tag
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return void
	 */
	static public function encode($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column   = vGrammar::underscorize($subject);
		$filename = ($values[$column] instanceof vFile) ? $values[$column]->getName() : NULL;
		if ($filename && strpos($values[$column]->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR . $filename) !== FALSE) {
			$filename = self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR . $filename;
		}
		
		return vHTML::encode($filename);
	}
	
	
	/**
	 * Adds metadata about features added by this class
	 * 
	 * @internal
	 * 
	 * @param  string $class      The class being inspected
	 * @param  string $column     The column being inspected
	 * @param  array  &$metadata  The array of metadata about a column
	 * @return void
	 */
	static public function inspect($class, $column, &$metadata)
	{
		if (!empty(self::$image_upload_columns[$class][$column])) {
			$metadata['feature'] = 'image';
			
		} elseif (!empty(self::$file_upload_columns[$class][$column])) {
			$metadata['feature'] = 'file';
		}
		
		$metadata['directory'] = self::$file_upload_columns[$class][$column]->getPath();
	}
	
	
	/**
	 * Moves uploaded files from the temporary directory to the permanent directory
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function moveFromTemp($object, &$values, &$old_values, &$related_records, &$cache)
	{
		foreach ($values as $column => $value) {
			if (!$value instanceof vFile) {
				continue;
			}
			
			// If the file is in a temp dir, move it out
			if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR) !== FALSE) {
				$new_filename = str_replace(self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR, '', $value->getPath());
				$new_filename = vFilesystem::makeUniqueName($new_filename);
				$value->rename($new_filename, FALSE);
			}
		}
	}
	
	
	/**
	 * Turns a filename into an vFile or vImage object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The vFile, vImage or raw value
	 */
	static public function objectify($class, $column, $value)
	{
		if ((!is_string($value) && !is_numeric($value) && !is_object($value)) || !strlen(trim($value))) {
			return $value;
		}
		
		$path = self::$file_upload_columns[$class][$column]->getPath() . $value;
		
		try {
			
			return vFilesystem::createObject($path);
			 
		// If there was some error creating the file, just return the raw value
		} catch (vExpectedException $e) {
			return $value;
		}
	}
	
	
	/**
	 * performs the upload action for file uploads during vActiveRecord::populate()
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return void
	 */
	static public function populate($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			if (vUpload::check($column, FALSE) || vRequest::check('existing-' . $column) || vRequest::check('delete-' . $column)) {
				$method = 'upload' . vGrammar::camelize($column, TRUE);
				$object->$method();
			}
		}
	}
	
	
	/**
	 * Prepares a file for output into HTML by returning filename or the web server path to the file
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return void
	 */
	static public function prepare($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		$column = vGrammar::underscorize($subject);
		
		if (sizeof($parameters) > 1) {
			throw new vProgrammerException(
				'The column specified, %s, does not accept more than one parameter',
				$column
			);
		}
		
		$translate_to_web_path = (empty($parameters[0])) ? FALSE : TRUE;
		$value                 = $values[$column];
		
		if ($value instanceof vFile) {
			$path = ($translate_to_web_path) ? $value->getPath(TRUE) : $value->getName();
		} else {
			$path = NULL;
		}
		
		return vHTML::prepare($path);
	}
	
	
	/**
	 * Takes a directory and creates a temporary directory inside of it - if the temporary folder exists, all files older than 6 hours will be deleted
	 * 
	 * @param  string $folder  The folder to create a temporary directory inside of
	 * @return vDirectory  The temporary directory for the folder specified
	 */
	static private function prepareTempDir($folder)
	{
		// Let's clean out the upload temp dir
		try {
			$temp_dir = new vDirectory($folder->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
		} catch (vValidationException $e) {
			$temp_dir = vDirectory::create($folder->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
		}
		
		$temp_files = $temp_dir->scan();
		foreach ($temp_files as $temp_file) {
			if (filemtime($temp_file->getPath()) < strtotime('-6 hours')) {
				unlink($temp_file->getPath());
			}
		}
		
		return $temp_dir;	
	}
	
	
	/**
	 * Handles re-processing an existing image file 
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return vActiveRecord  The record object, to allow for method chaining
	 */
	static public function process($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$class  = get_class($object);
		
		self::processImage($class, $column, $values[$column]);
		
		return $object;
	}
	
	
	/**
	 * performs image manipulation on an uploaded/set image
	 * 
	 * @internal
	 * 
	 * @param  string $class   The name of the class we are manipulating the image for
	 * @param  string $column  The column the image is assigned to
	 * @param  vFile  $image   The image object to manipulate
	 * @return void
	 */
	static public function processImage($class, $column, $image)
	{
		// If we don't have an image or we haven't set it up to manipulate images, just exit
		if (!$image instanceof vImage || empty(self::$vImage_method_calls[$class][$column])) {
			return;
		}
		
		$save_changes_called = FALSE;
		
		// Manipulate the image
		if (!empty(self::$vImage_method_calls[$class][$column])) {
			foreach (self::$vImage_method_calls[$class][$column] as $method_call) {
				if ($method_call['method'] == 'saveChanges') {
					$save_changes_called = TRUE;
				}
				$callback   = array($image, $method_call['method']);
				$parameters = $method_call['parameters'];
				if (!is_callable($callback)) {
					throw new vProgrammerException(
						'The vImage method specified, %s, is not a valid method',
						$method_call['method'] . '()'
					);
				}
				call_user_func_array($callback, $parameters);
			}
		}
		
		if (!$save_changes_called) {
			call_user_func($image->saveChanges);
		}
	}
	
	
	/**
	 * Adjusts the vActiveRecord::reflect() signatures of columns that have been configured in this class
	 * 
	 * @internal
	 * 
	 * @param  string  $class                 The class to reflect
	 * @param  array   &$signatures           The associative array of `{method name} => {signature}`
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	static public function reflect($class, &$signatures, $include_doc_comments)
	{
		$image_columns = (isset(self::$image_upload_columns[$class])) ? array_keys(self::$image_upload_columns[$class]) : array();
		$file_columns  = (isset(self::$file_upload_columns[$class]))  ? array_keys(self::$file_upload_columns[$class])  : array();
		
		foreach($file_columns as $column) {
			$camelized_column = vGrammar::camelize($column, TRUE);
			
			$noun = 'file';
			if (in_array($column, $image_columns)) {
				$noun = 'image';
			}
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the filename of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";
				$signature .= " * Only the filename will be returned, any directory will be stripped.\n";
				$signature .= " * \n";
				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '()';
			
			$signatures[$encode_method] = $signature;
			
			if (in_array($column, $image_columns)) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Takes the existing image and runs it through the prescribed vImage method calls\n";
					$signature .= " * \n";
					$signature .= " * @return vActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$process_method = 'process' . $camelized_column;
				$signature .= 'public function ' . $process_method . '()';
				
				$signatures[$process_method] = $signature;
			}
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the filename of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				$signature .= " * By default only the filename will be returned and any directory will be stripped.\n";
				$signature .= " * The \$include_web_path parameter changes this behaviour.\n";
				$signature .= " * \n";
				$signature .= " * @param  boolean \$include_web_path  If the full web path to the " . $noun . " should be included\n";
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '($include_web_path=FALSE)';
			
			$signatures[$prepare_method] = $signature;
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Takes a file uploaded through an HTML form for " . $column . " and moves it into the specified directory\n";
				$signature .= " * \n";
				$signature .= " * Any columns that were designated as inheriting from this column will get a copy\n";
				$signature .= " * of the uploaded file.\n";
				$signature .= " * \n";
				if ($noun == 'image') {
					$signature .= " * Any vImage calls that were added to the column will be processed on the uploaded image.\n";
					$signature .= " * \n";
				}
				$signature .= " * @return vFile  The uploaded file\n";
				$signature .= " */\n";
			}
			$upload_method = 'upload' . $camelized_column;
			$signature .= 'public function ' . $upload_method . '()';
			
			$signatures[$upload_method] = $signature;
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Takes a file that exists on the filesystem and copies it into the specified directory for " . $column . "\n";
				$signature .= " * \n";
				if ($noun == 'image') {
					$signature .= " * Any vImage calls that were added to the column will be processed on the copied image.\n";
					$signature .= " * \n";
				}
				$signature .= " * @return vActiveRecord  The record object, to allow for method chaining\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '()';
			
			$signatures[$set_method] = $signature;
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  string \$element  The element to return. Must be one of: 'type', 'not_null', 'default', 'valid_values', 'max_length', 'feature', 'directory'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';
			
			$signatures[$inspect_method] = $signature;
		}
	}
	
	
	/**
	 * Creates a copy of an uploaded file in the temp directory for the newly cloned record
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The cloned vFile object
	 */
	static public function replicate($class, $column, $value)
	{
		if (!$value instanceof vFile) {
			return $value;	
		}
		
		// If the file we are replicating is in the temp dir, the copy can live there too
		if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR) !== FALSE) {
			$value = clone $value;	
		
		// Otherwise, the copy of the file must be placed in the temp dir so it is properly cleaned up
		} else {
			$upload_dir = self::$file_upload_columns[$class][$column];
			
			try {
				$temp_dir = new vDirectory($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			} catch (vValidationException $e) {
				$temp_dir = vDirectory::create($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			}
			
			$value = $value->duplicate($temp_dir);	
		}
		
		return $value;
	}
	
	
	/**
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$column_inheritence   = array();
		self::$vUpload_method_calls = array();
		self::$file_upload_columns  = array();
		self::$vImage_method_calls  = array();
		self::$image_upload_columns = array();
		self::$transaction_level    = 0;
	}
	
	
	/**
	 * Rolls back a transaction, or decreases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function rollback()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return;
		}
		
		self::$transaction_level--;
		
		if (!self::$transaction_level) {
			vFilesystem::rollback();
		}
	}
	
	
	/**
	 * Copies a file from the filesystem to the file upload directory and sets it as the file for the specified column
	 * 
	 * This method will perform the vImage calls defined for the column.
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return vActiveRecord  The record object, to allow for method chaining
	 */
	static public function set($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		$class = get_class($object);
		
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column   = vGrammar::underscorize($subject);
		$doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
		
		if (!array_key_exists(0, $parameters)) {
			throw new vProgrammerException(
				'The method %s requires exactly one parameter',
				$method_name . '()'
			);
		}
		
		$file_path = $parameters[0];
		
		// Handle objects being passed in
		if ($file_path instanceof vFile) {
			$file_path = $file_path->getPath();	
		} elseif (is_object($file_path) && is_callable(array($file_path, '__toString'))) {
			$file_path = $file_path->__toString();
		} elseif (is_object($file_path)) {
			$file_path = (string) $file_path;
		}
		
		if ($file_path !== NULL && $file_path !== '' && $file_path !== FALSE) {
			if (!$file_path || (!file_exists($file_path) && !file_exists($doc_root . $file_path))) {
				throw new vEnvironmentException(
					'The file specified, %s, does not exist. This may indicate a missing enctype="multipart/form-data" attribute in form tag.',
					$file_path
				);
			}
			
			if (!file_exists($file_path) && file_exists($doc_root . $file_path)) {
				$file_path = $doc_root . $file_path;
			}
			
			if (is_dir($file_path)) {
				throw new vProgrammerException(
					'The file specified, %s, is not a file but a directory',
					$file_path
				);
			}
			
			$upload_dir = self::$file_upload_columns[$class][$column];
			
			try {
				$temp_dir = new vDirectory($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			} catch (vValidationException $e) {
				$temp_dir = vDirectory::create($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
			}
			
			$file     = vFilesystem::createObject($file_path);
			$new_file = $file->duplicate($temp_dir);
			
		} else {
			$new_file = NULL;
		}
		
		vActiveRecord::assign($values, $old_values, $column, $new_file);
		
		// perform column inheritance
		if (!empty(self::$column_inheritence[$class][$column])) {
			foreach (self::$column_inheritence[$class][$column] as $other_column) {
				self::set($object, $values, $old_values, $related_records, $cache, 'set' . vGrammar::camelize($other_column, TRUE), array($file));
			}
		}
		
		if ($new_file) {
			self::processImage($class, $column, $new_file);
		}
		
		return $object;
	}
	
	
	/**
	 * Sets up an vUpload object for a specific column
	 * 
	 * @param  string $class   The class to set up for
	 * @param  string $column  The column to set up for
	 * @return vUpload  The configured vUpload object
	 */
	static private function setUpvUpload($class, $column)
	{
		$upload = new vUpload();
		
		// Set up the vUpload class
		if (!empty(self::$vUpload_method_calls[$class][$column])) {
			foreach (self::$vUpload_method_calls[$class][$column] as $method_call) {
				if (!is_callable($upload->{$method_call['method']})) {
					throw new vProgrammerException(
						'The vUpload method specified, %s, is not a valid method',
						$method_call['method'] . '()'
					);
				}
				call_user_func_array($upload->{$method_call['method']}, $method_call['parameters']);
			}
		}
		
		return $upload;
	}
	
	
	/**
	 * Uploads a file
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name       The method that was called
	 * @param  array         $parameters        The parameters passed to the method
	 * @return vFile  The uploaded file
	 */
	static public function upload($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		$class = get_class($object);
		
		list ($action, $subject) = vORM::parseMethod($method_name);
		$column = vGrammar::underscorize($subject);
		
		$existing_temp_file = FALSE;
		
		
		// Try to upload the file putting it in the temp dir incase there is a validation problem with the record
		try {
			$upload_dir = self::$file_upload_columns[$class][$column];
			$temp_dir   = self::prepareTempDir($upload_dir);
			
			if (!vUpload::check($column)) {
				throw new vExpectedException('Please upload a file');	
			}
			
			$uploader = self::setUpvUpload($class, $column);
			$file     = $uploader->move($temp_dir, $column);
			
		// If there was an eror, check to see if we have an existing file
		} catch (vExpectedException $e) {
			
			// If there is an existing file and none was uploaded, substitute the existing file
			$existing_file = vRequest::get('existing-' . $column);
			$delete_file   = vRequest::get('delete-' . $column, 'boolean');
			$no_upload     = $e->getMessage() == self::compose('Please upload a file');
			
			if ($existing_file && $delete_file && $no_upload) {
				$file = NULL;
				
			} elseif ($existing_file) {
				
				$file_path = $upload_dir->getPath() . $existing_file;
				$file      = vFilesystem::createObject($file_path);
				
				$current_file = $values[$column];
				
				// If the existing file is the same as the current file, we can just exit now
				if ($current_file && $current_file instanceof vFile && $file->getPath() == $current_file->getPath()) {
					return;	
				}
				
				$existing_temp_file = TRUE;
				
			} else {
				$file = NULL;
			}
		}
		
		// Assign the file
		vActiveRecord::assign($values, $old_values, $column, $file);
		
		// perform the file upload inheritance
		if (!empty(self::$column_inheritence[$class][$column])) {
			foreach (self::$column_inheritence[$class][$column] as $other_column) {
				
				if ($file) {
					
					// Image columns will only inherit if it is an vImage object
					if (!$file instanceof vImage && isset(self::$image_upload_columns[$class]) && array_key_exists($other_column, self::$image_upload_columns[$class])) {
						continue;
					}
					
					$other_upload_dir = self::$file_upload_columns[$class][$other_column];
					$other_temp_dir   = self::prepareTempDir($other_upload_dir);
					
					if ($existing_temp_file) {
						$other_file = vFilesystem::createObject($other_temp_dir->getPath() . $file->getName());
					} else {
						$other_file = $file->duplicate($other_temp_dir, FALSE);
					}
					
				} else {
					$other_file = $file;
				}
				
				vActiveRecord::assign($values, $old_values, $other_column, $other_file);
				
				if (!$existing_temp_file && $other_file) {
					self::processImage($class, $other_column, $other_file);
				}
			}
		}
		
		// Process the file
		if (!$existing_temp_file && $file) {
			self::processImage($class, $column, $file);
		}
		
		return $file;
	}
	
	
	/**
	 * Validates uploaded files to ensure they match all of the criteria defined
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object                The vActiveRecord instance
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  array         &$cache                The cache array for the record
	 * @param  array         &$validation_messages  The existing validation messages
	 * @return void
	 */
	static public function validate($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
	{
		$class = get_class($object);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			$column_name = vORM::getColumnName($class, $column);
			
			if (isset($validation_messages[$column])) {
				$search_message  = self::compose('%sPlease enter a value', vValidationException::formatField($column_name));
				$replace_message = self::compose('%sPlease upload a file', vValidationException::formatField($column_name));
				$validation_messages[$column] = str_replace($search_message, $replace_message, $validation_messages[$column]);
			}
			
			// Grab the error that occured
			try {
				if (vUpload::check($column)) {
					$uploader = self::setUpvUpload($class, $column);
					$uploader->validate($column);
				}
			} catch (vValidationException $e) {
				if ($e->getMessage() != self::compose('Please upload a file')) {
					$validation_messages[$column] = vValidationException::formatField($column_name) . $e->getMessage();
				}
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vORMFile
	 */
	private function __construct() { }
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
