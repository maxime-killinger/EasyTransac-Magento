<?php

namespace Easytransac\Gateway\Controller\Payment;



Class OneClick extends \Easytransac\Gateway\Controller\OneClickAction
{

	public function execute()
	{
		$totals = $this->_checkoutSession->getQuote()->getTotals();
		$grand_total = $totals['grand_total'];
		$total_val = $grand_total->getValue();
		$amount = (int)($total_val * 100);
		$multiple_payments = false;
		$billing_address = $this->customerSession
								->getCustomer()
								->getDefaultBillingAddress()
								->convertToArray();
		
		if(isset($_POST['billing_address']['street'])
			&& (empty($_POST['billing_address']['street']) 
					|| !is_array($_POST['billing_address']['street']))){
			$street = $billing_address['street'];
		}elseif(isset($_POST['billing_address']['street'])
				&& is_array($_POST['billing_address']['street'])){
			$street = implode(' ', $_POST['billing_address']['street']);
		}
		
		// Reserve order (not good -> sets quote to inactive)
		$quote = $this->_checkoutSession->getQuote();
		$quote->collectTotals();

        if (!$quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'EasyTransac can\'t process orders with a zero balance. '
                    . 'To finish your purchase, please go through the standard'
					. ' checkout process.'
                )
            );
        }
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);
		// End reserve order

		$_SESSION['easytransac_gateway_processing_qid'] = $this->_checkoutSession->getQuoteId();
		
		$data = array(
			"Alias" => $_POST['Alias'],
			"Amount" => $amount,
			"ClientIp" => $this->get_visitor_ip(),
			"OrderId" => $this->_checkoutSession->getQuoteId(),
			"ClientId" => $this->getClientId(),
			"UserAgent" => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
		);
		
		// EasyTransac communication.
		$et_return = $this->api
				->setServiceOneClick()
				->communicate($this->easytransac->getConfigData('api_key'), $data);
		
		if(!empty($et_return['Error'])) {
			echo json_encode(array(
				'error' => 'yes', 'message' => $et_return['Error']
			));
			return;
		}

		$this->processResponse($et_return['Result']);
		
		$json_status_output = '';
		switch ($et_return['Result']['Status'])
		{
			case 'captured':
			case 'pending':
				$json_status_output = 'processed';
				break;
			
			default:
				$json_status_output = 'failed';
				break;
		}
		
		if(!empty($et_return['Result']['Error'])){
			$this->logger->error('EasyTransac Error: ' . $et_return['Result']['Error']);
			echo json_encode(array(
				'error' => 'yes', 'message' => $et_return['Result']['Error']
			));
		}
		else{
			echo json_encode(array(
				'paid_status' => $json_status_output,
				'error' => 'no',
				'redirect_page' => $this->storeManager->getStore()->getBaseUrl()
				. 'easytransac/payment/returnpage',
			));
		}
	}
}
