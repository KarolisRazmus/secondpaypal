<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaypalController;
use Illuminate\Console\Command;

class Reauthorizate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paypal:reauthorizate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reauthorizates authorized payments';

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
        $reauthorizate = new PaypalController();
        $reauthorizate->reauthorize();
    }
}
