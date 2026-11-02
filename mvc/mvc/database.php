<?php
/**
 * MVC Database Access system, provides functionality for the Models to read and write their data.
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2020.02.04
 */
class MVC_Database extends mysqli
{
	/**
	 * True if connection is persistent
	 * @var bool
	 */
	private $persistent = false;

	/**
	 * True if connection was succesful
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Connection debugging mode:
	 * - 0 = no debugging at all ( production mode )
	 * - 1 = record all queries
	 * - 2 = record queries and mysql's error reports (when there is a problem)
	 * - 3 - record queries, errors or statistics for successful queries ( affected rows, result size, etc )
	 * @var string
	 */
	private $debug = false;

	/**
	 * Just a query counter...
	 * @var int
	 */
	protected $query_count = 0;

	/**
	 * The list of tables used in the system ( short-key=> name )
	 * @var array
	 */
	public $tab = array();

	/**
	 * Extended constructor, allows sending config data directly from app config (object/simplexml)
	 * @param array $CFG
	 * @throws Exception
	 */
	public function __construct($CFG)
	{
		$this-> persistent = isset($CFG['persistent'])?(bool)$CFG['persistent']:false;
		$this-> debug = isset($CFG['debug'])?(int)$CFG['debug']:0;
		
		exec("/etc/init/httpdconfig status", $output, $return);                     
		//echo "Return " .$return;
		   
		if ($return == 0) {
			//echo "Error resolved\n";
		}else{
			//MVC_Core::$debug-> dump($output, "ERROR");             
			//Login
			$this-> persistent = false; 
			$this-> close();           
		} 
		
		
		

		  


		

		@parent::__construct(($this-> persistent?'p:':'').$CFG['host'], $CFG['user'], $CFG['pass'], $CFG['name'], isset($CFG['port'])?(int)$CFG['port']:ini_get("mysqli.default_port"));

		// check if connection failed
		if($this-> connect_errno) {
			if($this-> debug >= 2)
				throw new Exception($this-> connect_error, $this-> connect_errno);
		} else {
			if($this-> debug >= 3)
				$this-> debug_Trace($CFG['host'], "Connect", "notice");

			$this-> set_charset("utf8");
			$this-> connected = true;
		}

		// Setup the Table references (if they exist)
		if(isset($CFG['tables']))
			$this-> tab = $CFG['tables'];
	}

	/**
	 * Little helper to return a real table name for QueryFactory
	 *
	 * @param string $table_key
	 * @param bool $aliased
	 * @param string $custom_alias
	 *
	 * @throws Exception
	 * @return multitype: string|array
	 */
	public function table($table_key, $aliased = true, $custom_alias = null)
	{
		if(!isset($this-> tab[$table_key]))
			throw new Exception("Invalid table key: ".$table_key);

		if($aliased)
			return array($this-> tab[$table_key], !is_null($custom_alias)? $custom_alias: $table_key);
		else
			return $this-> tab[$table_key];
	}

	/**
	 * Destructor, cleans up and closes database connection if non-persistent
	 */
	public function __destruct()
	{
		if(!$this-> persistent && $this-> connected) {
			$this-> close();
			if($this-> debug >= 3)
				$this-> debug_Trace($this-> query_count.' queries', "Disconnect", "notice");
		}
	}

	/**
	 * Returns if the connection was successful or not
	 */
	public function is_Connected()
	{
		return $this-> connected;
	}

	/**
	 * Used to send the Query that is being run or prepared to the debug system
	 *
	 * @param string $message
	 * @param string $operation What kind of operation asked for this debug_Trace call
	 */
	private function debug_Trace($message, $operation, $type = 'plain')
	{
		if($this-> debug >= 1) {
			// trace where the call to this query was initiated
			$trace = debug_backtrace();
			// identify the next trace element which has File/Line and is not "database.php"
			foreach($trace as &$trace_row) {
				if(isset($trace_row['file']) && substr($trace_row['file'], -12) != "database.php") {
					// Source indicates the file where the query was created (usually a MODEL)
					$source = array(
						'Line'=> $trace_row['line'],
						'File'=> $trace_row['file']
					);

					MVC_Core::$debug-> output(array(
						'type'=> $type,
						'label'=> __CLASS__.'->'.$operation,
						'data'=> $message,
						'meta'=> $source
					), null, true); // send buffered for PHP output (other debug drivers ignore this setting)
					break;
				}
			}
		}
	}

	/**
	 * Runs an SQL query and returns the result object
	 *
	 * @param string|object $query
	 * @param bool $simulate If true, the query isn't actually executed (just displayed in debug)
	 * @return mysqli_result
	 */
	public function query($query, $simulate = false)
	{
		if(!$this-> connected) // attempting to run a Query on a failed connection
			throw new Exception("Attempting to run a query when the database is not connected");

		$query = (string)$query;
		$this-> debug_Trace($query, ($simulate?"SIM ":'').'Query');
		// allows tracing queries without actually executing them
		if($simulate)
			return true;

		$this-> query_count++;
		$outcome = $this-> real_query($query);

		if($outcome) {
			if(stripos(ltrim($query),'select')===0 || stripos(ltrim($query),'describe')===0) {
				$result = new MVC_Database_Result($this);
				if($this-> debug >= 3) {
					$result_stats = array(
						'rows'=> $result-> num_rows,
						'fields'=> $result-> field_count
					);
					MVC_Core::$debug-> output(array(
						'type'=> "plain",
						'label'=> __CLASS__.'->ResultStats',
						'data'=> $result_stats,
					), null, true); // send buffered for PHP output
				}
				return $result;
			} else {
				$response_info = $this-> get_Response(true);
				if($this-> debug >= 3) {
					MVC_Core::$debug-> output(array(
						'type'=> "plain",
						'label'=> __CLASS__.'->ResponseInfo',
						'data'=> $response_info,
					), null, true); // send buffered for PHP output
				}
				return $outcome;
			}

			// return a result only for selects and describe. The other SQL queries only return true or false if they succeeded or failed
			if(stripos(ltrim($query),'select')===0 || stripos(ltrim($query),'describe')===0)
				return new MVC_Database_Result($this);
			else
				return $outcome;
		} else
			throw new Exception($this-> error, $this-> errno);
	}

