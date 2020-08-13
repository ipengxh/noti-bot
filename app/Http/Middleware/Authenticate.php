<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $redirectUrl = urlencode(route('login'));
            $appId = env('FEISHU_APP_ID');
            return "https://open.feishu.cn/open-apis/authen/v1/index?redirect_uri={$redirectUrl}&app_id={$appId}&state=";
        }
    }
}
