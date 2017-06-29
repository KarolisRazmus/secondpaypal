<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->double('amount');
            $table->string('status', 50);
            $table->string('promise_status', 255);
            $table->string('payment_id', 255);
            $table->string('payer_id', 255);
            $table->string('auth_id', 255);
            $table->string('email', 255);
            $table->string('promiser_id', 255);
            $table->string('promise_id', 255);
            $table->string('supporter_id', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
