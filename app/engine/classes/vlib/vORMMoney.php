<?php
/**
 * Provides money functionality for vActiveRecord classes
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
 * @link       http://veluslib.opensource.velusuniverse.com/vORMMoney
 */
class vORMMoney
{
	// The following constants allow for nice looking callbacks to static methods
	const configureMoneyColumn       = 'vORMMoney::configureMoneyColumn';
	const encodeMoneyColumn          = 'vORMMoney::encodeMoneyColumn';
	const inspect                    = 'vORMMoney::inspect';
	const makeMoneyObjects           = 'vORMMoney::makeMoneyObjects';
	const objectifyMoney             = 'vORMMoney::objectifyMoney';
	const objectifyMoneyWithCurrency = 'vORMMoney::objectifyMoneyWithCurrency';
	const prepareMoneyColumn         = 'vORMMoney::prepareMoneyColumn';
	const reflect                    = 'vORMMoney::reflect';
	const reset                      = 'vORMMoney::reset';
	const setCurrencyColumn          = 'vORMMoney::setCurrencyColumn';
	const setMoneyColumn             = 'vORMMoney::setMoneyColumn';
	const validateMoneyColumns       = 'vORMMoney::validateMoneyColumns';
	
	
	/**
	 * Columns that store currency information for a money column
	 * 
	 * @var array
	 */
	static private $currency_columns = array();
	
	/**
	 * Columns that should be formatted as money
	 * 
	 * @var array
	 */
	static private $money_columns = array();
	
	
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
	 * Sets a column to be formatted as an vMoney object
	 * 
	 * @param  mixed  $class            The class name or instance of the class to set the column format
	 * @param  string $column           The column to format as an vMoney object
	 * @param  string $currency_column  If specified, this column will store the currency of the vMoney object
	 * @return void
	 */
	static public function configureMoneyColumn($class, $column, $currency_column=NULL)
	{
		$class     = vORM::getClass($class);
		$table     = vORM::tablize($class);
		$schema    = vORMSchema::retrieve($class);
		$data_type = $schema->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('float');
		if (!in_array($data_type, $valid_data_types)) {
			throw new vProgrammerException(
				'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a money column.',
				$column,
				$data_type,
				join(', ', $valid_data_types)
			);
		}
		
		if ($currency_column !== NULL) {
			$currency_column_data_type = $schema->getColumnInfo($table, $currency_column, 'type');
			$valid_currency_column_data_types = array('varchar', 'char', 'text');
			if (!in_array($currency_column_data_type, $valid_currency_column_data_types)) {
				throw new vProgrammerException(
					'The currency column specified, %1$s, is a %2$s column. Must be %3$s to be set as a currency column.',
					$currency_column,
					$currency_column_data_type,
					join(', ', $valid_currency_column_data_types)
				);
			}
		}
		
		$camelized_column = vGrammar::camelize($column, TRUE);
		
		vORM::registerActiveRecordMethod(
			$class,
			'encode' . $camelized_column,
			self::encodeMoneyColumn
		);
		
		vORM::registerActiveRecordMethod(
			$class,
			'prepare' . $camelized_column,
			self::prepareMoneyColumn
		);
		
		if (!vORM::checkHookCallback($class, 'post::validate()', self::validateMoneyColumns)) {
			vORM::registerHookCallback($class, 'post::validate()', self::validateMoneyColumns);
		}
		
		vORM::registerReflectCallback($class, self::reflect);
		vORM::registerInspectCallback($class, $column, self::inspect);
		
		$value = FALSE;
		
		if ($currency_column) {
			$value = $currency_column;	
			
			if (empty(self::$currency_columns[$class])) {
				self::$currency_columns[$class] = array();
			}
			self::$currency_columns[$class][$currency_column] = $column;
			
			if (!vORM::checkHookCallback($class, 'post::loadFromResult()', self::makeMoneyObjects)) {
				vORM::registerHookCallback($class, 'post::loadFromResult()', self::makeMoneyObjects);
			}
			
			if (!vORM::checkHookCallback($class, 'pre::validate()', self::makeMoneyObjects)) {
				vORM::registerHookCallback($class, 'pre::validate()', self::makeMoneyObjects);
			}
			
			vORM::registerActiveRecordMethod(
				$class,
				'set' . $camelized_column,
				self::setMoneyColumn
			);
			
			vORM::registerActiveRecordMethod(
				$class,
				'set' . vGrammar::camelize($currency_column, TRUE),
				self::setCurrencyColumn
			);
		
		} else {
			vORM::registerObjectifyCallback($class, $column, self::objectifyMoney);
		}
		
		if (empty(self::$money_columns[$class])) {
			self::$money_columns[$class] = array();
		}
		
		self::$money_columns[$class][$column] = $value;
	}
	
	
	/**
	 * Encodes a money column by calling vMoney::__toString()
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
	 * @return string  The encoded monetary value
	 */
	static public function encodeMoneyColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		$value  = $values[$column];
		
		if ($value instanceof vMoney) {
			$value = $value->__toString();
		}
		
