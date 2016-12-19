<?php

namespace Easytransac\Gateway\Controller;

use Easytransac\Gateway\Model\EasytransacApi;

/**
 * Parent class for Notification & Returnpage & OneClickAction.
 */
class NotifyAction extends \Magento\Framework\App\Action\Action
{
		/**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;
	
	/**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_order_repo;
	
	/**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $_builder;
	
	/**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $_invoiceSender;
	
	/**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoice_service;
	
	/**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_db_transaction;
	
	/**
     * @var \Magento\Framework\Logger\Monolog\Logger
     */
    protected $logger;
	
	/**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
	
	/**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;
	
	/**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
	
	/**
     * @var \Easytransac\Gateway\Model\EasytransacApi
     */
    protected $api;
	
	/**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
	
	/**
     * @var \Easytransac\Gateway\Model\Payment
     */
    protected $easytransac;
	
	/**
     * @var \Easytransac\Gateway\Model\Payment
     */
    protected $customerRepo;
	
	/**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
	
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Sales\Model\Order $_order,
		\Magento\Sales\Api\OrderRepositoryInterface $_order_repo,
		\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $_builder,
		\Magento\Sales\Model\Order\Email\Sender\InvoiceSender $_invoiceSender,
		\Magento\Sales\Model\Service\InvoiceService $_invoice_service,
		\Magento\Framework\DB\Transaction $_db_transaction,
		\Easytransac\Gateway\Model\EasytransacApi $api,
		\Psr\Log\LoggerInterface $logger,
		\Easytransac\Gateway\Model\Payment $easytransac,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Magento\Quote\Model\QuoteManagement $quoteManagement,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Customer\Model\ResourceModel\CustomerRepository $customerRepo,
		\Magento\Checkout\Model\Session $_checkoutSession)
	{
		parent::__construct($context);
		$this->_order = $_order;
		$this->_order_repo = $_order_repo;
		$this->_builder = $_builder;
		$this->_invoiceSender = $_invoiceSender;
		$this->_invoice_service = $_invoice_service;
		$this->_db_transaction = $_db_transaction;
		$this->logger = $logger;
		$this->easytransac = $easytransac;
		$this->quoteRepository = $quoteRepository;
		$this->quoteManagement = $quoteManagement;
		$this->customerSession = $customerSession;
		$this->storeManager = $storeManager;
		$this->api = $api;
		$this->customerRepo = $customerRepo;
		$this->_checkoutSession = $_checkoutSession;
	}
	
	/**
	 * Default is for Notification.
	 */
	public function execute() {
		
		$received_data = $_POST;

		if(empty($received_data)) {
			$this->logger->error('EasyTransac Error: Notification : Empty packet');
			die;
		}
		
		if(!EasytransacApi::validateIncoming($received_data, $this->easytransac->getConfigData('api_key'))) {
			$this->logger->error('EasyTransac Error: Notification : Incoming packet validation failed');
			die;
		}
		$this->processResponse($received_data);
	}
	
	/**
	 * Saves EasyTransacs ClientId for the customer.
	 * @param type $value		EasyTransac ClientId.
	 * @param type $customerId	Magento's customer ID.
	 */
	protected function saveClientIdForCustomerId($value, $customerId) {
		$cust = $this->customerRepo->getById($customerId);
		$cust->setCustomAttribute('easytransac_clientid', $value);
		$this->customerRepo->save($cust);
	}
	
	/**
	 * Returns current customer's EasyTransac ClientId or 0.
	 * @return int
	 */
	protected function getClientId() {
		$cust = $this->customerRepo->getById($this->customerSession->getCustomerId());
		$attr = $cust->getCustomAttribute('easytransac_clientid');
		if(!$attr) return 0;
		return $attr->getValue();
	}

