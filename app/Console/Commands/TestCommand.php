<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bots\RSS;
use App\Services\Bots\UrlMonitor;
use App\Services\Clients\Drivers\Feishu;
use Exception;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test command';

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
     * @param Feishu $feishu
     * @return mixed
     * @throws Exception
     */
    public function handle(Feishu $feishu)
    {
        $users = $feishu->getUsersDetails();
        foreach ($users as $user) {
            if (!$user->email) {
                dump("{$user->name} 没有邮件地址");
            }
            if (!$user->mobile) {
                dump("{$user->name} 没有手机号码");
            }
        }
    }
}
