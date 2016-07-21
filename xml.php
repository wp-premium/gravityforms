<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class RGXML
 *
 * Handles the formatting and output of XML content
 */
class RGXML {

	/**
	 * @access private
	 * @var array Options
	 */
	private $options = array();

	/**
	 * RGXML constructor.
	 *
	 * @access public
	 *
	 * @param array $options
	 */
	public function __construct( $options = array() ) {
		$this->options = $options;
	}

	/**
	 * Creates an indention to be used with XML strings
	 *
	 * @access private
	 *
	 * @param string $path The path string.  Depth in the path will determine depth of the indent
	 *
	 * @return string
	 */
	private function indent( $path ) {
		$depth  = sizeof( explode( "/", $path ) ) - 1;
		$indent = "";
		$indent = str_pad( $indent, $depth, "\t" );

		return "\r\n" . $indent;
	}

	/**
	 * Serializes an array into an XML string
	 *
	 * @access public
	 *
	 * @param string $parent_node_name The parent XML node name
	 * @param array  $data             The data to serialize
	 * @param string $path             Optional. The path inside the parent node.
	 *
	 * @return string The serialized XML string
	 */
	public function serialize( $parent_node_name, $data, $path = "" ) {
		$xml = "";
		if ( empty( $path ) ) {
			$path = $parent_node_name;
			$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		}

		// If this element is marked as hidden, ignore it
		$option = rgar( $this->options, $path );
		if ( rgar( $option, "is_hidden" ) ) {
			return "";
		}

		$padding = $this->indent( $path );

		// If the content is not an array, simply render the node
		if ( ! is_array( $data ) ) {
			$option = rgar( $this->options, $path );

			return strlen( $data ) == 0 && ! rgar( $option, "allow_empty" ) ? "" : "$padding<$parent_node_name>" . $this->xml_value( $parent_node_name, $data ) . "</$parent_node_name>";
		}
		$is_associative = $this->is_assoc( $data );
		$is_empty       = true;

		// Opening parent node
		$version = $path == $parent_node_name && isset( $this->options["version"] ) ? " version=\"" . $this->options["version"] . "\"" : "";
		$xml .= "{$padding}<{$parent_node_name}{$version}";

		if ( $is_associative ) {
			// Adding properties marked as attributes for associative arrays
			foreach ( $data as $key => $obj ) {
				$child_path = "$path/$key";
				if ( $this->is_attribute( $child_path ) ) {
					$value  = $this->xml_attribute( $obj );
					$option = rgar( $this->options, $child_path );
					if ( strlen( $value ) > 0 || rgar( $option, "allow_empty" ) ) {
						$xml .= " $key=\"$value\"";
						$is_empty = false;
					}
				}
			}
		}
		// Closing element start tag
		$xml .= ">";

		// For a regular array, the child element (if not specified in the options) will be the singular version of the parent element(i.e. <forms><form>...</form><form>...</form></forms>)
		$child_node_name = isset( $this->options[$path]["array_tag"] ) ? $this->options[$path]["array_tag"] : $this->to_singular( $parent_node_name );

		// Adding other properties as elements
		foreach ( $data as $key => $obj ) {
			$node_name  = $is_associative ? $key : $child_node_name;
			$child_path = "$path/$node_name";
			if ( ! $this->is_attribute( $child_path ) ) {

				$child_xml = $this->serialize( $node_name, $obj, $child_path );
				if ( strlen( $child_xml ) > 0 ) {
					$xml .= $child_xml;
					$is_empty = false;
				}
			}
		}

		// Closing parent node
		$xml .= "$padding</$parent_node_name>";

		return $is_empty ? "" : $xml;
	}

	/**
	 * Unserializes XML into an object to be used in PHP
	 *
	 * @access public
	 *
	 * @param string $xml_string The XML string to be unserialized
	 *
	 * @return array The unserialized array
	 */
	public function unserialize( $xml_string ) {
		$xml_string = trim( $xml_string );

		$loader = libxml_disable_entity_loader( true );
		$errors = libxml_use_internal_errors( true );

		$xml_parser = xml_parser_create();
		$values     = array();
		xml_parser_set_option( $xml_parser, XML_OPTION_CASE_FOLDING, false );
		xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );

		xml_parse_into_struct( $xml_parser, $xml_string, $values );

		$object = $this->unserialize_node( $values, 0 );
		xml_parser_free( $xml_parser );

		libxml_use_internal_errors( $errors );
		libxml_disable_entity_loader( $loader );

