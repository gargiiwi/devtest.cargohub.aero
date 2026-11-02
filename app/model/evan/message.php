<?php
/**
 * Evan Message object, has an ID, content and various options
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Model_Evan_Message
{
	/**
	 * Numeric ID for this message
	 * @var int
	 */
	public $id;

	/**
	 * UnixTime when the message was created
	 * @var int
	 */
	public $time;

	/**
	 * The primary content of the message
	 * @var string
	 */
	public $text;

	/**
	 * Options for the various types of messages
	 * @var array
	 */
	public $opts;

	private $displays_left = 1;

	/**
	 * Creates a message object
	 *
	 * @param int $id
	 * @param string $text
	 * @param array $opts
	 * @param mixed $detail
	 */
	public function __construct($id, $text, $opts=null)
	{
		$this-> id = $id;
		$this-> time = time();
		$this-> text = (string)$text;
		if(is_array($opts)) {
			// do some processing with options when required
			if(isset($opts['displays']) && $opts['displays']>1)
				$this-> displays_left = (int)$opts['displays'];
		}

		$this-> opts = $opts;
	}

	/**
	 * Uses the template to render the output
	 * TODO: This is obsolete... EVAN is now javascript controlled
	 */
	public function __toString()
	{
		if(!isset($this-> opts['permanent']))
			$this-> displays_left--;
		return $this-> text;
	}

	/**
	 * Prepares the message data for being displayed (directly from PHP or via JavaScript).
	 * - Also reduces display counter by one.
	 */
	public function prepare_Display()
	{
		if(!isset($this-> opts['permanent']))
			$this-> displays_left--;

		$msg = array(
			'id'=> $this-> id,
			'time'=> $this-> time,
			'text'=> $this-> text,
			'opts'=> $this-> opts
		);
		if(isset($this-> opts['permanent']) && isset($this-> opts['timeout']))
			$msg['time_left'] = $this-> opts['timeout'] - time();

		return $msg;
	}

	/**
	 * Checks if the message must still exist, or it can be removed
	 *
	 * @return bool True if the message has lived it's useful life, False if it must be kept
	 */
	public function self_Check()
	{
		// remove if it has been displayed the preset ammount of times
		if($this-> displays_left <= 0)
			return true;

		// remove if the message has expired
		if(isset($this-> opts['timeout'])) {
			if(time() >= $this-> opts['timeout'])
				return true;
		}
	}

	/**
	 * Sets this message up for deletion at the next self_Check(). It will still be accessible this instance
	 */
	public function last_Display()
	{
		unset($this-> opts['permanent'], $this-> opts['timeout']);
		$this-> displays_left = 1;
	}

	/**
	 * Necessary because the template first checks if the variable actually exists
	 * @param string $var
	 * @return boolean
	 */
	public function __isset($var)
	{
		switch($var) {
			case 'time_left':
				return true;
			default:
				return isset($this-> opts[$var]);
		}
	}

	/**
	 * Used to calculate some special variables
	 * @param string $var
	 * @return mixed
	 */
	public function __get($var)
	{
		switch($var) {
			case 'time_left':
				if(isset($this-> opts['timeout']))
					return $this-> opts['timeout'] - time();
				break;
			default:
				// attempt to find the value in $opts (quick access)
				if(isset($this-> opts[$var]))
					return $this-> opts[$var];
		}
	}

}