<?php
/**
 * A simple mail sender class which follows MIME standard ( RFC 2822 - Internet Message Format )
 * Allows sending of hybrid Text + HTML e-Mail, and also attachments
 * Supports multiple recipients in all forms ( To:, Cc:, Bcc: )
 *
 * This object is a one-shot run, once the e-mail has been set (or assambled as file) it cannot be reused.
 *
 * Note: For now, some code is borrowed from Zend Framework's "Mime" module, until a better way to do it is found.
 *
 * @author Wavetrex <wavetrex@gmail.com>
 */
class MimeMail
{
	// Error codes
	const
		ERR_INVALID_CONTACT_TYPE			=  2,
		ERR_FROM_ALREADY_SET				=  3,
		ERR_REPLYTO_ALREADY_SET				=  4,
		ERR_MISSING_MAIN_RECIPIENT			=  5, // no "To:" entry in contacts
		ERR_SENDER_NOT_SET					= 10,

		CHARSET = "UTF-8", // latin1 ( most common codepage ) - http://en.wikipedia.org/wiki/ISO/IEC_8859-1
		LINELENGTH = 75,
		LINEEND = "\n",
		MIMEMULTIPART = "This is a multi-part message in MIME format.";

	/**
	 * Characters which are not safe for transport (and must be encoded with Q encoding )
	 * @var string
	 */
	protected static $qChars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";
	protected static $qHSpecial = "()<>[]:;@\\,.\"";
	protected static $qcSearch = array();
	protected static $qcReplace = array();

	protected static $accepted_contact_types = array('To','Cc','Bcc','From','Reply-To');

	/**
	 * If true, there was a problem in building the e-Mail, cannot continue
	 * @var bool
	 */
	private $_has_error = false;

	public function __construct()
	{
		// build qcSearch and qcReplace at first use of this class
		if(empty(self::$qcSearch) && empty(self::$qcReplace)) {
			self::$qcSearch = str_split(self::$qChars, 1);
			foreach(self::$qcSearch as $char) {
				$chr = dechex(ord($char));
				if(strlen($chr)==1) $chr = '0'.$chr;
				self::$qcReplace[] = '='.strtoupper($chr);
			}
		}

		$this-> headers['MIME-Version'] = "1.0";
		$this-> headers['X-Mailer'] = 'PHP/'.phpversion().'/WTX-Mime-Mail';
	}

	/* Encoding methods */

	/**
	 * Test if string contains no unprintable characters. If this returns false, the string should be encoded for secure delivery.
	 *
	 * @param string $str
	 * @return boolean
	 */
	public static function is_Printable($str)
	{
		return (strcspn($str, self::$qChars) == strlen($str));
	}

	public static function is_NotHeaderSpecial($str)
	{
		return (strcspn($str, self::$qHSpecial) == strlen($str));
	}

	/**
	 * Encodes a string into QUOTED_PRINTABLE format (by replacing unprintable characters with their =XX equivalents)
	 *
	 * @param string $str
	 * @return string
	 */
	private static function encode_QPString($str)
	{
		$str = str_replace('=', '=3D', $str); // make sure = is not misinterpreted
		$str = rtrim(str_replace(self::$qcSearch, self::$qcReplace, $str));
		return $str;
	}

