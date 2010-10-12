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

class ProcessHandlerComponent extends Object {

	var $name = 'ProcessHandler';

/**
* Settings
*
* @var array
*/
	public $settings = array();

/**
* Default settings
*
* @var array
*/
	protected $_defaults = array(
		'pstools_path' => false
	);

	function initialize(&$controller, $settings = array()) {
		$this->controller =& $controller;
		$this->settings = array_merge($this->_defaults, $settings);
		if(DS == '\\' && !$this->settings['pstools_path']) $this->settings['pstools_path'] = VENDORS . 'pstools';

		if(DS == '\\' && file_exists(rtrim($this->settings['pstools_path'], DS) . DS . 'psexec.exe')) {
			$this->settings['pstools_psexec'] = rtrim($this->settings['pstools_path'], DS) . DS . 'psexec.exe';
			$this->settings['pstools_pslist'] = rtrim($this->settings['pstools_path'], DS) . DS . 'pslist.exe';
		}

		Configure::write('debug', 2);
	}

	function startup(&$controller){

	}

	function shutdown(&$controller) {

	}

	function start($cmd = null) {
		if(!$cmd) return false;

		if(DS == '\\') {
			$cmd = $this->settings['pstools_psexec'] . ' -d -accepteula ' . $cmd;
		} else { // works with cygwin + add log
			$cmd = $cmd . ' > /dev/null 2>&1 & echo $!';
		}

		$pipes = proc_exec($cmd, true);

		if(DS == '\\') {
			$return = !empty($pipes[2][5]) ? $pipes[2][5] : null;
			$pid = (preg_match("/process ID ([\d]{1,10})\./im", $return, $matches) && !empty($matches[1]) && intval($matches[1])) ? intval($matches[1]) : false;
		} else {
			$pid = intval($return) ? intval($return) : false;
		}

		$this->log(__FUNCTION__ . ' (' . __LINE__ . ')', compact('cmd', 'pipes', 'pid'));
		return $pid;

	}

	function isAlive($pid = null) {
		if(!$pid) return false;

		if(DS == '\\') {
			$cmd = $this->settings['pstools_pslist'] . ' -d -accepteula ' . $pid;
		} else {
			$cmd = 'ps ' . $pid;
		}

		$pipes = proc_exec($cmd);

		if(DS == '\\') {
			return !strpos($pipes[1], 'not found');
		} else {
			return (count(preg_split("/\n/", $return)) > 2);
		}

	}

	function kill($pid = null) {
		if(!$pid) return false;

		if(DS == '\\') {
			$cmd = ROOT . DS . 'engine' . DS . 'pstools' . DS . 'pskill.exe ' . $pid;
		} else {
			$cmd = 'kill ' . $pid;
		}

		$res = proc_open($cmd, array(array('pipe', 'r'),array('pipe', 'w'), array('pipe', 'w')), $pipes, dirname(__FILE__));
		$output = array_map('stream_get_contents', $pipes);
		$this->log($output);
		$return = $output[2] ? $output[2] : $output [1];
		array_map('fclose', $pipes);
		proc_close($res);

		if(DS == '\\') {
			return strpos($return, 'killed');
		} else {
			return empty($output[2]);
		}

	}

	/*~~ utility methods ~~*/

	function log($info = null, $data = null, $log = null) {
		if(!$info) return false;
		if(!$log) $log = SERVER_NAME . DS . date("Ymd") . '-' . SERVER_NAME . '-' . Inflector::underscore($this->name);

		if(is_array($info)) {
			if($data) $log = $data;
			$info = str_replace("\n", "\n\t", substr(print_r($info, true), 0, -1));
		} elseif(is_array($data) || is_object($data)) {
			$info = $info . "\n" . str_replace("\n", "\n\t", substr(print_r($data, true), 0, -1));
		}

		$this->controller->log($info, $log);
	}

	function loadModel($model) {
		return $this->controller->loadModel($model);
	}

}
?>
