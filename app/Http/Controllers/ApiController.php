<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Clients\Drivers\Feishu;
use Auth;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;
use Log;

class ApiController extends Controller
{
    /**
     * @param Feishu $feishu
     * @return Application|Factory|View
     * @throws Exception
     */
    public function index(Feishu $feishu)
    {
        $groups = $feishu->getUserGroupsHasBot(Auth::user()->open_id);
        return view('api', compact('groups'));
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return Application|RedirectResponse|Redirector
     * @throws Exception
     */
    public function exampleTest(Request $request, Feishu $feishu)
    {
        $feishu->textMessageToUser(Auth::user()->open_id, $request->input('content'));
        return redirect(route('api.index'))->with('status', 'Sent success, check your lark.');
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function send(Request $request, Feishu $feishu)
    {
        $user = User::where('token', '=', $request->token)->first();
        if (!$user) {
            return response()->json([
                'code' => '404',
                'message' => 'token not found'
            ]);
        }
        $text = str_replace('${me}', $user->name, $request->text);
        $text = str_replace('${date}', Carbon::today()->toDateString(), $text);
        $text = str_replace('${time}', Carbon::now()->toTimeString(), $text);
        $text = str_replace('@{all}', '<at user_id="all">所有人</at>', $text);
        $text = str_replace('@{所有人}', '<at user_id="all">所有人</at>', $text);
        preg_match_all("/@{[\x{4e00}-\x{9fa5}]+}/u", $text, $matches);
        if ($matches) {
            foreach ($matches[0] as $match) {
                $userName = rtrim(ltrim($match, '@{'), '}');
                $atUserModel = User::where('name', '=', $userName)->first();
                if (!$atUserModel) {
                    return response()->json([
                        'code' => 404,
                        'message' => "找不到 {$userName}"
                    ]);
                }
                $text = str_replace("@{{$userName}}", "<at open_id=\"{$atUserModel->open_id}\">@{$atUserModel->name}</at> ", $text);
            }
        }
        try {
            if ($request->chat) {
                Log::debug("{$user->name} send message to chat {$request->chat}: {$text} \t {$request->text}");
                $feishu->textMessageToUser($request->chat, $text);
            } else {
                if ($request->to_email || $request->to_name) {
                    Log::debug("{$user->name} send message to user {$request->to_email} {$request->to_name}: {$text} \t {$request->text}");
                    $this->sendToInternalUser($feishu, $request, "{$text}");
                } else {
                    Log::debug("{$user->name} send message: {$text} \t {$request->text}");
                    $feishu->textMessageToUser($user->open_id, $text);
                }
            }
            return response()->json([
                'code' => 200,
                'message' => 'done'
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'code' => 500,
                'message' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @param Feishu $feishu
     * @param Request $request
     * @param string $text
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendToInternalUser(Feishu $feishu, Request $request, string $text)
    {
        $user = User::where('email', '=', $request->to_email)->first();
        if (!$user) {
            $user = User::where('name', '=', $request->to_name)->first();
        }
        if ($user) {
            $feishu->textMessageToUser($user->open_id, $text);
        } else {
            throw new Exception("User not found");
        }
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws Exception
     */
    public function gitlab(Request $request, Feishu $feishu)
    {
        $user = User::where('token', '=', $request->token)->first();
        if (!$user) {
            return response()->json([
                'code' => '404',
                'message' => 'token not found'
            ]);
        }
        if ('push' === $request->object_kind) {
            return $this->gitlabPush($request, $user, $feishu);
        } else {
            return response()->json([
                'code' => 400,
                'message' => 'method is not supported'
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Model $user
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws Exception
     */
    private function gitlabPush(Request $request, Model $user, Feishu $feishu)
    {
        Log::debug($request->input());
        $commits = "";
        foreach ($request->commits as $key => $commit) {
            $commits .= ($key + 1) . ":\t{$commit['author']['name']}\t" . str_replace("\n\n", "\n", $commit['message']) . "\n";
        }
        if ($request->chat) {
            $feishu->textMessageToUser($request->chat, "项目 {$request->project['name']}({$request->project['description']})刚刚提交了代码\n共计 {$request->total_commits_count} 次commit：\n{$commits}\n{$request->repository['git_http_url']}");
        } else {
            $feishu->textMessageToUser($user->open_id, "项目 {$request->project['name']}({$request->project['description']})刚刚提交了代码\n共计 {$request->total_commits_count} 次commit：\n{$commits}\n{$request->repository['git_http_url']}");
        }
        return response()->json([
            'code' => 200,
            'message' => 'done'
        ]);
    }

    public function scripts(Request $request)
    {
        if (!$request->token) {
            return abort(400, "Oops, token required");
        }
        $appUrl = env('APP_URL');
        if ('sshrc' === $request->script) {
            return <<<EOF
#!/bin/bash
USER=`whoami`
IP=`echo \$SSH_CLIENT | awk '{print $1}'`
HOST_IP=`echo \$SSH_CONNECTION | awk '{print $3}'`
HOST_NAME=`hostname`
curl -s "{$appUrl}/send?token={$request->token}&text=用户\${USER}在\${IP}于\$\{date\} \$\{time\}通过ssh登录了\${HOST_NAME}(\${HOST_IP})" -o /dev/null &
EOF;

        } elseif ('noti' === $request->script) {
            return <<<EOF
#!/bin/bash
command=\$@
if [[ \$SUDO_USER ]]; then
    run_user=\$SUDO_USER
else
    run_user=`whoami`
fi
use_sudo=`test`
time=`date "+%Y-%m-%d %H:%M:%S"`
hostname=`hostname`
dir=`pwd`
if [[ "root" == \$run_user ]]; then
    noti_config="/root/.noti"
else
    noti_config="/home/\${run_user}/.noti"
fi
if [ ! -f \$noti_config ]; then
    read -p "输入您的token：" token
    echo \$token > \$noti_config
    chown \$run_user \$noti_config
else
    token=`head -1 \$noti_config`
fi
url="{$appUrl}/send?token=\${token}"
data="text=\${time} 用户 \\\${me} 在 \${hostname} 的 \${dir} 目录以\${run_user}用户执行命令: \${command}"
curl -X POST "\${url}" --data-urlencode "\${data}" --output /dev/null -s &
start_time=\$[\$(date +%s%N)/1000000]
output=`\${command}`
exit_code=\$?
end_time=\$[\$(date +%s%N)/1000000]
run_time=`expr \$end_time - \$start_time`
if [[ \$run_time -gt 1000 ]]; then
    run_time=`expr \$run_time / 1000`秒
else
    run_time=\${run_time}毫秒
fi
finish=`date "+%Y-%m-%d %H:%M:%S"`
if [[ \$output ]]; then
    data="text=\${finish} 命令: \${command} 执行完成，耗时\${run_time}，退出码为 \${exit_code}
输出内容：\${output}"
else
    data="text=\${finish} 命令: \${command} 执行完成，耗时\${run_time}，退出码为 \${exit_code}"
fi
curl -X POST "\${url}" --data-urlencode "\${data}" --output /dev/null -s &
EOF;
        } else {
            return "Oops, I don't know what you want";
        }
    }
}
