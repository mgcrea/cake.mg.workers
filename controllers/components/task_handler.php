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

class TaskHandlerComponent extends Object {

	var $name = 'TaskHandler';

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
		'cygwin_path' => 'C:\\cygwin',
		'cygwin_crontab' => false,
		'cygwin_bash' => false
	);

	function initialize(&$controller, $settings = array()) {

		$this->controller =& $controller;
		$this->settings = array_merge($this->_defaults, $settings);

		if(DS == '\\' && file_exists(rtrim($this->settings['cygwin_path'], DS)  . DS . 'bin' . DS . 'crontab.exe')) {
			$this->settings['cygwin_crontab'] = rtrim($this->settings['cygwin_path'], DS) . DS . 'bin' . DS . 'crontab.exe';
			$this->settings['cygwin_bash'] = rtrim($this->settings['cygwin_path'], DS) . DS . 'bin' . DS . 'bash.exe';
		}

		Configure::write('debug', 2);
	}

	function startup(&$controller){

	}

	function shutdown(&$controller) {

	}

	function index() {
		if(DS == '\\' && !$this->settings['cygwin_crontab']) {
			//$cmd = "schtasks /delete /tn $name /f";
			//exec($cmd, $result);
		} else {
			return $this->__crontab_list();
		}
	}

	function add($name = null, $job = null, $options = array()) {
		if(!$job) return false;

		$defaults = array(
			'm' => '*',
			'h' => '*',
			'dom' => '*',
			'mon' => '*',
			'dow' => '*'
		);

		$options = array_merge($defaults, $options);

		self::delete($name);

		if(DS == '\\' && !$this->settings['cygwin_crontab']) {

			//preg_match('/(.+)\\/(\\d+)/is', $defaults['m'], $matches);

			$tn = $name;
			$tr = $job;
			$sd = date("d/m/Y");
			$ed = date("d/m/Y", strtotime("+10 year"));
			$st = ($options['m'] == '*');
			$st = date("H:i:30");

			$months = array('', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEV');
			$m = is_int($options['mon']) ?  $months[$m] : null;

			$days = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
			$d = is_int($options['dow']) ? $days[$d] : null;

			$cmd = "schtasks /create /tn $tn /tr \"$tr\" /sd $sd /ed $ed /st $st /sc MINUTE /mo 1 /ru \"System\"";

			//debug($cmd); exit;

			exec($cmd, $result);

		} else {
			$prev = $this->__crontab_list();

			$log = cygpath(TMP . 'logs' . DS . 'workers' . DS . '`date +\%Y\%m\%d`' . '-' . Inflector::underscore($name) . '-raw.log', true);
			$log_errors = cygpath(TMP . 'logs' . DS . 'workers' . DS . '`date +\%Y\%m\%d`' . '-' . Inflector::underscore($name) . '-errors.log', true);
			if(!strpos($job, '>')) $job .= " >> $log";
			if(!strpos($job, '2>')) $job .= " 2> $log_errors";

			$content = "#\n";
			$content .= '# CakeTask : '  . $name . "\n";
			$content .= implode(' ', $options) . ' ' . $job . "\n";
			$content .= implode("\n", $prev);

			$result = $this->__crontab_update($content);
		}

		return $result;
	}

	function edit($name = null, $job = null, $options = array()) {
		return $this->add($name, $job, $options);
	}

	function delete($name = null) {

		if(DS == '\\' && !$this->settings['cygwin_crontab']) {
			$cmd = "schtasks /delete /tn $name /f";
			exec($cmd, $result);
		} else {

			$cron = $this->__crontab_list();

			if(is_int($k = array_find('# CakeTask : ' . $name, $cron))) {
				unset($cron[$k], $cron[$k+1], $cron[$k-1]);
			}

			$result = $this->__crontab_update($cron);

		}

		return $result;

	}

	function run($name = null) {

		if(DS == '\\' && !$this->settings['cygwin_crontab']) {
			$cmd = 'schtasks /run /tn CarlipaOnlineWorker'.ucfirst($name);
			exec($cmd, $result);
		} else {
			$cron = $this->__crontab_list();
			$result = false;
			if(is_int($k = array_find('# CakeTask : ' . $name, $cron))) {
				preg_match('/([^\s]+)\s([^\s]+)\s([^\s]+)\s([^\s]+)\s([^\s]+)\s([^#\n$]*)$/i', $cron[$k+1], $matches);
				if(!empty($matches[6])) {
					$cmd = $matches[6];
					if(DS == '\\') $cmd = 'bash -c "' . $cmd . '"';
					xdebug_start_trace(TMP . 'logs' . DS . 'xdebug.log');
					$pipes = proc_exec($cmd);
					xdebug_stop_trace();
					return $pipes;
				}
			}
		}

		return $result;
	}

	/* crontab */

	function __crontab_list($user = null) {

		$pipes = proc_exec('crontab -l');
		$return = explode("\n", $pipes[1]);

		if(DS == '\\') unset($return[0], $return[1], $return[2]);

		return $return;
	}

	function __crontab_reset($user = null) {
		//exec('crontab -r', $return);

		$process = proc_open('crontab -r', array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w")), $pipes, TMP);
		$pipes = array_map("stream_get_contents", $pipes);
		proc_close($process);
		$return = explode("\n", $pipes[1]);
		//array_pop($return);

		return $return;
	}

	function __crontab_update($content = null) {
		if(is_array($content)) $content = implode("\n", $content);

		$this->__crontab_reset();

		$destination = CACHE . 'master.cron';
		file_put_contents($destination, $content, LOCK_EX);
		exec('crontab ' . cygpath($destination), $result);
		//@unlink($destination);

		return $result;
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
