<?php
/**
 * Connector which grabs data from a form submit (including any uploaded files) and returns an array with information, or returns prepared data as the response of the request
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
class App_Plugin_WebAPI_connector_Provider implements App_Plugin_WebAPI_connector
{
	private $CFG;

	/**
	 * Constructor receives configuration for this instance of the plugin
	 * @param array $CFG
	 */
	public function __construct($CFG)
	{
		$this-> CFG = $CFG;
	}

	/**
	 * Analyzes the result of form submission ( through $_REQUEST and $_FILES )
	 */
	public function incoming()
	{
		if(!empty($this-> CFG['datasource'])) {
			if($this-> CFG['datasource'] == "files") {
				// The data is in the uploaded files themselves. Return an array with the file names as keys and the content of the files as values
				// !WARNING! - Large file uploads might crash PHP
				// TODO: A way to upload/import large files in a memory efficient way ( without loading in memory as a string )
				$data = array();
				foreach($_FILES as $file) {
					if(empty($file['name']) || empty($file['size']) || !empty($file['error']))
						continue; // Skip missing, empty or error files

					// TODO: Upload error check ?
					$meta = pathinfo($file['name']);
					$meta['size'] = $file['size'];
					$meta['type'] = $file['type']; // Some file formats are recognized by PHP

 					$data[] = array(
 						'meta'=> $meta,
 						'stream'=> file_get_contents($file['tmp_name'])
 					);
				}
				return $data;
			}
		}
	}

	/**
	 * Returns the data as part of the response itself (should already be encoded via the Codec to a certain format)
	 * @param mixed $data
	 */
	public function &outgoing($data)
	{
		return $data;
	}

}