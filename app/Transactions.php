<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    protected $table = 'transactions';

    protected $fillable = ['amount', 'status', 'payment_id', 'payer_id', 'auth_id', 'email', 'promiser_id', 'promise_id', 'supporter_id', 'promise_status'];

    public function supporters ()
    {
        return $this->hasMany(Transactions::class, 'promise_id', 'promise_id')->where("status", "captured-from-supporter");
    }
}
