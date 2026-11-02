<?php
/**
 * MVC Warehouse - Analyzer and Processor for image files
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage MVC Framework.Warehouse
 * @version 2019.10.01
 */
class MVC_Warehouse_Image implements MVC_Analyzer
{
	public static function register_Mime_Types()
	{
		// these are the mime types for which this analyzer module responds
		return array(
			image_type_to_mime_type(IMAGETYPE_GIF),
			image_type_to_mime_type(IMAGETYPE_JPEG),
			image_type_to_mime_type(IMAGETYPE_PNG),
			image_type_to_mime_type(IMAGETYPE_SWF),
			image_type_to_mime_type(IMAGETYPE_PSD),
			image_type_to_mime_type(IMAGETYPE_BMP),
			image_type_to_mime_type(IMAGETYPE_TIFF_II),
			image_type_to_mime_type(IMAGETYPE_TIFF_MM),
			image_type_to_mime_type(IMAGETYPE_JP2),
			image_type_to_mime_type(IMAGETYPE_SWC),
			image_type_to_mime_type(IMAGETYPE_IFF),
			image_type_to_mime_type(IMAGETYPE_WBMP),
			image_type_to_mime_type(IMAGETYPE_XBM),
			image_type_to_mime_type(IMAGETYPE_ICO),
			'image/vnd.adobe.photoshop'
		);
	}

	/**
	 * Initializes the image analysis
	 * @param array $fileinfo
	 * @param array $CFG
	 */
	public function __construct(&$fileinfo, $CFG)
	{
		// "imageinfo" disabled, can't decode/process it properly yet
// 		$imageinfo = array();
		$imagedata = getimagesize($fileinfo['_fullpath']); // , $imageinfo

		if($imagedata!==false) {
			$fileinfo['_image'] = array(
				'width'=> $imagedata[0],
				'height'=> $imagedata[1],
				'type'=> $imagedata['mime'],
			);
			if(isset($imagedata['bits']))
				$fileinfo['_image']['bits'] = $imagedata['bits'];
/* 			if(!empty($imageinfo)) {
				if(isset($imageinfo['APP13'])) {
					$iptc = iptcparse($imageinfo['APP13']);
					if(!empty($iptc))
						$fileinfo['_image']['aptc'] = $iptc;
				}
				$fileinfo['_image']['info'] = $imageinfo;
			}
 */
			// Exif turned on - Try to read Exif from image/jpeg
			if(!empty($CFG['exif']) && $fileinfo['mimetype'] == 'image/jpeg') {
				$exif = self::get_Image_Exif($fileinfo['_fullpath'], isset($CFG['exifThm']) && $CFG['exifThm']=='true');
				if($exif!==false)
					$fileinfo['_image']['exif'] = $exif;
			}

			// Scaler turned on - Read the image and generate scaled versions (thumbnails) according to the specified parameters in the configuration
			if(!empty($CFG['scaler']) && in_array($fileinfo['mimetype'], array('image/jpeg', 'image/png', 'image/gif'))) {
				foreach($CFG['scaler'] as $scaler_name=> $scaler_config)
					self::scale_Image($fileinfo, $scaler_config, $scaler_name);
			}
		}
	}

