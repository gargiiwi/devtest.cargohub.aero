<?php
/**
 * "CMS" records containing accepted locations for CMS pages and blog entries, plus the plugins they use
 * -- Also used in the admin module to control/operate the CMS structure
 * -- Maintains sync between actual files in the CMS structure and the database records
 *
 * Possible future extensions:
 * ~ Allow CMS pages to be grouped for better organization ( a page2group table )
 * ~ Labels (tags) associated to pages to create a "tag cloud" plugin or some sort of categorization of pages
 * ~ Comments/reply system for pages ( a comment2page tabe )
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.9
 */
class App_Model_CMS extends MVC_Model
{
	/**
	 * Root directory where the model finds content
	 * @var string
	 */
	public $root;

	/**
	 * This is a model that uses files from the disk, so set up for that.
	 *
	 * @param string $root_dir
	 */
	public function __construct($params = array())
	{
		if(is_array($params) && isset($params['root_dir']))
			$this-> root = $params['root_dir'];
		else
			$this-> root = ROOT. DS. 'cmsp';
	}

	//----- CMS Paths (pages) CRUD -----//

	/**
	 * Creates a CMS page (synchronized file and DB record)
	 * - Also creates the appropriate folders as specified in path, then the file
	 *
	 * @param string $path Unix style path, without the root. Example "docs/mail/readme.html" - Creates "docs" and "mail" directories if they didn't exist, then the file named "readme.html"
	 * @param string $contents Content of the file. Can be arbitrarily long, stored on disk as it is. If missing, empty file will be created anyway
	 * @param string $title Title of the page, used by the viewer
	 * @param array $plugins Plugin definition, used by the viewer
	 * @param string $description What is this page about - Used by the editor
	 *
	 * @return int|bool False if the file already exists or there's an error in the path, Int value > 1 if the CMS page has been created (database insert id)
	 */
	public function create($path, $contents = "", $title = "", $plugins = null, $description = null)
	{
		// Check if file on final location already exists and report if so
		if(file_exists($this-> root. $path. ".html"))
			return "The page on the specified path already exists. Please search for it in the tree.";

		// Extract the last part of the path, as the file name itself.
		$file = basename($path);
		$dir = dirname($path);

		// Make directory if necessary
		if(strlen($dir) > 0)
			if(!is_dir($this-> root. $dir))
				if(!mkdir($this-> root. $dir, 0777, true))
					return "Unable to create the specified path. Invalid characters or another error occured.";

		// ... and create the file itself
		$r = file_put_contents($this-> root. $path. ".html", $contents);

		if($r === false)
			return "Unable to create or write into the file. Possible filesystem error.";

		// File should exist, now create the matching database entry.
		$meta = array(
			'path'=> $path,
			'file_length'=> strlen($contents),
			'active'=> 1, // TODO: Support Activating / Deactivating pages!
			'title'=> $title
		);
		if(!is_null($plugins))
			$meta['plugins'] = json_encode($plugins);
		if(!is_null($description))
			$meta['description'] = $description;

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> table('cms'))
		-> values($meta);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Error, could not insert into DB

