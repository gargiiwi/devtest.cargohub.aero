<?php

/**
 * DonReY Framework :: Query Factory MySQL
 * - Modified for CargoITS's Mini-framework
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @link http://www.donrey.net/Documentation/Structure/Modules/Queryfactory/mysql.html
 * @license [GPL v3] http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.0.dev
 * @package DonReY
 * @subpackage Queryfactory
 */
//error_reporting(E_ALL & ~E_DEPRECATED);
//ini_set('display_errors', 1);

class QueryFactory
{
	/**
	 * Primary Statement Keyword. This is the first thing appearing in a statement
	 * @var string
	 */
	protected $psk;

	/**
	 * Configuration passed with the primary keyword (optional)
	 * @var mixed
	 */
	protected $config;

	/**
	 * Database Connection object, used (if it exists) by format_Value()
	 * @var mysqli
	 */
	private static $connection;



	// Add these properties at the class level
	//PK Comment Start
	// protected $qf_flags;
	// protected $qf_name;
	// protected $qf_i18n;
	// protected $qf_tabledef;
	// protected $qf_tables;
	// protected $qf_where;
	// protected $qf_having;
	// protected $qf_order;
	// protected $qf_group;
	// protected $qf_limit;
	// protected $qf_values;
	//PK Comment End

	/**
	 * Sets the connection that will be used by all QF Objects to escape values
	 *
	 * @param mysqli $connection
	 */
	public static function set_Connection(mysqli &$connection)
	{
		self::$connection = &$connection;
	}

	public static function unset_Connection()
	{
		self::$connection = null;
	}

	/**
	 * Query that resulted from the execution of compile()
	 *
	 * @var string
	 */
	protected $query;

	/**
	 * Query Factory Instancer - Creates a new QueryFactory object ( qfo )
	 *
	 * @param string $fname
	 * @param mixed $params
	 * @return mysql_query_factory
	 * @static QueryFactory
	 */
	static public function __callStatic($fname, $args): ?self
	{
		if (in_array($fname, array('create', 'alter', 'drop', 'select', 'insert', 'update', 'delete', 'replace')))
			return new self($fname, count($args) ? $args[0] : null);
		else
			return null; // when function not recognised
	}

	//----- QF Primary methods -----//

	/**
	 * Prepares a QueryFactory Object
	 *
	 * @param string $primary_keyword (SELECT, INSERT, etc.)
	 * @param mixed $config (Optiona) Keyword specific configuration
	 */
	public function __construct($primary_keyword, $config = null)
	{
		$this->psk = ucfirst(strtolower($primary_keyword));
		$this->config = $config;
	}

	/**
	 * Resets the query factory (however keeping primary keyword)
	 *
	 * @param array $what_to_reset (Optional) Indicates which part of the statement to reset (instead of everything)
	 * @return QueryFactory
	 */
	public function reset($what_to_reset = null)
	{

		$this->query = '';
		if (is_array($what_to_reset)) {
			foreach ($what_to_reset as $what_to)
				$this->{'qf_' . $what_to} = null;
		} else {
			// iterate the object and resets everything that starts with qf_
			foreach ($this as $name => $value)
				if (substr($name, 0, 3) == 'qf_') {
					$value = null;
					$this->$name = $value;
				}
		}
		$this->query = null;
		return $this;
	}

	/**
	 * Allows to grab QF data using getter functions "get_{what 2 get?}" ( ex: get_Values() )
	 *
	 * @param string $fname
	 * @param array $args
	 * @return QueryFactory
	 */
	public function __call($fname, $args): self
	{
		if (substr($fname, 0, 4) == 'get_') {
			$get_what = 'qf_' . strtolower(substr($fname, 4));
			if (isset($this->$get_what))
				return $this->$get_what;
		}
		// when not implemented, just return itself
		return $this;
	}

	/**
	 * General compile - Calls for existing compile parts (implemented in the engine)
	 *
	 * @return QueryFactory
	 */
	public function compile($force = false)
	{
		
		if (!$force && $this->query !== null && $this->query !== '')
			return $this; // do not attempt to recompile if a query string already exists. User force or reset() the query to recompile with the same QF object

		$this->query = strtoupper($this->psk) . PHP_EOL;
		
		// runs the compile function specific to the primary keyword (might be captured in a subclass by __call)
		// if that specific compile does not exist, an exception is thrown
		if (!(method_exists($this, 'compile_' . $this->psk)))
			throw new Exception("Unable to compile {$this->psk} statement");
		$method = 'compile_' . $this->psk;
		if (method_exists($this, $method)) {
			$this->$method();
		}
		
		return $this;
	}


	/**
	 * When QF object is used as a string, the query is auto-compiled and returned.
	 * If compile() encounters an error ( for example incomplete query ) it will throw an exception
	 *
	 * @return string
	 */
	public function __toString()
	{
		$this->compile();
		// TODO - Compile debugging
		return $this->query;
	}

	//----- Query Build Helpers -----//

	/**
	 * Converts a string into an array, to be used by the Query Factory, by the following rules:
	 * - "id" -> array( id )
	 * - "id, id, ..." -> array ( id, id, ... )
	 * - "id:alias" -> array ( alias=>id )
	 * - "id:alias, id:alias, ..." -> array ( alias=>id, alias=>id, ... )
	 *
	 * @param string $str Original comma separated values
	 * @return array
	 */
	public static function idents_Str2Array($str)
	{
		$ids_arr = explode(',', $str);
		$ids = array();
		foreach ($ids_arr as $id_entry) {
			if (strpos($id_entry, ':') !== false) {
				list($id, $alias) = explode(':', trim($id_entry));
				$ids[$alias] = $id;
			} else
				$ids[] = trim($id_entry);
		}
		return $ids;
	}

	/**
	 * Converts or creates aliases for use with field list in table() call,
	 * by adding a prefix to the existing alias or creating them from field names
	 * - a string key is simply prefixed {prefix}{old_key}
	 * - a numeric index is converted into {prefix}(string){value}
	 * Since this is only used for arrays containing field names, ALL values must be strings !
	 *
	 * @param string|array $field_list Original field list
	 * @param string $prefix It is added before the field name
	 * @return array Array ready to be passed to the ->table() method as the field list parameter
	 */
	public static function &autoalias_Add_Prefix($fields, $prefix = '')
	{
           
		if ($fields === false)
			$fields =[];
		elseif (is_string($fields))
			$fields = self::idents_Str2Array($fields);
		elseif (!is_array($fields))
			return null; // error in field list, not string, not array...
 
		$keyed = [];
		foreach ($fields as $key => $field_name) {
			if (is_numeric($key))
				$keyed[$prefix . $field_name] = $field_name;
			elseif (is_string($field_name))
				$keyed[$prefix . $key] = $field_name;
			// else error, field ignored
		}
		return $keyed;
	}

