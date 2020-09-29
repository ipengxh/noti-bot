<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Services\Clients\Drivers\Feishu;
use Cache;
use Exception;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Routing\Redirector;

class AuthController extends Controller
{
    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return Application|RedirectResponse|Redirector
     * @throws Exception
     */
    public function login(Request $request, Feishu $feishu)
    {
        if (!$request->code) {
            return redirect(route('home'));
        }
        $userInfo = $feishu->getLoginUserInfo($request->code);
        $user = User::where('open_id', '=', $userInfo->open_id)->first();
        if (!$user) {
            $user = User::create([
                'name' => $userInfo->name,
                'email' => '',
                'avatar' => $userInfo->avatar_big,
                'employee_id' => $userInfo->user_id,
                'mobile' => '',
                'open_id' => $userInfo->open_id,
                'union_id' => '',
                'token' => md5(uniqid())
            ]);
        } else {
            $user->avatar = $userInfo->avatar_big;
            $user->save();
        }
        Cache::put("user:{$user->open_id}:access-token", $userInfo->access_token, $userInfo->expires_in);
        Auth::login($user, false);
        \Log::debug("{$user->name} 登录");
        return redirect(route('home'));
    }

    public function logout()
    {
        Auth::logout();
        return redirect(route('logout.page'));
    }

    public function updateUser(UpdateUserRequest $request)
    {
        $user = User::findOrFail(Auth::user()->id);
        $user->mac = $request->mac;
        $user->save();
        return redirect(route('bot.explore'));
    }

    public function rotateToken()
    {
        $user = User::findOrFail(Auth::user()->id);
        $user->token = md5(uniqid());
        $user->save();
        return redirect(route('api.index'))->with(['rotate' => '重置完成，你的新token是' . $user->token]);
    }
}
