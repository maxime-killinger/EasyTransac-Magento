<?php

/**
 * Easytransac_Gateway payment method model.
 *
 * @category    Easytransac
 * @package     Easytransac_Gateway
 * @author      Easytrasac
 * @copyright   Easytransac (https://www.easytransac.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Easytransac\Gateway\Model;

class EasytransacApi
{
	/**
	 * Available services.
	 */
	const SERVICE_PAYMENT = 'api/payment/page';
	const SERVICE_LISTCARDS = 'api/payment/listcards';
	const SERVICE_ONECLICK = 'api/payment/oneclick';
	
	protected $selected_service;
	
	/**
	 * Packet validation helper.
	 * 
	 * @param type $received_data
	 * @return boolean
	 */
	static public function validateIncoming($received_data)
	{
		$required_fields = array(
			'RequestId',
			'Tid',
			'Uid',
			'OrderId',
			'Status',
			'Signature',
		);
		foreach ($required_fields as $field_key)
		{
			if (empty($received_data[$field_key]))
			{
				return false;
			}
		}
		return true;
	}

	/* Check the signature of EasyTransac's incoming data.
	 *
	 * @param array $data
	 *      ET received data.
	 * @param string $api_key
	 *      ET API key.
	 *
	 * @return bool
	 *   TRUE if the signature is valid.
	 */

	static public function easytransac__verify_signature($data, $api_key)
	{
		$signature = $data['Signature'];
		unset($data['Signature']);
		$calculated = self::easytransac__get_signature($data, $api_key);
		return $signature === $calculated;
	}

	/**
	 * Easytransac API key signature.
	 *
	 * @param array $params
	 *      Data to be signed.
	 * @param string $api_key
	 *      API key.
	 *
	 * @return string
	 *   API key.
	 */
	static public function easytransac__get_signature($params, $api_key)
	{
		$signature = '';
		if (is_array($params))
		{
			ksort($params);
			foreach ($params as $name => $valeur)
			{
				if (strcasecmp($name, 'signature') != 0)
				{
					if (is_array($valeur))
					{
						ksort($valeur);
						foreach ($valeur as $v)
						{
							$signature .= $v . '$';
						}
					}
					else
					{
						$signature .= $valeur . '$';
					}
				}
			}
		}
		else
		{
			$signature = $params . '$';
		}

		$signature .= $api_key;
		return sha1($signature);
	}
	
	/**
	 * Sets service to payment.
	 */
	public function setServicePayment() {
		$this->selected_service = self::SERVICE_PAYMENT;
		return $this;
	}
	
	/**
	 * Sets service to listcards.
	 */
	public function setServiceListCards() {
		$this->selected_service = self::SERVICE_LISTCARDS;
		return $this;
	}
	
	/**
	 * Sets service to oneclick.
	 */
	public function setServiceOneClick() {
		$this->selected_service = self::SERVICE_ONECLICK;
		return $this;
	}

	/**
	 * Communicates with EasyTransac.
	 * @param array $api_key	API key.
	 * @param array $data		Data payload.
	 * @return type				EasyTransac response.
	 */
	public function communicate($api_key, $data)
	{
		if($this->selected_service === null) {
			throw new \Exception('EasyTransac no service set');
		}
		
		$data['Signature'] = EasytransacApi::easytransac__get_signature($data, 
			$api_key);

		// Call EasyTransac API to initialize a transaction.
		if (function_exists('curl_version')) {
		  $curl = curl_init();
		  $curl_header = array('EASYTRANSAC-API-KEY:' . $api_key);
		  
		  // Add additional headers
		  if($this->selected_service === self::SERVICE_LISTCARDS) {
			  $curl_header[] = 'EASYTRANSAC-LISTCARDS:1';
		  } elseif($this->selected_service === self::SERVICE_ONECLICK) {
			  $curl_header[] = 'EASYTRANSAC-ONECLICK:1';
		  }
		  
		  curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_header);
		  curl_setopt($curl, CURLOPT_POST, TRUE);
		  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		  
		  if (defined('CURL_SSLVERSION_TLSv1_2')) {
			$cur_url = 'https://www.easytransac.com/' . $this->selected_service;
		  }
		  else {
			$cur_url = 'https://gateway.easytransac.com';
		  }
		  curl_setopt($curl, CURLOPT_URL, $cur_url);
		  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		  $et_return = curl_exec($curl);
		  if (curl_errno($curl)) {
			throw new \Exception('EasyTransac cURL Error: ' . curl_error($curl));
		  }
		  curl_close($curl);
		  $et_return = json_decode($et_return, TRUE);
		}
		else {
		  $opts = array(
			'http' => array(
			  'method' => 'POST',
			  'header' => "Content-type: application/x-www-form-urlencoded\r\n"
			  . "EASYTRANSAC-API-KEY:" . $api_key . "\r\n",
			  'content' => http_build_query($data),
			  'timeout' => 5,
			),
		  );
		  
		  // Add additional headers
		  if($this->selected_service === self::SERVICE_LISTCARDS) {
			  $opts['http']['header'] .=  'EASYTRANSAC-LISTCARDS:1' . "\r\n";
		  }
		  elseif($this->selected_service === self::SERVICE_ONECLICK) {
			  $opts['http']['header'] .=  'EASYTRANSAC-ONECLICK:1' . "\r\n";
		  }
		  
		  $context = stream_context_create($opts);
		  $et_return = file_get_contents('https://gateway.easytransac.com', FALSE, $context);
		  $et_return = json_decode($et_return, TRUE);
		}

		return $et_return;
	}

}