	/**
	 * Wraps an identifier with `'s (MySQL speciality)
	 * @param string $string
	 */
	protected function _q($string)
	{
		// TODO - Test expressions and others as field names
		if (preg_match("~\\(.*\\)~", $string) || $string == 'null' || is_numeric($string) || $string === 0) { // expression with paranthesis or number, return as is (no quoting)
			return $string;
		}
		if (is_null($string))
			return "null";

		if (strpos($string, '.') !== false) {
			list($p1, $p2) = explode('.', $string);
			return '`' . $p1 . '`.`' . $p2 . '`';
		} else {
			return '`' . $string . '`';
		}
	}

	//===== Valid anywhere =====//

	/**
	 * Flags valid for any type of statement
	 * @var array
	 */
	protected $qf_flags = array();

	/**
	 * Specifies list of flags. Either as an array or as multiple string parameters
	 * @param array $flags
	 * @return QueryFactory
	 */
	public function flags($flags)
	{
		if (is_array($flags))
			$this->qf_flags = $flags;
		else
			$this->qf_flags = func_get_args();
		return $this;
	}

	//===== Data Definition =====//

	protected $qf_name;

	/**
	 * Used for Create / Drop / Alter ( Data Definition statement )
	 * @return QueryFactory
	 */
	public function &name($entity_name)
	{
		$this->qf_name = $entity_name;
		return $this;
	}

	protected $qf_i18n;

	/**
	 * Internationalization support
	 * @param string $character_set
	 * @param string $collation
	 * @return QueryFactory
	 */
	public function &i18n($character_set, $collation)
	{
		$this->qf_i18n['character_set'] = $character_set;
		$this->qf_i18n['collation'] = $collation;
		return $this;
	}

	/**
	 * Table definition for Create Table or result from parsing Show Create Table
	 * @var object
	 */
	protected $qf_tabledef;

	/**
	 * Column definition. Data is analysed and goes into $qf_tabledef
	 *
	 * @param string $col_name Column name, must be a valid identifier
	 * @param mixed $create_def String or Array representing the type of column, or another format (created definition)
	 * @param mixed $options String, Array or Object representing various options for the column. Defaults to the option "not null" ( null: false )
	 * @param string $comment Column comment -> COMMENT 'string' (can also be passted using 'options')
	 * @return QueryFactory
	 */
	public function &column($col_name, $create_def = 'int', $options = array('null' => false), $comment = null)
	{
		/*
 Some data types as array (type, length, extra)
 array('INT') "int"
 array('BIT',8) "bit(8)"
 array('TINYINT',3,'uz') "tinyint(3) unsigned zerofill" // unsigned, zerofill ( null, 'u', 'uz', 'z' )
 array('FLOAT',array(3,2),'u') "float(3,2) unsigned" // array(length,decimals) ; unsigned
 array('VARCHAR',20,array('utf8','utf8_unicode_ci')) // character set, collation
 array('TEXT BINARY',null,array('utf8','utf8_unicode_ci'));
 array('ENUM',array('val1','val2','val3')); // enumeration of ENUM, SET passed as array
*/
		$d = array();
		if (is_string($create_def)) {
			// passed as string, parse it
			preg_match('~([^(]+)(?:\((.*)\))?(\s+unsigned)?(\s+zerofill)?(\s+binary)?(\s+character set \'\w+\')?(\s+collate \'\w+\')?~i', $create_def, $dt);

			// TODO: Check for bad string?

			$d[0] = strtoupper($dt[1]);
			// 1 = datatype, 2 = length/enum|set values, 3,4,5,6 = extra keywords (if not empty)
			if ($dt[1] == 'enum' || $dt[1] == 'set') {
				// break $dt[3] into an array of values (commas inside quotes are ignored)
				preg_match_all("~(?:^|,)(\"(?:[^\"]+|\"\")*\"|[^,]*)~", $dt[2], $esval);

				$d[1] = array();
				foreach ($esval[1] as $val) {
					if ($val[0] == '"')
						$d[1][] = (string)trim($val, '"');
					else {
						if (strpos($val, '.') !== false)
							$d[1][] = (float)$val;
						else
							$d[1][] = (int)$val;
					}
				}
			} elseif (in_array($dt[1], array('real', 'double', 'float', 'decimal', 'numeric'))) {
				// length + decimals
				$d[1] = explode(',', $dt[2]);
				foreach ($d[1] as &$_v)
					$_v = (int)$_v;
			} elseif (!empty($dt[2]))
				$d[1] = (int)$dt[2];
			// extra keywords
			$d[2] = null;
			for ($p = 3; $p <= 7; $p++) {
				if (isset($dt[$p])) {
					switch (substr($dt[$p], 0, 3)) {
						case ' un': // unsigned
							$d[2] .= 'u';
							break;
						case ' ze': // zerofill
							$d[2] .= 'z';
							break;
						case ' bi': // binary
							$d[2][] = 'b';
							break;
						case ' ch': // character set
							$d[2][] = substr($dt[$p], 15);
							break;
						case ' co': // collation
							$d[2][] = substr($dt[$p], 9);
							break;
					}
				}
			}
		} elseif (is_array($create_def)) {
			// assume create_definition is passed correctly
			$d = $create_def;
		} else
			// datadef is not string or array, throw exc
			throw new Exception("Problem with column definition");

		// init when necessary
		if (empty($this->qf_tabledef))
			$this->qf_tabledef = new stdClass();
		if (empty($this->qf_tabledef->columns))
			$this->qf_tabledef->columns = new stdClass();

		$this->qf_tabledef->columns->{$col_name}[0] = $d; // col_name column_definition
		$this->qf_tabledef->columns->{$col_name}[1] = $options;
		if (!empty($comment))
			$this->qf_tabledef->columns->{$col_name}[1]['comment'] = $comment;
		return $this;
	}

