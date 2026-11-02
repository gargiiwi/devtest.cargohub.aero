<?php
/**
 * MVC Warehouse - Server-side file storage and processing
 * - Handles file uploads, directory iterating and listing (single or recursive)
 * - Organizes file storage using identifiers stored in a database (optional)
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @version 2019.10.11
 * @package CargoConnect
 * @subpackage MVC Framework
 */
class MVC_Warehouse
{
	/**
	 * This component's configuration
	 * @var array
	 */
	public $config;

	/**
	 * List of mime types for which analyzers have registered. If a mime type is not here, no special analysis is done.
	 * @var array
	 */
	protected $analyzer_map = array();

	/**
	 * List of upload destinations depending on analyzer used
	 * @var array
	 */
	protected $paths_map = array();

	/**
	 * Database component
	 * @var MVC_Database
	 */
	protected $db;

	/**
	 * Module constructor, prepares analyzer data
	 * @param MVC_Database $db
	 * @param array $config
	 */
	public function __construct(&$db, $config)
	{
		// checks analyzers and builds a mime-type database to analyzer map
		if(isset($config['analyzers']))
			foreach($config['analyzers'] as $name=> $aCFG) {
				$types = call_user_func(array('MVC_Warehouse_'.ucfirst($name), "register_Mime_Types"));
				foreach($types as $mime)
					$this-> analyzer_map[$mime] = $name;

				if(isset($config['paths'][$name]))
					$this-> paths_map[$name] = $config['paths'][$name]['dir'];
			}
		// default upload path (when there are no special paths defined for different analyzers
		$this-> paths_map['_default'] = $config['paths']['_default']['dir'];

		// Database component is being used
		if(isset($config['database'])) {
			if(!isset($config['database']['table']))
				$config['database']['table'] = 'files';
 			$config['database']['fields'] = array('filepath','name','ext','size','mtime','mimetype','analyzer','metadata','chash','count_reads'); // No extra fields here

 			// extra fields (which are not necessary for this module, but contained in the same table - depends on the application)
 			if(isset($config['extra'])) {
 				foreach(array_keys($config['extra']) as $extra_field)
 					$config['database']['fields'][] = $extra_field;
 			}

 			$this-> db = &$db;
		}

		$this-> config = $config;
	}

	/**
	 * Result of processing uploaded or imported files (used internally)
	 * @var array
	 */
	protected $analysis;

	/**
	 * Temporary information added to the file record in the database
	 * @var array
	 */
	private $database_store;

	private $_c;

	protected $uploads;

	/**
	 * Temporary array mapper used with a current "file_set" structure
	 * @var Tree_Mapper
	 */
	private $set_mapper;

	protected function awr_helper($err_code, $key, $params)
	{
		$this-> set_mapper-> add_By_Keylist($this-> uploads[$params['container_key']], $this->_c, new MVC_Uploaded_File(
			// pick elements from the 5 arrays that $_FILES contains and put them into a single container
			array(
				'name'=> $this-> set_mapper-> search_By_Keylist($params['file_set']['name'], $this->_c),
				'type'=> $this-> set_mapper-> search_By_Keylist($params['file_set']['type'], $this->_c),
				'tmp_name'=> $this-> set_mapper-> search_By_Keylist($params['file_set']['tmp_name'], $this->_c),
				'error'=> $this-> set_mapper-> search_By_Keylist($params['file_set']['error'], $this->_c),
				'size'=> $this-> set_mapper-> search_By_Keylist($params['file_set']['size'], $this->_c)
			)
		));
		$this->_c++;
	}

	/**
	 * Checks if there are any file uploads and processes them ( sends to proper location, generates file information data )
	 */
	public function &process_Uploads($database_store = false)
	{
		$this-> analysis = array(); // Clear in preparation for one or more uploaded files
		$this-> database_store = $database_store;
		// find the actual structure of the posted files, inside the mess that $_FILES is delivering, and build a Tree of MVC_Uploaded_File out of it
		if(!empty($_FILES)) {
			$this-> uploads = array();
			foreach($_FILES as $key=> $file_set)
				if(is_array($file_set['error'])) { // test this variable, as it's always present (the others might not be, in case of errors)
					$this-> set_mapper = new Tree_Mapper($file_set['error']);

					// arranges the file upload objects in the same way as the incoming structure from HTTP POST, but groups together all data
					$this->_c = 0;
					array_walk_recursive($file_set['error'], array($this, "awr_helper"), array('container_key'=> $key, 'file_set'=> $file_set));
				} else {
					$this-> uploads[$key] = new MVC_Uploaded_File($file_set);
				}

			// now uploads can be processed one by one (and sent to their final destination)
			array_walk_recursive($this-> uploads, array($this, "process_File_Upload"));
			return $this-> analysis;
		} else {
			$null = null;
			return $null;
		}
	}

