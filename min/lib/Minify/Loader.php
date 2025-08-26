<?php
/**
 * Class Minify_Loader
 * @package Minify
 */

/**
 * Class autoloader
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class Minify_Loader {
	public function loadClass($class) {
		// check if namespace exists and separate the namespace from the class
		$ns = substr($class, 0, strrpos($class, '\\'));
		if (!empty($ns)) {
			$classonly = substr($class, strrpos($class, '\\') + 1);
		} else {
			$classonly = $class;
		}

		if (!class_exists($class)) {
			$file = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
			$file .= strtr($classonly, "\\_", DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) . '.php';
			if (is_readable($file)) {
				require_once($file);
			}
		}
	}

	static public function register() {
		$inst = new self();
		spl_autoload_register(array($inst, 'loadClass'));
	}
}
