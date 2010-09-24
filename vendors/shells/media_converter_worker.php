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

App::import('Lib', 'MgWorkers.MgWorkersLib');

class MediaConverterWorkerShell extends Shell {

	var $name = 'MediaConverterWorker';
	var $components = array('MgWorkers.WorkerHandler', 'MgWorkers.TaskHandler', 'MgWorkers.ProcessHandler');
	var $tasks = array('Ffmpeg2theora');
	var $uses = array();

	function construct() {
		# loading $this->components
		if(!empty($this->components)) {
			foreach($this->components as $comp) {
				App::import('Component', $comp);
				list($plugin, $component) = pluginSplit($comp);
				$componentClass = $component . 'Component';
				$this->{$component} = new $componentClass;
				$this->{$component}->initialize($this);
				if(!empty($this->{$component}->components)) foreach($this->{$component}->components as $subComp) {
					list($subPlugin, $subComponent) = pluginSplit($subComp);
					if(in_array($subComp, $this->components)) $this->{$component}->{$subComponent} =& $this->{$subComponent};
					else $this->error("subComponent \"$comp->$subComp\" unavailable from the shell \"$this->name\"", E_USER_ERROR);
				}
			}
 		}
	}

	function main() {
		self::construct();

		$this->WorkerHandler->work('media_converter');
	}

	function setup() {
		$console_path = cygpath(CAKE . 'console', true);

		$shell = Inflector::underscore($this->name);
		$job = "cd $console_path && ./cake $shell -app " . APP_DIR;
		$this->TaskHandler->add($this->name, $job);
	}

	/*~~ utility methods ~~*/

	function log($info = null, $data = null, $log = null) {
		if(!$info) return false;
		if(!$log) $log = 'workers' . DS . date("Ymd") . '-' . Inflector::underscore($this->name);

		if(is_array($info)) {
			if($data) $log = $data;
			$info = str_replace("\n", "\n\t", substr(print_r($info, true), 0, -1));
		} elseif(is_array($data) || is_object($data)) {
			$info = $info . "\n" . str_replace("\n", "\n\t", substr(print_r($data, true), 0, -1));
		}

		return parent::log($info, $log);
	}

	function error($error_msg, $error_type = E_USER_NOTICE) {

		$error = array(256 => "error", 1024 => "notice");
		$type = $error[$error_type];
		$log = SERVER_NAME . DS . SERVER_NAME . '-' . $type;
		$_headings = "\n\t" . "******** " . strtoupper($type) . " ********" . "\n\t";

		parent::log(SERVER_NAME . ' ~ ' . __CLASS__ . ' ~ ' . $error_msg, 'error');
		parent::log( $_headings . $error_msg . $_headings . str_replace("\n", "\n\t", Debugger::trace()) . "\n", $log);
		$this->log( $_headings . $error_msg . $_headings . str_replace("\n", "\n\t", Debugger::trace()) . "\n");

		trigger_error($error_msg, $error_type);
	}
}
?>