	/**
	 * Imports an existing file (by copying it) into the warehouse and returns the analysis
	 * @param array $file_set File data, just like coming from an upload
	 */
	public function import_File($file_set, $database_store = false)
	{
		$this-> analysis = array();
		$this-> database_store = $database_store;
		$file = new MVC_Uploaded_File($file_set, true);
		$this-> process_File_Upload($file);
		return reset($this-> analysis);
	}

	/**
	 * Processes a file upload (using the helper class MVC_Uploaded_File)
	 *
	 * @param MVC_Uploaded_File $file
	 */
	protected function process_File_Upload(MVC_Uploaded_File $file)
	{
		$file_cl = $file-> get_Current_Location();
		if($file_cl!==false) {
			$path_CFG = array('mode'=> 'direct'); // Start by assuming the mode is "direct", but analyze_File might decide something else
			$fileinfo = $this-> analyze_Uploaded_File($file_cl, $file-> get_Original_Name(), $path_CFG);

			// which moving function to use, split by contentHash, split by filename)
			switch($path_CFG['mode']) {
				case 'direct': // one directory for all files (can get slow if very large number of files)
					$fileinfo['_fullpath'] = $file-> move_Direct($this-> paths_map[$path_CFG['id']]);
					break;
				case 'contentHash':
					if(!isset($path_CFG['hashDepth'])) // defaults to 16x16 hash folders
						$path_CFG['hashDepth'] = 2;
					$fileinfo['_fullpath'] = $file-> move_ContentHash($this-> paths_map[$path_CFG['id']], $fileinfo['chash'], (int)$path_CFG['hashDepth']);
					break;
			}
			if($fileinfo['_fullpath']!==false) {
				// Store the relative path to the analyzer (or default) path, and fix the windows DS (\) by storing the linux-only way (/)
				$fileinfo['filepath'] = str_replace('\\', '/', substr($fileinfo['_fullpath'], strlen($path_CFG['dir'])+1));
				$fileinfo['ext'] = strtolower(substr($fileinfo['name'], strlen($fileinfo['_basename'])+1));
				unset($fileinfo['_fullpath'], $fileinfo['_basename']);

				if(isset($this-> config['database']) && $this-> database_store)
					$fileinfo['id'] = $this-> store_File_Data($fileinfo);
				// Clear some information which is not needed for output
				unset($fileinfo['filepath']);
			} else {
				$fileinfo['error'] = $file-> get_Error();
			}
			$this-> analysis[] = $fileinfo;

		} else
			$this-> analysis[] = array(
				'name'=> $file-> get_Original_Name(),
				'error'=> $file-> get_Error()
			);
	}

