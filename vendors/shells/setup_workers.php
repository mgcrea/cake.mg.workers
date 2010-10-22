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

class SetupWorkersShell extends Shell {
	var $name = 'SetupWorkers';
	var $uses = array();
	var $tasks = array();
	var $components = array('MgWorkers.TaskHandler');

	function construct() {
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


		if(DS == '\\' && !file_exists('C:\cygwin\bin\crontab.exe')) {

		} else {

			//$console_path = cygpath(CAKE . 'console', true);
			$console_path = cygpath(APP, true);
			$app_name = APP_DIR;

			//exec('crontab -r', $return);

			# player_check_task
			$shell = "player_check_task";
			$job = "${app_path}cake $shell";
			$this->TaskHandler->add(Inflector::camelize($shell), $job);

			# worker_media_converter
			$shell = "media_converter_worker";
			$job = "${app_path}cake $shell";
			$this->TaskHandler->add(Inflector::camelize($shell), $job);

			# worker_media_converter
			$shell = "broadcast_generator_worker";
			$job = "${app_path}cake $shell";
			$this->TaskHandler->add(Inflector::camelize($shell), $job);

			$this->out($this->TaskHandler->index());

		}
	}

}
?>