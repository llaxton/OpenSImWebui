<?php
/**
 * Provides additional date/time functionality for vActiveRecord classes
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
 * @link       http://veluslib.opensource.velusuniverse.com/vORMDate
 */
class vORMDate
{
	// The following constants allow for nice looking callbacks to static methods
	const configureDateCreatedColumn        = 'vORMDate::configureDateCreatedColumn';
	const configureDateUpdatedColumn        = 'vORMDate::configureDateUpdatedColumn';
	const configureTimezoneColumn           = 'vORMDate::configureTimezoneColumn';
	const inspect                           = 'vORMDate::inspect';
	const makeTimestampObjects              = 'vORMDate::makeTimestampObjects';
	const objectifyTimestampWithoutTimezone = 'vORMDate::objectifyTimestampWithoutTimezone';
	const reset                             = 'vORMDate::reset';
	const setDateCreated                    = 'vORMDate::setDateCreated';
	const setDateUpdated                    = 'vORMDate::setDateUpdated';
	const setTimestampColumn                = 'vORMDate::setTimestampColumn';
	const setTimezoneColumn                 = 'vORMDate::setTimezoneColumn';
	const validateTimezoneColumns           = 'vORMDate::validateTimezoneColumns';
	
	
	/**
	 * Columns that should be filled with the date created for new objects
	 * 
	 * @var array
	 */
	static private $date_created_columns = array();
	
	/**
	 * Columns that should be filled with the date updated
	 * 
	 * @var array
	 */
	static private $date_updated_columns = array();
	
	/**
	 * Columns that store timezone information for timestamp columns
	 * 
	 * @var array
	 */
	static private $timezone_columns = array();
	
	/**
	 * Timestamp columns that have a corresponding timezone column
	 * 
	 * @var array
	 */
	static private $timestamp_columns = array();
	
	
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
	 * Sets a column to be a date created column
	 * 
	 * When a new record is stored in the database, date created columns will
	 * be filled with the timestamp of the store operation.
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date created column
	 * @return void
	 */
	static public function configureDateCreatedColumn($class, $column)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a date created column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		vORM::registerHookCallback($class, 'pre::validate()', self::setDateCreated);
		
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		if (empty(self::$date_created_columns[$class])) {
			self::$date_created_columns[$class] = array();
		}
		
		self::$date_created_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a date updated column
	 * 
	 * Whenever a record is stored in the database, a date updated column will
	 * be set to the timestamp of the operation.
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date updated column
	 * @return void
	 */
	static public function configureDateUpdatedColumn($class, $column)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a date updated column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		vORM::registerHookCallback($class, 'pre::validate()', self::setDateUpdated);
		
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		if (empty(self::$date_updated_columns[$class])) {
			self::$date_updated_columns[$class] = array();
		}
		