	/**
	 * Analyzes an already uploaded file and updates it's database record if requested
	 * -- The existing file analyzer doesn't move files - It only studies an existing file pointed by a database reference
	 * -- It also doesn't care what type
	 *
	 * @param array $fileinfo Information about the file (must contain at least "id", "filepath", "analyzer"
	 * -- recommended: "chash" - to avoid hashing the file again); "name" used by certain modes of analyzers
	 * -- Might add/change "mimetype", "chash", "analyzer" and "metadata"
	 * @param bool $update_db If true, it will call to Warehouse to also update the database fields (analyzer, metadata) - If false, only the $fileinfo is updated
	 */
	public function analyze_Existing_File(&$fileinfo, $database_update = false)
	{
		if(!empty($fileinfo['analyzer']))
			return false; // Nothing to do, the file is already analyzed

		if(empty($fileinfo['filepath']))
			return MVC_Core::$debug-> dump("Filepath missing for Analyzer request");

		$fileinfo['_fullpath'] = str_replace('\\', '/', $this-> paths_map['_default']. DS. $fileinfo['filepath']);

		if(!isset($fileinfo['mimetype']))
			$fileinfo['mimetype'] = mime_content_type($fileinfo['_fullpath']);

		// Test if the existing file type is handled by an analyzer
		if(array_key_exists($fileinfo['mimetype'], $this-> analyzer_map)) {
			$fileinfo['analyzer'] = $this-> analyzer_map[$fileinfo['mimetype']];

			// If CHash is missing, the file is sha1_hashed (as some analyzers might need it)
			if(!isset($fileinfo['chash']))
				$fileinfo['chash'] = sha1_file($fileinfo['_fullpath']);

			if(isset($fileinfo['name']))
				$fileinfo['_basename'] = substr($fileinfo['name'], 0, strrpos($fileinfo['name'], "."));

			// the constructor should autofill extra data in the $fileinfo
			$analyzer_class = 'MVC_Warehouse_'.ucfirst($fileinfo['analyzer']);
			new $analyzer_class($fileinfo, $this-> config['analyzers'][$fileinfo['analyzer']]);
		}

		// Not needed anymore after analysis
		unset($fileinfo['filepath'], $fileinfo['_fullpath'], $fileinfo['_basename']);

		// prepare "metadata" field
		if(isset($fileinfo['analyzer'])) {
			$key = '_'.$fileinfo['analyzer'];
			// Warning: some analyzers might return binary/non-UTF-8 data ! In this case, json_encode will fail... TODO: Fix this somehow, or require analyzers to always return properly encoded data
			$fileinfo['metadata'] = json_encode($fileinfo[$key]);
			unset($fileinfo[$key]);
		}

		// Database update is requested
		if($database_update && !empty($fileinfo['analyzer'])) {
			if(empty($fileinfo['id'])) {
				MVC_Core::$debug-> dump("File ID missing from for Analyzer database update");
			} else {
				$db_update = array(
					'mimetype'=> $fileinfo['mimetype'],
					'analyzer'=> $fileinfo['analyzer'],
					'metadata'=> $fileinfo['metadata']
				);

				$q=QueryFactory::update()
				-> table($this-> config['database']['table'])
				-> values($db_update)
				-> where("`id` = ?", $fileinfo['id']);

				$this-> db-> query($q);
			}
		}

		return true;
	}

	/**
	 * Studies a file and grabs some information about it.
	 * It can optionally do more analysis on certain file types
	 *
	 * @param string $filepath File to study
	 * @param string $filename File name as it was uploaded or the source for copy operation (may be used by Analyzer plugin)
	 * @param array $path_CFG (To SET) Path configuration (from $config), depending if an Analyzer is set for a certain mime type
	 * @param bool $get_type Not needed here, ALL are "files" ( no link or dir, since this is an uploaded file )
	 * @return array
	 */
	protected function analyze_Uploaded_File($filepath, $filename, &$path_CFG, $get_type = false)
	{
		$finfo = new SplFileInfo($filepath);
		$fileinfo=array(
			// Get real full path (which is also used to store "filepath")
			'_fullpath'=>	realpath($filepath),
			'name'=>		$filename,
			'mtime'=>		$finfo->getMTime(),
//			'ctime'=>		$finfo->getCTime(),	// Not needed, mtime is sufficient ( file upload time )
			'size'=>		$finfo->getSize(),
		);
		$fileinfo['_basename'] = substr($filename, 0, strrpos($filename, "."));

		if($get_type)
			$fileinfo['type'] = $finfo-> getType();
		$fileinfo['mimetype'] = mime_content_type($fileinfo['_fullpath']);

		// test if the uploaded file type is handled by an analyzer
		if(array_key_exists($fileinfo['mimetype'], $this-> analyzer_map)) {
			$fileinfo['analyzer'] = $this-> analyzer_map[$fileinfo['mimetype']];

			// Analyzer might redirect file storage to a different location
			if(!empty($this-> paths_map[$fileinfo['analyzer']])) {
				$path_CFG = $this-> config['paths'][$fileinfo['analyzer']];
				$path_CFG['id'] = $fileinfo['analyzer'];
			}
		}

		// When no specific map is set for the detected analyzer, it revers to the "_default" one (which must exist !)
		if($path_CFG['mode'] == 'direct') {
			// When no specific map is set for the detected analyzer, it revers to the "_default" one (which must exist !)
			$path_CFG = $this-> config['paths']['_default'];
			$path_CFG['id'] = '_default';
		}

		// if file is to be moved into a hash-spread path, hash the file's content first (this might be used by the analyzer plugin too)
		if(isset($path_CFG['mode']) && $path_CFG['mode']=='contentHash')
			$fileinfo['chash'] = sha1_file($fileinfo['_fullpath']);

		// grab additional info about it (from the analyzer), by creating a single-use instance of the analyzer class (which will immediately process the file)
		if(!empty($fileinfo['analyzer'])) {
			// the constructor should autofill extra data in the $fileinfo
			$analyzer_class = 'MVC_Warehouse_'.ucfirst($fileinfo['analyzer']);
			new $analyzer_class($fileinfo, $this-> config['analyzers'][$fileinfo['analyzer']]);
		}

		return $fileinfo;
	}