	/**
	 * Index definition. Data is analysed and goes into $qf_tabledef
	 *
	 * @param string $index_type Type of index to be added: PRIMARY | UNIQUE | INDEX | FULLTEXT
	 * @param array[string]|string $index_columns Columns which will be part of this index ( usually a single one, but can be multiple ) - Column names are passed "as is" to the query
	 *   Each entry might contain a size: name(5) and optionally the direction of indexing (ASC|DESC) : family(4) DESC
	 * @param string $index_name Name of the index ( can be autogenerated from column names, or specified manually )
	 * @param string $comment Comment for this index
	 * @return QueryFactory
	 */
	public function &index($index_type, $index_columns, $index_name = null, $comment = null)
	{
		// generate name out of column names
		if (strtolower($index_type) == 'primary') {
			// check if a primary has alreay been defined, if so, throw
			if (isset($this->qf_tabledef->indexes->PRIMARY))
				throw new Exception("PRIMARY index already defined");
			// this will override any passed $index_name, when type is "primary" ( MySQL rule )
			$index_name = 'PRIMARY';
		} else {
			if (empty($index_name))
				// one column
				if (is_string($index_columns)) {
					preg_match("~(\\w+)(?:\\((.*)\\))?(\\s+asc|\\s+desc)?~i", strtolower($index_columns), $cnx); // 1 = column name, 2 = size, 3 = asc|desc (optional)
					$index_name = $cnx[1];
					if (isset($cnx[2]))
						$index_name .= $cnx[2];
					if (isset($cnx[3]))
						$index_name .= trim($cnx[3]);
				} else {
					// multiple columns
					$index_name = array();
					foreach ($index_columns as $column) {
						preg_match("~(\\w+)(?:\\((.*)\\))?(\\s+asc|\\s+desc)?~i", strtolower($column), $cnx);
						$in_frag = $cnx[1];
						if (isset($cnx[2]))
							$in_frag .= $cnx[2];
						if (isset($cnx[3]))
							$in_frag .= trim($cnx[3]);
						$index_name[] = $in_frag;
					}
					$index_name = implode('_', $index_name);
				}
		}
		// columns always array
		if (is_string($index_columns))
			$index_columns = array($index_columns);

		// init when necessary
		if (empty($this->qf_tabledef))
			$this->qf_tabledef = new stdClass();
		if (empty($this->qf_tabledef->indexes))
			$this->qf_tabledef->indexes = new stdClass();

		$this->qf_tabledef->indexes->{$index_name} = array(
			'type' => $index_type,
			'columns' => $index_columns,
			'comment' => $comment
		);
		return $this;
	}

	//===== Compilers for Data Definition Statements =====//

	/**
	 * Verifies that the config for Create/Alter/Drop is a string and is one of the accepted keywords
	 * @param unknown_type $keyword
	 */
	private function check_Config_CAD($keyword)
	{
		if (!is_string($this->config) || !in_array(strtolower($this->config), array('database', 'schema', 'table', 'index', 'view', 'function', 'procedure', 'trigger')))
			throw new Exception("Bad config parameter for {$keyword}");
	}

	/**
	 * Compiler for Create keyword ( Create Database, Create Table ... etc )
	 */
	protected function compile_Create()
	{
		$this->check_Config_CAD('Create');
		if (!$this->qf_name)
			throw new Exception("Missing {$this->config} name!");

		// stuff specific to Create table (skips the default)
		if (strtolower($this->config) == 'table') {
			$this->compile_Create_Table();
			return;
		}
		// ??
		$this->query .= strtoupper($this->config) . ' ' . $this->_q($this->qf_name) . PHP_EOL;

		// stuff specific to Create database|schema
		if (in_array(strtolower($this->config), array('database', 'schema'))) {
			if ($this->qf_i18n)
				$this->query .= 'DEFAULT CHARACTER SET ' . $this->qf_i18n['character_set'] . ' COLLATE ' . $this->qf_i18n['collation'];
		}
	}