		return $object;
	}

	/**
	 * Unserializes a node to be used in PHP
	 *
	 * @access private
	 *
	 * @param array  $values The values to be unserialized
	 * @param string $index  The index to unserialize
	 *
	 * @return array|string
	 */
	private function unserialize_node( $values, $index ) {
		$current = isset( $values[$index] ) ? $values[$index] : false;

		// Initializing current object
		$obj = array();

		// Each attribute becomes a property of the object
		if ( isset( $current["attributes"] ) && is_array( $current["attributes"] ) ) {
			foreach ( $current["attributes"] as $key => $attribute ) {
				$obj[$key] = $attribute;
			}
		}

		// For nodes without children(i.e. <title>contact us</title> or <rule fieldId="10" operator="is" />), simply return its content
		if ( $current["type"] == "complete" ) {
			$val = isset( $current["value"] ) ? $current["value"] : "";

			return ! empty( $obj ) ? $obj : $val;
		}

		// Get the current node's immediate children
		$children = $this->get_children( $values, $index );

		if ( is_array( $children ) ) {
			// If all children have the same tag, add them as regular array items (not associative)
			$is_identical_tags    = $this->has_identical_tags( $children );
			$unserialize_as_array = $is_identical_tags
				&& isset( $children[0]["tag"] )
				&& isset( $this->options[$children[0]["tag"]] )
				&& $this->options[$children[0]["tag"]]["unserialize_as_array"];

			// Serialize every child and add it to the object (as a regular array item, or as an associative array entry)
			foreach ( $children as $child ) {
				$child_obj = $this->unserialize_node( $values, $child["index"] );
				if ( $unserialize_as_array ) {
					$obj[] = $child_obj;
				} else {
					$obj[$child["tag"]] = $child_obj;
				}
			}
		}

		return $obj;
	}

	/**
	 * Gets the children to be added to the parent node.
	 *
	 * @access private
	 *
	 * @param array $values       The values to be added
	 * @param int   $parent_index The index of the parent
	 *
	 * @return array
	 */
	private function get_children( $values, $parent_index ) {
		$level = isset( $values[$parent_index]["level"] ) ? $values[$parent_index]["level"] + 1 : false;
		$nodes = array();
		for ( $i = $parent_index + 1, $count = sizeof( $values ); $i < $count; $i ++ ) {
			$current = $values[$i];

			//If we have reached the close tag for the parent node, we are done. Return the current nodes.
			if ( $current["level"] == $level - 1 && $current["type"] == "close" ) {
				return $nodes;
			} else if ( $current["level"] == $level && ( $current["type"] == "open" || $current["type"] == "complete" ) ) {
				$nodes[] = array( "tag" => $current["tag"], "index" => $i );
			} //this is a child, add it to the list of nodes

		}

		return $nodes;
	}

	/**
	 * Checks if the nodes have identical tags
	 *
	 * @access private
	 *
	 * @param array $nodes Nodes to check
	 *
	 * @return bool
	 */
	private function has_identical_tags( $nodes ) {
		$tag = isset( $nodes[0]["tag"] ) ? $nodes[0]["tag"] : false;
		foreach ( $nodes as $node ) {
			if ( $node["tag"] != $tag ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks is a property is an attribute
	 *
	 * @access private
	 *
	 * @param $path
	 *
	 * @return
	 */
	private function is_attribute( $path ) {
		$option = rgar( $this->options, $path );

		return rgar( $option, "is_attribute" );
	}

	/**
	 * Formats an XML value as either content or CDATA
	 *
	 * @param string $node_name The node name
	 * @param string $value     The value to insert
	 *
	 * @return string The formatted content
	 */
	private function xml_value( $node_name, $value ) {
		if ( strlen( $value ) == 0 ) {
			return "";
		}

		if ( $this->xml_is_cdata( $node_name ) ) {
			return $this->xml_cdata( $value );
		} else {
			return $this->xml_content( $value );
		}
	}

	/**
	 * Escapes an XML attribute
	 *
	 * @access private
	 *
	 * @param string $value The attribute value
	 *
	 * @return string The escaped attribute
	 */
	private function xml_attribute( $value ) {
		return esc_attr( $value );
	}

	/**
	 * Formats a value as XML CDATA
	 *
	 * @access private
	 *
	 * @param string $value The value
	 *
	 * @return string The formatted string
	 */
	private function xml_cdata( $value ) {
		return "<![CDATA[$value" . ( ( substr( $value, - 1 ) == ']' ) ? ' ' : '' ) . "]]>";
	}

	/**
	 * Returns XML content
	 *
	 * @param string $value The value
	 *
	 * @return string
	 */
	private function xml_content( $value ) {
		return $value;
	}

	/**
	 * Checks if an XML tag is CDATA.
	 *
	 * Always returns true when run directly from the base class.
	 *
	 * @access private
	 *
	 * @param null $node_name Not used in base class.
	 *
	 * @return bool true
	 */
	private function xml_is_cdata( $node_name ) {
		return true;
	}

	/**
	 * Checks if an an item is an associative array
	 *
	 * @access private
	 *
	 * @param array $array The array to check
	 *
	 * @return bool True if an associative array.  Otherwise, false.
	 */
	private function is_assoc( $array ) {
		return is_array( $array ) && array_diff_key( $array, array_keys( array_keys( $array ) ) );
	}

	/**
	 * Converts a string to its singular version
	 *
	 * @access private
	 *
	 * @param string $str The string to convert
	 *
	 * @return string The string after conversion
	 */
	private function to_singular( $str ) {

		$last3  = strtolower( substr( $str, strlen( $str ) - 3 ) );
		$fourth = strtolower( substr( $str, strlen( $str ) - 4, 1 ) );

		if ( $last3 == "ies" && in_array( $fourth, array( "a", "e", "i", "o", "u" ) ) ) {
			return substr( $str, 0, strlen( $str ) - 3 ) . "y";
		} else {
			return substr( $str, 0, strlen( $str ) - 1 );
		}
	}
}

if ( ! function_exists( "rgar" ) ) {
	function rgar( $array, $name ) {
		if ( isset( $array[$name] ) ) {
			return $array[$name];
		}

		return '';
	}
}

