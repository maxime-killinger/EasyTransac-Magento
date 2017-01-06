<?php

namespace EasyTransac\Gateway\Controller\Payment;
use Easytransac\Gateway\Model\EasytransacApi;

Class Url extends \Magento\Framework\App\Action\Action
{
	/**
     * @var \Magento\Checkout\Model\Cart
     */
//    protected $cart;
	
	/**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
	
	/**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
	
	/**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
	
	/**
     * @var \Magento\Framework\Logger\Monolog\Logger
     */
    protected $logger;
	
	/**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
	
	/**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $resolver;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Checkout\Model\Session $_checkoutSession,
		\Easytransac\Gateway\Model\Payment $easytransac,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Psr\Log\LoggerInterface $logger,
		\Easytransac\Gateway\Model\EasytransacApi $api,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Magento\Framework\Locale\Resolver $resolver)
	{
		parent::__construct($context);
		$this->_checkoutSession = $_checkoutSession;
		$this->easytransac = $easytransac;
		$this->customerSession = $customerSession;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
		$this->quoteRepository = $quoteRepository;
		$this->resolver = $resolver;
	}

	/**
	 * Returns an EasyTransac payment page URI.
	 */
	public function execute()
	{
		if(!$this->_checkoutSession->isSessionExists()) die;
		
		$totals = $this->_checkoutSession->getQuote()->getTotals();
		$grand_total = $totals['grand_total'];
		$total_val = $grand_total->getValue();
		$amount = (int)($total_val * 100);
		$multiple_payments = false;
		$three_d_secure = $this->easytransac->getConfigData('three_d_secure');
		$billing_address = $this->customerSession
								->getCustomer()
								->getDefaultBillingAddress()
								->convertToArray();
		
		// Takes default address if received address is empty.
		if(isset($_POST['billing_address']['street'])
			&& (empty($_POST['billing_address']['street']) 
					|| !is_array($_POST['billing_address']['street']))){
			$street = $billing_address['street'];
		}elseif(isset($_POST['billing_address']['street'])
				&& is_array($_POST['billing_address']['street'])){
			$street = implode(' ', $_POST['billing_address']['street']);
		}
		
		// Reserves order
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
		$langcode = substr($this->resolver->getLocale(), 0, 3) == 'fr_' ? 'FRE' : 'ENG';
		
		// Order mail if anonymous.
		$data = array(
		  "Amount" => $amount,
		  "ClientIp" => $this->get_visitor_ip(),
		  "Email" => $this->customerSession->getCustomer()->getEmail(),
		  "OrderId" => $this->_checkoutSession->getQuoteId(),
		  "Uid" => $this->customerSession->getCustomer()->getId(),
		  "ReturnUrl" => $this->storeManager->getStore()->getBaseUrl()
				. 'easytransac/payment/returnpage',
		  "CancelUrl" => $this->storeManager->getStore()->getBaseUrl() 
				. 'checkout',
		  "3DS" => $three_d_secure ? 'yes' : 'no',
		  "MultiplePayments" => $multiple_payments ? 'yes' : 'no',
		  "Firstname" => $billing_address['firstname'],
		  "Lastname" => $billing_address['lastname'],
		  "Address" => $billing_address['street'],
		  "Address" => $billing_address['street'],
		  "ZipCode" => $billing_address['postcode'],
		  "City" => $billing_address['city'],
		  "BirthDate" => "",
		  "Nationality" => "",
		  "CallingCode" => "",
		  "Phone" => $billing_address['telephone'],
		  "UserAgent" => isset($_SERVER['HTTP_USER_AGENT']) 
			? $_SERVER['HTTP_USER_AGENT'] : '',
		  "Version" => 'Magento 1.0.0',
		  "Language" => $langcode,
		);
		
		$et_return = $this->request_payment_page($data);
		
		if(!empty($et_return['Result']['PageUrl'])){
			echo json_encode(array(
				'payment_page' => $et_return['Result']['PageUrl'], 'error' => 'no'
			));
		}
		else{
			$this->logger->error('EasyTransac Error: ' . $et_return['Error']);
			echo json_encode(array(
				'error' => 'yes', 'message' => $et_return['Error']
			));
		}
	}
	

	/**
	 * Send payment page request.
	 * @param array $data		Data payload.
	 * @return type				EasyTransac response.
	 * 
	 * @todo use EasytransacApi's version
	 */
	private function request_payment_page($data)
	{
		$data['Signature'] = EasytransacApi::easytransac__get_signature($data, 
			$this->easytransac->getConfigData('api_key'));

		// Call EasyTransac API to initialize a transaction.
		if (function_exists('curl_version')) {
		  $curl = curl_init();
		  $curl_header = 'EASYTRANSAC-API-KEY:'
				. $this->easytransac->getConfigData('api_key');
		  curl_setopt($curl, CURLOPT_HTTPHEADER, array($curl_header));
		  curl_setopt($curl, CURLOPT_POST, TRUE);
		  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		  if (defined('CURL_SSLVERSION_TLSv1_2')) {
			$cur_url = 'https://www.easytransac.com/api/payment/page';
		  }
		  else {
			$cur_url = 'https://gateway.easytransac.com';
		  }
		  curl_setopt($curl, CURLOPT_URL, $cur_url);
		  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		  $et_return = curl_exec($curl);
		  if (curl_errno($curl)) {
			$this->logger->error('EasyTransac cURL Error: ' . curl_error($curl));
		  }
		  curl_close($curl);
		  $et_return = json_decode($et_return, TRUE);
		}
		else {
		  $opts = array(
			'http' => array(
			  'method' => 'POST',
			  'header' => "Content-type: application/x-www-form-urlencoded\r\n"
			  . "EASYTRANSAC-API-KEY:" . 
				$this->easytransac->getConfigData('api_key') . "\r\n",
			  'content' => http_build_query($data),
			  'timeout' => 5,
			),
		  );
		  $context = stream_context_create($opts);
		  $et_return = file_get_contents('https://gateway.easytransac.com', FALSE, $context);
		  $et_return = json_decode($et_return, TRUE);
		}

		return $et_return;
	}

	
	/** @return string */
	private function get_visitor_ip() {
		/** @var \Magento\Framework\ObjectManagerInterface $om */
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		/** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $a */
		$a = $om->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
		return $a->getRemoteAddress();
	}
}