	/**
	 * Compiles the huge array of options for Create Table
	 */
	private function compile_Create_Table()
	{
		if (!isset($this->qf_tabledef->columns))
			throw new Exception("No columns defined, unable to compile Create Table");

		// [TEMPORARY]
		if (in_array('temporary', $this->qf_flags))
			$this->query .= 'TEMPORARY ';
		$this->query .= strtoupper($this->config) . ' ' . $this->_q($this->qf_name);

		// [IF NOT EXISTS]
		if (in_array('if not exists', $this->qf_flags))
			$this->query .= ' IF NOT EXISTS';

		// Recognise some engines:
		if (in_array('MyISAM', $this->qf_flags))
			$this->qf_flags['engine'] = 'MyISAM';
		if (in_array('MEMORY', $this->qf_flags))
			$this->qf_flags['engine'] = 'MEMORY';

		$this->query .= ' (' . PHP_EOL;

		$comma = '';
		// // create_definition for COLUMNS (col_name column_definition):
		foreach ($this->qf_tabledef->columns as $colname => $coldef) {
			// Data def [0]
			$dd = $coldef[0];
			$this->query .= $comma . "\t" . $this->_q($colname); // column name (identifier)
			// data type [0.0] ( BIT, TINYINT, SMALLINT ... http://dev.mysql.com/doc/refman/5.5/en/create-table.html )
			$this->query .= ' ' . $dd[0];
			// length|values [0.1]
			if (isset($dd[1])) {
				$this->query .= '(';
				if (is_array($dd[1])) { // list of stuff
					$comma2 = '';
					foreach ($dd[1] as $vfrag) {
						$this->query .= $comma2;
						if (is_string($vfrag))
							$this->query .= '"' . $vfrag . '"';
						else
							$this->query .= $vfrag;
						$comma2 = ',';
					}
				} else
					$this->query .= $dd[1];
				$this->query .= ')';
			}
			// transforms [0.2] ( [UNSIGNED] [ZEROFILL] [BINARY] [CHARACTER SET charset_name] [COLLATE collation_name] )
			if (isset($dd[2])) {
				if (is_string($dd[2])) { // unsigned, zerofill, binary
					if (strpos($dd[2], 'u') !== false)
						$this->query .= ' UNSIGNED';
					if (strpos($dd[2], 'z') !== false)
						$this->query .= ' ZEROFILL';
					if (strpos($dd[2], 'b') !== false)
						$this->query .= ' BINARY';
				} elseif (is_array($dd[2])) { // character set, collation
					if (strpos($dd[2][0], '_')) // only collation
						$this->query .= ' COLLATE ' . $dd[2][0];
					elseif (count($dd[2]) == 2) // both
						$this->query .= ' CHARACTER SET ' . $dd[2][0] . ' COLLATE ' . $dd[2][1];
					else // just character set
						$this->query .= ' CHARACTER SET ' . $dd[2][0];
				}
			}
			// 	// Options [1] - While there are other options, only these are supported by current QF version:

			// 	// [NOT NULL | NULL]
			if (isset($coldef[1]['null'])) {
				$this->query .= $coldef[1]['null'] == true ? ' NULL' : ' NOT NULL';
			} else
				$this->query .= " NOT NULL"; // If not specified, column is always not-null.
			// [DEFAULT default_value]
			if (isset($coldef[1]['default'])) {
				$default_value = $coldef[1]['default'];
				if (is_string($default_value))
					$default_value = "'{$default_value}'";
				$this->query .= ' DEFAULT ' . $default_value;
			}
			// [AUTO_INCREMENT]
			if (isset($coldef[1]['auto increment']) || isset($coldef[1]['ai'])) { // ai = shortcut
				$this->query .= ' AUTO_INCREMENT';
			}
			// [COMMENT]
			if (isset($coldef[1]['comment'])) {
				$this->query .= " COMMENT '{$coldef[1]['comment']}'";
			}

			$comma = ',' . PHP_EOL;
		}

		// // create_definition for INDEXES
		if (isset($this->qf_tabledef->indexes))
			foreach ($this->qf_tabledef->indexes as $indexname => $indexdef) {
				switch ($indexdef['type']) {
					case 'primary':
						$this->query .= $comma . "\tPRIMARY KEY";
						break;
					case 'index':
					case 'key':
						$this->query .= $comma . "\tINDEX " . $this->_q($indexname);
						break;
					case 'unique':
						$this->query .= $comma . "\tUNIQUE " . $this->_q($indexname);
						break;
					case 'fulltext':
						$this->query .= $comma . "\tFULLTEXT " . $this->_q($indexname);
						break;
					default:
						return;
						// continue;
				}
				
				foreach ($indexdef['columns'] as &$colname_raw) {
					preg_match("~(\\w+)(\\(.*\\))?(\\s+asc|\\s+desc)?~i", $colname_raw, $cnx); // 1 = column name, 2 = size, 3 = asc|desc (optional)

					if (!isset($this->qf_tabledef->columns->{$cnx[1]}))
						throw new Exception("Attempted to set an index on an inexisting column: " . $cnx[1]);
					$colname_raw = $this->_q($cnx[1]);
					if (isset($cnx[2]))
						$colname_raw .= $cnx[2];
					if (isset($cnx[3]))
						$colname_raw .= $cnx[3];
				}

				$this->query .= ' (' . implode(',', $indexdef['columns']) . ')';
				if (!empty($indexdef['comment'])) {
					$this->query .= " COMMENT '{$indexdef['comment']}'";
				}
			}
		$this->query .= PHP_EOL . ') ENGINE=' . (isset($this->qf_flags['engine']) ? $this->qf_flags['engine'] : 'MyISAM'); // database server engine, defaults to MyISAM
	}

	/**
	 * Compiler for Alter keyword ( Alter Table ... etc )
	 */
	protected function compile_Alter()
	{
		$this->check_Config_CAD('Alter');
		if (!$this->qf_name)
			throw new Exception("Missing {$this->config} name!");
		$this->query .= strtoupper($this->config) . ' ' . $this->_q($this->qf_name) . PHP_EOL;

		// stuff specific to Alter database|schema
		if (in_array(strtolower($this->config), array('database', 'schema'))) {
			if ($this->qf_i18n)
				$this->query .= 'DEFAULT CHARACTER SET ' . $this->qf_i18n['character_set'] . ' COLLATE ' . $this->qf_i18n['collation'];
		}
	}

	/**
	 * Compiler for Drop keyword ( Drop Table, Drop Index ... etc )
	 */
	protected function compile_Drop()
	{
		$this->check_Config_CAD('Drop');
		if (!$this->qf_name)
			throw new Exception("Missing {$this->config} name!");
		$this->query .= strtoupper($this->config) . ' ' . $this->_q($this->qf_name) . PHP_EOL;
	}

	//===== Data Manipulation =====//

	/**
	 * Table information ( name, alias + operating fields + aliases )
	 *
	 * @var array
	 */
	protected $qf_tables;

