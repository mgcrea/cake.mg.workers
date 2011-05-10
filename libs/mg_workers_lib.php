<?php
/**
 * Copyright 2010, Magenta Creations (@olouv)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010, Magenta Creations (@olouv)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class MgWorkersLib {

/**
 * Performs a search in haystack array provided
 *
 * @param string $needle
 * @param array $hastack
 * @param boolean $search_keys
 * @return string Key found
 */
	function array_find($needle, $haystack = array(), $search_keys = false) {
		if(!$haystack) return false;
		foreach($haystack as $key => $value) {
			$what = ($search_keys) ? $key : $value;
			if(is_array($what) && $key = array_find($needle, $what, $search_keys) && $key) return $key;
			elseif(is_string($what) && strpos($what, $needle) !== false) return $key;
		}
		return false;
	}

/**
 * Converts dos to unix (cygwin) path
 *
 * @param string $path
 * @param boolean $checkDS
 * @return string converted $path
 */
	function cygpath($path, $checkDS = false) {
		if((!$checkDS || DS == '\\') && preg_match('/' . '([a-z]):\\\\((?:[-\\w\\.\\d\\`]+\\\\)*(?:[-\\w\\.\\d\\`]+)?)(\\s+(.*))?' . '/is', $path, $m)) {
			return strtolower('/cygdrive/' . $m[1] . '/' . str_replace('\\', '/', $m[2])) . (!empty($m[4]) ? ' ' . $m[4] : null);
		}
		return $path;
	}

/**
 * Execute a command with pipes control
 *
 * @param string $subject
 * @param boolean $explode
 * @return array $pipes
 */
	function proc_exec($cmd, $explode = false) {
		if($process = proc_open($cmd, array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w")), $pipes, TMP)) {
			$output_pipes = array_map("stream_get_contents", $pipes);
			array_map('fclose', $pipes);
			proc_close($process);
			if($explode) foreach($output_pipes as &$pipe) $pipe = explode("\n", $pipe);
			return $output_pipes;
		}
		return false;
	}

}

?>
