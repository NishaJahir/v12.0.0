<?php
/**
 * This module is used for real time processing of
 * Novalnet payment module of customers.
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

namespace Novalnet\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Order\Pdf\Events\OrderPdfGenerationEvent;
use Plenty\Modules\Order\Pdf\Models\OrderPdfGeneration;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Query;
use Novalnet\Models\TransactionLog;
use Plenty\Modules\Document\Models\Document;


use Novalnet\Methods\NovalnetSepaPaymentMethod;
use Novalnet\Methods\NovalnetCcPaymentMethod;
use Novalnet\Methods\NovalnetInvoicePaymentMethod;
use Novalnet\Methods\NovalnetPrepaymentPaymentMethod;
use Novalnet\Methods\NovalnetGuaranteedInvoicePaymentMethod;
use Novalnet\Methods\NovalnetGuaranteedSepaPaymentMethod;
use Novalnet\Methods\NovalnetIdealPaymentMethod;
use Novalnet\Methods\NovalnetSofortPaymentMethod;
use Novalnet\Methods\NovalnetGiropayPaymentMethod;
use Novalnet\Methods\NovalnetCashpaymentPaymentMethod;
use Novalnet\Methods\NovalnetPrzelewy24PaymentMethod;
use Novalnet\Methods\NovalnetEpsPaymentMethod;
use Novalnet\Methods\NovalnetInstalmentbyInvoicePaymentMethod;
use Novalnet\Methods\NovalnetInstalmentbySepaPaymentMethod;
use Novalnet\Methods\NovalnetPaypalPaymentMethod;
use Novalnet\Methods\NovalnetPostfinanceCardPaymentMethod;
use Novalnet\Methods\NovalnetPostfinancePaymentMethod;
use Novalnet\Methods\NovalnetBancontactPaymentMethod;
use Novalnet\Methods\NovalnetMultibancoPaymentMethod;

/**
 * Class NovalnetServiceProvider
 * @package Novalnet\Providers
 */
class NovalnetServiceProvider extends ServiceProvider
{
    use Loggable;

