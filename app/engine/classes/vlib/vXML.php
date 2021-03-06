<?php
/**
 * Provides functionality for XML files
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * http://veluslib.opensource.velusuniverse.com/docs/UTF-8 for more information.
 * 
 * @copyright  Copyright (c) 2014-2019 Alan Johnston, Velus Universe Ltd
 * @author     Alan Johnston [aj] <alan.johnston@velusuniverse.co.uk>
 * @author     Alan Johnston, Velus Universe Ltd [aj-vu] <alan.johnston@velusuniverse.co.uk>
 * @license    http://veluslib.opensource.velusuniverse.com/license
 * 
 * @package    Velus Lib
 * 
 * @version    0.0.1b
 * @changes    0.0.1b    The initial implementation [aj, 2014-12-13]
 * 
 * @link       http://veluslib.opensource.velusuniverse.com/vXML
 */
class vXML implements ArrayAccess
{
	// The following constants allow for nice looking callbacks to static methods
	const encode     = 'vXML::encode';
	const sendHeader = 'vXML::sendHeader';
	
	
	/**
	 * Encodes content for display in a UTF-8 encoded XML document
	 * 
	 * @param  string $content  The content to encode
	 * @return string  The encoded content
	 */
	static public function encode($content)
	{
		return htmlspecialchars(html_entity_decode($content, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	}


	/**
	 * This works around a bug in MAMP 1.9.4+ and PHP 5.3 where iconv()
	 * does not seem to properly assign the return value to a variable, but
	 * does work when returning the value.
	 *
	 * @param string $in_charset   The incoming character encoding
	 * @param string $out_charset  The outgoing character encoding
	 * @param string $string       The string to convert
	 * @return string  The converted string
	 */
	static private function iconv($in_charset, $out_charset, $string)
	{
		return iconv($in_charset, $out_charset, $string);
	}
	
	
	/**
	 * Sets the proper `Content-Type` HTTP header for a UTF-8 XML file
	 * 
	 * @return void
	 */
	static public function sendHeader()
	{
		header('Content-Type: text/xml; charset=utf-8');
	}
	
	
	/**
	 * Custom prefix => namespace URI mappings
	 * 
	 * @var array
	 */
	protected $__custom_prefixes;
	
	/**
	 * The dom element for this XML
	 * 
	 * @var DOMElement
	 */
	protected $__dom;
	
	/**
	 * An XPath object for performing xpath lookups
	 * 
	 * @var DOMXPath
	 */
	protected $__xpath;
	
	/**
	 * The XML string for serialization
	 * 
	 * @var string
	 */
	protected $__xml;
	
	
	/**
	 * Create the XML object from a string, vFile or URL
	 * 
	 * The `$default_namespace` will be used for any sort of methods calls,
	 * member access or array access when the element or attribute name does
	 * not include a `:`.
	 * 
	 * @throws vValidationException    When the source XML is invalid or does not exist
	 * 
	 * @param  vFile|string  $source                 The source of the XML, either an vFile object, a string of XML, a file path or a URL
	 * @param  numeric       $http_timeout           The timeout to use in seconds when requesting an XML file from a URL
	 * @param  boolean       $fix_entities_encoding  This will fix two common XML authoring errors and should only be used when experiencing decoding issues - HTML entities that haven't been encoded as XML, and XML content published in ISO-8859-1 or Windows-1252 encoding without an explicit encoding attribute
	 * @param  vFile|string  |$source
	 * @param  boolean       |$fix_entities_encoding
	 * @return vXML
	 */
	public function __construct($source, $http_timeout=NULL, $fix_entities_encoding=NULL)
	{
		if (is_bool($http_timeout)) {
			$fix_entities_encoding = $http_timeout;
			$http_timeout = NULL;
		}
		
		// Prevent spitting out errors to we can throw exceptions
		$old_setting = libxml_use_internal_errors(TRUE);
		
		$exception_message = NULL;
		try {
			if ($source instanceof vFile && $fix_entities_encoding) {
				$source = $source->read();
			}
			
			if ($source instanceof DOMElement) {
				$this->__dom = $source;
				$xml         = TRUE;
				
			} elseif ($source instanceof vFile) {
				$xml = simplexml_load_file($source->getPath());
				
			// This handles URLs specially by adding a reasonable timeout
			} elseif (preg_match('#^(?P<protocol>http(s)?)://#', $source, $matches)) {
				
				if ($http_timeout === NULL) {
					$http_timeout = ini_get('default_socket_timeout');	
				}
				
				// We use the appropriate protocol here so PHP can supress IIS https:// warnings
				$context = stream_context_create(array(
					$matches['protocol'] => array('timeout' => $http_timeout)
				));
				
				// If the URL is not loaded in time, this supresses the file_get_contents() warning
				vCore::startErrorCapture(E_WARNING);
				$xml = file_get_contents($source, 0, $context);
				vCore::stopErrorCapture();
				
				if (!$xml) {
					throw new vExpectedException('The URL specified, %s, could not be loaded', $source);
				}
				
				if ($fix_entities_encoding) {
					$xml = $this->fixEntitiesEncoding($xml);
				}
				
				$xml = new SimpleXMLElement($xml);
				
			} else {
				$is_path = $source && !preg_match('#^\s*<#', $source);
				
				if ($fix_entities_encoding) {
					if ($is_path) {
						$source = file_get_contents($source);
						$is_path = FALSE;
					}
					$source = $this->fixEntitiesEncoding($source);
				}
				
				$xml     = new SimpleXMLElement($source, 0, $is_path);
			}
		
		} catch (Exception $e) {
			$exception_message = $e->getMessage();
			$xml = FALSE;
		}
		
		// We want it to be clear when XML parsing issues occur
		if ($xml === FALSE) {
			$errors = libxml_get_errors();
			foreach ($errors as $error) {
				$exception_message .= "\n" . rtrim($error->message);	
			}
			// If internal errors were off before, turn them back off
			if (!$old_setting) {
				libxml_use_internal_errors(FALSE);	
			}
			throw new vValidationException(str_replace('%', '%%', $exception_message));
		}
		
		if (!$old_setting) {
			libxml_use_internal_errors(FALSE);	
		}
		
		if (!$this->__dom) {
			$this->__dom = dom_import_simplexml($xml);
		}
		
		if ($this->__dom->namespaceURI && $this->__dom->prefix == '') {
			$this->addCustomPrefix('__', $this->__dom->namespaceURI);
		}
	}
	
	
	/**
	 * Allows access to the text content of a child tag
	 * 
	 * The child element name (`$name`) may start with a namespace prefix and a
	 * `:` to indicate what namespace it is part of. A blank namespace prefix
	 * (i.e. an element name starting with `:`) is treated as the XML default
	 * namespace.
	 * 
	 * @internal
	 * 
	 * @param  string $name  The child element to retrieve
	 * @return vXML|NULL  The child element requested
	 */
	public function __get($name)
	{   
		// Handle nice callback syntax
		static $methods = array(
			'__construct'     => TRUE,
			'__get'           => TRUE,
			'__isset'         => TRUE,
			'__sleep'         => TRUE,
			'__toString'      => TRUE,
			'__wakeup'        => TRUE,
			'addCustomPrefix' => TRUE,  
			'getName'         => TRUE,
			'getNamespace'    => TRUE,
			'getPrefix'       => TRUE,
			'getText'         => TRUE,  
			'offsetExists'    => TRUE,
			'offsetGet'       => TRUE, 
			'offsetSet'       => TRUE,
			'offsetUnset'     => TRUE, 
			'toXML'           => TRUE,
			'xpath'           => TRUE
		);
		
		if (isset($methods[$name])) {
			return array($this, $name);
		}
		
		if ($this->__dom->namespaceURI && $this->__dom->prefix == '' && strpos($name, ':') === FALSE) {
			$name = '__:' . $name;
		}
		$first_child = $this->query($name . '[1]');
		if ($first_child->length) {
			return $first_child->item(0)->textContent;
		}
		
		return NULL;
	}
	
	
	/**
	 * The child element name (`$name`) may start with a namespace prefix and a
	 * `:` to indicate what namespace it is part of. A blank namespace prefix
	 * (i.e. an element name starting with `:`) is treated as the XML default
	 * namespace.
	 * 
	 * @internal
	 * 
	 * @param  string $name  The child element to check - see method description for details about namespaces
	 * @return boolean  If the child element is set
	 */
	public function __isset($name)
	{
		if ($this->__dom->namespaceURI && $this->__dom->prefix == '' && strpos($name, ':') === FALSE) {
			$name = '__:' . $name;
		}
		return (boolean) $this->query($name . '[1]')->length;
	}
	
	
	/**
	 * Prevents users from trying to set elements
	 * 
	 * @internal
	 * 
	 * @param  string $name   The element to set
	 * @param  mixed  $value  The value to set
	 * @return void
	 */	
	public function __set($name, $value)
	{
		throw new vProgrammerException('The %s class does not support modifying XML', __CLASS__);
	}
	
	
	/**
	 * The XML needs to be made into a string before being serialized
	 * 
	 * @internal
	 * 
	 * @return array  The members to serialize
	 */
	public function __sleep()
	{
		$this->__xml = $this->toXML();
		return array('__custom_prefixes', '__xml');	
	}
	
	
	/**
	 * Gets the string inside the root XML element
	 * 
	 * @return string  The text inside the root element
	 */
	public function __toString()
	{
		return (string) $this->__dom->textContent;	
	}
	
	
	/**
	 * Prevents users from trying to unset elements
	 * 
	 * @internal
	 * 
	 * @param  string $name  The element to unset
	 * @return void
	 */	
	public function __unset($name)
	{
		throw new vProgrammerException('The %s class does not support modifying XML', __CLASS__);
	}
	
	
	/**
	 * The XML needs to be made into a DOMElement when woken up
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __wakeup()
	{
		$this->__dom = dom_import_simplexml(new SimpleXMLElement($this->__xml));
		$this->__xml = NULL;
	}
	
	
	/**
	 * Adds a custom namespace prefix to full namespace mapping
	 * 
	 * This namespace prefix will be valid for any operation on this object,
	 * including calls to ::xpath().
	 * 
	 * @param  string $ns_prefix  The custom namespace prefix
	 * @param  string $namespace  The full namespace it maps to
	 * @return void             
	 */
	public function addCustomPrefix($ns_prefix, $namespace)
	{
		if (!$this->__custom_prefixes) {
			$this->__custom_prefixes = array();	
		}
		$this->__custom_prefixes[$ns_prefix] = $namespace;
		if ($this->__xpath) {
			$this->__xpath->registerNamespace($ns_prefix, $namespace);
		}
	}
	
	
	/**
	 * Fixes HTML entities that aren't XML encoded and fixes ISO-8859-1/Windows-1252 encoded content that does not have an encoding attribute
	 *
	 * @param string $xml  The XML to fix
	 * @return string  The fixed XML
	 */
	private function fixEntitiesEncoding($xml)
	{
		preg_match('#^<\?xml.*? encoding="([^"]+)".*?\?>#i', $xml, $match);
		$encoding = empty($match[1]) ? NULL : $match[1];
		
		// Try to detect the encoding via the BOM
		if ($encoding === NULL) {
			if (substr($xml, 0, 3) == "\x0\x0\xFE\xFF") {
				$encoding = 'UTF-32BE';
			} elseif (substr($xml, 0, 3) == "\xFF\xFE\x0\x0") {
				$encoding = 'UTF-32LE';
			} elseif (substr($xml, 0, 2) == "\xFE\xFF") {
				$encoding = 'UTF-16BE';
			} elseif (substr($xml, 0, 2) == "\xFF\xFE") {
				$encoding = 'UTF-16LE';
			} else {
				$encoding = 'UTF-8';
			}
		}
		
		// This fixes broken encodings where the XML author puts ISO-8859-1 or
		// Windows-1252 into an XML file without an encoding or UTF-8 encoding
		if (preg_replace('#[^a-z0-9]#', '', strtolower($encoding)) == 'utf8') {
			// Remove the UTF-8 BOM if present
			$xml = preg_replace("#^\xEF\xBB\xBF#", '', $xml);
			vCore::startErrorCapture(E_NOTICE);
			$cleaned = self::iconv('UTF-8', 'UTF-8', $xml);
			if ($cleaned != $xml) {
				$xml = self::iconv('Windows-1252', 'UTF-8', $xml);
			}
			vCore::stopErrorCapture();
		}
		
		$num_matches = preg_match_all('#&(?!gt|lt|amp|quot|apos)\w+;#', $xml, $matches, PREG_SET_ORDER);
		if ($num_matches) {
			// We convert non-UTF-* content to UTF-8 because some character sets
			// don't have characters for all HTML entities
			if (substr(strtolower($encoding), 0, 3) != 'utf') {
				$xml = self::iconv($encoding, 'UTF-8', $xml);
				$xml = preg_replace('#^(<\?xml.*?) encoding="[^"]+"(.*?\?>)#', '\1 encoding="UTF-8"\2', $xml);
				$encoding = 'UTF-8';
			}
			
			$entities = array();
			foreach ($matches as $match) {
				$entities[$match[0]] = html_entity_decode($match[0], ENT_COMPAT, $encoding);
			}
			$xml = strtr($xml, $entities);
		}
		
		return $xml;
	}
	
	
	/**
	 * Returns the name of the current element
	 * 
	 * @return string  The name of the current element
	 */
	public function getName()
	{
		return $this->__dom->localName;
	}
	
	
	/**
	 * Returns the namespace of the current element
	 * 
	 * @return string  The namespace of the current element
	 */
	public function getNamespace()
	{
		return $this->__dom->namespaceURI;
	}
	
	
	/**
	 * Returns the namespace prefix of the current element
	 * 
	 * @return string  The namespace prefix of the current element
	 */
	public function getPrefix()
	{
		return $this->__dom->prefix;
	}
	
	
	/**
	 * Returns the string text of the current element
	 * 
	 * @return string  The string text of the current element
	 */
	public function getText()
	{
		return (string) $this->__dom->textContent;
	}
	
	
	/**
	 * Provides functionality for isset() and empty() (required by arrayaccess interface)
	 * 
	 * Offsets refers to an attribute name. Attribute may start with a namespace
	 * prefix and a `:` to indicate what namespace the attribute is part of. A
	 * blank namespace prefix (i.e. an offset starting with `:`) is treated as
	 * the XML default namespace.
	 * 
	 * @internal
	 * 
	 * @param  string $offset  The offset to check
	 * @return boolean  If the offset exists
	 */
	public function offsetExists($offset)
	{
		return (boolean) $this->query('@' . $offset . '[1]')->length;
	}
	
	
	/**
	 * Provides functionality for get [index] syntax (required by ArrayAccess interface)
	 * 
	 * Offsets refers to an attribute name. Attribute may start with a namespace
	 * prefix and a `:` to indicate what namespace the attribute is part of. A
	 * blank namespace prefix (i.e. an offset starting with `:`) is treated as
	 * the XML default namespace.
	 * 
	 * @internal
	 * 
	 * @param  string $offset  The attribute to retrieve the value for
	 * @return string  The value of the offset
	 */
	public function offsetGet($offset)
	{
		$attribute = $this->query('@' . $offset . '[1]');
		if ($attribute->length) {
			return $attribute->item(0)->nodeValue;
		}
		return NULL;
	}
	
	
	/**
	 * Required by ArrayAccess interface
	 * 
	 * @internal
	 * 
	 * @param  integer|string $offset  The offset to set
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		throw new vProgrammerException('The %s class does not support modifying XML', __CLASS__);
	}
	
	
	/**
	 * Required by ArrayAccess interface
	 * 
	 * @internal
	 * 
	 * @param  integer|string $offset  The offset to unset
	 * @return void
	 */	
	public function offsetUnset($offset)
	{
		throw new vProgrammerException('The %s class does not support modifying XML', __CLASS__);
	}
	
	
	/**
	 * performs an XPath query on the current element, returning the raw results
	 * 
	 * @param  string $path  The XPath path to query
	 * @return array  The matching elements
	 */
	protected function query($path)
	{
		if (!$this->__xpath) {
			$this->__xpath = new DOMXPath($this->__dom->ownerDocument);
			if ($this->__custom_prefixes) {
				foreach ($this->__custom_prefixes as $prefix => $namespace) {
					$this->__xpath->registerNamespace($prefix, $namespace);
				}
			}	
		}
		
		// Prevent spitting out errors to we can throw exceptions
		$old_setting = libxml_use_internal_errors(TRUE);
		
		$result = $this->__xpath->query($path, $this->__dom);
		
		// We want it to be clear when XML parsing issues occur
		if ($result === FALSE) {
			$errors            = libxml_get_errors();
			$exception_message = '';
			
			foreach ($errors as $error) {
				$exception_message .= "\n" . $error->message;	
			}
			
			// If internal errors were off before, turn them back off
			if (!$old_setting) {
				libxml_use_internal_errors(FALSE);	
			}
			
			throw new vProgrammerException(str_replace('%', '%%', trim($exception_message)));
		}
		
		if (!$old_setting) {
			libxml_use_internal_errors(FALSE);	
		}
		
		return $result;	
	}
	
	
	/**
	 * Returns a well-formed XML string from the current element
	 * 
	 * @return string  The XML
	 */
	public function toXML()
	{
		return $this->__dom->ownerDocument->saveXML($this->__dom->parentNode === $this->__dom->ownerDocument ? $this->__dom->parentNode : $this->__dom);	
	}
	
	
	/**
	 * Executes an XPath query on the current element, returning an array of matching elements
	 * 
	 * @param  string  $path        The XPath path to query
	 * @param  boolean $first_only  If only the first match should be returned
	 * @return array|string|vXML  An array of matching elements, or a string or vXML object if `$first_only` is `TRUE`
	 */
	public function xpath($path, $first_only=FALSE)
	{
		$result = $this->query($path);
		
		if ($first_only) {
			if (!$result->length) { return NULL; }
			$result = array($result->item(0));
			
		} else {
			if (!$result->length) { return array(); }
		}
		
		$keys_to_remove = array();
		$output         = array();
		
		foreach ($result as $element) {
			
			if ($element instanceof DOMElement) {
				$child = new vXML($element);
				$child->__custom_prefixes = $this->__custom_prefixes;
				if ($child->__dom->namespaceURI && $child->__dom->prefix == '') {
					$child->addCustomPrefix('__', $child->__dom->namespaceURI);
				}
				$output[] = $child;
			
			} elseif ($element instanceof DOMCharacterData) {
				$output[] = $element->data;
			
			} elseif ($element instanceof DOMAttr) {
				
				$key      = $element->name;
				if ($element->prefix) {
					$key = $element->prefix . ':' . $key;	
				}
				
				// We will create an attrname and attrname[0] key for each
				// attribute and if more than one is found we remove the
				// key attrname. If only one is found we remove attrname[0].
				$key_1 = $key . '[1]';
				
				if (isset($output[$key_1])) {
					$i = 1;
					while (isset($output[$key . '[' . $i . ']'])) {
						$i++;
					}
					
					// This removes the key without the array index if more than one was found
					unset($output[$key]);
					unset($keys_to_remove[$key_1]);
					
					$key = $key . '[' . $i . ']';
				
				} else {
					$output[$key_1] = $element->nodeValue;
					$keys_to_remove[$key_1] = TRUE;		
				}
				
				$output[$key] = $element->nodeValue;	
			}
		}
		
		foreach ($keys_to_remove as $key => $trash) {
			unset($output[$key]);	
		}
		
		if ($first_only) {
			return current($output);	
		}
		
		return $output;
	}
}



/**
 * Copyright (c) Alan Johnston of Velus Universe Ltd <alan.johnston@velusuniverse.co.uk>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */