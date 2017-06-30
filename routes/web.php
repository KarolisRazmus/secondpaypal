<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/paypal-auth', ['uses' => 'PaypalController@auth', 'as' => 'paypal-auth']);
Route::get('/paypal-auth-complete{success?}', ['uses' => 'PaypalController@completeAuth', 'as' => 'paypal-auth-complete']);
Route::get('/get-money', ['uses' => 'PaypalController@getMoney', 'as' => 'get-money']);
Route::get('/push-money', ['uses' => 'PaypalController@pushMoney', 'as' => 'push-money']);
Route::get('/reauthorize', ['uses' => 'PaypalController@reauthorize', 'as' => 'reauthorize']);




Route::get('/new-promise-authorization', ['uses' => 'Controller@newPromiseAuthorization', 'as' => 'new-promise-authorization']);
Route::get('/promise-succeeded', ['uses' => 'Controller@promiseSucceeded', 'as' => 'promise-succeeded']);
Route::get('/promise-failed', ['uses' => 'Controller@promiseFailed', 'as' => 'promise-failed']);



