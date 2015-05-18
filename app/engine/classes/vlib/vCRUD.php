<?php
/**
 * Provides miscellaneous functionality for [http://en.wikipedia.org/wiki/Create,_read,_update_and_delete CRUD-like] pages
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
 * @link       http://veluslib.opensource.velusuniverse.com/vCRUD
 */
class vCRUD
{
	// The following constants allow for nice looking callbacks to static methods
	const getColumnClass           = 'vCRUD::getColumnClass';
	const getRowClass              = 'vCRUD::getRowClass';
	const getSearchValue           = 'vCRUD::getSearchValue';
	const getSortColumn            = 'vCRUD::getSortColumn';
	const getSortDirection         = 'vCRUD::getSortDirection';
	const printSortableColumn      = 'vCRUD::printSortableColumn';
	const redirectWithLoadedValues = 'vCRUD::redirectWithLoadedValues';
	const reset                    = 'vCRUD::reset';
	
	
	/**
	 * Any values that were loaded from the session, used for redirection
	 * 
	 * @var array
	 */
	static private $loaded_values = array();
	
	/**
	 * The current row number for alternating rows
	 * 
	 * @var integer
	 */
	static private $row_number = 1;
	
	/**
	 * The values for a search form
	 * 
	 * @var array
	 */
	static private $search_values = array();
	
	/**
	 * The column to sort by
	 * 
	 * @var string
	 */
	static private $sort_column = NULL;
	