	/**
	 * Processes EasyTransac's response and saves the order.
	 * @param type $received_data
	 * @return type
	 */
	public function processResponse($received_data) {
		
		file_put_contents(__DIR__.'/payload.log', date('c')."\n\n".var_export($received_data, true));
		
		// Instant fail, quote won't be submitted;
		if($received_data['Status'] == 'failed') return;
		
		try {
			$quote = $this->quoteRepository->get($received_data['OrderId']);
			if ($quote->getIsActive() && $quote->isVirtual()) {
				if($this->customerSession->getCustomer()->getDefaultBillingAddress()) {
					$quote->getBillingAddress()->addData(
							$this->customerSession
							->getCustomer()
							->getDefaultBillingAddress()
							->convertToArray());
				} else {
					$quote->getBillingAddress()->addData($this->getVirtualAddress());
				}
				if($this->customerSession->getCustomer()->getDefaultShippingAddress()) {
					$quote->getShippingAddress()->addData(
							$this->customerSession
							->getCustomer()
							->getDefaultShippingAddress()
							->convertToArray());
				} else {
					$quote->getShippingAddress()->addData($this->getVirtualAddress());
				}
			}
			
			if($quote->getIsActive() && !$quote->isVirtual()) {

				//Set Address to quote

				$billing_address = array(
					'firstname'    => $quote->getBillingAddress()->getFirstname(), //address Details
					'lastname'     => $quote->getBillingAddress()->getLastname(),
					'street' => $quote->getBillingAddress()->getStreet(),
					'city' => $quote->getBillingAddress()->getCity(),
					'country_id' => $quote->getBillingAddress()->getCountryId(),
					'region' => $quote->getBillingAddress()->getRegion(),
					'postcode' => $quote->getBillingAddress()->getPostcode(),
					'telephone' => $quote->getBillingAddress()->getTelephone(),
					'fax' => $quote->getBillingAddress()->getFax(),
					'save_in_address_book' => $quote->getBillingAddress()->getSaveInAddressBook()
				);
			
				// If billing address is empty, try to chose the default one.
				if(empty($billing_address['lastname'])
						&& $this->customerSession->getCustomer()->getDefaultBillingAddress()) {
					$billing_address = $this->customerSession
							->getCustomer()
							->getDefaultBillingAddress()
							->convertToArray();
				}
				$quote->getShippingAddress()->addData($billing_address);
				unset($billing_address);

				$shipping_address = array(
					'firstname'    => $quote->getShippingAddress()->getFirstname(), //address Details
					'lastname'     => $quote->getShippingAddress()->getLastname(),
					'street' => $quote->getShippingAddress()->getStreet(),
					'city' => $quote->getShippingAddress()->getCity(),
					'country_id' => $quote->getShippingAddress()->getCountryId(),
					'region' => $quote->getShippingAddress()->getRegion(),
					'postcode' => $quote->getShippingAddress()->getPostcode(),
					'telephone' => $quote->getShippingAddress()->getTelephone(),
					'fax' => $quote->getShippingAddress()->getFax(),
					'save_in_address_book' => $quote->getShippingAddress()->getSaveInAddressBook()
				);
				// If shipping address is empty, try to chose the default one.
				if(empty($shipping_address['lastname']) 
						&& $this->customerSession->getCustomer()->getDefaultShippingAddress()) {
					$shipping_address = $this->customerSession
							->getCustomer()
							->getDefaultShippingAddress()
							->convertToArray();
				}
				$quote->getShippingAddress()->addData($shipping_address);
				unset($shipping_address);
			}
			
			if(!$quote->getIsActive()) {
//				echo "Updating Quote...\n";
				$order = $this->_order->load($quote->getOrigOrderId());
			}
		}
		catch (\Magento\Framework\Exception\NoSuchEntityException $exc) {
			$this->logger->error('EasyTransac Error: Notification : Unknown quote id');
			echo 'K.O. - Unknown quote';
			die;
		}
		
		// Saves the cart, gets the order and saves the payment.
		if($quote->getIsActive()) {

			// Set the payment methods
			$quote->setPaymentMethod ('easytransac_gateway');

			// Set Sales Order Payment
			$quote->getPayment ()->importData (array ('method' => 'easytransac_gateway'));

			// Configure quote
			$quote->setInventoryProcessed (false);
			$quote->collectTotals ();
			$this->quoteRepository->save($quote);// Use repo for save

			// Update changes
			$quote->save();
			$order = $this->quoteManagement->submit($quote);
			if (!$order) {
				$this->logger->error('EasyTransac Error: Notification : Quote couldn\'t be submitted.');
				echo 'Quote submission error';
				die;
			}

			$trx = $received_data['Tid'];
			$paymentData = array();

			// Create Transaction
			$payment = $order->getPayment();
			$payment->setLastTransId($trx);
			$payment->setTransactionId($trx);
			$payment->setAdditionalInformation(
				array(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData)
			);
			$formatedPrice = $order->getBaseCurrency()->formatTxt(
				$order->getGrandTotal()
			);

			$message = 'EasyTransac : ' . __('The captured amount is %1.', $formatedPrice);
			//get the object of builder class
			$trans = $this->_builder;
			$transaction = $trans->setPayment($payment)
			->setOrder($order)
			->setTransactionId($trx)
			->setAdditionalInformation(
				array(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData)
			)
			->setFailSafe(true)
			//build method creates the transaction and returns the object
			->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

			$payment->addTransactionCommentsToOrder(
				$transaction,
				$message
			);
			$payment->setParentTransactionId(null);
			$payment->save();
		}
		
		// Sets order status.
		$order_status = null;
		switch ($received_data['Status'])
		{
			case 'failed':
				$order_status = \Magento\Sales\Model\Order::STATE_CANCELED;
				break;

			case 'captured':
				$order_status = \Magento\Sales\Model\Order::STATE_PROCESSING;
				$order->setTotalPaid((float)$received_data['Amount']);
				$this->saveClientIdForCustomerId($received_data['Client']['Id'], $received_data['Uid']);
				break;

			case 'pending':
				$order_status = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
				break;

			case 'refunded':
				$order_status = \Magento\Sales\Model\Order::STATE_CLOSED;
				break;
		}
		
		// Amount match check
		if($quote->getIsActive()) {

			$totals = $quote->getTotals();
			$grand_total = $totals['grand_total'];
			$quote_amount = (float)$grand_total->getValue();
			if($quote_amount != (float)$received_data['Amount']) {
				
				// Fraud detected !
				$order_status = \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW;
				$this->logger->error('EasyTransac Warning : Quote amount doesn\'t match order amount.');
				$order->addStatusHistoryComment(
						'EasyTransac : ' . __('Warning : the cart amount doesn\'t match the paid amount.')
				)
				->setIsCustomerNotified(true)
				->save();
			}
		} else {
			$old_status = $order->getStatus() . ' - ' . $order->getState();
		}

		// Payment occured.
		$order->setState($order_status);
		$order->setStatus($order_status);
		$order->save();
		$transaction->save();
		
//		if(!$quote->getIsActive()) {
//			echo "Status updated from $old_status to $order_status.\n";
//		}
		
		// Sends invoice
		$this->invoice($order->getId());
//		echo 'Transaction registration terminated.';
	}


