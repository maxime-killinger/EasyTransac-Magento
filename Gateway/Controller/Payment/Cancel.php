<?php

namespace Easytransac\Gateway\Controller\Payment;

Class Cancel extends \Magento\Framework\App\Action\Action
{
	/**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkout_session;
	
	/**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;
	
	/**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $repo;
	
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Checkout\Model\Session $checkout_session,
		\Magento\Checkout\Model\Cart $cart,
		\Magento\Sales\Model\OrderRepository $repo)
	{
		parent::__construct($context);
		
		$this->checkout_session = $checkout_session;
		$this->cart = $cart;
		$this->repo = $repo;
	}

	/**
	 * Refill cart with last order and delete the latter.
	 */
	public function execute()
	{
//		if(($last_order = $this->checkout_session->getLastRealOrder()) 
//			&& $last_order->getId() !== null){
//			
//			$items = $last_order->getItemsCollection();
//			$this->cart->truncate();
//			foreach ($items as $item) {
//				try {
//					$this->cart->addOrderItem($item);
//				}
//				catch (\Exception $e) {
//					$this->checkout_session->addException($e,
//						__('Cannot add the item to shopping cart.'));
//				}
//			}
//			$this->cart->save();
//			$this->repo->delete($last_order);
//		}
		$this->_redirect('checkout/cart');
	}

}