		$iid = MVC_Core::$db-> insert_id;
		return $iid;
	}

	/**
	 * Reads only the database record related to one CMS page (but not the content),
	 * -- used to determine if a page exists or not with the purpose of displaying it using the template system
	 *
	 * @param string $path
	 * @return array Page information, or false if not found in DB
	 */
	public function check($path)
	{
		if($path[0] != '/')
			$path = '/'.$path;

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['cms'])
		-> where("`path` = ?", $path)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Error, page not found

		$page = $r-> current();

		// Post processing
		$page['id'] = (int)$page['id'];
		$page['active'] = (bool)$page['active'];
		if(!empty($page['plugins']))
			$page['plugins'] = @json_decode($page['plugins'], true);

		return $page;
	}

	/**
	 * Reads the full CMS page (database record and content), locating it by either db ID or the path
	 * -- Used in the CMS editor module to display information and file content
	 *
	 * @param int|string $id_or_path
	 */
	public function read($id_or_path)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['cms'])
		-> limit(1);

		if(is_numeric($id_or_path))
			$q-> where("`id` = ?", $id_or_path);
		else {
			// Replace backslashes with slashes for the purpose of uniformity
			$q-> where("`path` = ?", $id_or_path);
		}

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Error, page not found

		$page = $r-> current();

		// Post processing
		$page['id'] = (int)$page['id'];
		$page['active'] = (bool)$page['active'];
		if(!empty($page['plugins']))
			$page['plugins'] = @json_decode($page['plugins'], true);

		// Also read the file contents
		$page['contents'] = file_get_contents($this-> root. $page['path']. '.html');
		return $page;
	}

	/**
	 * Reads the entire subfolder structure starting with a root and creates an array out of it, which can be then filtered and sent to the template system
	 * -- Recursive, only stops when all files have been detected, careful not to use on system root or any other structure with too many files
	 *
	 * @param string $dir The start directory, defaults to / (
	 */
	public function read_FileTree($dir = DS)
	{
		$result = array();
		foreach(scandir($this-> root. $dir) as $key => $value) {
			if(!in_array($value, array(".", "..", ".svn", "404.html", "_preview.html"))) {
				if(is_dir($this-> root. $dir. $value))
					$result[$value] = array('name'=> ucfirst($value), 'children'=> $this-> read_FileTree($dir. $value. DS));
				else {
					$value = substr($value, 0, -5);
					$result[$value] = array('name'=> ucfirst($value), 'path'=> base64_encode(str_replace("\\", "/", $dir). $value)); // path is the relative path to the CMS root
				}
			}
		}

		ksort($result);
		return $result;
	}

	/**
	 * Updates a CMS page information (database, file or both)
	 * -- TODO: Will be used in the CMS management module to update a page when saving from the editor
	 *
	 * @param int|string $id_or_path
	 * @param string $page_content New contents for the CMS file
	 * @param array $meta Can contain an update to the "active" field, new title, new content, new plugins structure or new description
	 */
	public function update($id_or_path, $page_content, $meta_update)
	{
		if(empty($id_or_path))
			return false; // Must have a value

		// First attempt to locate the file in the database to get both values (id AND path)
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['cms'], array('id', 'path', 'adminlock'))
		-> limit(1);
		

		if(is_numeric($id_or_path))
			$q-> where("`id` = ?", $id_or_path);
		else {
			// Replace backslashes with slashes for the purpose of uniformity
			$q-> where("`path` = ?", $id_or_path);
		}

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Error, page not found

		$page = $r-> current();

		if($page['adminlock'] == 'edit')
			return MVC_Core::$debug-> dump("AdminLock prevents updating this page", __METHOD__, null, 'error');

		// Can only update title and description (for now)
		$meta = array();
		if(isset($meta_update['title']))
			$meta['title'] = trim($meta_update['title']);
		if(isset($meta_update['description']))
			$meta['description'] = trim($meta_update['description']);
		$meta['file_length'] = strlen($page_content);

		// At this point we should have the ID and all data
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> table('cms'))
		-> where("`id` = ?", $page['id'])
		-> values($meta);
		

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Could not update the database", __METHOD__, null, 'error');

		// Finally, update the file itself
		return file_put_contents($this-> root. $page['path']. '.html', $page_content);
	}

	/**
	 * Deletes a CMS page (both record and corresponding file)
	 *
	 * @param int|string $id_or_path
	 */
	public function delete($id_or_path)
	{
		if(empty($id_or_path))
			return false; // Must have a value

		// First attempt to locate the file in the database to get both values (id AND path)
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['cms'], array('id', 'path', 'adminlock'))
		-> limit(1);

		if(is_numeric($id_or_path))
			$q-> where("`id` = ?", $id_or_path);
		else {
			// Replace backslashes with slashes for the purpose of uniformity
			$q-> where("`path` = ?", $id_or_path);
		}

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("The CMS path could not be found", __METHOD__, null, 'error');

		$page = $r-> current();

		if($page['adminlock'] == 'delete' || $page['adminlock'] == 'edit' || $page['adminlock'] == 'toggle')
			return MVC_Core::$debug-> dump("AdminLock prevents deleting this page", __METHOD__, null, 'error');

		// At this point we should have the ID and all data
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['cms'])
		-> where("`id` = ?", $page['id'])
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Could not update the database", __METHOD__, null, 'error');

		// Finally, delete the file itself.
		// Any created folders will remain in place, even if they contain no files.
		return unlink($this-> root. $page['path']. '.html');
	}

	/**
	 * Saves the special "_preview" page in the CMS root (this is a temporary file used when editing contents)
	 * @param string $contents
	 */
	public function save_Preview($contents)
	{
		return file_put_contents($this-> root. DS. '_preview.html', $contents);
	}

	/**
	 * Returns the contents of the CMS preview file
	 */
	public function read_Preview()
	{
		return file_get_contents($this-> root. DS. '_preview.html');
	}

	/**
	 * TODO: Reads the list of available pages (database searchable), used in the future CMS management module
	 *
	 * @return array (or DB result)
	 */
	public function get_List()
	{

	}

	/**
	 * Pre-process tag list (trim any spaces). Also builds tag cloud.
	 * @param string $taglist
	 * @return string
	 */
	private function blog_PreProcess_Taglist($taglist)
	{
		// When taglist string, convert to array first so each individual tag can be trimmed
		if(is_string($taglist))
			$taglist = explode(',', $taglist);
		foreach($taglist as &$tag)
			$tag = trim($tag);

		// TODO: Build Tag Cloud?

		return implode(',', array_filter($taglist));
	}

	/**
	 * Writes an entry to the CMS Blog table.
	 *
	 * @param string $content The entry's text (usually HTML)
	 * @param array $params Without any parameters the date becomes "now" and there is not title or other categorization
	 * Possible keys: "org_id", "group_id", "dt", "title", "taglist"
	 * @param string $mode Optional pre-processing of the content (nl2br, or filtering) - Will be used later when giving users access to this system
	 * - "html" Nothing is done, the content is stored unfiltered in any way (content comes from TinyMCE)
	 * - "text" NL's are converted to <br /> - Text is expected to come from a <textarea /> or similar non-rich editor
	 */
	public function blog_Create($content, $params = array(), $mode = 'html')
	{
		$content = trim($content);
		if(empty($content))
			return MVC_Core::$debug-> dump("Blog entry content is empty", __METHOD__, null, 'error');

		$blog_entry = array(
			'dt'=> isset($params['dt'])? $params['dt']: date('Y-m-d H:i:s'),
			'title'=> isset($params['title'])? trim($params['title']): null,
			'content'=> $content
		);
		if(!empty($params['mode']) && $params['mode'] == 'organization') {
			$blog_entry['org_id'] = MVC_Core::$ss-> user['org_id'];
			$blog_entry['user_id'] = MVC_Core::$ss-> user['id'];
		}

		if(isset($params['group_id'])) {
			// TODO: Verify if group exists and is of type "Blog Category"

			$blog_entry['group_id'] = (int)$blog_entry['group_id'];
			// TODO: Increase the number of entries for that group
		}

		if(isset($params['taglist']))
			$blog_entry['taglist'] = $this-> blog_PreProcess_Taglist($params['taglist']);

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['blg'])
		-> values($blog_entry);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Error, could not insert into DB", __METHOD__, null, 'error');

		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Read one single entry (usually used by the editor). Returns complete data or just the "content"
	 *
	 * @param int $id
	 * @param bool $content_only
	 * @return array|string
	 */
	public function blog_Read_Entry($id, $content_only = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['blg'])
		-> where("`id` = ?", $id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // Error, page not found

		$blog_entry = $r-> current();
		if($content_only)
			return $blog_entry['content'];
		else {
			// TODO: Post process the entry a bit and return

			return $blog_entry;
		}
	}

	/**
	 * Updates a blog entry (change the text) - Still, it's possible to alter other fields if present in $params
	 *
	 * @param int $id
	 * @param string $content
	 * @param array $params
	 */
	public function blog_Update_Entry($id, $content = null, $params = array())
	{
		$blog_entry_update = array();

		if(!empty($content))
			$blog_entry_update['content'] = trim($content);
		if(!empty($params['dt']))
			$blog_entry_update['dt'] = $params['dt'];
		if(!empty($params['title']))
			$blog_entry_update['title'] = $params['title'];

		if(!empty($params['mode']) && $params['mode'] == 'organization') {
			// Stores the user id who made the update
			$blog_entry_update['user_id'] = MVC_Core::$ss-> user['id'];
		}

		if(isset($params['taglist']))
			$blog_entry_update['taglist'] = $this-> blog_PreProcess_Taglist($params['taglist']);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['blg'])
		-> values($blog_entry_update)
		-> where('`id` = ?', $id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Deletes an entry when knowing it's ID (used by the blog manager module)
	 * @param int $id
	 */
	public function blog_Delete_Entry($id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['blg'])
		-> where('`id` = ?', $id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads multiple blog entries
	 *
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows
	 * @param int $start_row
	 * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
	 * @return MVC_Database_Result
	 */
	public function blog_Read($qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row, $calc_found_rows);

		$q-> table(MVC_Core::$db-> table('blg'));

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false;// failed to read any records

		$this-> get_List_Save_FoundRows();

		return $r;
	}

    /**
     * @param $days
     * @param $limit
     * @return array
     * @throws Exception
     */
    public function fetch_RecentUpdates($days, $limit){
        $result=array();
        $q = "SELECT `title`,`content`,`dt` FROM `".MVC_Core::$db-> tab['blg']."` WHERE dt>=DATE_ADD(CURDATE(), INTERVAL -".$days." DAY) order by dt desc limit ".$limit." ";
        $r = MVC_Core::$db-> query($q);
        if($r && $r-> num_rows >= 1) {
            foreach ($r as $key => $row){
                $result[$key]['title'] = $row['title'];
                $result[$key]['content'] = $row['content'];
                $result[$key]['dt'] = $row['dt'];
            }
        }
        return $result;
    }

}