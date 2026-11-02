<?php
/**
 * Connector which accesses an FTP server and attempt to download or upload a file (specified in the configuration)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
class App_Plugin_WebAPI_connector_FTP implements App_Plugin_WebAPI_connector
{
	private $CFG;

	/**
	 * Constructor receives configuration for this instance of the plugin
	 * @param array $CFG
	 */
	public function __construct($CFG)
	{
		if(!isset($CFG['host'])) {
			MVC_Core::$debug-> dump("FTP Server host address missing from the configuration", __METHOD__, null, 'error');
			throw new Exception();
		}
		if(!isset($CFG['port'])) { // Defaults
			if(!empty($CFG['ftps']))
				$CFG['port'] = 990;
			else
				$CFG['port'] = 21;
		}

		if(!isset($CFG['user'])) {
			$CFG['user'] = "anonymous";
			$CFG['pass'] = "webapi.connector@cargohub.nl";
		}

		$this-> CFG = $CFG;
	}

	/**
	 * Connection resource
	 * @var Resource
	 */
	private $conn = null;

	/**
	 * Connects to the FTP server specified in the configuration
	 * - If a path is mentioned in the config, moves to the path
	 * - Lists files found in that path (optionally filtering by extension)
	 * - Attempts to download and read the newest file from the list
	 */
	public function incoming()
	{
		if(!empty($this-> CFG['ftps'])) {
			// Connect through SSL
			$secure_text = " via SSL";
			$this-> conn = ftp_ssl_connect($this-> CFG['host'], $this-> CFG['port'], 15); // Only 15s timeout, default of 90 is way, way too high for today's fast internet
		} else {
			// Connect to basic FTP
			$secure_text = "";
			$this-> conn = ftp_connect($this-> CFG['host'], $this-> CFG['port'], 15);
		}
		if(empty($this-> conn))
			return MVC_Core::$debug-> dump("Couldn't connect to {$this-> CFG['host']}:{$this-> CFG['port']}{$secure_text}", __METHOD__, null, 'error');

		// FTP Auth and Systype
		if(isset($this-> CFG['user']) && isset($this-> CFG['pass'])) {
			$auth_result = @ftp_login($this-> conn, $this-> CFG['user'], $this-> CFG['pass']);
			if(!$auth_result) {
				ftp_close($this-> conn);
				return MVC_Core::$debug-> dump("FTP Authentication failed", __METHOD__, null, 'error');
			}
		}

		$this-> CFG['systype'] = ftp_systype($this-> conn);
		if($this-> CFG['systype'] != "UNIX") {
			ftp_close($this-> conn);
			return MVC_Core::$debug-> dump("Currently only UNIX server is supported", __METHOD__, null, 'error');
		}

		// Enter passive mode (Will to see if this is necessary on the live server or not, but for my computer it is)
		if(!empty($this-> CFG['pasv']))
			ftp_pasv($this-> conn, true);

		MVC_Core::$debug-> dump("Successful connection to {$this-> CFG['host']}:{$this-> CFG['port']}{$secure_text} ({$this-> CFG['systype']})", __METHOD__, null, 'notice');

		// Change to path where files should be located
 		if(isset($this-> CFG['path'])) {
			$path_result = @ftp_chdir($this-> conn, $this-> CFG['path']);
			if(!$path_result) {
				ftp_close($this-> conn);
				return MVC_Core::$debug-> dump("Could not change path to {$this-> CFG['path']}", __METHOD__, null, 'error');
			}
		}

		// Grab list files from the path - This is the first actual data stream
		$files = @ftp_rawlist($this-> conn, '.');
		if(!is_array($files)) {
			ftp_close($this-> conn);
			return MVC_Core::$debug-> dump("Invalid path or unable to stream data (PASV?)", __METHOD__, null, 'error');
		}

		$this-> CFG['file_count'] = count($files);

		// Decode the rawlist into something usable
		$items = array();
		foreach ($files as $file) {
			$item = array();
			$chunks = preg_split("/\s+/", $file);
			list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
			$item['type'] = $chunks[0]{0} === 'd' ? 'directory' : 'file';
			array_splice($chunks, 0, 8);
			$item['name'] = implode(" ", $chunks);
			if($item['type'] == 'file') {
				$item['ext'] = pathinfo($item['name'], PATHINFO_EXTENSION);
			}

			$item['dt'] = new DateTime($item['day'].' '.$item['month'].' '.date("Y").' '.$item['time']);

			// Mark if the file can be written or deleted
			if($item['rights'][2] == 'w')
				$item['wr'] = true;

			// Remove unneeded stuff to save memory
			unset($item['number'], $item['day'], $item['month'], $item['group'], $item['user'], $item['rights']);
			$items[] = $item;
		}
		// Sort items by filename
		usort($items, function($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		// How the FTP should be accessed ?
		if(!isset($this-> CFG['process'])) {
			// When no mode is specified, only the file count is returned (This may be used to determine if there are any files to be processed or not)
			return $this-> CFG['file_count'];
		}

		if($this-> CFG['file_count'] == 0) {
			return MVC_Core::$debug-> dump("FTP contains no files. Nothing to do.", __METHOD__, null, 'notice');
		}

		switch($this-> CFG['process']['mode']) {
			// Process all files from the FTP in no particular order (for when files are dumped into the FTP all the time)
			case 'all':

				$count_processed = 0; $dataset = array();
				foreach($items as $idx=> $file) {
					// Directories are skipped, this procedure is not recursive
					if($file['type'] == 'directory')
						continue;

					// When an extension is specified, skip files that don't have it. Otherwise process all extensions
					if(isset($this-> CFG['process']['ext']) && strtolower($file['ext'])!=strtolower($this-> CFG['process']['ext']))
						continue;

					// Grab the file
					$local_file = uniqid("_ftp_")."_".$idx;
					$get_result = ftp_get($this-> conn, $local_file, $file['name'], FTP_BINARY);
					if(!$get_result) {
						MVC_Core::$debug-> dump("Error while downloading: {$file['name']}. Skipping processing.", __METHOD__, null, 'warning');
						continue;
					}

					// Successful download ! Read the content and add it to the dataset
					$meta = array(
						'filename'=> pathinfo($file['name'], PATHINFO_FILENAME),
						'extension'=> $file['ext'],
						'type'=> mime_content_type($local_file), // This might not work
						'size'=> $file['size'],
						'dt'=> $file['dt']
					);

					$dataset[] = array(
						'meta'=> $meta,
						'stream'=> file_get_contents($local_file)
					);
					unlink($local_file);

					$count_processed++;
					if(!empty($this-> CFG['process']['limit']) && $count_processed >= $this-> CFG['process']['limit']) {
						// Process limit reached, ignoring any other files
						MVC_Core::$debug-> dump("Processing limit of {$this-> CFG['process']['limit']} files reached. Stopping", __METHOD__, null, 'notice');
						break;
					}
				}
				MVC_Core::$debug-> dump("FTP Reading Complete - Accessed {$count_processed} files", __METHOD__, null, 'notice');

				return $dataset;
			// Process newest file only (use one one single file is placed in the FTP rarely, for example a timed export)
			case 'newest1':
				$most_recent_time = null;
				$chosen_file_idx = null;
				foreach($items as $idx=> $file) {
					if(isset($this-> CFG['process']['ext']) && strtolower($file['ext'])!=strtolower($this-> CFG['process']['ext']))
						continue;

					$ts = $item['dt']-> getTimestamp();
					if($ts > $most_recent_time)
						$chosen_file_idx = $idx;
				}
				if(is_null($chosen_file_idx))
					return MVC_Core::$debug-> dump("Could not find a suitable file to download", __METHOD__, null, 'error');

				$chosen_file = $items[$chosen_file_idx];
				$local_file = uniqid("_ftp_");
				$get_result = ftp_get($this-> conn, $local_file, $chosen_file['name'], FTP_BINARY);
				if(!$get_result)
					return MVC_Core::$debug-> dump("Error while downloading file {$chosen_file['name']}", __METHOD__, null, 'error');

				ftp_close($this-> conn);

				// Finished !
				MVC_Core::$debug-> dump("FTP Connection Complete - Downloaded file: {$chosen_file['name']}", __METHOD__, null, 'notice');

				// Finally, with the temporary file obtained, read it, grab the data ( then delete it - Not implemented yet )
				$dataset = array();
				$meta = array(
					'filename'=> pathinfo($chosen_file['name'], PATHINFO_FILENAME),
					'extension'=> $chosen_file['ext'],
					'type'=> mime_content_type($local_file),
					'size'=> $chosen_file['size'],
					'dt'=> $chosen_file['dt']
				);

				$dataset[] = array(
					'meta'=> $meta,
					'stream'=> file_get_contents($local_file)
				);

				return $dataset;
			default:
				return MVC_Core::$debug-> dump("Unrecognized process mode: {$this-> CFG['process']['mode']}", __METHOD__, null, 'error');
		}

	}

	/**
	 * Deletes a file using Meta information
	 *
	 * @param array $data Must contain "filename", "extension" for it to work
	 */
	public function cmd_Delete($data)
	{
		// Delete file from FTP if allowed in config
		if(!empty($this-> CFG['process']['del_done'])) {
			$name = $data['filename'].'.'.$data['extension'];
			ftp_delete($this-> conn, $name);
			MVC_Core::$debug-> dump("Deleted file {$name}", __METHOD__, null, 'notice');
		}
	}

	public function disconnect()
	{
		ftp_close($this-> conn);
		MVC_Core::$debug-> dump("FTP Disconnected", __METHOD__, null, 'notice');
	}
}