	/**
	 * Database Component - Stores information about the uploaded file in the database, returns the insert_id()
	 * Can also be called from outside to add information to the $fileinfo before storing
	 * @param array $fileinfo
	 * @return int Entry ID
	 */
	public function store_File_Data(&$fileinfo)
	{
		// prepare "metadata" field
		if(isset($fileinfo['analyzer'])) {
			$key = '_'.$fileinfo['analyzer'];
			// Warning: some analyzers might return binary/non-UTF-8 data ! In this case, json_encode will fail... TODO: Fix this somehow, or require analyzers to always return properly encoded data
			$fileinfo['metadata'] = json_encode($fileinfo[$key]);
			unset($fileinfo[$key]);
		}

		// extra fields
		if(isset($this-> config['extra']))
			foreach($this-> config['extra'] as $field=> $value)
				$fileinfo[$field] = $value;

		$q=QueryFactory::insert()
		-> table($this-> config['database']['table'], $this-> config['database']['fields'])
		-> values($fileinfo);

		$this-> db-> query($q);
		return $this-> db-> insert_id;
	}

	/**
	 * Reads the stored file data for an ID and decodes metadata (if it exists)
	 * @param int $id
	 * @param bool $increase Increase the count_reads value
	 */
	public function read_File_Data($id, $increase = false)
	{
		$q=QueryFactory::select()
		-> table($this-> config['database']['table'])
		-> where('`id` = ?', $id)
		-> limit(1);

		$r = $this-> db-> query($q);

		if($r && count($r)==1) {
			$fileinfo = $r-> current();
			if(!empty($fileinfo['analyzer'])) {
				$key = '_'.$fileinfo['analyzer'];
				$fileinfo[$key] = json_decode($fileinfo['metadata'], true);
				unset($fileinfo['metadata']);
			}
			if($increase) {
				$q=QueryFactory::update()
				-> table($this-> config['database']['table'])
				-> values(array("`count_reads`"=>"`count_reads`+1"), null, true);
			}
			return $fileinfo;
		} else
			return false; // error, bad ID
	}