	/**
	 * Encode a given string with the QUOTED_PRINTABLE mechanism for Mail Headers.
	 *
	 * Mail headers depend on an extended quoted printable algorithm otherwise
	 * a range of bugs can occur.
	 *
	 * @param string $str
	 * @param string $charset
	 * @param int $lineLength
	 * @param string $lineEnd
	 * @return string
	 */
	public static function encode_QPHeader($str, $charset = self::CHARSET, $lineLength = self::LINELENGTH, $lineEnd = self::LINEEND)
	{
		// Reduce line-length by the length of the required delimiter, charsets and encoding
		$prefix = sprintf('=?%s?Q?', $charset);
		$lineLength = $lineLength-strlen($prefix)-3;

		$str = self::encode_QPString($str);

		// Mail-Header required chars have to be encoded also:
		$str = str_replace(array('?', ' ', '_'), array('=3F', '=20', '=5F'), $str);

		// initialize first line, we need it anyways
		$lines = array(0 => "");

		// Split encoded text into separate lines
		$tmp = "";
		while (strlen($str) > 0) {
			$currentLine = max(count($lines)-1, 0);
			$token	   = self::getNextQuotedPrintableToken($str);
			$str		 = substr($str, strlen($token));

			$tmp .= $token;
			if ($token == '=20') {
				// only if we have a single char token or space, we can append the
				// tempstring it to the current line or start a new line if necessary.
				if (strlen($lines[$currentLine] . $tmp) > $lineLength) {
					$lines[$currentLine+1] = $tmp;
				} else {
					$lines[$currentLine] .= $tmp;
				}
				$tmp = "";
			}
			// don't forget to append the rest to the last line
			if (strlen($str) == 0) {
				$lines[$currentLine] .= $tmp;
			}
		}

		// assemble the lines together by pre- and appending delimiters, charset, encoding.
		for ($i = 0; $i < count($lines); $i++) {
			$lines[$i] = " " . $prefix . $lines[$i] . "?=";
		}
		$str = trim(implode($lineEnd, $lines));
		return $str;
	}

	/**
	 * Encode a given string with the QUOTED_PRINTABLE mechanism and wrap the lines.
	 *
	 * @param string $str
	 * @param int $lineLength
	 * @param string $lineEnd
	 * @return string
	 */
	public static function encode_QP($str, $lineLength = self::LINELENGTH, $lineEnd = self::LINEEND)
	{
		$out = '';
		$str = self::encode_QPString($str);

		// Split encoded text into separate lines
		while ($str) {
			$ptr = strlen($str);
			if ($ptr > $lineLength) {
				$ptr = $lineLength;
			}

			// Ensure we are not splitting across an encoded character
			$pos = strrpos(substr($str, 0, $ptr), '=');
			if ($pos !== false && $pos >= $ptr - 2) {
				$ptr = $pos;
			}

			// Check if there is a space at the end of the line and rewind
			if ($ptr > 0 && $str[$ptr - 1] == ' ') {
				--$ptr;
			}

			// Add string and continue
			$out .= substr($str, 0, $ptr) . '=' . $lineEnd;
			$str = substr($str, $ptr);
		}

		$out = rtrim($out, $lineEnd);
		$out = rtrim($out, '=');
		return $out;
	}

	/**
	 * Retrieves the first token from a quoted printable string.
	 *
	 * @param  string $str
	 * @return string
	 */
	private static function getNextQuotedPrintableToken($str)
	{
		if (substr($str, 0, 1) == "=") {
			$token = substr($str, 0, 3);
		} else {
			$token = substr($str, 0, 1);
		}
		return $token;
	}

	/**
	 * Encode a given string in mail header compatible base64 encoding.
	 *
	 * @param string $str
	 * @param string $charset
	 * @param int $lineLength Defaults to {@link LINELENGTH}
	 * @param string $lineEnd Defaults to {@link LINEEND}
	 * @return string
	 */
	public static function encodeBase64Header($str, $charset = self::CHARSET, $lineLength = self::LINELENGTH, $lineEnd = self::LINEEND)
	{
		$prefix = '=?' . $charset . '?B?';
		$suffix = '?=';
		$remainingLength = $lineLength - strlen($prefix) - strlen($suffix);

		$encodedValue = rtrim(self::encodeBase64($str, $remainingLength, $lineEnd));
		$encodedValue = str_replace($lineEnd, $suffix . $lineEnd . ' ' . $prefix, $encodedValue);
		$encodedValue = $prefix . $encodedValue . $suffix;
		return $encodedValue;
	}

	/**
	 * Encode a given string in base64 encoding and break lines
	 * according to the maximum linelength.
	 *
	 * @param string $str
	 * @param int $lineLength Defaults to {@link LINELENGTH}
	 * @param string $lineEnd Defaults to {@link LINEEND}
	 * @return string
	 */
	public static function encodeBase64($str, $lineLength = self::LINELENGTH, $lineEnd = self::LINEEND)
	{
		return chunk_split(base64_encode($str), $lineLength, $lineEnd);
	}

	/* E-mail data storage (headers, parts) */

	/**
	 * List of contacts involved in this e-mail, and their type ( key = contact type, value = email address and optionally name )
	 * By default, first one is a "To", next ones are "Cc"
	 * Possible contact types: ["To", "Cc", "Bcc"] (multiple), ["From", "Reply-To"] (single; "From" is mandatory, unless set in the system)
	 * @var Array
	 */
	protected $contacts;

