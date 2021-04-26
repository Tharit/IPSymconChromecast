<?php

// Class to represent a protobuf object for a command.

class CastMessage {

	private static $fieldMap = [
		1 => 'protocol_version',
		2 => 'source_id',
		3 => 'destination_id',
		4 => 'namespace',
		5 => 'payload_type',
		6 => 'payload_utf8',
		7 => 'payload_binary'
	];

	public $protocol_version = 0; // CASTV2_1_0 - It's always this
	public $source_id = ""; // Source ID String
	public $destination_id = ""; // Receiver ID String
	public $namespace = ""; // Namespace
	public $payload_type = 0; // PayloadType String=0 Binary = 1
	public $payload_utf8 = ""; // Payload String
	public $payload_binary = ""; // Payload Binary

	public function encode() {

		// Deliberately represent this as a binary first (for readability and so it's obvious what's going on.
		// speed impact doesn't really matter!)
		$r = "";
	
		// First the protocol version
		$r = "00001"; // Field Number 1
		$r .= "000"; // Int
		// Value is always 0
		$r .= $this->varintToBin($this->protocol_version);

		// Now the Source id
		$r .= "00010"; // Field Number 2
		$r .= "010"; // String
		$r .= $this->stringToBin($this->source_id);

		// Now the Receiver id
		$r .= "00011"; // Field Number 3
		$r .= "010"; // String
		$r .= $this->stringToBin($this->destination_id);

		// Now the namespace
		$r .= "00100"; // Field Number 4
		$r .= "010"; // String
		$r .= $this->stringToBin($this->namespace);

		// Now the payload type
		$r .= "00101"; // Field Number 5
		$r .= "000"; // VarInt
		$r .= $this->varintToBin($this->payload_type);

		// Now payloadutf8
		$r .= "00110"; // Field Number 6
		$r .= "010"; // String
		$r .= $this->stringToBin($this->payload_utf8);
		
		// Ignore payload_binary field 7 as never used

		// Now convert it to a binary packet
		$hexstring = "";
		for ($i=0; $i < strlen($r); $i=$i+8) {
			$thischunk = substr($r,$i,8);
			$hx = dechex(bindec($thischunk));
			if (strlen($hx) == 1) { $hx = "0" . $hx; }
			$hexstring .= $hx;
		}
		$l = strlen($hexstring) / 2;
		$l = dechex($l);
		while (strlen($l) < 8) { $l = "0" . $l; }
		$hexstring = $l . $hexstring;
		return hex2bin($hexstring);
	}

	public function decode($message) {
		$this->protocol_version = 0; // CASTV2_1_0 - It's always this
		$this->source_id = ""; // Source ID String
		$this->destination_id = ""; // Receiver ID String
		$this->namespace = ""; // Namespace
		$this->payload_type = 0; // PayloadType String=0 Binary = 1
		$this->payload_utf8 = ""; // Payload String
		$this->payload_binary = ""; // Payload Binary

		if(strlen($message) < 4) {
			throw new Exception("Message too short");
		}

		$idx = 4;
		$messageLen = unpack("N", $message)[1];

		while($idx < $messageLen) {
			$v = $this->readvarint($message, $idx);
			$type = ($v & 0x07);
			$field = $v >> 3;

			if(!isset(CastMessage::$fieldMap[$field])) {
				throw new Exception("Encountered unknown field: " . $field);
			}
			if($type === 0) {
				$value = $this->readvarint($message, $idx);
			} else if($type === 2) {
				$len = $this->readvarint($message, $idx);
				$value = substr($message, $idx, $len);
				$idx += $len;
			} else {
				throw new Exception("Encounterd unimplemented field type: " . $type);
			}
			$this->{CastMessage::$fieldMap[$field]} = $value;
		}
	}

	private function readvarint($message, &$idx) {
		$bytes = [];
		do {
			$b = ord($message[$idx++]);
			$continue = ($b & 0x80);
			$bytes[] = ($b & 0x7f);
		} while($continue);
		$res = 0;
		$factor = 1;
		for($i = 0; $i < count($bytes); $i++) {
			$res += $bytes[$i] * $factor;
			$factor *= 128;
		}
		return $res;
	}

	private function varintToBin($inval) {
		// Convert an integer to a binary varint
		// A variant is returned least significant part first.
		// Number is represented in 7 bit portions. The 8th (MSB) of a byte represents if there
		// is a following byte.
		$r = array();
		while ($inval / 128 > 1) {
			$thisval = ($inval - ($inval % 128)) / 128;
			array_push($r, $thisval);
			$inval = $inval - ($thisval * 128);
		}
		array_push($r, $inval);
		$r = array_reverse($r);
		$binaryString = "";
		$c = 1;
		foreach ($r as $num) {
			if ($c != sizeof($r)) { $num = $num + 128; }
			$tv = decbin($num);
			while (strlen($tv) < 8) { $tv = "0" . $tv; }
			$c++;
			$binaryString .= $tv;
		}
		return $binaryString;
	}

	private function stringToBin($string) {
		// Convert a string to a Binary string
		// First the length (note this is a binary varint)
		$l = strlen($string);
		$ret = "";
		$ret = $this->varintToBin($l);
		for ($i = 0; $i < $l; $i++) {
			$n = decbin(ord(substr($string,$i,1)));
			while (strlen($n) < 8) { $n = "0" . $n; }
			$ret .= $n;
		}
		return $ret;
	}

}


?>
