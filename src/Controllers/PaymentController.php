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

namespace Novalnet\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Helper\PaymentHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Novalnet\Services\PaymentService;
use Plenty\Plugin\Templates\Twig;
use Novalnet\Constants\NovalnetConstants;
use Plenty\Plugin\ConfigRepository;
use Novalnet\Services\TransactionService;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentController
 * @package Novalnet\Controllers
 */
class PaymentController extends Controller
{
    use Loggable;
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var SessionStorageService
     */
    private $sessionStorage;

    /**
     * @var basket
     */
    private $basketRepository;
    
    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var Twig
     */
    private $twig;
    
    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var transaction
     */
    private $transaction;
    
    /**
     * @var LibraryCallContract
     */
    private $libCall;
    
    /**
     * PaymentController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param ConfigRepository $config
     * @param PaymentHelper $paymentHelper
     * @param SessionStorageService $sessionStorage
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentService $paymentService
     * @param TransactionService $transactionService,
     * @param Twig $twig
     */
    public function __construct(  Request $request,
                                  Response $response,
                                  ConfigRepository $config,
                                  PaymentHelper $paymentHelper,
                                  FrontendSessionStorageFactoryContract $sessionStorage,
                                  BasketRepositoryContract $basketRepository,             
                                  PaymentService $paymentService,
                                  TransactionService $transactionService,
                                  Twig $twig,
                                LibraryCallContract $libCall
                                )
    {

        $this->request         = $request;
        $this->response        = $response;
        $this->paymentHelper   = $paymentHelper;
        $this->sessionStorage  = $sessionStorage;
        $this->basketRepository  = $basketRepository;
        $this->paymentService  = $paymentService;
        $this->twig            = $twig;
        $this->transaction     = $transactionService;
        $this->config          = $config;
        $this->libCall = $libCall;
    }