	/**
	 * List of extra headers, including "Subject" (key = header type, value = header content)
	 * Things like Subject, User-Agent, MIME-Version, X-Mailer etc. (Here, only one of each)
	 * The final e-mail will also contain Contacts in headers, a header with Content-Type for the root Mime_Part and possibly system-added headers
	 *
	 * @var array
	 */
	protected $headers;

	/**
	 * A tree of e-mail parts ( can be just one, text/plain for the simplest message, or more complex, with multipart/mixed etc. )
	 * The e-mail message is assambled from these parts, while $this-> content (root part) will have it's content_type inside the e-mail headers themselves.
	 * (When having children parts, the rest of them have their own headers, which go inside the body of the message)
	 *
	 * @var wtx_Mime_Part
	 */
	protected $content;

	/* E-mail assembly methods */

	/**
	 * Adds a contact to this e-mail message (recipients, sender, return address)
	 * Formats a proper contact information like: Name <email@domain.com>
	 *
	 * @param string $recipient_email TODO: Also verify if email is actually an e-mail, and trigger an error if it's not.
	 * @param string $type Can be one of "To", "Cc", "Bcc", "From", "Reply-To". If something else, an error is triggered.
	 * @param string $recipient_name Name of the recipient (optional)
	 */
	public function add_Contact($contact_email, $type = null, $contact_name = null)
	{
		// default $type
		if(is_null($type)) {
			if(count($this-> contacts) == 0)
				$type = 'To';
			else
				$type = 'Cc';
		}
		// check types
		if(in_array($type, self::$accepted_contact_types)) { // correct

			if($type == 'From' && isset($this-> contacts['From']) && count($this-> contacts['From'])==1)
				$this-> trigger_Error(self::ERR_FROM_ALREADY_SET, "Sender e-mail (From:) already set.");
			if($type == 'Reply-To' && isset($this-> contacts['Reply-To']) && count($this-> contacts['Reply-To'])==1)
				$this-> trigger_Error(self::ERR_REPLYTO_ALREADY_SET, "Reply address (Reply-To:) already set.");

			$contact = '';
			$contact_name = trim($contact_name);
			$contact_email = trim($contact_email);
			if(!is_null($contact_name)) {
				if(self::is_Printable($contact_name) && self::is_NotHeaderSpecial($contact_name)) // Characters not allowed in the name part
					$contact.= $contact_name;
				else
					$contact.= self::encode_QPHeader($contact_name);
				$contact.= ' <'.$contact_email.'>';
			} else
				$contact = $contact_email;

			$this-> contacts[$type][] = $contact;
		} else
			$this-> trigger_Error(self::ERR_INVALID_CONTACT_TYPE, "Invalid contact type: {$type}");

		return $this;
	}

	/**
	 * Sets an arbitrary header to the e-mail (use cautiously !), and also encodes the header if necessary
	 *
	 * @param string $header_type
	 * @param string $header_content
	 */
	public function set_Header($header_type, $header_content)
	{
		if(self::is_Printable($header_content))
			$this-> headers[ucfirst($header_type)] = $header_content;
		else
			$this-> headers[ucfirst($header_type)] = self::encode_QPHeader($header_content);
		return $this;
	}

	/**
	 * Sets the subject of the e-mail.
	 * This method actually adds a "Subject" header, or overwrites existing one
	 *
	 * @param string $email_subject
	 */
	public function set_Subject($email_subject)
	{
		// note that when using the "mail()" PHP function, this header will be extracted and used as the second parameter of the function
		$this-> set_Header("Subject", utf8_decode($email_subject));
		return $this;
	}

	/* Content elements, will be transformed into $this->content as wtx_Mime_Part, at assembly time. Until then, they are simply stored */

	/**
	 * Text version of the e-mail.
	 * It's the first that appears in a message.
	 * @var string
	 */
	protected $content_textPlain;

	/**
	 * HTML version of the e-mail.
	 * If both textPlain and textHTML are inside the message, a multipart/alternative mime-part is created.
	 * @var string
	 */
	protected $content_textHTML;

