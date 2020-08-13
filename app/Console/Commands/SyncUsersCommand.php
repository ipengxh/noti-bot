<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Clients\Drivers\Feishu;
use Illuminate\Console\Command;

class SyncUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return int
     */
    public function handle(Feishu $feishu)
    {
        $users = $feishu->getUsersDetails();
        foreach ($users as $user) {
            $userModel = User::where('open_id', '=', $user->open_id)->first();
            if (!$userModel) {
                User::create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_640,
                    'employee_id' => $user->employee_id,
                    'mobile' => trim("+86", $user->mobile),
                    'open_id' => $user->open_id,
                    'union_id' => $user->union_id,
                    'token' => md5(uniqid())
                ]);
            } else {
                $userModel->name = $user->name;
                $userModel->email = $user->email;
                $userModel->avatar = $user->avatar_640;
                $userModel->employee_id = $user->employee_id;
                $userModel->mobile = trim("+86", $user->mobile);
                $userModel->union_id = $user->union_id;
                $userModel->save();
            }
        }
    }
}
