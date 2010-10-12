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

class Ffmpeg2theoraTask extends Shell {
	var $name = 'Ffmpeg2theora';
	var $uses = array();

	function execute(&$shell, $job) {
		$this->Shell =& $shell;

		if(DS == '\\') {
			$exe = VENDORS . 'ffmpeg2theora' . DS . 'ffmpeg2theora.exe';
		} else {
			$exe = 'ffmpeg2theora';
		}

		if(!isset($job['options'])) $job['options'] = null;
		$job['cmd'] = $exe . ' ' . $job['options'] . ' "' . $job['input'] . '"';
		$job['started'] = microtime(true);
		$this->Shell->log(__FUNCTION__ . ' (' . __LINE__ . ') : starting', compact('job'));
		$job['pid'] = $this->Shell->ProcessHandler->start($job['cmd']);
		$this->Shell->log(__FUNCTION__ . ' (' . __LINE__ . ') : started', compact('job'));

		return $job;
	}

	function beforeWork($job) {
		$job = parent::beforeWork($job);
		return $job;
	}

}
?>