	/**
	 * Array of file names (which should be accesible by the script), they will become embedded elements for the HTML version
	 * This array uses keys as the Content-ID for each embedded file (must be unique), so they correspond to the references that migth appear in the textHTML document.
	 * If there are any embeds, a multipart/related mime-part is created.
	 *
	 * @var array
	 */
	protected $content_textHTML_embeds;

	/**
	 * Array of file names (which should be accesible by the script), they will become attachments in the e-Mail
	 * If there are any attachments, a multipart/mixed mime-part is created.
	 * @var array
	 */
	protected $content_attachments;

	/**
	 * Sets the simple text/plain version of the message
	 *
	 * @param unknown_type $message
	 */
	public function set_textPlain($message)
	{
		$this-> content_textPlain = (string)$message; // not sure if this needs to be encoded in any way
		return $this;
	}

	/**
	 * Sets the text/html version of the message, optionally auto-generating the text/plain version as well
	 *
	 * @param string $message
	 * @param bool $autogenerate_textPlain
	 */
	public function set_textHTML($message, $autogenerate_textPlain = false)
	{
		if($autogenerate_textPlain)
			$this-> content_textPlain = utf8_decode(html2text::convert_html_to_text($message));

		$this-> content_textHTML = $message; // not sure if this needs to be encoded in any way
		return $this;
	}

	/**
	 * Adds a file as an embedded element (can be referenced from the HTML part)
	 * Note that if a textHTML has not been defined, all the embedded files are ignored
	 *
	 * @param string $filename
	 * @param string $content_id
	 */
	public function add_HTMLEmbedded($filename, $content_id)
	{
		// TODO: Check if the file exists and is accessible
		$this-> content_textHTML_embeds[$content_id] = $filename;
		return $this;
	}

	public function add_Attachment($filename, $as_name = null)
	{
		// TODO: Check if the file exists and is accessible
		if(!is_null($as_name))
			$this-> content_attachments[] = array($filename, $as_name);
		else
			$this-> content_attachments[] = $filename;
		return $this;
	}

	/* e-Mail assembly and sending methods */

	/**
	 * Assembles the correct e-mail message (headers, message)
	 */
	protected function assemble_Message($use_mail_function)
	{
		if($use_mail_function)
			unset($this-> headers['Subject']); // remove this, not part of the headers

		// the actual headers that will be sent
		$headers = array();
		$message_id_set = false;
		foreach($this-> headers as $header=> $content) {
			$headers[] = "{$header}: {$content}";
			if($header == "Message-ID")
				$message_id_set = true;
		}
		// If Message-ID was not part of the preset headers, set a default one now
		if(!$message_id_set)
			$header[] = array('Message-ID: '.uniqid('wtxmm').'@'.$_SERVER['SERVER_NAME']);

		// then, the contacts (4 headers max),
		foreach($this-> contacts as $c_type=> $c_list) {
			// To: excluded when using "mail" function - It is kept however for SMTP (future)
			if($use_mail_function && $c_type=="To")
				continue;
			$headers[] = "{$c_type}: ".implode(", ", $c_list);
		}

		// set up parts depending on what elements we have stored ( in $this-> content_* )

		// 1. Check if we have a HTML version, in which case, check if there are embeds
		if(!empty($this-> content_textHTML)) {

			// 1.1 Check if we have embedded files, if so, create a multipart/related
			if(!empty($this-> content_textHTML_embeds)) {
				$html_node = new wtx_Mime_Part('related');
				$html_node-> add_Child(new wtx_Mime_Part('HTML', $this-> content_textHTML));
				foreach($this-> content_textHTML_embeds as $embed_id=> $embed_file)
					$html_node-> add_Child(new wtx_Mime_Part('embed', $embed_file, $embed_id));
			} else {
				// no, just plain html
				$html_node = new wtx_Mime_Part('HTML', $this-> content_textHTML);
			}
		}

		// 2. Check if we have both text and HTML versions, or just of them
		if(!empty($this-> content_textPlain) && !empty($this-> content_textHTML)) {

			$message_node = new wtx_Mime_Part('alternative');
			// add text node first, then the HTML ( which can also be a multipart - set up in previous check ( #1 )
			$message_node-> add_Child(new wtx_Mime_Part('text', $this-> content_textPlain));
			$message_node-> add_Child($html_node);
			// 2.1 We have text, but not HTML
		} elseif (!empty($this-> content_textPlain) && empty($this-> content_textHTML)) {
			$message_node = new wtx_Mime_Part('text', $this-> content_textPlain);
			// 2.2 We have HTML, but not text
		} else
			$message_node = $html_node;

		// 3. When we have attachments, root node will be a multipart/mixed
		if(!empty($this-> content_attachments)) {
			$this-> content = new wtx_Mime_Part('mixed');
			$this-> content-> add_Child($message_node);
			foreach($this-> content_attachments as $attched_file)
				$this-> content-> add_Child(new wtx_Mime_Part('attachment', $attched_file));
		} else
			// no attachments
			$this-> content = $message_node;

		// Finally, Root part headers
		foreach($this-> content-> get_Headers() as $header=> $content)
			$headers[] = "{$header}: {$content}";

		// now, assamble the body, using the wtx_Mime_Part class to generate the proper content
		return array('headers'=> $headers, 'body'=> $this-> content-> render(true));
	}

