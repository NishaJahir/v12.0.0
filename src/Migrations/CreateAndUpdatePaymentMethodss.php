<?php
/**
 * This file is used for creating and updating Novalnet payment methods
 * This free contribution made by request.
 * 
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * All rights reserved. https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
 
namespace Novalnet\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Novalnet\Helper\PaymentHelper;
use Plenty\Plugin\Log\Loggable;
/**
 * Class UpgradePaymentMethods
 * @package Novalnet\Migrations
 */
class CreateAndUpdatePaymentMethodss
{
   use Loggable;
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * CreatePaymentMethod constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                PaymentHelper $paymentHelper)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Run on plugin build
     *
     * Create Method of Payment ID for Novalnet payment if they don't exist
     */
    public function run()
    {
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_SEPA', 'Novalnet Direct Debit SEPA');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_CC', 'Novalnet Credit Card');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_INVOICE', 'Novalnet Invoice');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PREPAYMENT', 'Novalnet Prepayment');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_GUARANTEED_INVOICE', 'Novalnet Guaranteed Invoice');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_GUARANTEED_SEPA', 'Novalnet Guaranteed Direct Debit SEPA');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_IDEAL', 'Novalnet iDEAL');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_SOFORT', 'Novalnet Online Transfer');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_GIROPAY', 'Novalnet giropay');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_CASHPAYMENT', 'Novalnet Cashpayment');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PRZELEWY24', 'Novalnet Przelewy24');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_EPS', 'Novalnet eps');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_INSTALMENT_INVOICE', 'Novalnet Instalment Invoice');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_INSTALMENT_SEPA', 'Novalnet Instalment Direct Debit SEPA');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PAYPAL', 'Novalnet PayPal');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_POSTFINANCE_CARD', 'Novalnet PostFinance Card');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_POSTFINANCE', 'Novalnet PostFinance E-Finance');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_BANCONTACT', 'Novalnet Bancontact');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_MULTIBANCO', 'Novalnet Multibanco');
        $this->getLogger(__METHOD__)->error('create payment Name', 'create');
    }

    /**
     * Create and update payment method with given parameters if it doesn't exist
     *
     * @param string $paymentKey
     * @param string $paymentName
     */
    private function createNovalnetPaymentMethodByPaymentKey($paymentKey, $paymentName)
    {
        $payment_data = $this->paymentHelper->getPaymentMethodByKey($paymentKey);
     $this->getLogger(__METHOD__)->error('Payment data', $payment_data);
        if ($payment_data == 'no_paymentmethod_found')
        {
            $paymentMethodData = ['pluginKey'  => 'plenty_novalnet',
                                'paymentKey' => $paymentKey,
                                'name' => $paymentName
                               ];
            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
        } elseif ($payment_data[1] == $paymentKey && !in_array ($payment_data[2], ['Novalnet Direct Debit SEPA', 'Novalnet Credit Card', 'Novalnet Invoice', 'Novalnet Prepayment', 'Novalnet Online Transfer', 'Novalnet PayPal', 'Novalnet iDEAL', 'Novalnet eps', 'Novalnet giropay', 'Novalnet Przelewy24', 'Novalnet Cashpayment']) ) {
            $paymentMethodData = ['pluginKey'  => 'Novalnet',
                                'paymentKey' => $paymentKey,
                                'name' => $paymentName,
                                'id' => $payment_data[0]
                               ];
            $this->getLogger(__METHOD__)->error('update payment Name', $paymentMethodData);
            $this->paymentMethodRepository->updateName($paymentMethodData);
        }
    }
}
