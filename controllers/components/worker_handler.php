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

class WorkerHandlerComponent extends Object {

	var $name = 'WorkerHandler';
	var $components = array('MgWorkers.TaskHandler', 'MgWorkers.ProcessHandler');

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
	);

	function initialize(&$controller, $settings = array()) {
		$this->Controller =& $controller;
		$this->settings = array_merge($this->_defaults, $settings);

		Configure::write('debug', 2);
	}

	function startup(&$controller){

	}

	function delete($worker = null) {
		if(!$worker) return false;
		return $this->TaskHandler->delete($worker);
	}

	function run($worker = null) {
		if(!$worker) return false;
		$this->log($worker.'::run');
		return $this->TaskHandler->run($worker);
	}

	function add($job = array()) {
		if(!$job) return false;

		$defaults = array(
			'id' => create_guid(),
			'plugin' => null,
			'shell' => null,
			'task' => 'work',
			'server' => SERVER_NAME,
			'created' => date('Y-m-d H:i:s')
		);
		$job = array_merge($defaults, $job);

		$job['worker'] = $worker = Inflector::camelize($job['shell'] . 'Worker');
		$job['cache'] = $cache = Inflector::underscore($job['worker']);

		//App::import('Core', 'Shell'); doesn't work - why ?
		include_once(ROOT . DS . 'cake' . DS . 'console' . DS . 'libs' . DS . 'shell.php');
		App::import('Shell', ($job['plugin'] ? Inflector::camelize($job['plugin']) . '.' : null) . Inflector::camelize($job['worker']));

		$className = Inflector::camelize($job['worker'] . 'Shell');
		if(class_exists($className)) {

			# beforeWork callback
			$Shell = new $className($this);
			$Shell->initialize();
			$ShellTask =& $Shell->{Inflector::camelize($job['task'])};
			if(!empty($ShellTask) && method_exists($ShellTask, 'beforeWork')) {
				$job = $ShellTask->beforeWork($Shell, $job);
			} elseif(method_exists($Shell, 'beforeWork')) {
				$job = $Shell->beforeWork($job);
			}

			# add job to cache
			if(!empty($job)) {
				$jobs = Cache::read($job['cache'], 'workers');
				$jobs[] = $job;
				$this->log($worker . '::add_job', $job);
				Cache::write($job['cache'], $jobs, 'workers');
				return $this->run($job['worker']);
			} else {
				$this->log($worker . '::empty_after_beforeWork');
			}
		} else {
			$this->log($worker . '::!class_exists($className)');
		}

		return false;
	}

	function work($shell = null) {
		if(!$shell) return false;

		$worker = Inflector::camelize($shell . 'Worker');
		$cache = Inflector::underscore($worker);

		$jobs = Cache::read($cache, 'workers');
		if(is_array($jobs)&&isset($jobs[0])) $job =& $jobs[0];
		else $job = null;

		if (!$job && !count($jobs)) {
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'sleep');
		} elseif(!is_array($job)) {
			if(is_array($jobs)) array_shift($jobs);
			else $jobs = array();
			Cache::write($cache, $jobs, 'workers');
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'invalid_job', compact('job'));
			$this->log($jobs); exit;
			$this->work($shell);

		} else {

			$className = Inflector::camelize($job['worker'] . 'Shell');
			//App::import('Core', 'Shell'); doesn't work - why ?
			include_once(ROOT . DS . 'cake' . DS . 'console' . DS . 'libs' . DS . 'shell.php');
			$Shell = new $className($this);
			$Shell->initialize();

			$ShellTask =& $this->Controller->{Inflector::camelize($job['task'])};

			if(!empty($job['pid'])) {
				if($this->ProcessHandler->isAlive($job['pid'])) {
					$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'isAlive', compact('job'));
					exit();
				} else {
					array_shift($jobs);
					Cache::write($cache, $jobs, 'workers');
					$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'isDead', compact('job'));

					if(!empty($ShellTask) && method_exists($ShellTask, 'afterWork')) {
						$ShellTask->afterWork($this->Controller, $job);
					} elseif(method_exists($Shell, 'afterWork')) {
						$Shell->afterWork($job);
					}

					$this->work($shell);
				}
			} elseif(isset($job['pid'])&&!$job['pid']) {
				array_shift($jobs);
				Cache::write($cache, $jobs, 'workers');
				$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'invalid_pid', compact('job'));
				$this->work($shell);
			}

			# update pid with current process
			$job['pid'] = getmypid();
			Cache::write($cache, $jobs, 'workers');

			$Shell->initialize();

			if(!empty($ShellTask) && method_exists($ShellTask, 'execute')) {
				$jobs[0] = $ShellTask->execute($this->Controller, $job);
			} elseif(method_exists($Shell, $job['task'])) {
				$jobs[0] = $Shell->{$job['task']}($job);
			} else {
				array_shift($jobs);
				Cache::write($cache, $jobs, 'workers');
				$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'unknown_task', compact('job'));
				$this->work($shell);
			}

			Cache::write($cache, $jobs, 'workers');
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $shell . '::' . 'started', compact('job'));
		}

		exit;
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

		$this->Controller->log($info, $log);
	}

	function loadModel($model) {
		return $this->Controller->loadModel($model);
	}

}
?>
