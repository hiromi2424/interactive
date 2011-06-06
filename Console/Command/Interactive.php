<?php
class InteractiveShell extends Shell {
	public $uses = array('Interactive.Interactive');
	public $history = array();
	public $historyCount = 20;

	public $cacheKey = 'interactive_cache';

	public function startup() {
		if ($history = Cache::read($this->cacheKey)) {
			$this->history = array_values($history);
		}
	}

	public function main() {
		$this->out('Interactive Shell');
		$this->hr();

		$cmds = array();
		if (!empty($this->args)) {
			$cmds = am(explode(';', implode(' ', $this->args)), array('Q'));
		} else {
			$cmds = array($this->in('Enter Command ("q" to quit, "?" for help):'));
		}

		foreach($cmds as $cmd) {
			if (is_numeric($cmd)) {
				if (!empty($this->history[$cmd])) {
					$cmd = $this->history[$cmd];
				} else {
					$this->out(sprintf(__('%d: command not found', true), $cmd));
					continue;
				}
			}

			switch (strtolower($cmd)) {
				case 'q':
					exit(0);
				case 'help':
				case '?':
					$this->help();
					break;
				case 'h':
					$this->history();
					break;
				case '':
					$cmd = end($this->history);
				default:
					$this->addHistory($cmd);
					$results = $this->Interactive->process($cmd);
					$this->__display($cmd, $results);
					break;
			}

			echo "\n";
		}

		$this->hr();
		$this->main();
	}

	public function addHistory($cmd) {
		$this->history[] = $cmd;
		if (count($this->history) > $this->historyCount) {
			reset($this->history);
			list($key, $value) = each($this->history);
			unset($this->history[$key]);
		}
		Cache::write($this->cacheKey, $this->history);
	}

	public function history() {
		foreach($this->history as $i => $history) {
			$this->out($i . "	" . $history);
		}
	}

	public function help() {
		$this->hr();
		$this->out(__('Interactive Shell:', true));
		$this->hr();
		$this->out(__('usage:', true));
		$this->out('Q - to quit');
		$this->out('?, help - for this help');
		$this->out('H - for history');
		$this->out('# - run that command from history');
		$this->out('<enter> on a blank line - rerun the previous command');
		$this->out('');
		$this->hr();
	}

	private function __display($cmd, $results) {
		$this->out($cmd);
		foreach($results as $result) {
			if (is_array($result['output'])) {
				$this->out(print_r($result['output'], true));
			} else {
				if (is_bool($result['output'])) {
					$result['output'] = ife($result['output'], 'true', 'false');
				}

				if ($result['raw']) {
					$this->out(htmlentities($result['output']) . "\n");
				}

				$this->out($result['output'] . "\n");
			}
		}
	}

}