	/**
	 * Sends order invoice.
	 * @param type $order_id
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	protected function invoice($order_id)
	{
		$order = $this->_order->load($order_id);


		if ($order->canInvoice())
		{
			// Create invoice for this order
			$invoice = $this->_invoice_service->prepareInvoice($order);

			// Make sure there is a qty on the invoice
			if (!$invoice->getTotalQty())
			{
				throw new \Magento\Framework\Exception\LocalizedException(
				__('You can\'t create an invoice without products.')
				);
			}

			// Register as invoice item
			$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
			$invoice->register();

			// Save the invoice to the order
			$transaction = $this->_db_transaction
					->addObject($invoice)
					->addObject($invoice->getOrder());

			$transaction->save();

			// \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
			$this->_invoiceSender->send($invoice);

			$order->addStatusHistoryComment(
							'EasyTransac : ' . __('Notified customer about invoice #%1.', $invoice->getId())
					)
					->setIsCustomerNotified(true)
					->save();
		}
	}
	
	/** @return string */
	protected function get_visitor_ip() {
		/** @var \Magento\Framework\ObjectManagerInterface $om */
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		/** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $a */
		$a = $om->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
		return $a->getRemoteAddress();
	}
	
	/**
	 * Returns a dummy address for when none is available.
	 * @return array
	 */
	protected function getVirtualAddress() {
		return array(
			'firstname' => 'VirtualOrder',
			'lastname' => 'N.D.',
			'street' => 'No address found',
			'city' => 'N.D.',
			'country_id' => 3,
			'region' => 3,
			'postcode' => 'N.D.',
			'telephone' => '',
			'fax' => '',
			'save_in_address_book' => 0
		);
	}
}