		self::$date_updated_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a timestamp column to store the timezone in another column
	 * 
	 * Since not all databases support timezone information in timestamp 
	 * columns, this method allows storing the timezone in another columns. 
	 * When the timestamp and timezone are retrieved from the database, they
	 * will be automatically combined together into an vTimestamp object.
	 * 
	 * @param  mixed  $class             The class name or instance of the class to set the column format
	 * @param  string $timestamp_column  The timestamp column to store the timezone for
	 * @param  string $timezone_column   The column to store the timezone in
	 * @return void
	 */
	static public function configureTimezoneColumn($class, $timestamp_column, $timezone_column)
	{
		$class               = vORM::getClass($class);
		$table               = vORM::tablize($class);
		$schema              = vORMSchema::retrieve($class);
		$timestamp_data_type = $schema->getColumnInfo($table, $timestamp_column, 'type');
		
		if ($timestamp_data_type != 'timestamp') {
			throw new vProgrammerException(
				'The timestamp column specified, %1$s, is a %2$s column. Must be a %3$s to have a related timezone column.',
				$timestamp_column,
				$data_type,
				'timestamp'
			);
		}
		
		$timezone_column_data_type = $schema->getColumnInfo($table, $timezone_column, 'type');
		$valid_timezone_column_data_types = array('varchar', 'char', 'text');
		if (!in_array($timezone_column_data_type, $valid_timezone_column_data_types)) {
			throw new vProgrammerException(
				'The timezone column specified, %1$s, is a %2$s column. Must be %3$s to be set as a timezone column.',
				$timezone_column,
				$timezone_column_data_type,
				join(', ', $valid_timezone_column_data_types)
			);
		}
		
		if (!vORM::checkHookCallback($class, 'post::validate()', self::validateTimezoneColumns)) {
			vORM::registerHookCallback($class, 'post::validate()', self::validateTimezoneColumns);
		}
		
		if (!vORM::checkHookCallback($class, 'post::loadFromResult()', self::makeTimestampObjects)) {
			vORM::registerHookCallback($class, 'post::loadFromResult()', self::makeTimestampObjects);
		}
		
		if (!vORM::checkHookCallback($class, 'pre::validate()', self::makeTimestampObjects)) {
			vORM::registerHookCallback($class, 'pre::validate()', self::makeTimestampObjects);
		}
		
		vORM::registerInspectCallback($class, $timezone_column, self::inspect);
		
		vORM::registerActiveRecordMethod(
			$class,
			'set' . vGrammar::camelize($timestamp_column, TRUE),
			self::setTimestampColumn
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'set' . vGrammar::camelize($timezone_column, TRUE),
			self::setTimezoneColumn
		);
		
		if (empty(self::$timestamp_columns[$class])) {
			self::$timestamp_columns[$class] = array();
		}
		self::$timestamp_columns[$class][$timestamp_column] = $timezone_column;
		
		if (empty(self::$timezone_columns[$class])) {
			self::$timezone_columns[$class] = array();
		}
		self::$timezone_columns[$class][$timezone_column] = $timestamp_column;
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
		if (!empty(self::$date_created_columns[$class][$column])) {
			$metadata['feature'] = 'date created';
		}
		
		if (!empty(self::$date_updated_columns[$class][$column])) {
			$metadata['feature'] = 'date updated';
		}
		
		if (!empty(self::$timezone_columns[$class][$column])) {
			$metadata['feature'] = 'timezone';
		}
	}
	
	
	/**
	 * Creates vTimestamp objects for every timestamp/timezone combination in the object
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
	static public function makeTimestampObjects($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		if (!isset(self::$timezone_columns[$class])) {
			return;	
		}
		
		foreach(self::$timezone_columns[$class] as $timezone_column => $timestamp_column) {
			self::objectifyTimestampWithTimezone($values, $old_values, $timestamp_column, $timezone_column);
		}	
	}
	
	
	/**
	 * Turns a timestamp value into an vTimestamp object with a timezone specified by another column
	 * 
	 * @internal
	 * 
	 * @param  array  &$values           The current values
	 * @param  array  &$old_values       The old values
	 * @param  string $timestamp_column  The column holding the timestamp
	 * @param  string $timezone_column   The column holding the timezone
	 * @return void
	 */
	static public function objectifyTimestampWithTimezone(&$values, &$old_values, $timestamp_column, $timezone_column)
	{
		if ((!is_string($values[$timestamp_column]) &&
				!is_object($values[$timestamp_column]) &&
				!is_numeric($values[$timestamp_column])) ||
			  !strlen(trim($values[$timestamp_column]))) {
			return;
		}
			
		try {
			$value = $values[$timestamp_column];
			if ($value instanceof vTimestamp) {
				$value = $value->__toString();	
			}
			
			$timezone = $values[$timezone_column];
			if (!$timezone && $timezone !== '0' && $timezone !== 0) {
				$timezone = NULL;	
			}
			
			$value = new vTimestamp($value, $timezone);
			 
			if (vActiveRecord::hasOld($old_values, $timezone_column) && !vActiveRecord::hasOld($old_values, $timestamp_column)) {
				vActiveRecord::assign($values, $old_values, $timestamp_column, $value);		
			} else {
				$values[$timestamp_column] = $value;
			}
			
			if ($values[$timezone_column] === NULL) {
				vActiveRecord::assign($values, $old_values, $timezone_column, $value->format('e'));
			}
			 
		// If there was some error creating the timestamp object, we just leave all values alone
		} catch (vExpectedException $e) { }	
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
		self::$date_created_columns = array();
		self::$date_updated_columns = array();
		self::$timezone_columns     = array();
		self::$timestamp_columns    = array();
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was created (for new records)
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
	static public function setDateCreated($object, &$values, &$old_values, &$related_records, &$cache)
	{
		if ($object->exists()) {
			return;
		}
		
		$class = get_class($object);
		
		foreach (self::$date_created_columns[$class] as $column => $enabled) {
			vActiveRecord::assign(
				$values,
				$old_values,
				$column,
				vORM::objectify($class, $column, date('Y-m-d H:i:s'))
			);
			// If the column has a corresponding timezone column, set that too
			if (isset(self::$timestamp_columns[$class][$column])) {
				vActiveRecord::assign(
					$values,
					$old_values,
					self::$timestamp_columns[$class][$column],
					vTimestamp::getDefaultTimezone()
				);	
			}
		}
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was updated
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
	static public function setDateUpdated($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		foreach (self::$date_updated_columns[$class] as $column => $enabled) {
			vActiveRecord::assign(
				$values,
				$old_values,
				$column,
				vORM::objectify($class, $column, date('Y-m-d H:i:s'))
			);
			// If the column has a corresponding timezone column, set that too
			if (isset(self::$timestamp_columns[$class][$column])) {
				vActiveRecord::assign(
					$values,
					$old_values,
					self::$timestamp_columns[$class][$column],
					vTimestamp::getDefaultTimezone()
				);	
			}
		}
	}
	
	
	/**
	 * Sets the timestamp column and then tries to objectify it with an related timezone column
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
	static public function setTimestampColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$class  = get_class($object);
		
		if (!isset($parameters[0])) {
			throw new vProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);	
		}
		
		$value = $parameters[0];
		
		vActiveRecord::assign($values, $old_values, $column, $value);
		
		$timezone_column = self::$timestamp_columns[$class][$column];
		
		// See if we can make an vTimestamp object out of the values
		self::objectifyTimestampWithTimezone($values, $old_values, $column, $timezone_column);
		
		if ($value instanceof vTimestamp) {
			vActiveRecord::assign($values, $old_values, $timezone_column, $value->format('e'));
		}
		
		return $object;
	}
	
	
	/**
	 * Sets the timezone column and then tries to objectify the related timestamp column
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
	static public function setTimezoneColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$class  = get_class($object);
		
		if (!isset($parameters[0])) {
			throw new vProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);	
		}
		
		vActiveRecord::assign($values, $old_values, $column, $parameters[0]);
		
		// See if we can make an vTimestamp object out of the values
		self::objectifyTimestampWithTimezone(
			$values,
			$old_values,
			self::$timezone_columns[$class][$column],
			$column
		);
		
		return $object;
	}
	
	
	/**
	 * Validates all timestamp/timezone columns
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
	static public function validateTimezoneColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$timezone_columns[$class])) {
			return;
		}
		
		foreach (self::$timezone_columns[$class] as $timezone_column => $timestamp_column) {
			if ($values[$timestamp_column] instanceof vTimestamp || $values[$timestamp_column] === NULL) {
				continue;
			}
			if (!vTimestamp::isValidTimezone($values[$timezone_column])) {
				$validation_messages[$timezone_column] = self::compose(
					'%sThe timezone specified is invalid',
					vValidationException::formatField(vORM::getColumnName($class, $timezone_column))
				);	
				
			} else {
				$validation_messages[$timestamp_column] = self::compose(
					'%sPlease enter a date/time',
					vValidationException::formatField(vORM::getColumnName($class, $timestamp_column))
				);
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vORMDate
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