    /**
     * Handled the redirect payments after initial payment call success
     *
     */
    public function paymentResponse() 
    {
        $requestData = $this->request->all();
        $responseData = $this->checksumForRedirects($requestData);
        $isPaymentSuccess = isset($responseData['result']['status']) && in_array($responseData['result']['status'], ['PENDING', 'SUCCESS']);
        $notificationMessage = $this->paymentHelper->getTranslatedText('paymentSuccess');
        if ($isPaymentSuccess) {
            $this->paymentService->pushNotification($notificationMessage, 'success', 100);
        } else {
            $this->paymentService->pushNotification($responseData['status_text'], 'error', 100);    
        }
        $paymentRequestParameters = $this->sessionStorage->getPlugin()->getValue('nnPaymentData');
       // $paymentRequestParameters['payment_key'] = 'novalnet_paypal';
        $this->sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($paymentRequestParameters, $responseData));
        $this->paymentService->validatePaymentResponse(strtoupper($this->paymentHelper->getPaymentNameByResponse($responseData['transaction']['payment_type'])));
        return $this->response->redirectTo('confirmation');
    }
    
    /**
     * Checksum validation for redirect payment
     *
     */
    public function checksumForRedirects($response) 
    {
        // Condition to check whether the payment is redirect
        if (! empty($response['checksum']) && ! empty($response['tid']) && !empty($response['txn_secret']) && !empty($response['status'])) {
            $strRevKey = implode(array_reverse(str_split(trim($this->config->get('Novalnet.novalnet_access_key')))));
            $token_string = $response['tid'] . $response['txn_secret'] . $response['status'] . $strRevKey;
            $generated_checksum = hash('sha256', $token_string);
            if ($generated_checksum !== $response['checksum']) {
                $notificationMessage = $this->paymentHelper->getTranslatedText('checksumInvalid');
                $this->paymentService->pushNotification($notificationMessage, 'error', 100);
                return $this->response->redirectTo('checkout');
            } else {
                $data = [];
                $data['transaction']['tid'] = $response['tid'];
                $responseData = $this->libCall->call('Novalnet::guzzle_client',
                ['nn_access_key' => trim($this->config->get('Novalnet.novalnet_access_key')), 'nn_request' => $data, 'nn_request_process_url' => NovalnetConstants::TX_DETAILS_UPDATE_URL] 
            );
                 $this->getLogger(__METHOD__)->error('Transaction update call response redirect', $responseData);
                //$responseData = $this->paymentHelper->executeCurl(json_encode($data), NovalnetConstants::TX_DETAILS_UPDATE_URL);
                return $responseData;
            }
        }
    }

    /**
     * Process the form payments - Credit card, Direct Debit SEPA and Instalment Invoice
     *
     */
    public function processPayment() 
    {
        $requestData = $this->request->all();
        $birthday = sprintf('%4d-%02d-%02d',$requestData['nnBirthdayYear'],$requestData['nnBirthdayMonth'],$requestData['nnBirthdayDate']);
        $paymentRequestParameters = $this->paymentService->getPaymentRequestParameters($this->basketRepository->load(), $requestData['paymentKey']);
        if (empty($paymentRequestParameters['data']['customer']['first_name']) && empty($paymentRequestParameters['data']['customer']['last_name'])) {
            $notificationMessage = $this->paymentHelper->getTranslatedText('firstLastNameError');
            $this->paymentService->pushNotification($notificationMessage, 'error', 100);
            return $this->response->redirectTo('checkout');
        }
        $paymentKey = explode('_', strtolower($requestData['paymentKey']));
        
        // Send DOB to NN server
        if(in_array($requestData['paymentKey'], ['NOVALNET_GUARANTEED_INVOICE', 'NOVALNET_GUARANTEED_SEPA', 'NOVALNET_INSTALMENT_INVOICE', 'NOVALNET_INSTALMENT_SEPA']) && empty($paymentRequestParameters['data']['customer']['billing']['company'])) {
            $paymentRequestParameters['data']['customer']['birth_date']   =  $birthday;
        }
        // Send Instalment info to NN server
        if (in_array($requestData['paymentKey'], ['NOVALNET_INSTALMENT_INVOICE', 'NOVALNET_INSTALMENT_SEPA'])) {
            $paymentRequestParameters['data']['instalment']['interval'] = '1m';
            $paymentRequestParameters['data']['instalment']['cycles'] = $requestData['nnInstalmentCycle'];
        }
        // Send Spefic payments required paramters to NN server
        if (!empty($requestData[$paymentKey[0].$paymentKey[1].'SelectedToken']) && empty($requestData['newForm'])) {
            $paymentRequestParameters['data']['transaction']['payment_data']['token'] = $requestData[$paymentKey[0].$paymentKey[1].'SelectedToken'];
        } else {
            // Common for one-click-shopping supported payments
            if ($this->config->get('Novalnet.'. strtolower($requestData['paymentKey']) .'_shopping_type') == true) {
              $paymentRequestParameters['data']['transaction']['create_token'] = 1;  
            }
            // Build request params for Credit card
            if($requestData['paymentKey'] == 'NOVALNET_CC') {
                $paymentRequestParameters['data']['transaction']['payment_data']['pan_hash'] = $requestData['nnCcPanHash'];
                $paymentRequestParameters['data']['transaction']['payment_data']['unique_id'] = $requestData['nnCcUniqueId'];
            } elseif (in_array($requestData['paymentKey'], ['NOVALNET_SEPA', 'NOVALNET_GUARANTEED_SEPA', 'NOVALNET_INSTALMENT_SEPA'])) { // Build request params for Direct Debit SEPA
                $paymentRequestParameters['data']['transaction']['payment_data']['bank_account_holder'] = $paymentRequestParameters['data']['customer']['first_name'] . ' ' . $paymentRequestParameters['data']['customer']['last_name'];
                $paymentRequestParameters['data']['transaction']['payment_data']['iban'] = $requestData['nnSepaIban']; 
            }
        }
        $this->sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentRequestParameters);
        return $this->response->redirectTo('place-order');
    }
    
    /**
     * Remove the saved payment details from the database
     *
     */
    public function removePaymentDetails() 
    {
        $requestData = $this->request->all();
        $this->transaction->removeSavedPaymentDetails('saveOneTimeToken', $requestData);
    }
}
