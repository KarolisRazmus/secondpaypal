<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Authorize new payment
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function newPromiseAuthorization()
    {

        $paypal = new PaypalController();
        return $paypal->auth(20, '40', '40', '43');

    }

    /**
     * Set promise status to successful
     */
    public function promiseSucceeded()
    {

        $paypal = new PaypalController();
        $paypal->promiseSuccessful('40');

    }

    /**
     * Set promise status to failed
     */
    public function promiseFailed()
    {
        $paypal = new PaypalController();
        $paypal->promiseCanceled('40');
    }



}
