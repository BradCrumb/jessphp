<?php
/**
 * Jessphp
 *
 * Jessphp is a Javascript compiler that adds extra functionality to your javascript code.
 * For example it gives you Javascript requires and compiles them to a merged Javacript file
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2013 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @package JessCompiler
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 */
class JessCompiler {

	public $importDir = '';

/**
 * Constructor
 */
	public function __construct() {
	}

/**
 * Execute jessphp on a .jess file or a jessphp cache structure
 *
 * The jessphp cache structure contains information about a specific
 * jess file having been parsed. It can be used as a hint for future
 * calls to determine whether or not a rebuild is required.
 *
 * The cache structure contains two important keys that may be used
 * externally:
 *
 * compiled: The final compiled JS
 * updated: The time (in seconds) the JS was last compiled
 *
 * The cache structure is a plain-ol' PHP associative array and can
 * be serialized and unserialized without a hitch.
 *
 * The method is a copy of the cachedCompile method of "lessphp": http://leafo.net/lessphp
 *
 * @param mixed $in Input
 * @param bool $force Force rebuild?
 *
 * @return array lessphp cache structure
 */
	public function cachedCompile($in, $force = false) {
		// assume no root
		$root = null;

		if (is_string($in)) {
			$root = $in;
		} elseif (is_array($in) && isset($in['root'])) {
			if ($force || !isset($in['files'])) {
				// If we are forcing a recompile or if for some reason the
				// structure does not contain any file information we should
				// specify the root to trigger a rebuild.
				$root = $in['root'];
			} elseif (isset($in['files']) && is_array($in['files'])) {
				foreach ($in['files'] as $fname => $ftime) {
					if (!file_exists($fname) || filemtime($fname) > $ftime) {
						// One of the files we knew about previously has changed
						// so we should look at our incoming root again.
						$root = $in['root'];
						break;
					}
				}
			}
		} else {
			// TODO: Throw an exception? We got neither a string nor something
			// that looks like a compatible lessphp cache structure.
			return null;
		}

		if ($root !== null) {
			// If we have a root value which means we should rebuild.
			$out = array();
			$out['root'] = $root;
			$out['compiled'] = $this->compileFile($root);
			$out['files'] = $this->allParsedFiles();
			$out['updated'] = time();
			return $out;
		} else {
			// No changes, pass back the structure
			// we were given initially.
			return $in;
		}
	}

/**
 * Compile a Jess file
 *
 * @param {String} $fname Filename to compile
 *
 * @return {String} Compiled string
 */
	public function compileFile($fname, $outFname = null) {
		if (!is_readable($fname)) {
			throw new Exception('JessCompiler Error: failed to find ' . $fname);
		}

		$pi = pathinfo($fname);

		$oldImport = $this->importDir;

		//Grap the current import dir and append the dir of the current file
		$this->importDir = (array)$this->importDir;
		$this->importDir[] = $pi['dirname'] . '/';

		$this->allParsedFiles = array();
		$this->_addParsedFile($fname);

		$out = $this->compile(file_get_contents($fname), $fname);

		$this->importDir = $oldImport;

		if ($outFname !== null) {
			return file_put_contents($outFname, $out);
		}

		return $out;
	}

/**
 * Compile a Jess String
 *
 * @param {String} $string Jess string
 *
 * @return {String} Compiled string
 */
	public function compile($string, $name = null) {
		$out = $this->__compileFunctions($string);

		return $out;
	}

/**
 * Compile function calls on the "jess" object
 *
 * @param {String} $string Jess file contents
 *
 * @return {String} String with all function call compiled
 */
	private function __compileFunctions($string) {
		preg_match_all("/jess\s*\.\s*([a-z0-9_]*)\((.*?)\);/smi", $string, $functionCalls, PREG_SET_ORDER);

		foreach ($functionCalls as $call) {
			$method = $call[1];

			$methodName = 'js_' . $method;

			$attributes = $this->__parseFunctionArguments($call[2]);

			if (method_exists('JessJsObject', $methodName)) {
				$string = JessJsObject::{$methodName}(array(
					'string' => $string,
					'attributes' => $attributes,
					'full_call' => $call[0],
					'import_dir' => $this->importDir
				), $this);
			}
		}

		return $string;
	}
/**
 * This methods explodes all arguments from a full arguments string
 *
 * @param {String} $input String of arguments
 * @param {String} $delimiter Delemiter to split the attributes (Default: ',')
 * @param {String} $openTag Opening tag of a function call (Default: '\(')
 * @param {String} $closeTag Closing tag of a function call (Default: '\)')
 *
 * @return {array} Array of all arguments
 */
	private function __exploder($input, $delimiter = ',', $openTag = '\(', $closeTag = '\)') {
		// this will match any text inside parenthesis
		// including parenthesis itself and without nested parenthesis
		$regexp = '/' . $openTag . '[^' . $openTag . $closeTag . ']*' . $closeTag . '/';

		// put in placeholders like {{\d}}. They can be nested.
		$r = array();
		while (preg_match_all($regexp, $input, $matches)) {
			if ($matches[0]) {
				foreach ($matches[0] as $match) {
					$r[] = $match;
					$input = str_replace($match, '{{' . count($r) . '}}', $input);
				}
			} else {
				break;
			}
		}
		$output = array_map('trim', explode($delimiter, $input));

		// put everything back
		foreach ($output as &$a) {
			while (preg_match('/{{(\d+)}}/', $a, $matches)) {
				$a = str_replace($matches[0], $r[$matches[1] - 1], $a);
			}
		}

		return $output;
	}

/**
 * Parse all function arguments so we can use them
 *
 * @param {String} $argumentsString A full string of all the arguments of a function call
 *
 * @return {array} All parsed arguments
 */
	private function __parseFunctionArguments($argumentsString) {
		$rawArgs = $this->__exploder($argumentsString);

		$args = array();
		foreach ($rawArgs as $arg) {
			switch(true) {
				case $this->__isString($arg):
					$args[] = array(
						'type' => 'string',
						'value' => $this->__parseString($arg)
					);
					break;
				case $this->__isObject($arg):
					$args[] = array(
						'type' => 'object',
						'value' => json_decode($arg)
					);

					break;
			}
		}

		return $args;
	}

/**
 * Checks if the String is an Javascript Object
 *
 * @param {String} $value String to be checked
 *
 * @return {Boolean} If the String is an object or not
 */
	private function __isObject($value) {
		return $value[0] == "{" && $value[strlen($value) - 1] == "}";
	}
/**
 * Checks if the String is an Javascript String
 *
 * @param {String} $value String to be checked
 *
 * @return {Boolean} If the String is an string or not
 */
	private function __isString($value) {
		return ($value[0] == "'" && $value[strlen($value) - 1] == "'") || ($value[0] == '"' && $value[strlen($value) - 1] == '"');
	}
/**
 * Parse a javascript string
 *
 * @param {String} $value String value to be parsed
 *
 * @return {String} Parsed string
 */
	private function __parseString($value) {
		return substr($value, 1, strlen($value) - 2);
	}

/**
 * Add a file that has been parsed to the collection
 *
 * @param {String} $file File path of parsed file
 */
	protected function _addParsedFile($file) {
		$this->allParsedFiles[realpath($file)] = filemtime($file);
	}

/**
 * Retrieve all parsed files
 *
 * @return {String[]} All parsed files
 */
	public function allParsedFiles() {
		return $this->allParsedFiles;
	}

}