    /**
     * Register the route service provider
     */
    public function register()
    {
        $this->getApplication()->register(NovalnetRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param Dispatcher $eventDispatcher
     * @param paymentHelper $paymentHelper
     * @param PaymentService $paymentService
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentMethodContainer $payContainer
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param Twig $twig
     * @param ConfigRepository $config
     * @param PaymentRepositoryContract $paymentRepository
     * @param DataBase $dataBase
     */
    public function boot( Dispatcher $eventDispatcher,
                          PaymentHelper $paymentHelper,
                          PaymentService $paymentService,
                          AddressRepositoryContract $addressRepository,
                          BasketRepositoryContract $basketRepository,
                          PaymentMethodContainer $payContainer,
                          FrontendSessionStorageFactoryContract $sessionStorage,
                          Twig $twig,
                          ConfigRepository $config,
                          PaymentRepositoryContract $paymentRepository,
                          DataBase $dataBase
                        )
    {
        // Register the Novalnet payment methods in the payment method container
        $payContainer->register('plenty_novalnet::NOVALNET_SEPA', NovalnetSepaPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CC', NovalnetCcPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_INVOICE', NovalnetInvoicePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PREPAYMENT', NovalnetPrepaymentPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_GUARANTEED_INVOICE', NovalnetGuaranteedInvoicePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_GUARANTEED_SEPA', NovalnetGuaranteedSepaPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_IDEAL', NovalnetIdealPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_SOFORT', NovalnetSofortPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_GIROPAY', NovalnetGiropayPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CASHPAYMENT', NovalnetCashpaymentPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PRZELEWY24', NovalnetPrzelewy24PaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_EPS', NovalnetEpsPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_INSTALMENT_INVOICE', NovalnetInstalmentbyInvoicePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_INSTALMENT_SEPA', NovalnetInstalmentbySepaPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PAYPAL', NovalnetPaypalPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_POSTFINANCE_CARD', NovalnetPostfinanceCardPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_POSTFINANCE', NovalnetPostfinancePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_BANCONTACT', NovalnetBancontactPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_MULTIBANCO', NovalnetMultibancoPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
                function(GetPaymentMethodContent $event) use($config, $paymentHelper, $paymentService, $addressRepository,  $basketRepository, $sessionStorage, $twig, $dataBase)
                {
                    if($paymentHelper->getPaymentKeyByMop($event->getMop()))
                    {   
                        $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                        $name = trim($config->get('Novalnet.' . strtolower($paymentKey) . '_payment_name'));
                        $paymentName = ($name ? $name : $paymentHelper->getTranslatedText(strtolower($paymentKey)));
                        $basket = $basketRepository->load();
                        $oneClickShopping = (int) trim($config->get('Novalnet.' . strtolower($paymentKey) . '_shopping_type') == true);
                        $this->getLogger(__METHOD__)->error('one click shop', $config->get('Novalnet.' . strtolower($paymentKey) . '_shopping_type'));
                        // Get the payment request data
                        $paymentRequestParameters = $paymentService->getPaymentRequestParameters($basket, $paymentKey);
                        if (empty($paymentRequestParameters['data']['customer']['first_name']) && empty($paymentRequestParameters['data']['customer']['last_name'])) {
                            $content = $paymentHelper->getTranslatedText('firstLastNameError');
                            $contentType = 'errorCode';
                        } else {
                            if(in_array($paymentKey, ['NOVALNET_CC', 'NOVALNET_SEPA', 'NOVALNET_GUARANTEED_SEPA', 'NOVALNET_INSTALMENT_SEPA', 'NOVALNET_INSTALMENT_INVOICE']) || ($paymentKey == 'NOVALNET_PAYPAL' && $oneClickShopping)) {
                                $contentType = 'htmlContent';
                                $billingAddressId = $basket->customerInvoiceAddressId;
                                $billingAddress = $addressRepository->findAddressById($billingAddressId);
                                $customerAccount = pluginApp(AccountService::class);
                                $this->getLogger(__METHOD__)->error('customer No', $customerAccount->getAccountContactId() );
                                $savedPaymentDetails = $dataBase->query(TransactionLog::class)->where('paymentName', '=', strtolower($paymentKey))->where('customerEmail', '=', $billingAddress->email)->where('saveOneTimeToken', '!=', "")->whereNull('maskingDetails', 'and', true)->orderBy('id','DESC')->limit(2)->get();
                                $savedPaymentDetails = json_decode(json_encode($savedPaymentDetails), true);
                                foreach($savedPaymentDetails as $key => $paymentDetail) {
                                    $savedPaymentDetails[$key]['iban'] = json_decode($paymentDetail['maskingDetails'])->iban;
                                    $savedPaymentDetails[$key]['decodedMaskingDetails'] = json_decode($paymentDetail['maskingDetails']);
                                }
                                $this->getLogger(__METHOD__)->error('saved details', $savedPaymentDetails);
                                $contectTemplate = ((in_array($paymentKey, ['NOVALNET_SEPA', 'NOVALNET_GUARANTEED_SEPA', 'NOVALNET_INSTALMENT_SEPA'])) ? 'Novalnet::PaymentForm.NovalnetSepa' : (($paymentKey == 'NOVALNET_CC') ? 'Novalnet::PaymentForm.NovalnetCc' : 'Novalnet::PaymentForm.NovalnetPaypal'));
                                
                                if(in_array($paymentKey, ['NOVALNET_CC', 'NOVALNET_SEPA', 'NOVALNET_GUARANTEED_SEPA', 'NOVALNET_INSTALMENT_SEPA', 'NOVALNET_PAYPAL'])) {
                                    $ccFormDetails = $paymentService->getCcFormData($basket, $paymentKey);
                                    $ccCustomFields = $paymentService->getCcFormFields();
                                    $content = $twig->render($contectTemplate, [
                                        'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
                                        'paymentMopKey'       =>  $paymentKey,
                                        'paymentName'         => $paymentName,
                                        'guaranteePayment'    => $paymentService->checkGuaranteePaymentDisplayStatus($basket, strtolower($paymentKey)),
                                        'ccFormDetails'       => !empty($ccFormDetails) ? $ccFormDetails : '',
                                        'ccCustomFields'       => !empty($ccCustomFields) ? $ccCustomFields : '',
                                        'customerNo'           => $customerAccount->getAccountContactId(),
                                        'oneClickShopping'   => $oneClickShopping,
                                        'savedPaymentDetails' => $savedPaymentDetails,
                                        'removedSavedPaymentDetail' => $paymentHelper->getTranslatedText('removedSavedPaymentDetail'),
                                        'savedPaymentDetailsRemovalUrl' => $paymentService->getSavedTokenRemovalUrl(),
                                    ]);
                                } elseif (in_array($paymentKey, ['NOVALNET_INSTALMENT_INVOICE'])) {
                                    $content = $twig->render('Novalnet::PaymentForm.NovalnetAdditionalPaymentForm', [
                                    'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
                                    'paymentMopKey'       =>  $paymentKey,
                                    'paymentName'         => $paymentName,
                                    'instalmentNetAmount'  => $basket->basketAmount,
                                    'orderCurrency' => $basket->currency,
                                    'instalmentCycles' => explode(',', $config->get('Novalnet.' . strtolower($paymentKey . '_cycles')))
                                    ]);
                                }
                            } else {
                                    $sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentRequestParameters);
                                    $content = '';
                                    $contentType = 'continue';
                            }
                        }
                                    $event->setValue($content);
                                    $event->setType($contentType);
                    } 
                });

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $paymentService, $sessionStorage)
            {
                if($paymentHelper->getPaymentKeyByMop($event->getMop())) {
                    $sessionStorage->getPlugin()->setValue('nnOrderNo',$event->getOrderId());
                    //$sessionStorage->getPlugin()->setValue('mop',$event->getMop());
                    $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                    $sessionStorage->getPlugin()->setValue('paymentKey',$paymentKey);
                    $paymentService->performServerCall();
                }
            });
        
     
        // Adding transaction comments on Invoice PDF
        