	/**
	 * Assembles the e-mail message and sends it using the mail() function
	 */
	public function send()
	{
		if(!isset($this-> contacts['From']) || count($this-> contacts['From'])==0)
			$this-> trigger_Error(self::ERR_SENDER_NOT_SET, "Sender not set. (From:)");

		// extract the first "To", because it appears that on Linux you cannot omit the
		if(!isset($this-> contacts['To']) || count($this-> contacts['To'])==0)
			$this-> trigger_Error(self::ERR_MISSING_MAIN_RECIPIENT, "Primary recipient not set. (To:)");

		$subject = $this-> headers['Subject'];
		$message = $this-> assemble_Message(true);

		return mail(implode(", ",$this-> contacts['To']), $subject, $message['body'], implode("\r\n", $message['headers']), '-fno-reply@cargoclaims.aero');
	}

	/* Error handling methods */

	/**
	 * Something bad in formation of the E-mail, cannot continue
	 *
	 * @param int $error_code
	 * @param string $error_text
	 */
	protected function trigger_Error($error_code, $error_text)
	{
		$this->_has_error = true;
		header ('Content-Type: text/plain; charset=UTF-8');
		throw new Exception($error_text, $error_code);
	}

}

/**
 * Fragment of an e-mail representing either a file (attachment, embed) or a message (text, html)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 */
