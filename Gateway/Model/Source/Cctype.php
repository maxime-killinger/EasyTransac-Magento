<?php
/**
* Easytransac_Gateway CC type source model.
*
* @category    Easytransac
* @package     Easytransac_Gateway
* @author      Easytrasac
* @copyright   Easytransac (https://www.easytransac.com)
* @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

namespace Easytransac\Gateway\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC');
    }
}
