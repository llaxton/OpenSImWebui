<?php
/**
 * Provides special column functionality for vActiveRecord classes
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
 * @link       http://veluslib.opensource.velusuniverse.com/vORMColumn
 */
class vORMColumn
{
	// The following constants allow for nice looking callbacks to static methods
	const configureEmailColumn  = 'vORMColumn::configureEmailColumn';
	const configureLinkColumn   = 'vORMColumn::configureLinkColumn';
	const configureNumberColumn = 'vORMColumn::configureNumberColumn';
	const configureRandomColumn = 'vORMColumn::configureRandomColumn';
	const encodeNumberColumn    = 'vORMColumn::encodeNumberColumn';
	const inspect               = 'vORMColumn::inspect';
	const generate              = 'vORMColumn::generate';
	const objectifyNumber       = 'vORMColumn::objectifyNumber';
	const prepareLinkColumn     = 'vORMColumn::prepareLinkColumn';
	const prepareNumberColumn   = 'vORMColumn::prepareNumberColumn';
	const reflect               = 'vORMColumn::reflect';
	const reset                 = 'vORMColumn::reset';
	const setEmailColumn        = 'vORMColumn::setEmailColumn';
	const setRandomStrings      = 'vORMColumn::setRandomStrings';
	const validateEmailColumns  = 'vORMColumn::validateEmailColumns';
	const validateLinkColumns   = 'vORMColumn::validateLinkColumns';
	
	
	/**
	 * Columns that should be formatted as email addresses
	 * 
	 * @var array
	 */
	static private $email_columns = array();
	
	/**
	 * Columns that should be formatted as links
	 * 
	 * @var array
	 */
	static private $link_columns = array();
	
	/**
	 * Columns that should be returned as vNumber objects
	 * 
	 * @var array
	 */
	static private $number_columns = array();
	
	/**
	 * Columns that should be formatted as a random string
	 * 
	 * @var array
	 */
	static private $random_columns = array();
	
	
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
	 * Sets a column to be formatted as an email address
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureEmailColumn($class, $column)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as an email column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		vORM::registerActiveRecordMethod(
			$class,
			'set' . vGrammar::camelize($column, TRUE),
			self::setEmailColumn
		);
		
		if (!vORM::checkHookCallback($class, 'post::validate()', self::validateEmailColumns)) {
			vORM::registerHookCallback($class, 'post::validate()', self::validateEmailColumns);
		}
		
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		if (empty(self::$email_columns[$class])) {
			self::$email_columns[$class] = array();
		}
		
		self::$email_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as a link
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as a link
	 * @return void
	 */
	static public function configureLinkColumn($class, $column)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a link column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		vORM::registerActiveRecordMethod(
			$class,
			'prepare' . vGrammar::camelize($column, TRUE),
			self::prepareLinkColumn
		);
		
		if (!vORM::checkHookCallback($class, 'post::validate()', self::validateLinkColumns)) {
			vORM::registerHookCallback($class, 'post::validate()', self::validateLinkColumns);
		}
		
		vORM::registerReflectCallback($class, self::reflect);
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		if (empty(self::$link_columns[$class])) {
			self::$link_columns[$class] = array();
		}
		
		self::$link_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be returned as an vNumber object from calls to `get{ColumnName}()`
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to return as an vNumber object
	 * @return void
	 */
	static public function configureNumberColumn($class, $column)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('integer', 'float');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a number column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		$camelized_column = vGrammar::camelize($column, TRUE);
		
		vORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			self::encodeNumberColumn
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			self::prepareNumberColumn
		);
		
		vORM::registerReflectCallback($class, self::reflect);
		vORM::registerInspectCallback($class, $column, self::inspect);
		vORM::registerObjectifyCallback($class, $column, self::objectifyNumber);
		
		if (empty(self::$number_columns[$class])) {
			self::$number_columns[$class] = array();
		}
		
		self::$number_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a random string column - a random string will be generated when the record is saved
	 * 
	 * @param  mixed   $class   The class name or instance of the class
	 * @param  string  $column  The column to set as a random column
	 * @param  string  $type    The type of random string, must be one of: `'alphanumeric'`, `'alpha'`, `'numeric'`, `'hexadecimal'`
	 * @param  integer $length  The length of the random string
	 * @return void
	 */
	static public function configureRandomColumn($class, $column, $type, $length)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a random string column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		$valid_types = array('alphanumeric', 'alpha', 'numeric', 'hexadecimal');
		if (!in_array($type, $valid_types)) {
			throw new vProgrammerException(
				'The type specified, %1$s, is an invalid type. Must be one of: %2$s.',
				$type,
				join(', ', $valid_types)
			);
		}
		
		if (!is_numeric($length) || $length < 1) {
			throw new vProgrammerException(
				'The length specified, %s, needs to be an integer greater than zero.',
				$length
			);
		}
		
		vORM::registerActiveRecordMethod(
			$class,
			'generate' . vGrammar::camelize($column, TRUE),
			self::generate
		);
		
		if (!vORM::checkHookCallback($class, 'pre::validate()', self::setRandomStrings)) {
			vORM::registerHookCallback($class, 'pre::validate()', self::setRandomStrings);
		}
		
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		if (empty(self::$random_columns[$class])) {
			self::$random_columns[$class] = array();
		}
		
		self::$random_columns[$class][$column] = array('type' => $type, 'length' => (int) $length);
	}
	
	
	/**
	 * Encodes a number column by calling vNumber::__toString()
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @param  string        $method_name      The method that was called
	 * @param  array         $parameters       The parameters passed to the method
	 * @return string  The encoded number
	 */
	static public function encodeNumberColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column      = vGrammar::underscorize($subject);
		$class       = get_class($object);
		$schema      = vORMSchema::retrieve($class);
		$table       = vORM::tablize($class);
		$column_info = $schema->getColumnInfo($table, $column);
		$value       = $values[$column];
		
		if ($value instanceof vNumber) {
			if ($column_info['type'] == 'float') {
				$decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
				$value = $value->trunc($decimal_places)->__toString();
			} else {
				$value = $value->__toString();
			}
		}
		
		return vHTML::prepare($value);
	}
	
	
	/**
	 * Generates a new random value for the column
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
	 * @return string  The newly generated random value
	 */
	static public function generate($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$class  = get_class($object);
		$table  = vORM::tablize($class);
		
		$schema = vORMSchema::retrieve($class);
		$db     = vORMDatabase::retrieve($class, 'read');
		
		$settings = self::$random_columns[$class][$column];
		
		// Check to see if this is a unique column
		$unique_keys      = $schema->getKeys($table, 'unique');
		$is_unique_column = FALSE;
		foreach ($unique_keys as $unique_key) {
			if ($unique_key == array($column)) {
				$is_unique_column = TRUE;
				$sql = "SELECT %r FROM %r WHERE %r = %s";
				do {
					$value = vCryptography::randomString($settings['length'], $settings['type']);
				} while ($db->query($sql, $column, $table, $column, $value)->countReturnedRows());
			}
		}
		
		// If is is not a unique column, just generate a value
		if (!$is_unique_column) {
			$value = vCryptography::randomString($settings['length'], $settings['type']);
		}
		
		vActiveRecord::assign($values, $old_values, $column, $value);
		
		return $value;
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
		if (!empty(self::$email_columns[$class][$column])) {
			$metadata['feature'] = 'email';
		}
		
		if (!empty(self::$link_columns[$class][$column])) {
			$metadata['feature'] = 'link';
		}
		
		if (!empty(self::$random_columns[$class][$column])) {
			$metadata['feature'] = 'random';
		}
		
		if (!empty(self::$number_columns[$class][$column])) {
			$metadata['feature'] = 'number';
		}
	}
	
	
	/**
	 * Turns a numeric value into an vNumber object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The vNumber object or raw value
	 */
	static public function objectifyNumber($class, $column, $value)
	{
		if ((!is_string($value) && !is_numeric($value) && !is_object($value)) || !strlen(trim($value))) {
			return $value;
		}
		
		try {
			return new vNumber($value);
			 
		// If there was some error creating the number object, just return the raw value
		} catch (vExpectedException $e) {
			return $value;
		}
	}
	
	
	/**
	 * Prepares a link column so that the link will work properly in an `a` tag
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
	 * @return string  The formatted link
	 */
	static public function prepareLinkColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$value  = $values[$column];
		
		// Fix domains that don't have the protocol to start
		if (strlen($value) && !preg_match('#^https?://|^/#iD', $value)) {
			$value = 'http://' . $value;
		}
		
		$value = vHTML::prepare($value);
		
		if (isset($parameters[0]) && $parameters[0] === TRUE) {
			return '<a href="' . $value . '">' . $value . '</a>';	
		}
		
		return $value;
	}
	
	
	/**
	 * Prepares a number column by calling vNumber::format()
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
	 * @return string  The formatted link
	 */
	static public function prepareNumberColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column      = vGrammar::underscorize($subject);
		$class       = get_class($object);
		$table       = vORM::tablize($class);
		$schema      = vORMSchema::retrieve($class);
		$column_info = $schema->getColumnInfo($table, $column);
		$value       = $values[$column];
		
		if ($value instanceof vNumber) {
			if ($column_info['type'] == 'float') {
				$decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
				if ($decimal_places !== NULL) {
					$value = $value->trunc($decimal_places)->format();
				} else {
					$value = $value->format();
				}
			} else {
				$value = $value->format();
			}
		}
		
		return vHTML::prepare($value);
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
		
		if (isset(self::$link_columns[$class])) {
			foreach(self::$link_columns[$class] as $column => $enabled) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * This method will ensure all links that start with a domain name are preceeded by http://\n";
					$signature .= " * \n";
					$signature .= " * @param  boolean \$create_link  Will cause link to be automatically converted into an [a] tag\n";
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . vGrammar::camelize($column, TRUE);
				$signature .= 'public function ' . $prepare_method . '($create_link=FALSE)';
				
				$signatures[$prepare_method] = $signature;
			}
		}
		
		if (isset(self::$number_columns[$class])) {
			
			$table  = vORM::tablize($class);
			$schema = vORMSchema::retrieve($class);
			
			foreach(self::$number_columns[$class] as $column => $enabled) {
				$camelized_column = vGrammar::camelize($column, TRUE);
				$type             = $schema->getColumnInfo($table, $column, 'type');
				
				// Get and set methods
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Gets the current value of " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @return vNumber  The current value\n";
					$signature .= " */\n";
				}
				$get_method = 'get' . $camelized_column;
				$signature .= 'public function ' . $get_method . '()';
				
				$signatures[$get_method] = $signature;
				
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Sets the value for " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @param  vNumber|string|integer \$" . $column . "  The new value - don't use floats since they are imprecise\n";
					$signature .= " * @return vActiveRecord  The record object, to allow for method chaining\n";
					$signature .= " */\n";
				}
				$set_method = 'set' . $camelized_column;
				$signature .= 'public function ' . $set_method . '($' . $column . ')';
				
				$signatures[$set_method] = $signature;
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Encodes the value of " . $column . " for output into an HTML vORM\n";
					$signature .= " * \n";
					$signature .= " * If the value is an vNumber object, the ->__toString() method will be called\n";
					$signature .= " * resulting in the value without any thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n"; 		
					}
					$signature .= " * @return string  The HTML vORM-ready value\n";
					$signature .= " */\n";
				}
				$encode_method = 'encode' . $camelized_column;
				$signature .= 'public function ' . $encode_method . '(';
				if ($type == 'float') {
					$signature .= '$decimal_places=NULL';
				}
				$signature .= ')';
				
				$signatures[$encode_method] = $signature;
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * If the value is an vNumber object, the ->format() method will be called\n";
					$signature .= " * resulting in the value including thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n"; 		
					}
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . $camelized_column;
				$signature .= 'public function ' . $prepare_method . '(';
				if ($type == 'float') {
					$signature .= '$decimal_places=NULL';
				}
				$signature .= ')';
				
				$signatures[$prepare_method] = $signature;
			}
		}
		
		if (isset(self::$random_columns[$class])) {
			foreach(self::$random_columns[$class] as $column => $settings) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Generates a new random " . $settings['type'] . " character " . $settings['type'] . " string for " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * If there is a UNIQUE constraint on the column and the value is not unique it will be regenerated until unique\n";
					$signature .= " * \n";
					$signature .= " * @return string  The randomly generated string\n";
					$signature .= " */\n";
				}
				$generate_method = 'generate' . vGrammar::camelize($column, TRUE);
				$signature .= 'public function ' . $generate_method . '()';
				
				$signatures[$generate_method] = $signature;
			}
		}
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
		self::$email_columns  = array();
		self::$link_columns   = array();
		self::$number_columns = array();
		self::$random_columns = array();
	}
	
	
	/**
	 * Sets the value for an email column, trimming the value if it is a valid email
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
	static public function setEmailColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$class  = get_class($object);
		
		if (count($parameters) < 1) {
			throw new vProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);	
		}
		
		$email = $parameters[0];
		if (preg_match('#^\s*[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}\s*$#iD', $email)) {
			$email = trim($email);	
		}

		if ($email === '') {
			$email = NULL;
		}
		
		vActiveRecord::assign($values, $old_values, $column, $email);
		
		return $object;
	}
	
	
	/**
	 * Sets the appropriate column values to a random string if the object is new
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object            The vActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  array         &$cache            The cache array for the record
	 * @return string  The formatted link
	 */
	static public function setRandomStrings($object, &$values, &$old_values, &$related_records, &$cache)
	{
		if ($object->exists()) {
			return;
		}
		
		$class = get_class($object);
		$table = vORM::tablize($class);
		
		foreach (self::$random_columns[$class] as $column => $settings) {
			if (vActiveRecord::hasOld($old_values, $column) && $values[$column]) {
				continue;	
			}
			self::generate(
				$object,
				$values,
				$old_values,
				$related_records,
				$cache,
				'generate' . vGrammar::camelize($column, TRUE),
				array()
			);
		}
	}
	
	
	/**
	 * Validates all email columns
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object                The vActiveRecord instance
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  array         &$cache                The cache array for the record
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateEmailColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$email_columns[$class])) {
			return;
		}
		
		foreach (self::$email_columns[$class] as $column => $enabled) {
			if (!strlen($values[$column])) {
				continue;
			}
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#iD', $values[$column])) {
				$validation_messages[$column] = self::compose(
					'%sPlease enter an email address in the vORM name@example.com',
					vValidationException::formatField(vORM::getColumnName($class, $column))
				);
			}
		}
	}
	
	
	/**
	 * Validates all link columns
	 * 
	 * @internal
	 * 
	 * @param  vActiveRecord $object                The vActiveRecord instance
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  array         &$cache                The cache array for the record
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateLinkColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$link_columns[$class])) {
			return;
		}
		
		foreach (self::$link_columns[$class] as $column => $enabled) {
			if (!is_string($values[$column])) {
				continue;
			}
			
			$ip_regex       = '(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])';
			$hostname_regex = '[a-z]+(?:[a-z0-9\-]*[a-z0-9]\.?|\.)*';
			$domain_regex   = '([a-z]+([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}';
			if (!preg_match('#^(https?://(' . $ip_regex . '|' . $hostname_regex . ')(?=/|$)|' . $domain_regex . '(?=/|$)|/)#i', $values[$column])) {
				$validation_messages[$column] = self::compose(
					'%sPlease enter a link in the vORM http://www.example.com',
					vValidationException::formatField(vORM::getColumnName($class, $column))
				);
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vORMColumn
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