	/**
	 * Table on which the query will be performed
	 *
	 * @param string|QueryFactory $table Table name and alias ( name | name:alias ) or another QF object (subquery support)
	 * @param string|array $fields Fields for operation ( 'field,field,...' | 'field:alias,field:alias,...' | array(field,field,...) | array(alias=>field,alias=>field,...)
	 * @param string $jointype If this table is used in a join, specify join type: straight | inner (same as specifying multiple tables) | left | natural (left)
	 * @param string $joincond Join condition for use with ON clause. If in paranthesis ( ) a USING is considered ( same field in both tables )
	 * @param bool $no_delete Special flag for the multiple-table delete syntax, indicating that rows in this table are not to be deleted
	 * - rows in tables without this flag are deleted (appear in the "FROM" part)
	 * - other types of queries do not use this value.
	 * @return Queryfactory
	 */
	function table($table, $fields = null, $jointype = null, $joincond = null, $no_delete = false)
	{
		// for INSERT/REPLACE there is only one single table, any subsequent table() calls are ignored
		if (($this->psk == "Insert" || $this->psk == "Replace") && !is_null($this->qf_tables)) {
			// TODO: issue warning, call ignored.
			return $this;
		}
		
		$tableinfo = array();

		// autocompile if the passed object was another query factory (subquery support)
		if ($table instanceof QueryFactory) {
			$table = (string)$table;
			$tableinfo['name'] = $table;
			$tableinfo['alias'] = uniqid('t');

			// specify table name and alias directly
		} elseif (is_array($table)) {
			$tableinfo['name'] = $table[0];
			$tableinfo['alias'] = $table[1];

			// or, colon syntax
		} elseif (strpos($table, ':') !== false) {
			list($tableinfo['name'], $tableinfo['alias']) = explode(':', trim($table));

			// or plain table
		} else {
			$tableinfo['name'] = $table;
			$tableinfo['alias'] = $table;
		}
		
		// process the $fields parameter
		
		$tableinfo['fields'] = match (true) {
			is_array($fields) => $fields,
			$fields === false => [],
			is_string($fields) => self::idents_Str2Array($fields),
			$this->psk === 'Select' => ['*'],
			default => $tableinfo['fields'] ?? []
		};
		
		// Check if there are tables in the queue and handle join conditions
		if (!empty($this->qf_tables)) {
			if ($jointype !== null) {
				$tableinfo['jointype'] = $jointype;
			}
			
			if ($joincond !== null) {
				$tableinfo['using'] = $joincond[0] === '(' ? trim($joincond, ' ') : $joincond;
			}
		}


		// if (is_array($fields))
		// 	$tableinfo['fields'] = $fields;
		// elseif ($fields === false)
		// 	$tableinfo['fields'] = array();
		// elseif (is_string($fields))
		// 	$tableinfo['fields'] = self::idents_Str2Array($fields);
		// elseif ($this->psk == 'Select') // default field list to "*" (everything) if none specified
		// 	$tableinfo['fields'] = array('*');
		// // a join can only be performed if another table is already in the queue.
		// if (count($this->qf_tables) > 0) {
		// 	if (!is_null($jointype))
		// 		$tableinfo['jointype'] = $jointype;
		// 	if (!is_null($joincond)) {
		// 		if ($joincond[0] == '(')
		// 			$tableinfo['using'] = trim($joincond, ' ');
		// 		else
		// 			$tableinfo['joincond'] = $joincond;
		// 	}
		// }
		if ($no_delete == true)
			$tableinfo['no_delete'] = true;
		$this->qf_tables[] = $tableinfo;
		
		return $this;
		
	}

	/**
	 * SELECT/UPDATE/DELETE: List of conditions. If it's an OR the string starts with ||
	 *
	 * @var array[string]
	 */
	protected $qf_where;

	/**
	 * SELECT/UPDATE/DELETE: Second list of conditions ( for HAVING ). If it's an OR the string starts with ||
	 *
	 * @var array[string]
	 */
	protected $qf_having;

	/**
	 * WHERE {where condition} ( adds another condition, using boolean AND to the previous one )
	 *
	 * @return QueryFactory
	 */
	public function &where()
	{
		$args = func_get_args();
		$this->qf_where[] = call_user_func_array(array($this, 'quote_Into'), $args);
		return $this;
	}

	/**
	 * WHERE (or) {where condition} ( adds another condition, using boolean ON to the previous one ).
	 * If used as the first, the "or" component is removed.
	 *
	 * @return QueryFactory
	 */
	public function &where_or()
	{
		$allow_or = true;
		if (empty($this->qf_where))
			$allow_or = false;

		$args = func_get_args();
		$this->qf_where[] = ($allow_or ? '||' : '') . call_user_func_array(array($this, 'quote_Into'), $args);
		return $this;
	}


	/**
	 * Limit for using IN(?) in the next method. Everything bigger becomes an automatic right-join by constructing a temporary memory table
	 */
	const JOIN_MODE_LIMIT = 100;

	private $idlist_used = false;

	/**
	 * Creates a where or where_or with a list of numeric ID's ( an array ) within the current query.
	 * However, if the number of elements is bigger than a preset value, it uses a temporary table which will be right-joined.
	 * -- This doesn't work with other joins when there's a field conflict (Study and improve)
	 *
	 * @param array $id_list
	 * @param string $id_field Which field from the main table is used
	 * @return QueryFactory
	 */
	public function &idlist_Filter($id_list, $id_field = "id")
	{
		if (!is_array($id_list))
			throw new Exception("QueryFactory::idlist_Filter did not receive an ID List");

		if ($this->idlist_used)
			throw new Exception("QueryFactory::idlist_Filter has already been used. Cannot add a second ID List");

		if (count($id_list) > self::JOIN_MODE_LIMIT) {
			$create = new self('create', 'table');
			self::$connection->query(
				$create
					->name("__id_list")
					->flags("temporary", "MEMORY")
					->column($id_field)
			);
			unset($create);

			$id_list_chunks = array_chunk($id_list, self::JOIN_MODE_LIMIT);
			foreach ($id_list_chunks as $chunk) {
				$insert = new self('insert');
				$insert->table("__id_list");
				foreach ($chunk as $id)
					$insert->values(array('id' => $id));
				self::$connection->query($insert);
				unset($insert);
			}

			// Right-Join the ID List to the concat values so only those ID's mentioned in the temporary table are loaded
			$this->table("__id_list", false, 'right', "({$id_field})");
		} else
			$this->where("`{$id_field}` IN(?)", $id_list);

		$this->idlist_used = true; // Only allow this method to be called once (because of the joining thing)
		return $this;
	}

	/**
	 * Don't forget to remove the temporary table ;)
	 */
	public static function idlist_Cleanup($id_list_count)
	{
		if ($id_list_count > self::JOIN_MODE_LIMIT) {
			MVC_Core::$db->query(
				QueryFactory::drop('table')
					->flags("temporary")
					->name("__id_list")
			);
		}
	}

	/**
	 * HAVING {where condition} ( adds another condition, using boolean AND to the previous one )
	 *
	 * @return &module_db
	 */
	public function &having()
	{
		$args = func_get_args();
		$this->qf_having[] = call_user_func_array(array($this, 'quote_Into'), $args);
		return $this;
	}

	/**
	 * HAVING (or) {where condition} ( adds another condition, using boolean ON to the previous one ).
	 * If used as the first, the "or" component is removed.
	 *
	 * @return QueryFactory
	 */
	public function &having_or()
	{
		$allow_or = true;
		if (empty($this->qf_having))
			$allow_or = false;

		$args = func_get_args();
		$this->qf_having[] = ($allow_or ? '||' : '') . call_user_func_array(array($this, 'quote_Into'), $args);
		return $this;
	}

	/**
	 * SELECT/UPDATE:  order conditions
	 *
	 * @var array
	 */
	protected $qf_order;