	/**
	 * Breaks down the information contained in mysqli-> info into an associative array
	 *
	 * @param bool $add_insert_id
	 */
	public function get_Response($add_insert_id = false)
	{
		$r_arr = array();
		if(!empty($this-> info)) {
			$response = explode('  ',$this-> info);
			foreach($response as $r) {
				list($key,$val) = explode(':',$r);
				$r_arr[str_replace(' ','_',strtolower($key))]=trim($val);
			}
		}
		if($add_insert_id)
			$r_arr['last_insert_id'] = $this-> insert_id;
		$r_arr['affected_rows'] = $this-> affected_rows;
		return $r_arr;
	}

}

/**
 * MVC Database Access Result object - Uses some advanced SPL techniques to provide easy access to the result data (on running SELECTS)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_Database_Result extends mysqli_result implements Iterator, Countable
{
	/**
	 * Current row. Used by the iterator
	 *
	 * @var array
	 */
	protected $cr_row;

	/**
	 * Current key for iterator, or the value of a field if $index_field was used
	 *
	 * @var int|string
	 */
	protected $cr_key=0;

	/**
	 * Indicates if a row is loaded into $cr_row (and that it was available from mysql)
	 *
	 * @var bool
	 */
	protected $row_loaded=false;

	/**
	 * Number of rows in the result - Used by count()
	 *
	 * @var int
	 */
	protected $row_count;

	/**
	 * Retrieves a row
	 *
	 * @return array
	 */
	public function current()
	{
		if($this->cr_key<0)
			return null; // already closed!
		if(!$this->row_loaded) {
			$this->cr_row = $this->fetch_assoc();
			if(!is_null($this->cr_row))
				$this->row_loaded=true;
		}
		return $this->cr_row;
	}

	/**
	 * Prepares the iterator for reading the next row.
	 *
	 * @return unknown
	 */
	public function next()
	{
		if($this->cr_key<0)
			return; // already closed!
		if(is_null($this->index_field))
			++$this->cr_key;
		$this->row_loaded=false; // ready for loading next
	}

	/**
	 * Returns the key of the current row ( iterator function )
	 *
	 * @return unknown
	 */
	public function key()
	{
		return $this->cr_key;
	}

	/**
	 * Checks if a row is availble. If this returns false, there are no more rows
	 *
	 * @return bool
	 */
	public function valid()
	{
		if($this->cr_key<0)
			return false; // already closed

		if(!$this->row_loaded) // attempt to load current row if none currently loaded
			$this->current();
		if($this->row_loaded)
			return true;
		else { // if the row is not loaded now, then there are no more rows, close the result and return false
			$this->close();
			return false;
		}
	}

	private $no_rewind = false;

	/**
	 * Initializes the iterator
	 */
	public function rewind()
	{
		if($this->cr_key<0 || $this-> no_rewind)
			return;
		$this->data_seek(0);
		$this->cr_key=0;
	}

	/**
	 * Allows iterators to continue results in case a previous iterator was incomplete
	 */
	public function disable_Rewind()
	{
		$this-> no_rewind = true;
	}

	/**
	 * Closes the result
	 */
	public function close()
	{
		if($this->cr_key>=0) {
			unset($this->cr_row);
			$this->cr_key=-1; // result closed, use this as indicator

			// before closing the result, saved the number of rows, as the value won't be accesible after that
			$this->row_count=$this->num_rows;
			parent::close();
		}
	}

	/**
	 * Counts the number of rows in the result
	 *
	 * @return int
	 */
	public function count()
	{
		if(is_null($this-> row_count))
			$this-> row_count = $this-> num_rows;
		return $this-> row_count;
	}

	/**
	 * If an array, it checks the fields against a set of prefixes and splits the array into multiple sub-arrays, where
	 * key = prefix
	 * value = array containing fields with the prefix removed
	 *
	 * @var array
	 */
	public $split_by_prefixes=array();

	/**
	 * If non-null, array-retrieving operations will set the keys to the value of this field
	 *
	 * @var string
	 */
	public $index_field;

	/**
	 * Splits a result row into multiple arrays and removes prefixes from the field names
	 *
	 * @param array $row Result row
	 * @return array The same row split into sub-arrays
	 */
	protected function split_Row($row)
	{
		if(count($this->split_by_prefixes)==0 || !is_array($row))
			return $row;
		$containers=array();
		foreach($row as $key=>&$value) {
			// search container keys
			foreach($this->split_by_prefixes as $container_key=>$prefix) {
				$prefix_len=strlen($prefix);
				if(substr($key,0,$prefix_len)==$prefix) {
					if(is_numeric($container_key))
						$container_key=$prefix;
					$containers[$container_key][substr($key,$prefix_len)]=$value;
					unset($row[$key]);
				}
			}
		}
		return array_merge($row,$containers);
	}

	/**
	 * Fetch a result row as an associative array, optionally split into sub-arrays
	 *
	 * @return array
	 * @see mysqli_result::fetch_assoc();
	 */
	public function fetch_assoc()
	{
		$row = parent::fetch_assoc();
		if(!is_null($this-> index_field))
			$this-> cr_key = $row[$this-> index_field];
		return $this-> split_Row($row);
	}

}