class wtx_Mime_Part
{
	/**
	 * List of mime type by file extension
	 * Thanks to http://webdesign.about.com/od/multimedia/a/mime-types-by-file-extension.htm
	 *
	 * @var array
	 */
	public static $mime_by_extension = array(
// 		'323'=>'text/h323',
		'acx'=>'application/internet-property-stream',
		'ai'=>'application/postscript',
		'aif'=>'audio/x-aiff',
		'aifc'=>'audio/x-aiff',
		'aiff'=>'audio/x-aiff',
		'asf'=>'video/x-ms-asf',
		'asr'=>'video/x-ms-asf',
		'asx'=>'video/x-ms-asf',
		'au'=>'audio/basic',
		'avi'=>'video/x-msvideo',
		'axs'=>'application/olescript',
// 		'bas'=>'text/plain',
		'bcpio'=>'application/x-bcpio',
		'bin'=>'application/octet-stream',
		'bmp'=>'image/bmp',
// 		'c'=>'text/plain',
		'cat'=>'application/vnd.ms-pkiseccat',
		'cdf'=>'application/x-cdf',
		'cdf'=>'application/x-netcdf',
		'cer'=>'application/x-x509-ca-cert',
		'class'=>'application/octet-stream',
		'clp'=>'application/x-msclip',
		'cmx'=>'image/x-cmx',
		'cod'=>'image/cis-cod',
		'cpio'=>'application/x-cpio',
		'crd'=>'application/x-mscardfile',
		'crl'=>'application/pkix-crl',
		'crt'=>'application/x-x509-ca-cert',
		'csh'=>'application/x-csh',
// 		'css'=>'text/css',
		'dcr'=>'application/x-director',
		'der'=>'application/x-x509-ca-cert',
		'dir'=>'application/x-director',
		'dll'=>'application/x-msdownload',
		'dms'=>'application/octet-stream',
		'doc'=>'application/msword',
		'dot'=>'application/msword',
		'dvi'=>'application/x-dvi',
		'dxr'=>'application/x-director',
		'eps'=>'application/postscript',
// 		'etx'=>'text/x-setext',
		'evy'=>'application/envoy',
		'exe'=>'application/octet-stream',
		'fif'=>'application/fractals',
		'flr'=>'x-world/x-vrml',
		'gif'=>'image/gif',
		'gtar'=>'application/x-gtar',
		'gz'=>'application/x-gzip',
// 		'h'=>'text/plain',
		'hdf'=>'application/x-hdf',
		'hlp'=>'application/winhlp',
		'hqx'=>'application/mac-binhex40',
		'hta'=>'application/hta',
// 		'htc'=>'text/x-component',
// 		'htm'=>'text/html',
// 		'html'=>'text/html',
// 		'htt'=>'text/webviewhtml',
		'ico'=>'image/x-icon',
		'ief'=>'image/ief',
		'iii'=>'application/x-iphone',
		'ins'=>'application/x-internet-signup',
		'isp'=>'application/x-internet-signup',
		'jfif'=>'image/pipeg',
		'jpe'=>'image/jpeg',
		'jpeg'=>'image/jpeg',
		'jpg'=>'image/jpeg',
		'js'=>'application/x-javascript',
		'latex'=>'application/x-latex',
		'lha'=>'application/octet-stream',
		'lsf'=>'video/x-la-asf',
		'lsx'=>'video/x-la-asf',
		'lzh'=>'application/octet-stream',
		'm13'=>'application/x-msmediaview',
		'm14'=>'application/x-msmediaview',
		'm3u'=>'audio/x-mpegurl',
		'man'=>'application/x-troff-man',
		'mdb'=>'application/x-msaccess',
		'me'=>'application/x-troff-me',
		'mht'=>'message/rfc822',
		'mhtml'=>'message/rfc822',
		'mid'=>'audio/mid',
		'mny'=>'application/x-msmoney',
		'mov'=>'video/quicktime',
		'movie'=>'video/x-sgi-movie',
		'mp2'=>'video/mpeg',
		'mp3'=>'audio/mpeg',
		'mpa'=>'video/mpeg',
		'mpe'=>'video/mpeg',
		'mpeg'=>'video/mpeg',
		'mpg'=>'video/mpeg',
		'mpp'=>'application/vnd.ms-project',
		'mpv2'=>'video/mpeg',
		'ms'=>'application/x-troff-ms',
		'msg'=>'application/vnd.ms-outlook',
		'mvb'=>'application/x-msmediaview',
		'nc'=>'application/x-netcdf',
		'nws'=>'message/rfc822',
		'oda'=>'application/oda',
		'p10'=>'application/pkcs10',
		'p12'=>'application/x-pkcs12',
		'p7b'=>'application/x-pkcs7-certificates',
		'p7c'=>'application/x-pkcs7-mime',
		'p7m'=>'application/x-pkcs7-mime',
		'p7r'=>'application/x-pkcs7-certreqresp',
		'p7s'=>'application/x-pkcs7-signature',
		'pbm'=>'image/x-portable-bitmap',
		'pdf'=>'application/pdf',
		'pfx'=>'application/x-pkcs12',
		'pgm'=>'image/x-portable-graymap',
		'pko'=>'application/ynd.ms-pkipko',
		'pma'=>'application/x-perfmon',
		'pmc'=>'application/x-perfmon',
		'pml'=>'application/x-perfmon',
		'pmr'=>'application/x-perfmon',
		'pmw'=>'application/x-perfmon',
		'png'=>'image/png',
		'pnm'=>'image/x-portable-anymap',
		'pot'=>'application/vnd.ms-powerpoint',
		'ppm'=>'image/x-portable-pixmap',
		'pps'=>'application/vnd.ms-powerpoint',
		'ppt'=>'application/vnd.ms-powerpoint',
		'prf'=>'application/pics-rules',
		'ps'=>'application/postscript',
		'pub'=>'application/x-mspublisher',
		'qt'=>'video/quicktime',
		'ra'=>'audio/x-pn-realaudio',
		'ram'=>'audio/x-pn-realaudio',
		'ras'=>'image/x-cmu-raster',
		'rgb'=>'image/x-rgb',
		'rmi'=>'audio/mid',
		'roff'=>'application/x-troff',
		'rtf'=>'application/rtf',
// 		'rtx'=>'text/richtext',
		'scd'=>'application/x-msschedule',
// 		'sct'=>'text/scriptlet',
		'setpay'=>'application/set-payment-initiation',
		'setreg'=>'application/set-registration-initiation',
		'sh'=>'application/x-sh',
		'shar'=>'application/x-shar',
		'sit'=>'application/x-stuffit',
		'snd'=>'audio/basic',
		'spc'=>'application/x-pkcs7-certificates',
		'spl'=>'application/futuresplash',
		'src'=>'application/x-wais-source',
		'sst'=>'application/vnd.ms-pkicertstore',
		'stl'=>'application/vnd.ms-pkistl',
// 		'stm'=>'text/html',
		'sv4cpio'=>'application/x-sv4cpio',
		'sv4crc'=>'application/x-sv4crc',
		'svg'=>'image/svg+xml',
		'swf'=>'application/x-shockwave-flash',
		't'=>'application/x-troff',
		'tar'=>'application/x-tar',
		'tcl'=>'application/x-tcl',
		'tex'=>'application/x-tex',
		'texi'=>'application/x-texinfo',
		'texinfo'=>'application/x-texinfo',
		'tgz'=>'application/x-compressed',
		'tif'=>'image/tiff',
		'tiff'=>'image/tiff',
		'tr'=>'application/x-troff',
		'trm'=>'application/x-msterminal',
// 		'tsv'=>'text/tab-separated-values',
// 		'txt'=>'text/plain',
// 		'uls'=>'text/iuls',
		'ustar'=>'application/x-ustar',
// 		'vcf'=>'text/x-vcard',
		'vrml'=>'x-world/x-vrml',
		'wav'=>'audio/x-wav',
		'wcm'=>'application/vnd.ms-works',
		'wdb'=>'application/vnd.ms-works',
		'wks'=>'application/vnd.ms-works',
		'wmf'=>'application/x-msmetafile',
		'wps'=>'application/vnd.ms-works',
		'wri'=>'application/x-mswrite',
		'wrl'=>'x-world/x-vrml',
		'wrz'=>'x-world/x-vrml',
		'xaf'=>'x-world/x-vrml',
		'xbm'=>'image/x-xbitmap',
		'xla'=>'application/vnd.ms-excel',
		'xlc'=>'application/vnd.ms-excel',
		'xlm'=>'application/vnd.ms-excel',
		'xls'=>'application/vnd.ms-excel',
		'xlt'=>'application/vnd.ms-excel',
		'xlw'=>'application/vnd.ms-excel',
		'xof'=>'x-world/x-vrml',
		'xpm'=>'image/x-xpixmap',
		'xwd'=>'image/x-xwindowdump',
		'z'=>'application/x-compress',
		'zip'=>'application/zip'
	);

