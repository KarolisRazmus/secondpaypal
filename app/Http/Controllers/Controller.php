<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function newPromiseAuthorization()
    {

        $paypal = new PaypalController();
        return $paypal->auth(50, '1', '1', '1');

    }

    public function promiseSucceeded()
    {

        $paypal = new PaypalController();
        $paypal->promiseSuccessful('1');

    }

    public function promiseFailed()
    {
        $paypal = new PaypalController();
        $paypal->promiseCanceled('1');
    }



}
