<?php
class InteractiveController extends Controller {
	public $name = 'Interactive';
	public $uses = array('Interactive.Interactive');
	public $components = array('RequestHandler');
	public $helpers = array('DebugKit.Toolbar' => array('output' => 'DebugKit.HtmlToolbar'));

	public function beforeFilter() {
		if (!empty($this->Security)) {
			$this->Security->validatePost = false;
		}
	}

	public function cmd() {
		// the debug_kit toolbar component, which is probably included in AppController
		// forces the output to be FirePHP, which means we can't use makeNeatArray
		$this->helpers['DebugKit.Toolbar']['output'] = 'DebugKit.HtmlToolbar';

		if (Configure::read('debug') == 0) {
			return $this->redirect($this->referer());
		}

		Configure::write('debug', 0);

		if (empty($this->data['Interactive']['cmd'])) {
			return;
		}

		$Controller = $this;
		$View = new $this->viewClass($this);
		$this->Interactive->set(compact('Controller', 'View'));
		$results = $this->Interactive->process($this->data['Interactive']['cmd']);
		$this->set('results', $results);
	}

}
