<?php
/**
 * Google Authenticator Two-Factor Authentication
 *
 * @author People on the net. Optimized by Wavetrex
 */
class GA2FA {

	const keyRegeneration	= 30;	// Interval between key regeneration
	const otpLength			= 6;	// Length of the Token generated

	public static $lut = array();
	public static $keychars = "";

	/**
	 * Initialize Look-up Table
	 */
	public static function initialize()
	{
		if(empty(self::$lut))
			for($c=0; $c<32; $c++) {
				self::$keychars.=$c<26?chr(65+$c):chr(24+$c);
				self::$lut[self::$keychars[$c]] = $c;
			}
	}

	/**
	 * Creates a fresh random key
	 *
	 * @param int $length
	 * @return string
	 */
	public static function create_key($length = 16)
	{
		$key = '';
		for ($c=0; $c< $length; $c++)
			$key.= self::$keychars[mt_rand(0,31)];

		return $key;
	}

	/**
	 * Returns the current Unix Timestamp devided by the keyRegeneration period.
	 * @return integer
	 **/
	public static function get_timestamp()
	{
		return floor(microtime(true)/self::keyRegeneration);
	}

	/**
	 * Decodes a base32 string into a binary string.
	 **/
	public static function base32_decode($b32)
	{
		$b32 = strtoupper($b32);

		if (!preg_match('/^['.self::$keychars.']+$/', $b32, $match))
			throw new Exception('Invalid characters in the base32 string.');

		$l 	= strlen($b32);
		$n	= 0;
		$j	= 0;
		$binary = "";

		for ($i = 0; $i < $l; $i++) {
			$n = $n << 5; 				// Move buffer left by 5 to make room
			$n = $n + self::$lut[$b32[$i]]; 	// Add value into buffer
			$j = $j + 5;				// Keep track of number of bits in buffer

			if ($j >= 8) {
				$j = $j - 8;
				$binary .= chr(($n & (0xFF << $j)) >> $j);
			}
		}

		return $binary;
	}

	/**
	 * Takes the secret key and the timestamp and returns the one time
	 * password.
	 *
	 * @param binary $key - Secret key in binary form.
	 * @param integer $counter - Timestamp as returned by get_timestamp.
	 * @return string
	 **/
	public static function oath_hotp($key, $counter)
	{
		if (strlen($key) < 8)
			throw new Exception('Secret key is too short. Must be at least 16 base 32 characters');

		$bin_counter = pack('N*', 0). pack('N*', $counter);		// Counter must be 64-bit int
		$hash = hash_hmac ('sha1', $bin_counter, $key, true);

		return str_pad(self::oath_truncate($hash), self::otpLength, '0', STR_PAD_LEFT);
	}

	/**
	 * Verifies a user key against the current timestamp. Checks $window
	 * keys either side of the timestamp.
	 *
	 * @param string $oathkey
	 * @param string $otp - User specified key
	 * @param integer $window Value 2 = 1 minute ( 2 x 30 secs ) - It allows slight mismatch between clocks on the user's phone and the server.
	 * @param boolean $useTimeStamp
	 * @return boolean
	 **/
	public static function verify_key($oathkey, $otp, $window = 2, $useTimeStamp = true)
	{
		$timeStamp = self::get_timestamp();

		if ($useTimeStamp !== true) $timeStamp = (int)$useTimeStamp;

		$binarySeed = self::base32_decode($oathkey);

		for ($ts = $timeStamp - $window; $ts <= $timeStamp + $window; $ts++)
			if (self::oath_hotp($binarySeed, $ts) == $otp)
				return true;

		return false;
	}

	/**
	* Extracts the OTP from the SHA1 hash.
	 * @param binary $hash
	 * @return integer
	 **/
	 public static function oath_truncate($hash)
	 {
		 $offset = ord($hash[19]) & 0xf;

		 return (((ord($hash[$offset+0]) & 0x7f) << 24) | ((ord($hash[$offset+1]) & 0xff) << 16 ) | ((ord($hash[$offset+2]) & 0xff) << 8 ) | (ord($hash[$offset+3]) & 0xff)) % pow(10, self::otpLength);
	 }

}

/*
$ga = new GA2FA();

switch($_GET['cmd']) {
	case 'key':
		echo GA2FA::create_key();
		break;
	case 'qr':
		// These are mandatory
		$key = $_GET['key'];
		$id = $_GET['id'];
		if(!$key || !$id) {
			echo "Missing key or ID";
			break;
		}

		// White background for the QR code recommended
		// https://developers.google.com/chart/infographics/docs/qr_codes
		$size = 332;

		$urlencoded = urlencode("otpauth://totp/{$id}?secret={$key}");
		$image_url = "https://chart.apis.google.com/chart?chs={$size}x{$size}&cht=qr&chld=M|0&chl=".$urlencoded;

		echo "<body style='background-color: white;'><img src='{$image_url}' /></body>";

		break;
	case 'otp':
		// Current token ( should be synched with the authenticator on the phone etc. )
		$key = $_GET['key'];

		$TimeStamp	  = GA2FA::get_timestamp();
		$secretkey 	  = GA2FA::base32_decode($key);	// Decode it into binary
		$otp       	  = GA2FA::oath_hotp($secretkey, $TimeStamp);	// Get current token
		echo $otp;
		break;
	case 'check':
		$otp = $_GET['otp'];
		$key = $_GET['key'];

		$result = GA2FA::verify_key($key, $otp);
		if($result)
			echo "YEY!";
		else
			echo "ney :(";

		break;

	default:
		echo "Hello World ;)";
}
*/