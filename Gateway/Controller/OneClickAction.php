<?php

namespace Easytransac\Gateway\Controller;

use Easytransac\Gateway\Model\EasytransacApi;

/**
 * OneClick payment logic parent for OneClick.
 */
class OneClickAction extends \Easytransac\Gateway\Controller\NotifyAction
{

	public function execute() {
		if(!$this->customerSession->isLoggedIn()) {
			throw new \Exception('EasyTransac : Not logged in.');
		}
	}
	
}