	/**
	 * Order part of the query ( only for SELECT / UPDATE )
	 * Note: Multiple table UPDATE doesn't support ORDER !
	 *
	 * @param string|array $order_by
	 * @param string $dir Optional - Direction = ASC or DESC (must not be present when using array, instead, put the order into strings in the array directly)
	 * @return QueryFactory
	 */
	function &order($order_by, $dir = null)
	{
		if ($this->psk == "Select" || $this->psk == "Update") {
			if (is_array($order_by))
				foreach ($order_by as $piece) {
					if (is_string($piece) || $piece instanceof Serializable)
						$this->qf_order[] = (string)$piece;
					else
						throw new Exception("QueryFactory::Order() parameter must be array of strings, a string, or a serializable object.");
				}
			elseif (is_string($order_by) || $order_by instanceof Serializable) {
				if (!is_null($dir))
					$order_by .= ' ' . strtoupper($dir);

				$this->qf_order[] = (string)$order_by;
			} else
				throw new Exception("QueryFactory::Order() parameter must be array of strings, a string, or a serializable object.");
		}
		return $this;
	}

	/**
	 * SELECT: Group results after selecting
	 *
	 * @var array
	 */
	protected $qf_group;

	/**
	 * Group by part of the query ( only for SELECT )
	 *
	 * @param string|array $group_by Field or fields to GROUP BY
	 * @return QueryFactory
	 */
	public function &group($group_by)
	{
		if ($this->psk == "Select") {
			if (is_array($group_by))
				foreach ($group_by as $piece) {
					if (is_string($piece) || $piece instanceof Serializable)
						$this->qf_group[] = (string)$piece;
					else
						throw new Exception("QueryFactory::Group() parameter must be array of strings, a string, or a serializable object.");
				}
			elseif (is_string($group_by) || $group_by instanceof Serializable)
				$this->qf_group[] = (string)$group_by;
			else
				throw new Exception("QueryFactory::Group() parameter must be array of strings, a string, or a serializable object.");
		}
		return $this;
	}

	/**
	 * SELECT/UPDATE/DELETE: Limit put to the query: [0] the limit itself [1] start row ([1] might miss and assume start row is 0)
	 *
	 * @var array
	 */
	protected $qf_limit;

	/**
	 * Limit part of the query. Either one value ( the limit ) or two values ( the limit and start row )
	 *
	 * @param string|array $limit
	 * @return QueryFactory
	 */
	function &limit($limit)
	{
		if (func_num_args() == 2)
			$this->qf_limit = array($limit, func_get_arg(1));
		elseif (is_array($limit))
			$this->qf_limit = $limit;
		else
			$this->qf_limit = explode(',', $limit);
		return $this;
	}

	/**
	 * INSERT/REPLACE/UPDATE: actual values that will be placed in a table
	 *
	 * @var array
	 */
	protected $qf_values;

	/**
	 * Saves the values for the INSERT or UPDATE query.
	 * Subsequent calls of this function preform differently depending on query type:
	 * - for INSERT it will set values for the next record that will be inserted
	 * - for UPDATE/REPLACE it will add/change values for a column to the new value ( discarding previous one )
	 *   (this only works when passed array has keys with corresponding column names.
	 *
	 * @param array|QueryFactory $val_arr List of values which will be inserted/updated of another QF object (insert..select support)
	 * @param string $for_table Name or alias of the table for which these values are ment.
	 *   If specified, the table name/alias is added in front of each field
	 *   For inserts, since there is only one table, this value is ignored
	 *   NOTE: if you initially set values() for a table, then try to update them without specifying that table, (when using UPDATE)
	 *     the value updateing will fail, and possibly the entire query will be erroneous...
	 * @param string $string_expressions How string values are interpreted: false = they are escaped and put between quotes | true = they are placed as-is
	 *   true is used when instead of a simple value an expresion is used to determine what will be inserted/updated
	 *   when inserting actual strings with this param set to true, dont' forget to quote them !
	 * @return QueryFactory
	 */
	function &values($val_arr, $for_table = null, $string_expressions = false)
	{
		if ($val_arr instanceof QueryFactory) {
			$this->qf_values = (string)$val_arr;
			return $this;
		}

		if (!is_array($val_arr) || count($val_arr) == 0) {
			// TODO: issue warning, not passed an array or passed an empty array
			return $this;
		}

		foreach ($val_arr as $field => $value)
			$values[$field] = $this->format_Value($value, $string_expressions);

		if ($this->psk == "Insert" || $this->psk == "Replace")
			$this->qf_values[] = $values;
		else { // UPDATE
			reset($values);
			if (is_string(key($values))) { // keys are strings
				if (is_null($for_table))
					$qf_values = &$this->qf_values[0];
				else
					$qf_values = &$this->qf_values[$for_table];

				foreach ($values as $field => $value)
					$qf_values[$field] = $value;
			}
		}

		return $this;
	}

	//===== Compilers for Data Manipulation Statements =====//

	/**
	 * Places numerical or string values inside a string containing question marks. Supports multiple ?'s
	 *
	 * @param string $str String containing ?'s
	 * @return string The resulted string
	 */
	public function quote_Into($str)
	{
		if (($num_args = func_num_args()) > 1) {
			$arg = func_get_args();
			$fragments = explode('?', $str);

			$s = '';
			foreach ($fragments as $idx => $fragment) {
				$s .= $fragment;
				if ($idx < $num_args - 1)
					$s .= $this->format_Value($arg[$idx + 1]);
			}
			return $s;
		} else
			return $str;
	}

	/**
	 * Properly formats a PHP value to be used inside a MySQL query string
	 * Numbers are kept as-is, strings are escaped and quotes added, arrays are made as comma separated lists
	 *
	 * @param mixed $value Original value
	 * @param bool $string_is_expression If true, strings are not escaped/quoted ( assumed having proper format already )
	 * @return string Returned string to be used in query
	 */
	private function format_Value($value, $string_is_expression = false)
	{
		if (is_null($value))
			return "NULL";
		elseif (is_int($value) || is_float($value) || $string_is_expression)
			return $value;
		elseif (is_bool($value))
			return $value ? "'1'" : "'0'";
		elseif (is_string($value))
			return "'" . (!is_null(self::$connection) ? self::$connection->real_escape_string($value) : $value) . "'";
		elseif (is_array($value)) { // passing value as array results in array being transformed into a CSV string
			foreach ($value as &$piece) {
				if (is_null($piece))
					$piece = "NULL";
				elseif (is_int($piece) || is_float($piece));
				elseif (is_string($piece))
					$piece = "'" . (!is_null(self::$connection) ? self::$connection->real_escape_string($piece) : $piece) . "'";
				// else leave piece unchanged. Don't pass array elements as objects, or this will break...
			}
			return implode(',', $value);
		} else {
			return ''; // ( should also issue an error here, invalid format )
		}
	}