	/**
	 * What kind of part this is ( text/plain, text/html, image/jpeg, etc. )
	 * @var string
	 */
	public $content_type;

	/**
	 * Used only for embedded content ( for multipart/related )
	 * @var string
	 */
	public $content_id;

	/**
	 * Content of this part - Can be a text, a file name, or an array containing other parts ( for multipart/* )
	 * @var string|array
	 */
	public $content;

	/**
	 * The boundary line that will sepparate children of this Part
	 * @var string
	 */
	private $boundary;

	/**
	 * Part construction, for files this will actually read the file and encode!
	 *
	 * @param string $type Part type ( mixed, alternative, related, text, HTML, embedded, attachment )
	 * @param string $content Part content, file name or nothing (when setting up multiparts)
	 * @param array $extra Other information that might be necessary ( like content_id for embedded )
	 */
	public function __construct($type, $content = null, $content_id = null)
	{
		switch($type) {
			case 'mixed':
			case 'alternative':
			case 'related':
				usleep(2000); // Sometimes, microtime(1) . uniqid() generates the same boundary because the code executes too fast, so introduce a 2ms delay every time a part is being generated
				$this-> boundary = '-----' . sha1(microtime(1) . uniqid());
				$this-> content = array();
				$this-> content_type = 'multipart/'.$type;
				break;
			case 'text':
				$this-> content_type = 'text/plain';
				$this-> content = $content;
				break;
			case 'HTML':
				$this-> content_type = 'text/html';
				$this-> content = $content;
				break;
			default:
				// for files (attachment or embedded), read the file and do base64 encoding on them
				if(is_array($content)) {
					$path = (string)$content[0];
					$name = (string)$content[1];
				} else
					$path = $name = (string)$content;

				if(is_file($path)) {
					if(!is_null($content_id))
						$this-> content_id = $content_id;

					$file_extension = (($p=strrpos($name, '.'))!==false)?strtolower(substr($name, $p+1)):'';

					// TODO: Replace (or complement) this with Fileinfo class when porting this to DRY (@deprecated)
					if(isset(self::$mime_by_extension[$file_extension]))
						$this-> content_type = self::$mime_by_extension[$file_extension];
					else
						$this-> content_type = "application/octet-stream";

 					$this-> content = MimeMail::encodeBase64(file_get_contents($path));
					$this-> filename = $name;
				} else {
					throw new Exception("Cannot access attachment or embedded file");
				}
			}
	}

