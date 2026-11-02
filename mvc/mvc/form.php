<?php
/**
 * MVC Form - Handles form preparation and post/get handling, in a simple way.
 * Doesn't attempt to build the form's HTML or manage it's layout, it instead provides strictly the data necessary for form elements ( name, default value, optionally ID )
 * Can handle $_POST or $_GET array and use the already posted values as defaults ( supporting nested names: name="group[element]" => $_POST['group']['element'] )
 *
 * @author Wavetrex <wavetrex@gmail.com>    
 * @package CargoClaims
 * @version 2.5
 */
class MVC_Form
{
	/**
	 * Array of form elements
	 * ( key = element ID, used to find it in the output array ; value = DRY\Formdata\Element object )
	 * @var unknown_type
	 */
	protected $elements;

	/**
	 * Adds an element to the form, so it's data can be prepared or recognised in the Input array
	 *
	 * @param string $type What kind of element ( Not the HTML types ! ). Can be "String", "Select", "Choices", ?
	 * @param string $id Key in the array of elements
	 * @param string $name Name of the element (if differs from the $id, otherwise the $id is used)
	 * - $name can be empty string, in which case a group is necessary, and the element will be named: group[] (can be used for multiple checkboxes under the same name)
	 * @param string|array $default Either the intial String, or the initial choices for other type of elements
	 * @param array $options Necessary to build groups of elements (like checkboxes, options for <select />, radio buttons), or other kinds of options for speciality elements
	 * @param string $group Dot.syntax location of the element ( if missing, the element is in the root of the Input array )
	 * Example (when element $name is "element"):
	 * - top.middle will use name: "top[middle][element]", and is searched in $input['top']['middle']['element']
	 * @param string $display Can be used to specify a label for the form element (or any other display data, it's simply transmitted to the output)
	 *
	 * @return DRY\Formdata\Element_base
	 */
	public function add_Element($element_type, $id, $name = null, $default = null, $options = array(), $group = null, $display = null)
	{
		$element_class = 'MVC_FormEl_'.$element_type;

		if(isset($this-> elements[$id]))
			throw new Exception("Element ID: {$id} is already used!"); // TODO: proper exceptions

		if(is_null($name))
			$name = $id;
		$this-> elements[$id] = new $element_class($id, $name, !is_null($default)? $default: null, $options, $group, $display);
		return $this-> elements[$id];
	}

	/**
	 * Set default values of all inputs by searching their name + group inside an array of values
	 * (which might come from a previous $_POST, or the result of a query, or build customly )
	 */
	public function input($value_array)
	{
		$dam = new Tree_Mapper();
		foreach($this-> elements as $element) {
			// search $input for the element
			$value = $dam-> search_By_Keylist($value_array, $element-> get_Location_Map());
			if(!is_null($value) && $value!=='')
				$element-> input($value);
		}
	}

	/**
	 * Constructs the output data array, which could be used for example in a template system (or other uses)
	 * @return array
	 */
	public function output()
	{
		$output = array();
		foreach($this-> elements as $id=> $element) {
			$output[$id] = $element-> output();
		}
		return $output;
	}

	/**
	 * Object oriented method of accessing an element's output by it's ID
	 *
	 * @param string $element_id
	 * @return MVC_FormEl_base
	 */
	public function &__get($element_id)
	{
		if(isset($this-> elements[$element_id]))
			return $this-> elements[$element_id];
	}
}

/**
 * Base class for form elements
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @package CargoClaims
 * @version 1.0.dev
 */
abstract class MVC_FormEl_base
{
	public $id;

	public $name;

	public $group;

	/**
	 * General Variable setter - Can only be called from the child objects
	 * @param int $id
	 * @param string $name
	 * @param mixed $default
	 * @param string $group
	 * @param string $display
	 */
	protected function __construct($id, $name, $default, $group, $display)
	{
		$this-> id = $id;
		$this-> name = $name;
		if($name === '' && empty($group))
			throw new Exception("When the name is empty, a group is mandatory !");
		$this-> def = $default;
		$this-> group = $group;
		$this-> display = $display;
	}

	/**
	 * Creates a HTML Name ( group[subgroup][element] ) out of the element name and group (which is in dot.syntax)
	 * @return string
	 */
	protected function render_HTMLName()
	{
		if(!empty($this-> group)) {
			$group_fragments = explode('.', $this-> group);
			$htmlgroups = '';
			foreach($group_fragments as $idx=> $subgroup) {
				if($idx==0)
					$htmlgroups.= $subgroup;
				else
					$htmlgroups.= "[{$subgroup}]";
			}
			return $htmlgroups."[{$this-> name}]";
		} else
			return $this-> name;
	}

	/**
	 * Creates a HTML id (by using underscore as separator for group components and element's ID)
	 * @param string $separator
	 * @return string
	 */
	protected function render_HTMLId($separator = '_')
	{
		if(!empty($this-> group)) {
			$htmlgroups = str_replace('.', $separator, $this-> group);
			return $htmlgroups."{$separator}{$this-> name}";
		} else
			return $this-> name;
	}

