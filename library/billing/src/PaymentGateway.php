<?php
/**
 * Payment Gateways for credit card transactions
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Billing;

use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;

class PaymentGateway
{
    private $gateway;
    private $card;
    private $apiKey;
    private $transactionKey;
    private $production;

    public function __construct($name)
    {
        $this->production = !$GLOBALS['gateway_mode_production'];
        $this->apiKey = $GLOBALS['gateway_api_key'] ? decryptStandard($GLOBALS['gateway_api_key']) : '';
        $this->transactionKey = $GLOBALS['gateway_transaction_key'] ? decryptStandard($GLOBALS['gateway_transaction_key']) : '';
        // Setup payment Gateway
        $this->setGateway($name);
    }

    function setApiKey($key)
    {
        $this->apiKey = $key;
    }

    function setTransactionKey($key)
    {
        $this->transactionKey = $key;
    }

    function setProduction($tf)
    {
        $this->production = $tf;
    }

    /**
     * @param $card
     * @return bool|string
     * $card = [];
     * $card['card'] = '';
     * $card['expiremonth'] = '';
     * $card['expireyear'] = '';
     * $card['cvv'] = '';
     */
    function setCard($card)
    {
        try {
            $ccard = new CreditCard($card);
            $ccard->validate();
            $this->card = $card;
            return true;
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * @param $pay
     * @return bool|string
     */
    function submitPaymentCard($pay)
    {
        try {
            // Send purchase request
            $response = $this->gateway->purchase(
                [
                    'amount' => $pay['amount'],
                    'currency' => $pay['currency'],
                    'card' => $this->card
                ]
            )->send();
            // Process response
            if ($response->isSuccessful()) {
                return $response;
            } elseif ($response->isRedirect()) {
                // Redirect to offsite payment gateway
                return $response->getMessage();
            } else {
                // Payment failed
                return $response->getMessage();
            }
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * @param $pay
     * @return bool|string
     */
    function submitPaymentToken($pay)
    {
        try {
            // Send purchase request with card token
            $response = $this->gateway->purchase($pay)->send();
            // Process response
            if ($response->isSuccessful()) {
                return $response;
            } elseif ($response->isRedirect()) {
                // Redirect to offsite payment gateway
                return $response->getMessage();
            } else {
                // Payment failed
                return $response->getMessage();
            }
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * @param $which
     * @return string
     */
    function setGateway($which)
    {
        if (isset($this->gateway)) {
            unset($this->gateway);
        }
        try {
            if (stripos($which, "stripe") !== false) {
                $gatewayName = 'Stripe';
                $this->gateway = Omnipay::create($gatewayName);
                $this->gateway->setApiKey($this->apiKey);
            } else {
                $gatewayName = 'AuthorizeNetApi_Api';
                $this->gateway = Omnipay::create($gatewayName);
                $this->gateway->setAuthName($this->apiKey);
                $this->gateway->setTransactionKey($this->transactionKey);
                $this->gateway->setTestMode($this->production);
            }
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }
}