	/**
	 * The direction to sort
	 * 
	 * @var string
	 */
	static private $sort_direction = NULL;
	
	
	/**
	 * Return the string `'sorted'` if `$column` is the column that is currently being sorted by, otherwise returns `''`
	 * 
	 * This method will only be useful if used with the other sort methods 
	 * ::printSortableColumn(), ::getSortColumn() and ::getSortDirection(). 
	 * 
	 * @param  string $column  The column to check
	 * @return string  The CSS class for the column, either `''` or `'sorted'`
	 */
	static public function getColumnClass($column)
	{
		if (self::$sort_column == $column) {
			return 'sorted';
		}
		return '';
	}
	
	
	/**
	 * Returns the previous values for the specified search field
	 * 
	 * @param  string $column  The column to get the value for
	 * @return mixed  The previous value
	 */
	static private function getPreviousSearchValue($column)
	{
		return vSession::get(__CLASS__ . '::' . vURL::get() . '::previous_search::' . $column, NULL);
	}
	
	
	/**
	 * Return the previous sort column, if one exists
	 * 
	 * @return string  The previous sort column
	 */
	static private function getPreviousSortColumn()
	{
		return vSession::get(__CLASS__ . '::' . vURL::get() . '::previous_sort_column', NULL);
	}
	
	
	/**
	 * Return the previous sort direction, if one exists
	 * 
	 * @return string  The previous sort direction
	 */
	static private function getPreviousSortDirection()
	{
		return vSession::get(__CLASS__ . '::' . vURL::get() . '::previous_sort_direction', NULL);
	}
	
	
	/**
	 * Returns a CSS class name for a row
	 * 
	 * Will return `'even'`, `'odd'`, or `'highlighted'` if the two parameters
	 * are equal and not `NULL`. The first call to this method will return
	 * the appropriate class concatenated with `' first'`.
	 * 
	 * @param  mixed $row_value       The value from the row
	 * @param  mixed $affected_value  The value that was just added or updated
	 * @return string  The css class
	 */
	static public function getRowClass($row_value=NULL, $affected_value=NULL)
	{
		if ($row_value !== NULL && $row_value == $affected_value) {
			 self::$row_number++;
			 $class = 'highlighted';
		} else {
			$class = (self::$row_number++ % 2) ? 'odd' : 'even';
		}
		
		$class .= (self::$row_number == 2) ? ' first' : '';
		return $class;
	}
	
	
	/**
	 * Gets the current value of a search field
	 * 
	 * If a value is an empty string and no cast to is specified, the value will
	 * become `NULL`.
	 * 
	 * If a query string of `?reset` is passed, all previous search values will
	 * be erased.
	 * 
	 * @param  string $column   The column that is being pulled back
	 * @param  string $cast_to  The data type to cast to
	 * @param  string $default  The default value
	 * @return mixed  The current value
	 */
	static public function getSearchValue($column, $cast_to=NULL, $default=NULL)
	{
		// Reset values if requested
		if (self::wasResetRequested()) {
			self::setPreviousSearchValue($column, NULL);
			return;
		}
		
		if (self::getPreviousSearchValue($column) && !vRequest::check($column)) {
			self::$search_values[$column] = self::getPreviousSearchValue($column);
			self::$loaded_values[$column] = self::$search_values[$column];
		} else {
			self::$search_values[$column] = vRequest::get($column, $cast_to, $default);
			self::setPreviousSearchValue($column, self::$search_values[$column]);
		}
		return self::$search_values[$column];
	}
	
	
	/**
	 * Gets the current column to sort by, defaults to first one specified
	 * 
	 * @param  string $possible_column  The columns that can be sorted by, defaults to first
	 * @param  string ...
	 * @return string  The column to sort by
	 */
	static public function getSortColumn($possible_column)
	{
		// Reset value if requested
		if (self::wasResetRequested()) {
			self::setPreviousSortColumn(NULL);
			return;
		}
		
		$possible_columns = func_get_args();
		
		if (sizeof($possible_columns) == 1 && is_array($possible_columns[0])) {
			$possible_columns = $possible_columns[0];
		}
		
		if (self::getPreviousSortColumn() && !vRequest::check('sort')) {
			self::$sort_column = self::getPreviousSortColumn();
			self::$loaded_values['sort'] = self::$sort_column;
		} else {
			self::$sort_column = vRequest::getValid('sort', $possible_columns);
			self::setPreviousSortColumn(self::$sort_column);
		}
		return self::$sort_column;
	}
	
	
	/**
	 * Gets the current sort direction
	 * 
	 * @param  string $default_direction  The default direction, `'asc'` or `'desc'`
	 * @return string  The direction, `'asc'` or `'desc'`
	 */
	static public function getSortDirection($default_direction)
	{
		// Reset value if requested
		if (self::wasResetRequested()) {
			self::setPreviousSortDirection(NULL);
			return;
		}
		
		if (self::getPreviousSortDirection() && !vRequest::check('dir')) {
			self::$sort_direction = self::getPreviousSortDirection();
			self::$loaded_values['dir'] = self::$sort_direction;
		} else {
			self::$sort_direction = vRequest::getValid('dir', array($default_direction, ($default_direction == 'asc') ? 'desc' : 'asc'));
			self::setPreviousSortDirection(self::$sort_direction);
		}
		return self::$sort_direction;
	}
	
	
	/**
	 * Prints a sortable column header `a` tag
	 * 
	 * The a tag will include the CSS class `'sortable_column'` and the
	 * direction being sorted, `'asc'` or `'desc'`.
	 * 
	 * {{{
	 * #!php
	 * vCRUD::printSortableColumn('name', 'Name');
	 * }}}
	 * 
	 * would create the following HTML based on the page context
	 * 
	 * {{{
	 * #!html
	 * <!-- If name is the current sort column in the asc direction, the output would be -->
	 * <a href="?sort=name&dir=desc" class="sorted_column asc">Name</a>
	 * 
	 * <!-- If name is not the current sort column, the output would be -->
	 * <a href="?sort-name&dir=asc" class="sorted_column">Name</a>
	 * }}}
	 * 
	 * @param  string $column       The column to create the sortable header for
	 * @param  string $column_name  This will override the humanized version of the column
	 * @return void
	 */
	static public function printSortableColumn($column, $column_name=NULL)
	{
		if ($column_name === NULL) {
			$column_name = vGrammar::humanize($column);
		}
		
		if (self::$sort_column == $column) {
			$sort      = $column;
			$direction = (self::$sort_direction == 'asc') ? 'desc' : 'asc';
		} else {
			$sort      = $column;
			$direction = 'asc';
		}
		
		$columns = array_merge(array('sort', 'dir'), array_keys(self::$search_values));
		$values  = array_merge(array($sort, $direction), array_values(self::$search_values));
		
		$url         = vHTML::encode(vURL::get() . vURL::replaceInQueryString($columns, $values));
		$css_class   = (self::$sort_column == $column) ? ' ' . self::$sort_direction : '';
		$column_name = vHTML::prepare($column_name);
		
		echo '<a href="' . $url . '" class="sortable_column' . $css_class . '">' . $column_name . '</a>';
	}
		
	
	/**
	 * Checks to see if any values (search or sort) were loaded from the session, and if so redirects the user to the current URL with those values added
	 * 
	 * @return void
	 */
	static public function redirectWithLoadedValues()
	{
		// If values were reset, redirect to the plain URL
		if (self::wasResetRequested()) {
			vURL::redirect(vURL::get() . vURL::removeFromQueryString('reset'));
		}
		
		$query_string = vURL::replaceInQueryString(array_keys(self::$loaded_values), array_values(self::$loaded_values));
		$url = vURL::get() . $query_string;
		
		if ($url != vURL::getWithQueryString() && $url != vURL::getWithQueryString() . '?') {
			vURL::redirect($url);
		}
	}
	
	
	/**
	 * Resets the configuration and data of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		vSession::clear(__CLASS__ . '::');
		
		self::$loaded_values  = array();
		self::$row_number     = 1;
		self::$search_values  = array();
		self::$sort_column    = NULL;
		self::$sort_direction = NULL;
	}
	
	
	/**
	 * Sets a value for a search field
	 * 
	 * @param  string $column  The column to save the value for
	 * @param  mixed  $value   The value to save
	 * @return void
	 */
	static private function setPreviousSearchValue($column, $value)
	{
		vSession::set(__CLASS__ . '::' . vURL::get() . '::previous_search::' . $column, $value);
	}
	
	
	/**
	 * Set the sort column to be used on returning pages
	 * 
	 * @param  string $sort_column  The sort column to save
	 * @return void
	 */
	static private function setPreviousSortColumn($sort_column)
	{
		vSession::set(__CLASS__ . '::' . vURL::get() . '::previous_sort_column', $sort_column);
	}
	
	
	/**
	 * Set the sort direction to be used on returning pages
	 * 
	 * @param  string $sort_direction  The sort direction to save
	 * @return void
	 */
	static private function setPreviousSortDirection($sort_direction)
	{
		vSession::set(__CLASS__ . '::' . vURL::get() . '::previous_sort_direction', $sort_direction);
	}
	
	
	/**
	 * Indicates if a reset was requested for search values
	 * 
	 * @return boolean  If a reset was requested
	 */
	static private function wasResetRequested()
	{
		$tail = substr(vURL::getWithQueryString(), -6);
		return $tail == '?reset' || $tail == '&reset';
	}
	
	
	/**
	 * Prevent instantiation
	 * 
	 * @return vCRUD
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