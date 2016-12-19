/**
* Easytransac_Gateway Magento JS Component
*
* @category    Easytransac
* @package     Easytransac_Gateway
* @author      Easytrasac
* @copyright   Easytransac (https://www.easytransac.com)
* @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'easytransac_gateway',
                component: 'Easytransac_Gateway/js/view/payment/method-renderer/easytransac-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);