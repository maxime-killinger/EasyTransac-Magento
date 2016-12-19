<?php
namespace Easytransac\Gateway\Setup;
 
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Create ClientId field for customers.
 */
class InstallData implements InstallDataInterface {

    /**
     * Customer setup factory
     *
     * @var \Magento\Customer\Setup\CustomerSetupFactory
     */
    private $customerSetupFactory;

    public function __construct(CustomerSetupFactory $customerSetupFactory) {
        $this->customerSetupFactory = $customerSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context) {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerSetup->addAttribute(Customer::ENTITY, 'easytransac_clientid', [
            'label' => 'EasyTransac Client Id',
            'input' => 'text',
            'required' => false,
            'sort_order' => 40,
            'visible' => false,
            'system' => false,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true]
        );

        $customerSetup->getEavConfig()
				->getAttribute('customer', 'easytransac_clientid')
				->save();

        $setup->endSetup();
    }

}
