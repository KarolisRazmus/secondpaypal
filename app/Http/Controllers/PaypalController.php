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
                env('CLIENT_ID'),
                env('CLIENT_SECRET')
            )
        );

        $apiContext->setConfig([

            'mode' => env('mode'),
            'http.ConnectionTimeOut' => env('CONNECTION_TIMEOUT'),
            'log.LogEnabled' => env('LOG_ENABLED'),
            'log.FileName' => env('LOG_FILE_NAME'),
            'log.LogLevel' => env('LOG_LEVEL'),
            'validation.level' => env('VALIDATION_LEVEL')

        ]);

        return $apiContext;
    }

    /**
     * Autheticate user
     *
     * @param null $reservationAmount
     * @param $promiser_id
     * @param $promise_id
     * @param $supporter_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function auth($reservationAmount = null, $promiser_id, $promise_id, $supporter_id)
    {

        //Set data to session
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
            // Get paypal redirect URL
            $approvalUrl = $payment->getApprovalLink();

        } catch (PayPalConnectionException $ex) {
            echo $ex->getCode();
            echo $ex->getData();
            die($ex);
        } catch (Exception $ex) {
            die($ex);
        }

        //Redirect user to approval url
        return redirect($approvalUrl);
    }

    /**
     * Complete authentication
     *
     * @param $success
     * @return Payment
     */
    public function completeAuth($success)
    {

        if ($success == 'true') {

            //Get data from session
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

    /**
     * Reauthorize authorized payments
     */
    public function reauthorize()
    {
        //Get data from database where status is authorized
        $transactions = Transactions::where('status', 'authorized')->get()->toArray();

        //Loops through all transactions
        foreach ($transactions as $transaction) {

            if($transaction['promise_status'] == 'in-progress') {

                try {
                    //Get authorization
                    $authorization = Authorization::get($transaction['auth_id'], $this->apiConfig());

                    //initialize Amount object and set details
                    $amount = new Amount();
                    $amount->setCurrency("USD");
                    $amount->setTotal($transaction['amount']);

                    //Reauthorize
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



    }


    /**
     * Gets money from users
     */
    public function getMoney()
    {
        //get data from DB where status is authorized
        $transactions =  Transactions::where('status', 'authorized')->get()->toArray();

        //Loops thtough all transactions
        foreach ($transactions as $transaction) {


            //If promise fails, gets money only from promiser
            if($transaction['promiser_id'] == $transaction['supporter_id'] && $transaction['promise_status'] == 'promise-canceled') {

                //Get authorization
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

                    //Get single record
                    $singleTransaction = Transactions::find($transaction['id']);

                    //Changes status depending on capture state
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



            //If promise successful gets money from all supporters
            } elseif($transaction['promiser_id'] != $transaction['supporter_id'] && $transaction['promise_status'] == 'promise-successful') {

                //Get authorization
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

                    //Get single record
                    $singleTransaction = Transactions::find($transaction['id']);


                    //Changes status depending on capture state
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

    /**
     * Push money to user accounts
     */
    public function pushMoney()
    {
        //Gets all transactions from database
        $transactions = Transactions::where('promise_status', 'promise-successful')
                                    ->where('status', 'authorized')
                                    ->with('supporters')
                                    ->get()
                                    ->toArray();

        //Loops through all transactions
        foreach ($transactions as $transaction) {

            //Amount to get from supporters
            $amountFromSupporters = 0;

            foreach ($transaction['supporters'] as $supporter) {
                $amountFromSupporters += $supporter['amount'];
            }

            //Initialize new Payout object and set details
            $payouts = new Payout();
            $senderBatchHeader = new PayoutSenderBatchHeader();
            $senderBatchHeader->setSenderBatchId(uniqid())
                ->setEmailSubject("You have a Payout!");
            $senderItem = new PayoutItem();
            $senderItem->setRecipientType('Email')
                ->setNote('Thanks!')
                ->setReceiver($transaction['email'])
                ->setAmount(new Currency('{"value":"' . $amountFromSupporters . '","currency":"USD"}'));
            $payouts->setSenderBatchHeader($senderBatchHeader)
                ->addItem($senderItem);

            //Create new payout
            $payouts->createSynchronous($this->apiConfig());

            //Update transaction status
            Transactions::find($transaction['id'])->update(['status' => 'completed']);
        }

    }

    /**
     * Sets promise status to promise-successful
     *
     * @param $id
     */
    public function promiseSuccessful($id)
    {
        //Get transactions from Database
        $transactions = Transactions::where('promise_id', $id)->get();

        foreach ($transactions as $transaction) {

            $transaction->update(['promise_status' => 'promise-successful']);

        }

    }

    /**
     * Sets promise status to promise-successful
     *
     * @param $id
     */
    public function promiseCanceled($id)
    {
        //Get transactions from Database
        $transactions = Transactions::where('promise_id', $id)->get();

        foreach ($transactions as $transaction) {

            $transaction->update(['promise_status' => 'promise-canceled']);

        }
    }


}