	/**
	 * Creates field names for use with select query under formats
	 * TODO: Check if this method is putting back apostrophes properly everywhere (or skilling them when not necessary)
	 *
	 * `field`, `field`, ...
	 * `table`.`field`, `table`.`field`, ...
	 * `field` AS field_alias, ...
	 * `table`.`field` AS field_alias, ...
	 *
	 * @param array $tbl Query constructor table used
	 * @param bool $prefix If true, adds the table name in front of each field
	 * @return string Query component
	 */
	private function compile_Field_List(&$tbl, $prefix = true)
	{

		if (count($tbl['fields']) == 0)
			return "";

		$q = '';
		$comma = '';
		$tpfx = $this->_q((isset($tbl['alias']) ? $tbl['alias'] : $tbl['name']));
		foreach ($tbl['fields'] as $alias => $field) {
			if ($field === '*') {
				// place * as is ( special meaning - all fields )
				$q .= $comma . ($prefix ? $tpfx . '.*' : '*');
			} elseif (stripos($field, '#') !== false || preg_match("~\\(.*\\)~", $field)) { // table name is inside field, or the field is an expression
				//-- do not auto-add prefix (alias must be present in the expression)
				$q .= $comma . str_replace('#', $tpfx, $field);
			} else // normal mode, just the field name
				$q .= $comma . ($prefix ? $tpfx . '.' : '') . $this->_q($field);

			if (!is_numeric($alias))
				$q .= ' AS ' . $this->_q($alias);
			$comma = ', ';
		}
		return $q;
	}

	/**
	 * Searches in the string for references to tables in the format:
	 * #number. and replaces them with table aliases
	 *
	 * @param string $string
	 * @return string
	 */
	private function table_Reference_Substitute($string)
	{
		$tok = strtok($string, "#");
		$ret = '';
		while ($tok !== false) {

			$tab_id = (int)$tok;
			if ($tab_id > 0) {
				$len = strlen((string)$tab_id);
				$ret .= '`' . $this->qf_tables[$tab_id - 1]['alias'] . '`' . substr($tok, $len);
			} else
				$ret .= $tok;
			$tok = strtok("#");
		}
		return $ret;
	}

	/**
	 * Creates a list of tables and table joins for use with SELECT and UPDATE
	 *
	 * @param bool $no_alias If true, aliases are disabled
	 * @param bool $no_join If true, all JOIN formatting is disabled
	 * @return string Query part
	 */
	private function compile_table_list($no_alias = false, $no_join = false)
	{

		$q = '';
		$comma = '';
		foreach ($this->qf_tables as &$table) {
			// join with previous, instead of appending to it with comma
			if (!empty($table['jointype']) && $no_join == false) {
				// replace some shortcuts
				$table['jointype'] = str_ireplace(array('join', 'inner', 'straight', 'cross', 'left', 'natural', 'right'), array('JOIN', 'INNER JOIN', 'STRAIGHT_JOIN', 'CROSS JOIN', 'LEFT JOIN', 'NATURAL JOIN', 'RIGHT JOIN'), trim($table['jointype']));

				$q .= "\n\t " . $table['jointype'] . ' `' . $table['name'] . '`';
				if ($no_alias == false && $table['alias'] != $table['name'])
					$q .= ' AS ' . $table['alias'];
				if (!empty($table['joincond'])) {
					if (stristr($table['joincond'], ' AND ') || stristr($table['joincond'], ' OR '))
						$table['joincond'] = '(' . $table['joincond'] . ')';
					$q .= ' ON ' . $this->table_Reference_Substitute($table['joincond']);
				}
				if (!empty($table['using']))
					$q .= ' ON ' . $table['using'];
			} else {
				if (stripos($table['name'], 'select') !== false) // subquery support
					$q .= $comma . '(' . $table['name'] . ')';
				else
					$q .= $comma . '`' . $table['name'] . '`';
				if ($no_alias == false && $table['alias'] != $table['name'])
					$q .= ' AS ' . $table['alias'];
			}
			$comma = ', ';
		}
		return $q;
	}

	/**
	 * Compiles the SELECT Statement
	 */
	protected function compile_Select()
	{
		// TODO - Check valid flags for each query type !
		if (!empty($this->qf_flags)) {
			foreach ($this->qf_flags as $flag)
				$this->query .= ' ' . strtoupper($flag);
			$this->query .= " \n\t";
		}

		// fields
		$comma = '';
		foreach ($this->qf_tables as &$table) {
			$fields = $this->compile_Field_List($table, count($this->qf_tables) > 1);
			if (empty($fields))
				continue;
			$this->query .= $comma . $fields;
			$comma = ", \n\t";
		}
		// from tables
		$this->query .= "\n\t FROM " . $this->compile_Table_List();
		$this->compile_Statement_Components(array('where', 'group', 'having', 'order', 'limit'));
	}

