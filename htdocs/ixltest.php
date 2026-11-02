<?php

function send_JSON_Headers($data, $key = null, $do_not_encode = false)
	{
	 
		if($do_not_encode) {
			if(!is_string($data))
				return false; // error
			// send just one header (+ the metadata header), without encoding it - If $data is not proper JSON, the JS side will fail to decode...
			header('X-JSON-Meta: {"count":1,"lengths":['.strlen($data).'],"keys":["'.$key.'"]}');
			header('X-JSON-0: '.$data);
		} else {
			// send out multiple headers, one for each entry in the $data array - Since PHP Arrays are like JavaScript Objects (values can have named keys), also send the keys in the Meta header
			if(is_array($data)) {
				$encoded_data = array(); $lengths = array(); $keys = array();
				foreach($data as $name=> $data_row) {
				 
					$encoded_row = json_encode(($data_row));
					$encoded_data[] = $encoded_row;
					$lengths[] = strlen($encoded_row);
					$keys[] = $name;
				} 
				header(sprintf('X-JSON-Meta: {"count":%d,"lengths":[%s],"keys":%s}', count($data), implode(',', $lengths), json_encode($keys)));
				foreach($encoded_data as $idx=> $row) 
					{
					header(sprintf('X-JSON-%d: %s', $idx, $row)); 
				 
					 }
					 
			} else {
				// not an array, assume we're dealing with an object or scalar value, send just one header
				$encoded_data = json_encode($data);
				header('X-JSON-Meta: {"count":1,"lengths":['.strlen($encoded_data).'],"keys":["'.$key.'"]}');
				header('X-JSON-0: '.$encoded_data);
			}
		}
	}

$data = array(
    "\nname" => str_repeat("x", 2500),
    'b' => "\n",
    'c' => str_repeat("x", 25),
    'd' => "\r",
    'name' => str_repeat("x", 25),
    'k' => str_repeat("x", 25),
);

$data = "name";

send_JSON_Headers($data, "name", true);

echo "test";