/**
 * The JS Object, all methods inside this class with prefix "js_" are available inside a JESS file and wil be compiled
 */
class JessJsObject {

/**
 * Require another JESS file
 *
 * @example
 * With jess.require('test'); you require the "test.jess" file,
 * The contents of test.jess will be compiled inside
 *
 * @param {array} $settings The settings we need to compile the require method:
 *                          - attributes: All the arguments of the function call
 *                          - string: The contents of the JESS file to be compiled
 *                          - import_dir: All directory to search for .jess files
 *                          - full_call: The full function call to replace the contents of the required file
 *
 * @return {String} Compiled Javascript
 */
	public static function js_require($settings, JessCompiler $jessc) {
		if (!isset($settings['attributes'][0])) {
			throw new Exception('JessCompiler Error: Require cannot have 0 arguments');
		} elseif ($settings['attributes'][0]['type'] != 'string') {
			throw new Exception("JessCompiler Error: Require only accepts a String argument");
		} elseif (count($settings['attributes']) > 1) {
			throw new Exception("JessCompiler Error: Require only accepts 1 argument");
		}

		$fileName = $settings['attributes'][0]['value'];

		//Support a require call without .jess extension
		if (strpos($fileName, '.jess') === false) {
			$fileName .= ".jess";
		}

		$string = $settings['string'];
		//Loop threw all import directories to search for the required file
		foreach ($settings['import_dir'] as $dir) {
			if (file_exists($dir . $fileName)) {
				$file = $jessc->compile(file_get_contents($dir . $fileName));

				$string = str_replace($settings['full_call'], $file, $string);

				break;
			}
		}

		if (!isset($file)) {
			throw new Exception('JessCompiler Error: Cannot require "' . $fileName . '"');
		}

		return $string;
	}
}