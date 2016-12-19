<?php

namespace EasyTransac\Gateway\Controller\Payment;

use Easytransac\Gateway\Model\EasytransacApi;

class Returnpage extends \Easytransac\Gateway\Controller\NotifyAction
{

	/**
	 * Return page from external payment page.
	 */
	public function execute()
	{
		// User hasn't followed the standard checkout process.
		if(empty($_SESSION['easytransac_gateway_processing_qid'])) {
			$this->_redirect('checkout/cart');
			return;
		}
		$quote = $this->quoteRepository
				->get($_SESSION['easytransac_gateway_processing_qid']);
		
		if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on'
			|| empty($_POST)) {
			 
			if (!$quote->getIsActive()) {
				// EasyTransac notification has arrived before.
				$this->pendingPage();
				return;
			}

			// HTTP wait for EasyTransac notification.
			$this->waitingPage();
			die;
		}
		
		if (!$quote->getIsActive()) {
			// EasyTransac notification has arrived before.
			$this->pendingPage();
			return;
		}

		$received_data = $_POST;

		// Data validation
		
		if(empty($received_data)) {
			$this->logger->error('EasyTransac Error: Returnpage : Empty packet');
			$this->_redirect('checkout/cart');
			return;
		}
		
		if(!EasytransacApi::validateIncoming($received_data, $this->easytransac->getConfigData('api_key'))) {
			$this->logger->error('EasyTransac Error: Returnpage : Incoming packet validation failed');
			$this->_redirect('checkout/cart');
			return;
		}
		
		$this->processResponse($received_data);
		
//		$this->_redirect('checkout/onepage/success/');
		
		switch ($received_data['Status'])
		{
			case 'failed':
				$this->failedPage();
				break;

			case 'captured':
				$this->acceptedPage();
				break;

			case 'pending':
				$this->pendingPage();
				break;

			case 'refunded':
				$this->pendingPage();
				break;
		}
	}
	
	/**
	 * Client payment waiting page.
	 */
	public function waitingPage() {
		echo '<html>
			<header><meta http-equiv="refresh" content="4"></header><body>
			<div style="background-color: #37BC9B; 
						margin: 50px; 
						padding: 40px; 
						text-align:center; 
						font-family: Arial, sans-serif; 
						color: white; 
						font-size:26px;">
				EasyTransac is processing your payment...
			</div></body>
		';
	}
	
	/**
	 * Client payment accepted page.
	 */
	public function acceptedPage() {
		echo '<html>
			<header><meta http-equiv="refresh" content="4;URL=\''. $this->storeManager->getStore()->getBaseUrl() 
				. 'customer/account/\'"></header><body>
			<div style="background-color: #37BC9B; 
						margin: 50px; 
						padding: 40px; 
						text-align:center; 
						font-family: Arial, sans-serif; 
						color: white; 
						font-size:26px;">
				Your payment has been accepted!<br/> You\'ll be redirected to the merchant site...
			</div></body>
		';
	}
	
	/**
	 * Client payment pending/processed page when status is unknown.
	 */
	public function pendingPage() {
		echo '<html>
			<header><meta http-equiv="refresh" content="4;URL=\''. $this->storeManager->getStore()->getBaseUrl() 
				. 'customer/account/\'"></header><body>
			<div style="background-color: #37BC9B; 
						margin: 50px; 
						padding: 40px; 
						text-align:center; 
						font-family: Arial, sans-serif; 
						color: white; 
						font-size:26px;">
				Your payment has been processed.<br/> You\'ll be redirected to the merchant site...
			</div></body>
		';
	}
	
	/**
	 * Client payment failed page.
	 */
	public function failedPage() {
		echo '<html>
			<header><meta http-equiv="refresh" content="4;URL=\''. $this->storeManager->getStore()->getBaseUrl() 
				. 'customer/account/\'"></header><body>
			<div style="background-color: #D65559; 
						margin: 50px; 
						padding: 40px; 
						text-align:center; 
						font-family: Arial, sans-serif; 
						color: white; 
						font-size:26px;">
				Your payment has failed.<br/> You\'ll be redirected to the merchant site...
			</div></body>
		';
	}
}
