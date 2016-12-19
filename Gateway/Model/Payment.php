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
//use Magento\Framework\DataObject;
//use Magento\Quote\Api\Data\PaymentInterface;
//use Magento\Quote\Model\Quote\Payment;
class Payment extends \Magento\Payment\Model\Method\Cc

//class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'easytransac_gateway';

    protected $_code = self::CODE;

    protected $_isGateway                   = true;
//    protected $_canCapture                  = true;
//    protected $_canCapturePartial           = true;
	
    protected $_canOrder                 = true;
//    protected $_canAuthorize                 = true;
//    protected $_canRefund                   = true;
//    protected $_canRefundInvoicePartial     = false;
	
	// Use config status
	protected $_isInitializeNeeded = true;

    protected $_countryFactory;

    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array('EUR');

	protected $_order_repo;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
		\Magento\Sales\Api\OrderRepositoryInterface $_order_repo,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );
		
        $this->_countryFactory = $countryFactory;

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
		
		$this->_order_repo = $_order_repo;
    }
	
	public function validate()
	{
		return true;
	}

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && (
            $quote->getBaseGrandTotal() < $this->_minAmount
            || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
        ) {
            return false;
        }

        if (!$this->getConfigData('api_key')) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }
	

}