	/**
	 * Add children to a multipart mime-part
	 *
	 * @param wtx_Mime_Part $child
	 */
	public function add_Child(wtx_Mime_Part $child)
	{
		if(substr($this-> content_type, 0, 9)=='multipart')
			$this-> content[] = $child;
	}

	public function get_Headers()
	{
		// Depending on type, add other information ( like boundary, charset, format, etc. )
		switch($this-> content_type) {
			case 'multipart/mixed':
			case 'multipart/alternative':
			case 'multipart/related':
				return array(
					'Content-Type'=> $this-> content_type.';'.MimeMail::LINEEND.' boundary="'.$this-> boundary.'"'
				);
			case 'text/plain':
				return array(
					'Content-Type'=> $this-> content_type.'; charset='.MimeMail::CHARSET.'; format=flowed',
					'Content-Transfer-Encoding'=> MimeMail::is_Printable($this-> content)?'7bit':'8bit'
				);
			case 'text/html':
				return array(
					'Content-Type'=> $this-> content_type.'; charset='.MimeMail::CHARSET,
					'Content-Transfer-Encoding'=> '7bit'
				);
			default:
				// attachments and embeds
				$hdr_arr = array(
					'Content-Type'=> $this-> content_type.'; name="'.basename($this-> filename).'"',
					'Content-Transfer-Encoding'=> 'base64'
				);
				if(!empty($this-> content_id)) {
					$hdr_arr['Content-ID'] = '<'.$this-> content_id.'>';
					$hdr_arr['Content-Disposition'] = 'inline; filename="'.basename($this-> filename).'"';
				} else {
					$hdr_arr['Content-Disposition'] = 'attachment; filename="'.basename($this-> filename).'"';
				}
				return $hdr_arr;
		}

	}

	/**
	 * Generates the proper format, sepparating children with boundaries
	 * @return string
	 */
	public function render($is_root = false)
	{
		// Test if this part is a multipart container, if so, generate multipart header, set boundaries and go recursively
		if(substr($this-> content_type, 0, 9)=='multipart') {
			$body = '';
			if($is_root)
				$body.= MimeMail::MIMEMULTIPART.MimeMail::LINEEND;
			// begin child parts
			foreach($this-> content as $part) {
				// part boundary
				$body.= '--'.$this-> boundary.MimeMail::LINEEND;
				// headers for the part
				$part_headers = array();
				foreach($part-> get_Headers() as $header=> $content)
					$part_headers[] = "{$header}: {$content}";
				$body.= implode(MimeMail::LINEEND, $part_headers);
				// empty line after headers (mandatory)
				$body.= MimeMail::LINEEND.MimeMail::LINEEND;

				// body for the part
				$body.= $part-> render().MimeMail::LINEEND;
			}
			// at the end, final boundary of this multipart
			$body.= '--'.$this-> boundary.'--'.MimeMail::LINEEND;
			return $body;
		} else {
			// No, just plain element, display the content
			if($this-> content_type == 'text/plain')
				return wordwrap($this-> content);
			else
				return $this-> content;
		}

	}
}

/**
 * Procedural initialization (Mime Mail)
 */
function WMail()
{
	return new MimeMail();
}