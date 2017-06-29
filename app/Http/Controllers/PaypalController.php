<?php

namespace App\Http\Controllers;

use App\Transactions;
use Illuminate\Support\Facades\Input;
use PayPal\Api\Authorization;
use PayPal\Api\Capture;
use PayPal\Api\Currency;
use PayPal\Api\Payment;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Payout;
use PayPal\Api\PayoutItem;
use PayPal\Api\PayoutSenderBatchHeader;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConfigurationException;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use SebastianBergmann\RecursionContext\Exception;

class PaypalController extends Controller
{
    /**
     * Environment configuration
     * @return ApiContext
     */
    private function apiConfig()
    {
        $apiContext = new ApiContext(
            new OAuthTokenCredential(
                'AV1hLFkUv-3ozBb7_JW3xHP04RYT8JLQasJzAPugkiooOdFF3QPpJy67a1afHlw8KiWsAo-EBUS0T0qT',
                'EKhQUXAcv1iXlyOEZJbBn1EjHcBV1yNlHwvXx9CXDi8IqJZhEWgaH_WdY1V3Fvv8VkGir5qBNplf4_ZF'
            )
        );
        $apiContext->setConfig([
            'mode' => 'sandbox',
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => false,
            'log.FileName' => '',
            'log.logLevel' => 'FINE',
            'validation.level' => 'log'
        ]);
        return $apiContext;
    }