        // Listen for the document generation event
        $eventDispatcher->listen(OrderPdfGenerationEvent::class,
        function (OrderPdfGenerationEvent $event) use ($paymentHelper, $paymentService, $paymentRepository) {
            
        /** @var Order $order */ 
        $order = $event->getOrder();
        if (!empty($order->id)) {
            $payments = $paymentRepository->getPaymentsByOrderId($order->id);
            $paymentKey = $paymentHelper->getPaymentKeyByMop($payments[0]->mopId);
            $dbDetails = $paymentService->getDatabaseValues($order->id);
            try {
                if (in_array($paymentKey, ['NOVALNET_CC', 'NOVALNET_SEPA', 'NOVALNET_INVOICE', 'NOVALNET_INSTALMENT_INVOICE', 'NOVALNET_PAYPAL']) && !empty($dbDetails['plugin_version'])
                ) {
                    $transactionCommentVal = $paymentService->getTransactionCommentVal($order->id);
                    $transactionComments = $paymentService->formTransactionCommentsInvoicePDF($transactionCommentVal);
                    $orderPdfGenerationModel = pluginApp(OrderPdfGeneration::class);
                    $orderPdfGenerationModel->advice = $paymentHelper->getTranslatedText('novalnetDetails'). PHP_EOL . $transactionComments;
                    if ($event->getDocType() == Document::INVOICE) {
                        $event->addOrderPdfGeneration($orderPdfGenerationModel); 
                    }
                }
            } catch (\Exception $e) {
                        $this->getLogger(__METHOD__)->error('Adding transaction comments on invoice PDF is failed for an order' . $order->id , $e);
            } 
        }
        });
        
    }
}
