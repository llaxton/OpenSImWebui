<?php
/**
 * Represents a date as a value object
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
 * @link       http://veluslib.opensource.velusuniverse.com/vDate
 */
class vDate
{
	/**
	 * Composes text using vText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
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
	 * A timestamp of the date
	 * 
	 * @var integer
	 */
	protected $date;
	
	
	/**
	 * Creates the date to represent, no timezone is allowed since dates don't have timezones
	 * 
	 * @throws vValidationException  When `$date` is not a valid date
	 * 
	 * @param  vDate|object|string|integer $date  The date to represent, `NULL` is interpreted as today
	 * @return vDate
	 */
	public function __construct($date=NULL)
	{
		if ($date === NULL) {
			$timestamp = time();
		} elseif (is_numeric($date) && preg_match('#^-?\d+$#D', $date)) {
			$timestamp = (int) $date;
		} elseif (is_string($date) && in_array(strtoupper($date), array('CURRENT_TIMESTAMP', 'CURRENT_DATE'))) {
			$timestamp = time();
		} else {
			if (is_object($date) && is_callable(array($date, '__toString'))) {
				$date = $date->__toString();	
			} elseif (is_numeric($date) || is_object($date)) {
				$date = (string) $date;	
			}
			
			$date = vTimestamp::callUnformatCallback($date);
			
			$timestamp = strtotime(vTimestamp::fixISOWeek($date));
		}
		
		$is_51    = vCore::checkVersion('5.1');
		$is_valid = ($is_51 && $timestamp !== FALSE) || (!$is_51 && $timestamp !== -1);
		
		if (!$is_valid) {
			throw new vValidationException(
				'The date specified, %s, does not appear to be a valid date',
				$date
			);
		}
		
		$this->date = strtotime(date('Y-m-d 00:00:00', $timestamp));
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
	 * Returns this date in `Y-m-d` format
	 * 
	 * @return string  The `Y-m-d` format of this date
	 */
	public function __toString()
	{
		return date('Y-m-d', $this->date);
	}
	
	
	/**
	 * Changes the date by the adjustment specified, only adjustments of a day or more will be made
	 * 
	 * @throws vValidationException  When `$adjustment` is not a relative date measurement
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return vDate  The adjusted date
	 */
	public function adjust($adjustment)
	{
		$timestamp = strtotime($adjustment, $this->date);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			throw new vValidationException(
				'The adjustment specified, %s, does not appear to be a valid relative date measurement',
				$adjustment
			);
		}
		
		return new vDate($timestamp);
	}
	
	
	/**
	 * If this date is equal to the date passed
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to compare with, `NULL` is interpreted as today
	 * @return boolean  If this date is equal to the one passed
	 */
	public function eq($other_date=NULL)
	{
		$other_date = new vDate($other_date);
		return $this->date == $other_date->date;
	}
	
	
	/**
	 * formats the date
	 * 
	 * @throws vValidationException  When a non-date formatting character is included in `$format`
	 * 
	 * @param  string $format  The [http://php.net/date date()] function compatible formatting string, or a format name from vTimestamp::defineformat()
	 * @return string  The formatted date
	 */
	public function format($format)
	{
		$format = vTimestamp::translateformat($format);
		
		$restricted_formats = 'aABcegGhHiIOPrsTuUZ';
		if (preg_match('#(?!\\\\).[' . $restricted_formats . ']#', $format)) {
			throw new vProgrammerException(
				'The formatting string, %1$s, contains one of the following non-date formatting characters: %2$s',
				$format,
				join(', ', str_split($restricted_formats))
			);
		}
		
		return vTimestamp::callformatCallback(date($format, $this->date));
	}
	
	
	/**
	 * Returns the approximate difference in time, discarding any unit of measure but the least specific.
	 * 
	 * The output will read like:
	 * 
	 *  - "This date is `{return value}` the provided one" when a date it passed
	 *  - "This date is `{return value}`" when no date is passed and comparing with today
	 * 
	 * Examples of output for a date passed might be:
	 * 
	 *  - `'2 days after'`
	 *  - `'1 year before'`
	 *  - `'same day'`
	 * 
	 * Examples of output for no date passed might be:
	 * 
	 *  - `'2 days from now'`
	 *  - `'1 year ago'`
	 *  - `'today'`
	 * 
	 * You would never get the following output since it includes more than one unit of time measurement:
	 * 
	 *  - `'3 weeks and 1 day'`
	 *  - `'1 year and 2 months'`
	 * 
	 * Values that are close to the next largest unit of measure will be rounded up:
	 * 
	 *  - `6 days` would be represented as `1 week`, however `5 days` would not
	 *  - `29 days` would be represented as `1 month`, but `21 days` would be shown as `3 weeks`
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to create the difference with, `NULL` is interpreted as today
	 * @param  boolean                     $simple      When `TRUE`, the returned value will only include the difference in the two dates, but not `from now`, `ago`, `after` or `before`
	 * @param  boolean                     |$simple
	 * @return string  The fuzzy difference in time between the this date and the one provided
	 */
	public function getFuzzyDifference($other_date=NULL, $simple=FALSE)
	{
		if (is_bool($other_date)) {
			$simple     = $other_date;
			$other_date = NULL;
		}
		
		$relative_to_now = FALSE;
		if ($other_date === NULL) {
			$relative_to_now = TRUE;
		}
		$other_date = new vDate($other_date);
		
		$diff = $this->date - $other_date->date;
		
		if (abs($diff) < 86400) {
			if ($relative_to_now) {
				return self::compose('today');
			}
			return self::compose('same day');
		}
		
		static $break_points = array();
		if (!$break_points) {
			$break_points = array(
				/* 5 days      */
				432000     => array(86400,    self::compose('day'),   self::compose('days')),
				/* 3 weeks     */
				1814400    => array(604800,   self::compose('week'),  self::compose('weeks')),
				/* 9 months    */
				23328000   => array(2592000,  self::compose('month'), self::compose('months')),
				/* largest int */
				2147483647 => array(31536000, self::compose('year'),  self::compose('years'))
			);
		}
		
		foreach ($break_points as $break_point => $unit_info) {
			if (abs($diff) > $break_point) { continue; }
			
			$unit_diff = round(abs($diff)/$unit_info[0]);
			$units     = vGrammar::inflectOnQuantity($unit_diff, $unit_info[1], $unit_info[2]);
			break;
		}
		
		if ($simple) {
			return self::compose('%1$s %2$s', $unit_diff, $units);
		}
		
		if ($relative_to_now) {
			if ($diff > 0) {
				return self::compose('%1$s %2$s from now', $unit_diff, $units);
			}
			
			return self::compose('%1$s %2$s ago', $unit_diff, $units);
		}
		
		if ($diff > 0) {
			return self::compose('%1$s %2$s after', $unit_diff, $units);
		}
		
		return self::compose('%1$s %2$s before', $unit_diff, $units);
	}
	
	
	/**
	 * If this date is greater than the date passed
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to compare with, `NULL` is interpreted as today
	 * @return boolean  If this date is greater than the one passed
	 */
	public function gt($other_date=NULL)
	{
		$other_date = new vDate($other_date);
		return $this->date > $other_date->date;
	}
	
	
	/**
	 * If this date is greater than or equal to the date passed
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to compare with, `NULL` is interpreted as today
	 * @return boolean  If this date is greater than or equal to the one passed
	 */
	public function gte($other_date=NULL)
	{
		$other_date = new vDate($other_date);
		return $this->date >= $other_date->date;
	}
	
	
	/**
	 * If this date is less than the date passed
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to compare with, `NULL` is interpreted as today
	 * @return boolean  If this date is less than the one passed
	 */
	public function lt($other_date=NULL)
	{
		$other_date = new vDate($other_date);
		return $this->date < $other_date->date;
	}
	
	
	/**
	 * If this date is less than or equal to the date passed
	 * 
	 * @param  vDate|object|string|integer $other_date  The date to compare with, `NULL` is interpreted as today
	 * @return boolean  If this date is less than or equal to the one passed
	 */
	public function lte($other_date=NULL)
	{
		$other_date = new vDate($other_date);
		return $this->date <= $other_date->date;
	}
	
	
	/**
	 * Modifies the current date, creating a new vDate object
	 * 
	 * The purpose of this method is to allow for easy creation of a date
	 * based on this date. Below are some examples of formats to
	 * modify the current date:
	 * 
	 *  - `'Y-m-01'` to change the date to the first of the month
	 *  - `'Y-m-t'` to change the date to the last of the month
	 *  - `'Y-\W5-N'` to change the date to the 5th week of the year
	 * 
	 * @param  string $format  The current date will be formatted with this string, and the output used to create a new object
	 * @return vDate  The new date
	 */
	public function modify($format)
	{
	   return new vDate($this->format($format));
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