	/**
	 * Reads the contents of a file, and optionally also the stored file metadata
	 * -- Can also generate the proper headers depending on file type, to generate a download or display inside
	 * @param int $id
	 * @param array $file_meta If null, it reads and stores into this variable the file metadata. If array, it attempts to use existing data (must contain "name", "mimetype", "size", "filepath" and they should be correct or the file download will fail)
	 * @param array $flags Contains a list of modifier for the use of this functions, which when they exist and equal "true":
	 * -- generate_headers : Sets the headers to prepare the download of this warehouse file
	 * -- force_download : Sets a special header that will force the browser to download the file even if it can be displayed inline
	 * -- direct_output : Reads the file directly to the output buffer and returns the number of bytes read or False if there was an error
	 *    (If missing or false, it reads the file and returns the file content or False on failure)
	 * -- use_browser_cache :  True: Sets some headers so the browser attempts to use the cached version if the file hasn't been changed, and returns HTTP 304 not modified ; False/missing = disable caching and always send content
	 *
	 * @param bool $generate_headers
	 * @param bool $direct_output When generating headers, returns the file directly (readfile), instead of putting it into a string
	 * @param bool $force_download When generating headers, sets the headers for forced download for any file type (including images and text)
	 * @return string|bool Content of the file or "true" when $direct_output = true
	 */
	public function read_File_Content($id, &$file_meta = null, $flags = array())
	{
		if(is_null($file_meta)) {
			$file_meta = $this-> read_File_Data($id, true);
			if(!$file_meta)
				return false; // error, warehouse id not found
		} elseif(is_array($file_meta)) {
			if(!isset($file_meta['name']) || !isset($file_meta['mimetype']) || !isset($file_meta['size']) || !isset($file_meta['filepath']))
				return false; // error, fileinfo doesn't contain the required fields
		}

		// Determine full path ( config root, based on analyzer )
		if(!empty($file_meta['analyzer']) && !empty($this-> config['paths'][$file_meta['analyzer']]))
			$root = $this-> config['paths'][$file_meta['analyzer']]['dir'];
		else
			$root = $this-> config['paths']['_default']['dir'];
		// Fix filepath for windows and assamble $fullpath
		if(DS == '\\')
			$file_meta['filepath'] = str_replace("/", DS, $file_meta['filepath']);
		$fullpath = $root. DS. $file_meta['filepath'];

		// Check if the file actually exists
		if(!file_exists($fullpath))
			return false;

		if(isset($flags['generate_headers']) && $flags['generate_headers'] == true) {
			if(isset($flags['force_download']) && $flags['force_download'] == true) {
				header("Content-Type: application/force-download");
				header("Content-Disposition: attachment; filename=\"{$file_meta['name']}\"");
			} else {
				header("Content-Type: {$file_meta['mimetype']}");
				header("Content-Disposition: inline; filename=\"{$file_meta['name']}\"");
			}
			header("Content-Length: ".(string)$file_meta['size']);

			if(isset($flags['use_browser_cache']) && $flags['use_browser_cache'] == true) {
				// TODO: Implement Last-Modified / Header 304 Not Modified / Etag ?

				// Check file data and compare with whatever browser is sending?
				// header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
				// header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");

			} else {
				// Disable all caching
				header("Pragma: public");
				header("Pragma: no-cache");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Cache-Control: private", false); // required for certain browsers
			}

		}

		if(isset($flags['direct_output']) && $flags['direct_output'] == true)
			return @readfile($fullpath);
		else
			return @file_get_contents($fullpath);
	}

	/**
	 * Removes a stored file reference, by it's ID. Searches if the same file (fullpath) is used in another reference. If not, removes the file itself as well.
	 *
	 * @param int $id
	 * @return int The number of references which still use the file, or 0 if the file was actually deleted. False if ID not found
	 * TODO: Rethink this
	 */
/* 	public function remove_File_Reference($id)
	{
		$wh_row = $this['record']-> factory_Record('_warehouse', $id);
		if($wh_row-> get_ID()) {
			$fullpath = (string)$wh_row-> fullpath;

			$q = $this['record']['queryfactory']-> select()
				-> table((string)$this-> CFG-> database['table'], 'count(id):count_refs')
				-> where('fullpath = ?', $fullpath);
			$res = $this['record']['connection']-> query($q)-> current();

			$wh_row-> destroy();
			if($res['count_refs']==1) {
				// if this was the only reference, remove the file itself from the warehouse
				unlink($fullpath);
				return 0;
			} else
				return $res['count_refs']-1;

		} else
			return false; // error, bad ID
	}
 */
}

/**
 * Helper class for file uploads
 * @author Wavetrex <wavetrex@gmail.com>
 * @link http://www.donrey.net/Documentation/Structure/Modules/warehouse.html
 * @license [GPL v3] http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.0.dev
 * @package DonReY
 * @subpackage Module.Warehouse
 */
class MVC_Uploaded_File
{
	const UPLOAD_ERR_CANNOT_MOVE = 100;

	/**
	 * Indicates the state of the upload: 0 - Invalid upload, 1 = still into temporary location (where php uploaded it), 2 = moved to destination
	 * @var int
	 */
	private $completed = 0;

	private $name;

	private $mime_type;

	/**
	 *
	 * Temporary path + filename where php uploaded the file
	 * @var string
	 */
	private $tmp_name;

	/**
	 * Absolute path + filename where the file was moved (after one of the move methods)
	 * @var string
	 */
	private $final_name;

	private $size = 0;

	/**
	 * Error code for failed uploads
	 * @var int
	 */
	private $error;

	private $fake_upload = false;

