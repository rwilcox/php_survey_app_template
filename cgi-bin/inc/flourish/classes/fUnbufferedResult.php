<?php
/**
 * Representation of an unbuffered result from a query against the fDatabase class
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fUnbufferedResult
 * 
 * @version    1.0.0b7
 * @changes    1.0.0b7  Fixed a bug with decoding MSSQL national column when using an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b6  Added the method ::unescape(), changed ::tossIfNoRows() to return the object for chaining [wb, 2009-08-12]
 * @changes    1.0.0b5  Added the method ::asObjects() to allow for returning objects instead of associative arrays [wb, 2009-06-23]
 * @changes    1.0.0b4  Fixed a bug with not properly converting SQL Server text to UTF-8 [wb, 2009-06-18]
 * @changes    1.0.0b3  Added support for Oracle, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2  Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b   The initial implementation [wb, 2008-05-07]
 */
class fUnbufferedResult implements Iterator
{
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * The character set to transcode from for MSSQL queries
	 * 
	 * @var string
	 */
	private $character_set = NULL;
	
	/**
	 * The current row of the result set
	 * 
	 * @var array
	 */
	private $current_row = NULL;
	
	/**
	 * The database object the result was created from
	 * 
	 * @var fDatabase
	 */
	private $database = NULL;
	
	/**
	 * If rows should be converted to objects
	 * 
	 * @var boolean
	 */
	private $output_objects = FALSE;
	
	/**
	 * The position of the pointer in the result set
	 * 
	 * @var integer
	 */
	private $pointer;
	
	/**
	 * The result resource
	 * 
	 * @var resource
	 */
	private $result = NULL;
	
	/**
	 * The SQL query
	 * 
	 * @var string
	 */
	private $sql = '';
	
	/**
	 * Holds the data types for each column to allow for on-the-fly unescaping
	 * 
	 * @var array
	 */
	private $unescape_map = array();
	
