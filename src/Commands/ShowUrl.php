<?php

namespace Selfreliance\Payeer\Commands;

use Illuminate\Console\Command;

class ShowUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payeer:url';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show urls for configs shop';

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

        $this->table([
            'Название', 'Значение'
        ], [
            ['URL успешной оплаты', route('payeer.after_pay_to_cab')],
            ['URL неуспешной оплаты', route('payeer.cancel')],
            ['URL обработчика', route('payeer.confirm')],
        ]);
    }
}