		return vHTML::prepare($value);
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
		unset($metadata['auto_increment']);
		$metadata['feature'] = 'money';
	}
	
	
	/**
	 * Makes vMoney objects for all money columns in the object that also have a currency column
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
	static public function makeMoneyObjects($object, &$values, &$old_values, &$related_records, &$cache)
	{
		$class = get_class($object);
		
		if (!isset(self::$currency_columns[$class])) {
			return;	
		}
		
		foreach(self::$currency_columns[$class] as $currency_column => $value_column) {
			self::objectifyMoneyWithCurrency($values, $old_values, $value_column, $currency_column);
		}	
	}
	
	
	/**
	 * Turns a monetary value into an vMoney object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The vMoney object or raw value
	 */
	static public function objectifyMoney($class, $column, $value)
	{
		if ((!is_string($value) && !is_numeric($value) && !is_object($value)) || !strlen(trim($value))) {
			return $value;
		}
		
		try {
			return new vMoney($value);
			 
		// If there was some error creating the money object, just return the raw value
		} catch (vExpectedException $e) {
			return $value;
		}
	}
	
	
	/**
	 * Turns a monetary value into an vMoney object with a currency specified by another column
	 * 
	 * @internal
	 * 
	 * @param  array  &$values          The current values
	 * @param  array  &$old_values      The old values
	 * @param  string $value_column     The column holding the value
	 * @param  string $currency_column  The column holding the currency code
	 * @return void
	 */
	static public function objectifyMoneyWithCurrency(&$values, &$old_values, $value_column, $currency_column)
	{
		if ((!is_string($values[$value_column]) && !is_numeric($values[$value_column]) && !is_object($values[$value_column])) || !strlen(trim($values[$value_column]))) {
			return;
		}
			
		try {
			$value = $values[$value_column];
			if ($value instanceof vMoney) {
				$value = $value->__toString();	
			}
			
			$currency = $values[$currency_column];
			if (!$currency && $currency !== '0' && $currency !== 0) {
				$currency = NULL;	
			}
			
			$value = new vMoney($value, $currency);
			 
			if (vActiveRecord::hasOld($old_values, $currency_column) && !vActiveRecord::hasOld($old_values, $value_column)) {
				vActiveRecord::assign($values, $old_values, $value_column, $value);		
			} else {
				$values[$value_column] = $value;
			}
			
			if ($values[$currency_column] === NULL) {
				vActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}
			 
		// If there was some error creating the money object, we just leave all values alone
		} catch (vExpectedException $e) { }	
	}
	
	
	/**
	 * Prepares a money column by calling vMoney::format()
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
	 * @return string  The formatted monetary value
	 */
	static public function prepareMoneyColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
	{
		list ($action, $subject) = vORM::parseMethod($method_name);
		
		$column = vGrammar::underscorize($subject);
		if (empty($values[$column])) {
			return $values[$column];
		}
		$value = $values[$column];
		
		$remove_zero_fraction = FALSE;
		if (count($parameters)) {
			$remove_zero_fraction = $parameters[0];
		}
		
		if ($value instanceof vMoney) {
			$value = $value->format($remove_zero_fraction);
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
		if (!isset(self::$money_columns[$class])) {
			return;	
		}
		
		foreach(self::$money_columns[$class] as $column => $enabled) {
			$camelized_column = vGrammar::camelize($column, TRUE);
			
			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return vMoney  The current value\n";
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
				$signature .= " * @param  vMoney|string|integer \$" . $column . "  The new value - a string or integer will be converted to the default currency (if defined)\n";
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
				$signature .= " * If the value is an vMoney object, the ->__toString() method will be called\n";
				$signature .= " * resulting in the value minus the currency symbol and thousands separators\n";
				$signature .= " * \n";
				$signature .= " * @return string  The HTML vORM-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '()';
			
			$signatures[$encode_method] = $signature;
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				$signature .= " * If the value is an vMoney object, the ->format() method will be called\n";
				$signature .= " * resulting in the value including the currency symbol and thousands separators\n";
				$signature .= " * \n";
				$signature .= " * @param  boolean \$remove_zero_fraction  If a fraction of all zeros should be removed\n";
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '($remove_zero_fraction=FALSE)';
			
			$signatures[$prepare_method] = $signature;
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
		self::$currency_columns = array();
		self::$money_columns    = array();
	}
	
	
	/**
	 * Sets the currency column and then tries to objectify the related money column
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
	static public function setCurrencyColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
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
		
		vActiveRecord::assign($values, $old_values, $column, $parameters[0]);
		
		// See if we can make an vMoney object out of the values
		self::objectifyMoneyWithCurrency(
			$values,
			$old_values,
			self::$currency_columns[$class][$column],
			$column
		);
		
		return $object;
	}
	
	
	/**
	 * Sets the money column and then tries to objectify it with an related currency column
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
	static public function setMoneyColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
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
		
		$value = $parameters[0];
		
		vActiveRecord::assign($values, $old_values, $column, $value);
		
		$currency_column = self::$money_columns[$class][$column];
		
		// See if we can make an vMoney object out of the values
		self::objectifyMoneyWithCurrency($values, $old_values, $column, $currency_column);
		
		if ($currency_column) {
			if ($value instanceof vMoney) {
				vActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}	
		}
		
		return $object;
	}
	
	
	/**
	 * Validates all money columns
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
	static public function validateMoneyColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$money_columns[$class])) {
			return;
		}
		
		foreach (self::$money_columns[$class] as $column => $currency_column) {
			if ($values[$column] instanceof vMoney || $values[$column] === NULL) {
				continue;
			}
			
			// Remove any previous validation warnings
			unset($validation_messages[$column]);
			
			if ($currency_column && !in_array($values[$currency_column], vMoney::getCurrencies())) {
				$validation_messages[$currency_column] = self::compose(
					'%sThe currency specified is invalid',
					vValidationException::formatField(vORM::getColumnName($class, $currency_column))
				);	
				
			} else {
				$validation_messages[$column] = self::compose(
					'%sPlease enter a monetary value',
					vValidationException::formatField(vORM::getColumnName($class, $column))
				);
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vORMMoney
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