	/**
	 * Generates a location map used by Tree_Mapper-> search_By_Keylist().
	 * @return array
	 */
	public function get_Location_Map()
	{
		if(empty($this-> group))
			$map = array();
		else
			$map = explode('.', $this-> group);
		if(!empty($this-> name))
			$map[] = $this-> name;
		return $map;
	}

	public $def;

	abstract function input($value);

	protected $output_cache = null;

	public $display;

	abstract function output();
}

/**
 * Scalar type form element ( input type="text", type="password", type="hidden", textarea ) - String, Number, Date... etc.
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_FormEl_Scalar extends MVC_FormEl_base
{
	public function __construct($id, $name, $default, $options, $group, $display)
	{
		parent::__construct($id, $name, htmlspecialchars($default, ENT_QUOTES), $group, $display);
	}

	public function input($value)
	{
		if(!is_scalar($value))
			throw new Exception("Input value for Scalar element cannot be of complex type");
		$this-> def = htmlspecialchars($value, ENT_QUOTES);
		$this-> output_cache = null;
	}

	/**
	 * Prepares the data of this element type for output
	 */
	public function output()
	{
		if(is_null($this-> output_cache))
			$this-> output_cache = array(
				'id'=> $this-> render_HTMLId(),
				'name'=> $this-> render_HTMLName(),
				'value'=> $this-> def,
				'display'=> $this-> display
			);
		return $this-> output_cache;
	}
}

/**
 * Single choice type form element ( input type="radio", select (multiple = off) + option(s) )
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_FormEl_Enum extends MVC_FormEl_base
{
	public $options;

	public function __construct($id, $name, $default, $options = array(), $group, $display)
	{
		parent::__construct($id, $name, $default, $group, $display);

		if(!is_array($options) || empty($options))
			throw new Exception("Enum-type form control must have at least one option");
		$this-> options = $options;
	}

	public function input($value)
	{
		if(!is_scalar($value))
			throw new Exception("Input value for Enum element cannot be of complex type");
		$this-> def = $value;
		$this-> output_cache = null;
	}

	/**
	 * Prepares the data of this element type for output
	 */
	public function output()
	{
		if(is_null($this-> output_cache)) {
			$this-> output_cache = array(
				'id'=> $this-> render_HTMLId(),
				'name'=> $this-> render_HTMLName(),
				'display'=> $this-> display,
				'current'=> $this-> def
			);
			$this-> output_cache['options'] = array();

			foreach($this-> options as $value=> $display) {
				$option = array(
					'value'=> $value,
					'display'=> $display
				);
				if(!is_null($this-> def) && $value == $this-> def) {
					$option['active'] = true;
					$this-> output_cache['value_display'] = $display;
				}
				$this-> output_cache['options'][] = $option;
			}
		}
		return $this-> output_cache;
	}
}

/**
 * Multiple choice type form element ( input type="checkbox", select (multiple = on) + option(s) )
 *
 * @author Wavetrex <wavetrex@gmail.com>
 * @package CargoClaims
 * @version 1.0.dev
 */
class MVC_FormEl_Set extends MVC_FormEl_base
{
	public $options;

	public function __construct($id, $name, $default, $options = array(), $group, $display)
	{
		parent::__construct($id, $name, $default, $group, $display);

		if(!is_array($options) || empty($options))
			throw new Exception("Set-type form control must have at least one option");
		$this-> options = $options;
	}

	public function input($value)
	{
		if(!is_array($value))
			return false; // cannot set input if the value is not an array!
		// test if all the values of the array are "1" or true, then use the keys as the input. ( like it usually comes from checkboxes )
		// if not, use the value themselves in a numerically indexed array ( from <select multiple> or another javascript control )
		$only_1s = true;
		foreach($value as $el)
			if($el!=true) {
				$only_1s = false; break;
			}

		if($only_1s)
			$value = array_keys($value);
		else
			$value = array_values($value);

		if(!is_array($value))
			throw new Exception("Input value for Set element must an array");
		$this-> def = $value;
		$this-> output_cache = null;
	}

	/**
	 * Prepares the data of this element type for output
	 */
	public function output()
	{
		if(is_null($this-> output_cache)) {
			$html_name = $this-> render_HTMLName();
			$this-> output_cache = array(
				'id'=> $this-> render_HTMLId(),
				'name'=> $html_name."[]",
				'display'=> $this-> display,
				'current'=> $this-> def
			);
			$this-> output_cache['options'] = array();
			foreach($this-> options as $value=> $display) {
				$option = array(
					'value'=> $value,
					'display'=> $display,
					'name'=> "{$html_name}[{$value}]"
				);
				if(is_array($this-> def) && in_array($value, $this-> def))
					$option['active'] = true;
				$this-> output_cache['options'][] = $option;
			}
		}
		return $this-> output_cache;
	}

}