	/**
	 * The SQL from before translation
	 * 
	 * @var string
	 */
	private $untranslated_sql = NULL;
	
	
	/**
	 * Configures the result set
	 * 
	 * @internal
	 * 
	 * @param  fDatabase $database       The database object this result was created from
	 * @param  string    $character_set  MSSQL only: the character set to transcode from since MSSQL doesn't do UTF-8
	 * @return fUnbufferedResult
	 */
	public function __construct($database, $character_set=NULL)
	{
		if (!$database instanceof fDatabase) {
			throw new fProgrammerException(
				'The database object provided does not appear to be a descendant of fDatabase'
			);
		}
		
		$this->database      = $database;
		$this->character_set = $character_set;
	}
	
	
	/**
	 * Frees up the result object
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		if (!is_resource($this->result) && !is_object($this->result)) {
			return;
		}
		
		switch ($this->database->getExtension()) {
			case 'mssql':
				mssql_free_result($this->result);
				break;
				
			case 'mysql':
				mysql_free_result($this->result);
				break;
				
			case 'mysqli':
				mysqli_free_result($this->result);
				break;
				
			case 'oci8':
				oci_free_statement($this->result);
				break;
				
			case 'odbc':
				odbc_free_result($this->result);
				break;
				
			case 'pgsql':
				pg_free_result($this->result);
				break;
				
			case 'sqlite':
				unset($this->result);
				break;
				
			case 'sqlsrv':
				sqlsrv_free_stmt($this->result);
				break;
				
			case 'pdo':
				$this->result->closeCursor();
				break;
		}
		
		$this->result = NULL;
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
	 * Gets the next row from the result and assigns it to the current row
	 * 
	 * @return void
	 */
	private function advanceCurrentRow()
	{
		switch ($this->database->getExtension()) {
			case 'mssql':
				// For some reason the mssql extension will return an empty row even
				// when now rows were returned, so we have to explicitly check for this
				if ($this->pointer == 0 && !mssql_num_rows($this->result)) {
					$row = FALSE;	
				
				} else {
					$row = mssql_fetch_assoc($this->result);
					if (empty($row)) {
						mssql_fetch_batch($this->result);
						$row = mssql_fetch_assoc($this->result);
					}
					if (!empty($row)) {
						$row = $this->fixDblibMSSQLDriver($row);
					}
				}
				break;
					
			case 'mysql':
				$row = mysql_fetch_assoc($this->result);
				break;
				
			case 'mysqli':
				$row = mysqli_fetch_assoc($this->result);
				break;
				
			case 'oci8':
				$row = oci_fetch_assoc($this->result);
				break;
				
			case 'odbc':
				$row = odbc_fetch_array($this->result);
				break;
				
			case 'pgsql':
				$row = pg_fetch_assoc($this->result);
				break;
				
			case 'sqlite':
				$row = sqlite_fetch_array($this->result, SQLITE_ASSOC);
				break;
				
			case 'sqlsrv':
				$row = sqlsrv_fetch_array($this->result, SQLSRV_FETCH_ASSOC);
				break;
				
			case 'pdo':
				$row = $this->result->fetch(PDO::FETCH_ASSOC);
				break;
		}
		
		// Fix uppercase column names to lowercase
		if ($row && $this->database->getType() == 'oracle') {
			$new_row = array();
			foreach ($row as $column => $value) {
				$new_row[strtolower($column)] = $value;
			}	
			$row = $new_row;
		}
		
		// This is an unfortunate fix that required for databases that don't support limit
		// clauses with an offset. It prevents unrequested columns from being returned.
		if ($row && ($this->database->getType() == 'mssql' || $this->database->getType() == 'oracle')) {
			if ($this->untranslated_sql !== NULL && isset($row['flourish__row__num'])) {
				unset($row['flourish__row__num']);
			}	
		}
		
		// This decodes the data coming out of MSSQL into UTF-8
		if ($row && $this->database->getType() == 'mssql') {
			if ($this->character_set) {
				foreach ($row as $key => $value) {
					if (!is_string($value) || strpos($key, '__flourish_mssqln_') === 0 || isset($row['fmssqln__' . $key]) || preg_match('#[\x0-\x8\xB\xC\xE-\x1F]#', $value)) {
						continue;
					} 		
					$row[$key] = iconv($this->character_set, 'UTF-8', $value);
				}
			}
			$row = $this->decodeMSSQLNationalColumns($row);
		} 
		
		if ($this->unescape_map) {
			foreach ($this->unescape_map as $column => $type) {
				if (!isset($row[$column])) { continue; }
				$row[$column] = $this->database->unescape($type, $row[$column]);
			}	
		}
		
		$this->current_row = $row;
	}
	
	
	/**
	 * Sets the object to return rows as objects instead of associative arrays (the default)
	 * 
	 * @return fUnbufferedResult  The result object, to allow for method chaining
	 */
	public function asObjects()
	{
		$this->output_objects = TRUE;
		return $this;
	}
	
	
	/**
	 * Returns the current row in the result set (required by iterator interface)
	 * 
	 * @throws fNoRowsException       When the query did not return any rows
	 * @throws fNoRemainingException  When there are no rows left in the result
	 * @internal
	 * 
	 * @return array|stdClass  The current row
	 */
	public function current()
	{
		$this->validateState();
		
		// Primes the result set
		if ($this->pointer === NULL) {
			$this->pointer = 0;
			$this->advanceCurrentRow();
		}
		
		if(!$this->current_row && $this->pointer == 0) {
			throw new fNoRowsException('The query did not return any rows');
			
		} elseif (!$this->current_row) {
			throw new fNoRemainingException('There are no remaining rows');
		}
		
		if ($this->output_objects) {
			return (object) $this->current_row;	
		}
		return $this->current_row;
	}
	
	
	/**
	 * Decodes national (unicode) character data coming out of MSSQL into UTF-8
	 * 
	 * @param  array $row  The row from the database
	 * @return array  The fixed row
	 */
	private function decodeMSSQLNationalColumns($row)
	{
		if (strpos($this->sql, 'fmssqln__') === FALSE) {
			return $row;
		}
		
		$columns = array_keys($row);
		
		foreach ($columns as $column) {
			if (substr($column, 0, 9) != 'fmssqln__') {
				continue;
			}	
			
			$real_column = substr($column, 9);
			
			$row[$real_column] = iconv('ucs-2le', 'utf-8', $this->database->unescape('blob', $row[$column]));
			unset($row[$column]);
		}
		
		return $row;
	}
	
	
	/**
	 * Returns the row next row in the result set (where the pointer is currently assigned to)
	 * 
	 * @throws fNoRowsException       When the query did not return any rows
	 * @throws fNoRemainingException  When there are no rows left in the result
	 * 
	 * @return array|stdClass  The next row in the result
	 */
	public function fetchRow()
	{
		$this->validateState();
		
		$row = $this->current();
		$this->next();
		return $row;
	}
	
	
	/**
	 * Warns the user about bugs in the DBLib driver for MSSQL, fixes some bugs
	 * 
	 * @param  array $row  The row from the database
	 * @return array  The fixed row
	 */
	private function fixDblibMSSQLDriver($row)
	{
		static $using_dblib = NULL;
		
		if ($using_dblib === NULL) {
		
			// If it is not a windows box we are definitely not using dblib
			if (!fCore::checkOS('windows')) {
				$using_dblib = FALSE;
			
			// Check this windows box for dblib
			} else {
				ob_start();
				phpinfo(INFO_MODULES);
				$module_info = ob_get_contents();
				ob_end_clean();
				
				$using_dblib = !preg_match('#FreeTDS#ims', $module_info, $match);
			}
		}
		
		if (!$using_dblib) {
			return $row;
		}
		
		foreach ($row as $key => $value) {
			if ($value == ' ') {
				$row[$key] = '';
				trigger_error(
					self::compose(
						'A single space was detected coming out of the database and was converted into an empty string - see %s for more information',
						'http://bugs.php.net/bug.php?id=26315'
					),
					E_USER_NOTICE
				);
			}
			if (strlen($key) == 30) {
				trigger_error(
					self::compose(
						'A column name exactly 30 characters in length was detected coming out of the database - this column name may be truncated, see %s for more information.',
						'http://bugs.php.net/bug.php?id=23990'
					),
					E_USER_NOTICE
				);
			}
			if (strlen($value) == 256) {
				trigger_error(
					self::compose(
						'A value exactly 255 characters in length was detected coming out of the database - this value may be truncated, see %s for more information.',
						'http://bugs.php.net/bug.php?id=37757'
					),
					E_USER_NOTICE
				);
			}
		}
		
		return $row;
	}
	
	
	/**
	 * Returns the result
	 * 
	 * @internal
	 * 
	 * @return mixed  The result of the query
	 */
	public function getResult()
	{
		$this->validateState();
		
		return $this->result;
	}
	
	
	/**
	 * Returns the SQL used in the query
	 * 
	 * @return string  The SQL used in the query
	 */
	public function getSQL()
	{
		return $this->sql;
	}
	
	
	/**
	 * Returns the SQL as it was before translation
	 * 
	 * @return string  The SQL from before translation
	 */
	public function getUntranslatedSQL()
	{
		return $this->untranslated_sql;
	}
	
	
	/**
	 * Returns the current row number (required by iterator interface)
	 * 
	 * @throws fNoRowsException       When the query did not return any rows
	 * @throws fNoRemainingException  When there are no rows left in the result
	 * @internal
	 * 
	 * @return integer  The current row number
	 */
	public function key()
	{
		$this->validateState();
		
		if ($this->pointer === NULL) {
			$this->current();
		}
		
		return $this->pointer;
	}
	
	
	/**
	 * Advances to the next row in the result (required by iterator interface)
	 * 
	 * @throws fNoRowsException       When the query did not return any rows
	 * @throws fNoRemainingException  When there are no rows left in the result
	 * @internal
	 * 
	 * @return void
	 */
	public function next()
	{
		$this->validateState();
		
		if ($this->pointer === NULL) {
			$this->current();
		}
		
		$this->advanceCurrentRow();
		$this->pointer++;
	}
	
	
	/**
	 * Rewinds the query (required by iterator interface)
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function rewind()
	{
		$this->validateState();
		
		if (!empty($this->pointer)) {
			throw new fProgrammerException(
				'Unbuffered database results can not be iterated through multiple times'
			);
		}
	}
	
	
	/**
	 * Sets the result from the query
	 * 
	 * @internal
	 * 
	 * @param  mixed $result  The result from the query
	 * @return void
	 */
	public function setResult($result)
	{
		$this->result = $result;
	}
	
	
	/**
	 * Sets the SQL used in the query
	 * 
	 * @internal
	 * 
	 * @param  string $sql  The SQL used in the query
	 * @return void
	 */
	public function setSQL($sql)
	{
		$this->sql = $sql;
	}
	
	
	/**
	 * Sets the SQL from before translation
	 * 
	 * @internal
	 * 
	 * @param  string $untranslated_sql  The SQL from before translation
	 * @return void
	 */
	public function setUntranslatedSQL($untranslated_sql)
	{
		$this->untranslated_sql = $untranslated_sql;
	}
	
	
	/**
	 * Throws an fNoResultException if the query did not return any rows
	 * 
	 * @throws fNoRowsException  When the query did not return any rows
	 * 
	 * @param  string $message  The message to use for the exception if there are no rows in this result set
	 * @return fUnbufferedResult  The result object, to allow for method chaining
	 */
	public function tossIfNoRows($message=NULL)
	{
		try {
			$this->current();
		} catch (fNoRowsException $e) {
			if ($message !== NULL) {
				$e->setMessage($message);
			}	
			throw $e;
		}
		
		return $this;
	}
	
	
	/**
	 * Sets the result object to unescape all values as they are retrieved from the object
	 * 
	 * The data types should be from the list of types supported by
	 * fDatabase::unescape().
	 * 
	 * @param  array $column_data_type_map  An associative array with column names as the keys and the data types as the values
	 * @return fUnbufferedResult  The result object, to allow for method chaining
	 */
	public function unescape($column_data_type_map)
	{
		 if (!is_array($column_data_type_map)) {
			throw new fProgrammerException(
				'The column to data type map specified, %s, does not appear to be an array',
				$column_data_type_map
			);
		 }
		 
		 $this->unescape_map = $column_data_type_map;
		 
		 return $this;
	}
	
	
	/**
	 * Returns if the query has any rows left
	 * 
	 * @return boolean  If the iterator is still valid
	 */
	public function valid()
	{
		$this->validateState();
		
		if ($this->pointer === NULL) {
			$this->advanceCurrentRow();
			$this->pointer = 0;
		}
		
		return !empty($this->current_row);
	}
	
	
	/**
	 * Throws an exception if this object has been deconstructed already
	 * 
	 * @return void
	 */
	private function validateState()
	{
		if ($this->result === NULL) {
			throw new fProgrammerException('This unbuffered result has been fully fetched, or replaced by a newer result');	
		}	
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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