	/**
	 * Creates a "file" entry which can be further processed
	 * @param array $file_upload
	 * @param bool $fake_upload If true, the file is not an actual upload, but an existing file which must be introduced into the warehouse
	 */
	public function __construct($file_upload, $fake_upload = false)
	{
		if($fake_upload || $file_upload['error'] == UPLOAD_ERR_OK) {
			$this-> completed = 1;

			$this-> name = $file_upload['name'];
			$this-> mime_type = $file_upload['type'];
			$this-> tmp_name = $file_upload['tmp_name'];
			$this-> size = $file_upload['size'];
			$this-> fake_upload = $fake_upload;
		} else
			$this-> error = $file_upload['error'];
	}

	/**
	 * Returns the absolute path that this file has (before and after being moved)
	 * @return string|false False if there was a problem with the upload
	 */
	public function get_Current_Location()
	{
		switch($this-> completed) {
			case 1:
				return $this-> tmp_name;
			case 2:
				return $this-> final_name;
		}
		// error, invalid upload
		return false;
	}

	/**
	 * Returns the original file name (how it was called in the user's browser). Final name might be different ( due to renaming or using it's hash )
	 * @return string
	 */
	public function get_Original_Name()
	{
		return $this-> name;
	}

	/**
	 * Returns upload error code (http://www.php.net/manual/en/features.file-upload.errors.php)
	 * @return int
	 */
	public function get_Error()
	{
		return $this-> error;
	}

	/**
	 * Move a file upload to a destination folder, optionally renaming it if a new name is specified
	 * @param string $dest_path
	 * @param string $rename
	 */
	public function move_Direct($dest_path, $rename = null)
	{
		// move only if file is in temporary location
		if($this-> completed==1) {
			// optionally rename
			$this-> final_name = $dest_path. DS. (is_string($rename)?$rename:$this-> name);
			if($this-> fake_upload)
				$r = @copy($this-> tmp_name, $this-> final_name);
			else
				$r = @move_uploaded_file($this-> tmp_name, $this-> final_name);
			if($r==false) {
				$this-> error = self::UPLOAD_ERR_CANNOT_MOVE;
				return false;
			}
			$this-> completed = 2; // set as moved
			return $this-> final_name;
		} elseif($this-> completed==2)
			return $this-> final_name;
		return false;
	}

	/**
	 * Moves the uploaded file into hash-distributed directories, by using the hashed content of the file.
	 * This is used to distribute large number of uploads in order to avoid directory overload. Also makes sure all files are unique.
	 *
	 * @param string $dest_path
	 * @param string $chash The hash resulted from sha1_file()
	 * @param string $hash_depth How deep the rabbit hole goes - defaults to 16x16 hash dirs (storage split in 256 subdirectories)
	 * @return array Relevant information about this file, in order to locate it in the distributed dirs
	 */
	public function move_ContentHash($dest_path, $chash, $hash_depth = 2)
	{
		// move only if file is in temporary location
		if($this-> completed==1) {
			for($c = 0; $c< $hash_depth; $c++)
				$dest_path.= DS. $chash[$c];

			$ext = (($p=strrpos($this-> name, '.'))!==false)?strtolower(substr($this-> name, $p+1)):''; // might be empty if strrpos doesn't find a dot,
			$this-> final_name = $dest_path. DS. $chash. '.'. $ext;

			// move if the destination doesn't already exist
			if(!file_exists($this-> final_name)) {
				// path doesn't exist, create it first, before moving the file
				if(!is_dir($dest_path))
					mkdir($dest_path, 0777, true);

				if($this-> fake_upload)
					$r = @copy($this-> tmp_name, $this-> final_name);
				else
					$r = @move_uploaded_file($this-> tmp_name, $this-> final_name);
				if($r==false) {
					$this-> error = self::UPLOAD_ERR_CANNOT_MOVE;
					return false;
				}
			}
			$this-> completed = 2; // set as moved
			return $this-> final_name;
		} elseif($this-> completed==2)
			return $this-> final_name;
		return false;
	}
}

/**
 * Analyzer required methods
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @link http://www.donrey.net/Documentation/Structure/Modules/warehouse.html
 * @license [GPL v3] http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.0.dev
 * @package DonReY
 * @subpackage Module.Warehouse
 */
interface MVC_Analyzer
{
	/**
	 * Studies a file which is presumed to be of a certain type
	 * @param array $fileinfo
	 * @param array $CFG
	 */
	public function __construct(&$fileinfo, $CFG);
}