	/**
	 * Compiles the INSERT or REPLACE Statements
	 */
	protected function compile_Insert()
	{
		// TODO - Check valid flags for each query type !
		if (!empty($this->qf_flags)) {
			foreach ($this->qf_flags as $flag)
				$this->query .= ' ' . strtoupper($flag);
			$this->query .= " \n\t";
		}

		$this->query .= " INTO `" . $this->qf_tables[0]['name'] . "`"; // only take first table into consideration, INSERT/REPLACE doesn't support multiple tables

		// construct field list
		// - if 'fields' key exists in qf_tables, use those as the the fields. in $qf_values those fields must exist, or error will occur.
		// - if 'fields' key doesn't exist in qf_tables or is an empty array:
		//   - check if the fields in $qf_values have names, if so, use them to create the field list
		//   - if not, skip the field list completely and just insert ALL $qf_values (assuming they are in the proper order)

		if (isset($this->qf_tables[0]['fields']) && count($this->qf_tables[0]['fields'])) {
			$field_list = $this->qf_tables[0]['fields'];
			$use_field_list = true;
		} else {

			// find first value array
			if (is_array($this->qf_values)) {
				$first_values = reset($this->qf_values);
				if (is_string(key($first_values))) {
					$field_list = array_keys($first_values);
					$use_field_list = true;
				}
			}
		}

		if (is_string($this->qf_values)) { // INSERT..SELECT support
			$this->query .= "\n\t " . $this->qf_values;
			return;
		}

		$comma = '';
		if (isset($use_field_list)) {
			$this->query .= "\n\t (";
			foreach ($field_list as $field_name) {
				$this->query .= "$comma`$field_name`";
				$comma = ',';
			}
			$this->query .= ")";
		}

		// value list, the entire "values" table ( multiple row inserts )
		if (count($this->qf_values)) {
			foreach ($this->qf_values as &$valueset) {
				if ($use_field_list) {
					$used_field_list = array();
					foreach ($field_list as $field_name)
						$used_field_list[] = isset($valueset[$field_name]) ? $valueset[$field_name] : 'DEFAULT';
				} else
					$used_field_list = &$valueset;
				$valueset = '(' . implode(', ', $used_field_list) . ')';
			}
			$values = implode(', ', $this->qf_values);
		} else
			$values = "()";

		$this->query .= "\n\t VALUES " . $values;
	}

	/**
	 * Replace has identical syntax as Insert
	 */
	protected function compile_Replace()
	{
		$this->compile_Insert();
	}

	/**
	 * Compiles the Update statement
	 *
	 * @return bool
	 */
	protected function compile_Update()
	{
		// TODO - Check valid flags for each query type !
		if (!empty($this->qf_flags)) {
			foreach ($this->qf_flags as $flag)
				$this->query .= ' ' . strtoupper($flag);
			$this->query .= " \n\t";
		}
		if (count($this->qf_tables) == 1) { // one table only
			$allowed_keywords = array('where', 'order', 'limit');
		} else {
			$allowed_keywords = array('where');
		}
		// if there are no valuse, there's nothing to update. Query error !
		if (empty($this->qf_values))
			return false;

		$this->query .= $this->compile_Table_List() . "\n\t SET"; // $no_alias,true | params: no alias, no join
		// values for all tables
		$comma = '';

		foreach ($this->qf_values as $table_name => &$values) {
			foreach ($values as $field => $value) {
				$this->query .= $comma . ' ';
				if (is_string($table_name))
					$this->query .= "`{$table_name}`.";
				$this->query .= "`{$field}`={$value}";
				$comma = ',';
			}
		}
		$this->compile_Statement_Components($allowed_keywords);
	}

	/**
	 * Compiles the Delete statement
	 */
	protected function compile_Delete()
	{
		// TODO - Check valid flags for each query type !
		if (!empty($this->qf_flags)) {
			foreach ($this->qf_flags as $flag)
				$this->query .= ' ' . strtoupper($flag);
			$this->query .= " \n\t";
		}

		$this->query .= "FROM ";
		if (count($this->qf_tables) == 1) { // single table syntax -> supports ORDER BY and LIMIT
			$this->query .= $this->qf_tables[0]['name'];
			$allowed_keywords = array('where', 'order', 'limit');
		} else { // multiple table syntax
			$comma = '';
			foreach ($this->qf_tables as &$table) {
				if (isset($table['no_delete']))
					continue;
				$this->query .= $comma . $table['alias'];
				$comma = ', ';
			}
			$this->query .= "\n\t ON " . $this->compile_Table_List();
			$allowed_keywords = array('where');
		}
		$this->compile_Statement_Components($allowed_keywords);
	}

	/**
	 * Compiles all statement components in the proper order
	 *
	 * @param array $allowed_keywords
	 */
	private function compile_Statement_Components($allowed_keywords)
	{
		// where
		if (in_array('where', $allowed_keywords) && method_exists($this, 'compile_Where_Having'))
			$this->compile_Where_Having('WHERE');

		// group by
		if (in_array('group', $allowed_keywords) && method_exists($this, 'compile_Group'))
			$this->compile_Group();

		// having
		if (in_array('having', $allowed_keywords) && method_exists($this, 'compile_Where_Having'))
			$this->compile_Where_Having('HAVING');

		// order
		if (in_array('order', $allowed_keywords) && method_exists($this, 'compile_Order'))
			$this->compile_Order();

		// limit
		if (in_array('limit', $allowed_keywords) && method_exists($this, 'compile_Limit'))
			$this->compile_Limit();
	}

	/**
	 * Where or Having
	 *
	 * @param unknown_type $mode WHERE | HAVING
	 */
	private function compile_Where_Having($mode = "WHERE")
	{
		$qf_what = ($mode == 'HAVING' ? $this->qf_having : $this->qf_where);
		if (!empty($qf_what)) {
			$this->query .= "\n\t {$mode} ";
			$bool = '';
			foreach ($qf_what as $widx => $wcond) {
				if (substr($wcond, 0, 2) == '||') {
					$wcond = substr($wcond, 2);
					if ($widx > 0)
						$bool = ' OR ';
				}
				if (stristr($wcond, ' AND ') || stristr($wcond, ' OR ')) //  || stristr($wcond, 'CASE WHEN')
					$wcond = '(' . $wcond . ')';
				$this->query .= $bool . $this->table_Reference_Substitute($wcond);
				$bool = ' AND ';
			}
		}
	}

	/**
	 * Compiles GROUP BY clause
	 */
	private function compile_Group()
	{
		if (!empty($this->qf_group)) {
			$this->query .= "\n\t GROUP BY " . $this->table_Reference_Substitute(implode(', ', $this->qf_group));
		}
	}

	/**
	 * Compiles ORDER clause
	 */
	private function compile_Order()
	{
		if (!empty($this->qf_order)) {
			$this->query .= "\n\t ORDER BY " . $this->table_Reference_Substitute(implode(', ', $this->qf_order));
		}
	}

	/**
	 * Compiles LIMIT clause
	 */
	private function compile_Limit()
	{
		if (!empty($this->qf_limit)) {
			$this->query .= "\n\t LIMIT ";
			if (!empty($this->qf_limit[1]))
				$this->query .= $this->qf_limit[1] . ", " . $this->qf_limit[0];
			else
				$this->query .= $this->qf_limit[0];
		}
	}
}
