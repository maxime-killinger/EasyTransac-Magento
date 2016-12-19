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
//        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (Component, $, quote, customer, fullScreenLoader) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            paymentPageUrl: null,
            
            defaults: {
                template: 'Easytransac_Gateway/payment/easytransac-form'
            },

            getCode: function() {
                return 'easytransac_gateway';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
//                var $form = $('#' + this.getCode() + '-form');
//                return $form.validation() && $form.validation('isValid');
                return true;
            },
            
            /**
             * Loads OneClick payment logic.
             * @returns {undefined}
             */
            loadOneClick: function(){
                
                // Address builder helper
                var payload_builder = function() {
                    var address = quote.billingAddress();
                    var payload = {
                        cart_id: quote.getQuoteId(),
                        shipping_address_is_default: quote.shippingAddress().isDefaultShipping(),
                        shipping_address: {
                            firstname: quote.shippingAddress().firstname,
                            lastname: quote.shippingAddress().lastname,
                            postcode: quote.shippingAddress().postcode,
                            telephone: quote.shippingAddress().telephone,
                            city: quote.shippingAddress().city,
                            countryId: quote.shippingAddress().countryId,
                            regionId: quote.shippingAddress().regionId
                        },
                        billing_address_is_default: quote.billingAddress().isDefaultBilling(),
                        billing_address: {
                            firstname: quote.billingAddress().firstname,
                            lastname: quote.billingAddress().lastname,
                            postcode: quote.billingAddress().postcode,
                            telephone: quote.billingAddress().telephone,
                            street: quote.billingAddress().street,
                            city: quote.billingAddress().city,
                            countryId: quote.billingAddress().countryId,
                            regionId: quote.billingAddress().regionId
                        },
                        customer_id: address.customerId
                    };
                    return payload;
                };

                // Unified OneClick loader
                // Requires : listcards_url
                //            oneclick_url
                //
                var listcards_url = '/easytransac/payment/listcards';
                var oneclick_url = '/easytransac/payment/oneclick';
                $('#easytransac-oneclick').hide().html('<span id="etocloa001">OneClick loading ...</span>').fadeIn();
                
                // JSON Call
                $.getJSON(listcards_url, {}, function(json){
                    $('#etocloa001').fadeOut().remove();
                    if(!json.status) return;
                    var _space = $('#easytransac-oneclick');
                    
                    // Label
                    _space.append($('<span style="width:100px;" title="Direct credit card payment">OneClick : </span>'));
                    
                    // Dropdown
                    _space.append($('<select id="etalcadd001" style="width:200px; margin-left:10px;">'));
                    $.each(json.packet, function(i,row){
                        $('#etalcadd001')
                        .append($('<option value="'+row.Alias+'">'+row.CardNumber+'</option>'));
                    });
                    
                    // Button
                    _space.append($(' <button id="etocbu001" type="button" style="width:150px; margin-left:15px;">OneClick Pay</button>'));
                    
                    $('#etocbu001').click(function(e){
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        $('#etocbu001').prop('disabled', 'disabled');
                        
                        // Click
                        var payload = payload_builder();
                        payload.Alias = $('#etalcadd001 > option:selected').val();

                        fullScreenLoader.startLoader();
                        $.ajax({
                            url: oneclick_url,
                            data: payload,
                            type: 'POST',
                            dataType: 'json'
                        }).done(function (data) {
                            if(data.error === 'no'){
                                if(data.paid_status === 'processed') {
                                    $.mage.redirect(data.redirect_page);
                                } else {
                                    alert('EasyTransac : Payment failed');
                                    fullScreenLoader.stopLoader();
                                    $('#etocbu001').prop('disabled');
                                }
                            } else {
                                alert('Error: ' + data.message);
                                fullScreenLoader.stopLoader();
                                $('#etocbu001').prop('disabled');
                            }
                        }).fail(function(){fullScreenLoader.stopLoader();});
                    });
                });
                
            },
            
            /** Redirect to EasyTransac */
            continueToEasytransac: function () {
                fullScreenLoader.startLoader();
//                console.log('continueToEasytransac');
//                window.SQ = quote;
                var address = quote.billingAddress();
                var payload = {
                    cart_id: quote.getQuoteId(),
                    shipping_address_is_default: quote.shippingAddress().isDefaultShipping(),
                    shipping_address: {
                        firstname: quote.shippingAddress().firstname,
                        lastname: quote.shippingAddress().lastname,
                        postcode: quote.shippingAddress().postcode,
                        telephone: quote.shippingAddress().telephone,
                        city: quote.shippingAddress().city,
                        countryId: quote.shippingAddress().countryId,
                        regionId: quote.shippingAddress().regionId
                    },
                    billing_address_is_default: quote.billingAddress().isDefaultBilling(),
                    billing_address: {
                        firstname: quote.billingAddress().firstname,
                        lastname: quote.billingAddress().lastname,
                        postcode: quote.billingAddress().postcode,
                        telephone: quote.billingAddress().telephone,
                        street: quote.billingAddress().street,
                        city: quote.billingAddress().city,
                        countryId: quote.billingAddress().countryId,
                        regionId: quote.billingAddress().regionId
                    },
                    customer_id: address.customerId
                };
                
                // Guest e-mail
                if (!customer.isLoggedIn()) {
                    payload.email = quote.guestEmail;
                }
                
                var self = this;
                
                $.ajax({
                    url: '/easytransac/payment/url',
                    data: payload,
                    type: 'POST',
                    dataType: 'json'
                }).done(function (data) {
                    if(data.error === 'no'){
//                        XxXself.placeOrder();
                        self.paymentPageUrl = data.payment_page;
                        $.mage.redirect(data.payment_page);

                    } else {
                        alert('Error: ' + data.message);
                        fullScreenLoader.stopLoader();
                    }
                }).fail(function(){fullScreenLoader.stopLoader();});
            },
            
            /**
             * After place order callback
             * @unused
             */
            afterPlaceOrder: function () {
//                console.log('afterPlaceOrder');

                // Redirect to payment page
//                $.mage.redirect(this.paymentPageUrl);
            }
        });
    }
);
