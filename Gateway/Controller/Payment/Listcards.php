<?php

namespace Easytransac\Gateway\Controller\Payment;

use Easytransac\Gateway\Model\EasytransacApi;

Class Listcards extends \Easytransac\Gateway\Controller\OneClickAction
{

	/**
	 * Cards aliases.
	 */
	public function execute()
	{
		parent::execute();
		$output = array('status' => 0);
		
		if (($client_id = $this->easytransac->getConfigData('api_key')))
		{
			$data = array(
				"ClientId" => $this->getClientId(),
			);
			$response = $this->api->setServiceListCards()->communicate(
					$this->easytransac->getConfigData('api_key'), $data);

			if (!empty($response['Result']))
			{
				$buffer = array();
				foreach ($response['Result'] as $row)
				{
					$buffer[] = array_intersect_key($row, array('Alias' => 1, 'CardNumber' => 1));
				}
				$output = array('status' => !empty($buffer), 'packet' => $buffer);
			}
		}
		echo json_encode($output);
	}
}