    public function auth($reservationAmount = null, $promiser_id, $promise_id, $supporter_id)
    {


        request()->session()->push('info', [

            'promiser_id' => $promiser_id,
            'promise_id' => $promise_id,
            'supporter_id' => $supporter_id

        ]);

        // Create new payer and method
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        // Set redirect urls
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(route('paypal-auth-complete', 'true'))
            ->setCancelUrl(route('paypal-auth-complete', 'false'));

        // Set payment amount
        $amount = new Amount();
        $amount->setCurrency("USD")
            ->setTotal($reservationAmount);

        // Set transaction object
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setDescription("User authorization");

        // Create the full payment object
        $payment = new Payment();
        $payment->setIntent("authorize")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));

        // Create payment with valid API context
        try {
            $payment->create($this->apiConfig());
            // Get paypal redirect URL and redirect user
            $approvalUrl = $payment->getApprovalLink();
            // REDIRECT USER TO $approvalUrl
        } catch (PayPalConnectionException $ex) {
            echo $ex->getCode();
            echo $ex->getData();
            die($ex);
        } catch (Exception $ex) {
            die($ex);
        }

        return redirect($approvalUrl);
    }

    public function completeAuth($success)
    {

        if ($success == 'true') {

            $dataFromSession = request()->session()->pull('info');


            //Get payment object by passing paymentId
            $paymentId = Input::get('paymentId');
            $payment = Payment::get($paymentId, $this->apiConfig());
            // Execute payment with payer id
            $payerId = Input::get('PayerID');
            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);
            try {
                // Execute payment
                $result = $payment->execute($execution, $this->apiConfig());
                // Extract authorization id
                $authid = $payment->transactions[0]->related_resources[0]->authorization->id;
                // Extract amount
                $amount = $payment->transactions[0]->amount->total;
                //Extract payer email
                $email = $payment->payer->payer_info->email;
                if ($result) {
                    //Store data to DB
                    Transactions::create([
                        'promise_id' => $dataFromSession[0]['promise_id'],
                        'promiser_id' => $dataFromSession[0]['promiser_id'],
                        'supporter_id' => $dataFromSession[0]['supporter_id'],
                        'payment_id' => $paymentId,
                        'payer_id' => $payerId,
                        'auth_id' => $authid,
                        'amount' => $amount,
                        'status' => 'authorized',
                        'email' => $email,
                        'promise_status' => 'in-progress'
                    ]);
                } else {
                    echo 'FAILED';
                }
            } catch (PayPalConnectionException $ex) {
                echo $ex->getCode();
                echo $ex->getData();
                die($ex);
            } catch (Exception $ex) {
                die($ex);
            }
            return $result;
        } else {
            echo 'Failed';
        }
    }


    public function reauthorize()
    {

        $transactions = Transactions::where('status', 'authorized')->get()->toArray();

        foreach ($transactions as $transaction) {

            try {

                $authorization = Authorization::get($transaction['auth_id'], $this->apiConfig());

                $amount = new Amount();
                $amount->setCurrency("USD");
                $amount->setTotal($transaction['amount']);

                $authorization->setAmount($amount);
                $authorization->reauthorize($this->apiConfig());

            } catch (PayPalConnectionException $ex) {
                echo $ex->getCode(); // Prints the Error Code
                echo $ex->getData(); // Prints the detailed error message
                die($ex);
            } catch (Exception $ex) {
                die($ex);
            }

        }

    }



    public function getMoney()
    {
        //get data from DB
        $transactions =  Transactions::where('status', 'authorized')->get()->toArray();
        foreach ($transactions as $transaction) {


            //Jeigu promise'as failina, tai nuima pinigus tik is promiserio
            if($transaction['promiser_id'] == $transaction['supporter_id'] && $transaction['promise_status'] == 'promise-failed') {


                $authorization = Authorization::get($transaction['auth_id'], $this->apiConfig());

                try {
                    // Set capture details
                    $amt = new Amount();
                    $amt->setCurrency("USD")
                        ->setTotal($transaction['amount']);
                    // Capture authorization

                    $capture = new Capture();
                    $capture->setAmount($amt);
                    $getCapture = $authorization->capture($capture, $this->apiConfig());
                    $singleTransaction = Transactions::find($transaction['id']);
                    if($getCapture) {
                        if($getCapture->getState() == 'completed') {
                            $singleTransaction->update([
                                'status' => 'captured-from-promiser'
                            ]);
                        }
                    }
                } catch (PayPalConnectionException $ex) {
                    echo $ex->getCode();
                    echo $ex->getData();
                    die($ex);
                } catch (Exception $ex) {
                    die($ex);
                }



            //Jeigu promise'as ivykdomas, tada nuimami pinigai is visu supporteriu
            } elseif($transaction['promiser_id'] != $transaction['supporter_id'] && $transaction['promise_status'] == 'promise-successful') {


                $authorization = Authorization::get($transaction['auth_id'], $this->apiConfig());

                try {
                    // Set capture details
                    $amt = new Amount();
                    $amt->setCurrency("USD")
                        ->setTotal($transaction['amount']);
                    // Capture authorization

                    $capture = new Capture();
                    $capture->setAmount($amt);
                    $getCapture = $authorization->capture($capture, $this->apiConfig());
                    $singleTransaction = Transactions::find($transaction['id']);



                    if($getCapture) {


                        if($getCapture->getState() == 'completed') {
                            $singleTransaction->update([
                                'status' => 'captured-from-supporter'
                            ]);
                        }
                    }
                } catch (PayPalConnectionException $ex) {
                    echo $ex->getCode();
                    echo $ex->getData();
                    die($ex);
                } catch (Exception $ex) {
                    die($ex);
                }

            }

        }

    }


    public function pushMoney()
    {

        $transactions = Transactions::get()->toArray();


        foreach ($transactions as $transaction) {


            if($transaction['promiser_id'] == $transaction['supporter_id'] && $transaction['promise_status'] == 'promise-successful') {


                $promiserEmail = $transaction['email'];
                $promiserId = $transaction['promiser_id'];

            }

            if($transaction['promiser_id'] == $promiserId && $transaction['status'] == 'captured-from-supporter') {

                $payouts = new Payout();
                $senderBatchHeader = new PayoutSenderBatchHeader();
                $senderBatchHeader->setSenderBatchId(uniqid())
                    ->setEmailSubject("You have a Payout!");
                $senderItem = new PayoutItem();
                $senderItem->setRecipientType('Email')
                    ->setNote('Thanks!')
                    ->setReceiver($promiserEmail)
//            ->setSenderItemId("2014031400023")
                    ->setAmount(new Currency('{"value":"' . $transaction['amount'] . '","currency":"USD"}'));
                $payouts->setSenderBatchHeader($senderBatchHeader)
                    ->addItem($senderItem);
                $payouts->createSynchronous($this->apiConfig());


            }

            Transactions::find($transaction['id'])->update(['status' => 'completed']);

        }

    }


    public function promiseSuccessful($id)
    {
        $transactions = Transactions::where('promise_id', $id)->get();

        foreach ($transactions as $transaction) {

            $transaction->update(['promise_status' => 'promise-successful']);

        }

    }

    public function promiseCanceled($id)
    {
        $transactions = Transactions::where('promise_id', $id)->get();

        foreach ($transactions as $transaction) {

            $transaction->update(['promise_status' => 'promise-canceled']);

        }
    }


}