	/**
	 * Creates a small and a large thumbnail out of a warehouse file of type image/jpeg, image/png or image/gif
	 * -- Other formats are currently not supported; After upgrading to PHP 7, Windows Bitmap will also be loadable
	 * -- Suplimentary, if in the future these functions will use ImageMagik, more formats will be supported
	 * The files are then saved in /htdocs/uimg/thms and /htdocs/uimg/thml as JPG's
	 * -- thms - compression ratio 70% (tiny files, might get moved in the DB later as blobs); thml - compression ratio 85% (keeps most of the image quality)
	 *
	 * @param array $fileinfo Line in the "files" table, or line that is about to be entered in the files table - Must contain elements: ["filepath", "mimetype", "chash"] and the file specified by filepath should exist
	 * @param array $config Configuration for the scaler
	 * -- May contain 'store_mode' (what to do with the scaled image), 'path' (mandatory for store modes that use files), 'maxpixels' (def: 128), 'quality' (def: 75, or -1)
	 * @param string $name Name of the current scaler configuration (used in storing scaler metadata) - If not present, metadata is stored as next numeric array key
	 *
	 * @return Creates or appends to the $file_record the element "metadata" (array) which will contain {"thms": <size>, "thml": <size>}
	 * -- The calling method can then either update existing file reference, or create one with the thumbnails information already set
	 */
	public static function scale_Image(&$fileinfo, $config, $name = null)
	{
		if(!isset($config['maxpixels']))
			$config['maxpixels'] = 128;
		if(!isset($config['quality']))
			$config['quality'] = -1; // Default (75?)

		if(!isset($config['store_mode']))
			throw new Exception("Scaler store mode not present in configuration");

		// Prepare image storage path (unless the mode is "stream")
		switch($config['store_mode']) {
			case 'direct':
				// Store the file directly, using the file "name" (must be present in $fileinfo) - No directories are being created
				// -- Only use when controling the image naming well, for example for CMS/News/Logos, etc. because high chance of naming conflict and overwriting files
				$storagepath = $config['path'];
				$basename = $fileinfo['_basename'].'.jpg';
				break;
			case 'chash1':
				// Use $fileinfo['chash'] - First character becomes the directory - max 16 (use for small scale uploads where there are not many images uploaded, like customer logos/signatures)
				$storagepath = $config['path']. DS. substr($fileinfo['chash'], 0, 1);
				$basename = substr($fileinfo['chash'], 1). '.jpg';
				break;
			case 'chash2':
				// Use $fileinfo['chash'] - First two characters become the directory - max 256 (use for large scale uploads like primary warehouse storage)
				$storagepath = $config['path']. DS. substr($fileinfo['chash'], 0, 2);
				$basename = substr($fileinfo['chash'], 2). '.jpg';
				break;
			case 'chash12':
				// TODO: Use $fileinfo['chash'] - First character becomes the directory, second and third subdirectory - max 4096 dirs
				// -- use for ultra scale deployment, when the first level directory becomes a mapping to remote servers
			case 'stream':
				// TODO: Return the scaled image stream, to be stored for example in a database, or base64-encoded inside E-mail as embedded image
				break;
			default:
				throw new Exception(sprintf("Scaler store mode [%s] not supported", $config['store_mode']));
		}

		// Load the image into GD
		switch($fileinfo['mimetype']) {
			case 'image/png':
				$si = @imagecreatefrompng($fileinfo['_fullpath']);
				break;
			case 'image/jpeg':
				$si = @imagecreatefromjpeg($fileinfo['_fullpath']);
				break;
			case 'image/gif':
				$si = @imagecreatefromgif($fileinfo['_fullpath']);
				break;
			default:
				return false; // Error, the file is not an image, nothing to do here.
		}
		if(!$si)
			$di = self::show_Image_Load_Error($fileinfo['name'], $config['maxpixels']);
		else {
			// If the source image is smaller than $config['maxpixels'], the image is not scaled in any way, and $di becomes $si
			$maxdimension = max($fileinfo['_image']['width'], $fileinfo['_image']['height']);
			if($maxdimension <= $config['maxpixels']) {
				$di = $si; $not_scaled = true;
				$width = $fileinfo['_image']['width'];
				$height = $fileinfo['_image']['height'];
			} else {
				// At least one dimension is bigger than maxpixels, we need to scale, so neither of the dimensions are bigger than $config['maxpixels'];
				$ratio = $fileinfo['_image']['width']/$fileinfo['_image']['height'];
				if($ratio >= 1) {
					$width = $config['maxpixels'];
					$height = (int)($config['maxpixels'] / $ratio);
				} else {
					$width = (int)($config['maxpixels'] * $ratio);
					$height = $config['maxpixels'];
				}
				$di = imagecreatetruecolor($width, $height);
				imagecopyresampled($di, $si, 0, 0, 0, 0, $width, $height, $fileinfo['_image']['width'], $fileinfo['_image']['height']);
			}
		}

		// Make sure the destination path exists:
		if(!is_dir($storagepath))
			mkdir($storagepath, 0777, true);
		// Output the generated scaled image. Done !
		$r = imagejpeg($di, $storagepath. DS. $basename, $config['quality']);
		if(empty($not_scaled))
			imagedestroy($di);
		imagedestroy($si);

		if(!$r)
			return false; // Error on file create

		// Get stored scaled image file info (for size)
		$sii = new SplFileInfo($storagepath. DS. $basename);

		// Construct the result (path fragment, scaled image width/height, size of the file that was stored, etc.)
		$scaler_metadata = array(
			'width'=> $width,
			'height'=> $height,
			'fsize'=> $sii-> getSize()
		);
		if(!isset($fileinfo['_image']['scaler']))
			$fileinfo['_image']['scaler'] = array();
		if(is_string($name))
			$fileinfo['_image']['scaler'][$name] = $scaler_metadata;
		else
			$fileinfo['_image']['scaler'][] = $scaler_metadata;

		return $r;
	}

	/**
	 * Little helper to show an error text in case the image could not be loaded (invalid JPG/PNG/GIF)
	 * @param string $name Name of the image
	 * @param int $maxpixels The size of the error image (will be a rectangle width:height 4:1)
	 */
	private static function show_Image_Load_Error($name, $maxpixels)
	{
		$im  = imagecreatetruecolor($maxpixels, (int)($maxpixels/4));
		$bgc = imagecolorallocate($im, 255, 255, 255);
		$tc  = imagecolorallocate($im, 0, 0, 0);
		imagefilledrectangle($im, 0, 0, $maxpixels, ($maxpixels/4), $bgc);

		if($maxpixels > 200) {
			imagestring($im, 1, 5, 5, 'Error loading '. $name, $tc);
		} else {
			imagestring($im, 1, 5, 5, 'Error loading', $tc);
			imagestring($im, 1, 5, 25, $name, $tc);
		}
		return $im;
	}

	/**
	 * Reads EXIF data from an image
	 *
	 * @param string $filepath
	 * @param bool $get_thumbnail
	 * @param bool $remove_makernote
	 */
	public static function get_Image_Exif($filepath, $get_thumbnail = false, $remove_makernote = true)
	{
		$exif = exif_read_data($filepath, null, true, $get_thumbnail);

		if($exif === false)
			return false; // no exif header found (or not really a jpeg)

		if($remove_makernote) { // remove this, can't decode it yet.
			unset($exif['EXIF']['MakerNote']);
		}
		// remove [FILE], it's irrelevant ( data not actually stored inside image )
		unset($exif['FILE']);

		return $exif;
	}

}
