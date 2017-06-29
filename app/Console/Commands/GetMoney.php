<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaypalController;
use Illuminate\Console\Command;

class GetMoney extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paypal:getmoney';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets money from users';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $pushMoney = new PaypalController();
        return $pushMoney->getMoney();
    }
}
