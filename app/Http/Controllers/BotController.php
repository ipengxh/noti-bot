<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotSubscribe;
use App\Services\Clients\Drivers\Feishu;
use Auth;
use DB;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class BotController extends Controller
{
    /**
     * @return Application|Factory|View
     */
    public function explore()
    {
        return view('bot.explore');
    }

    /**
     * @param Feishu $feishu
     * @return Application|Factory|View
     * @throws Exception
     */
    public function my(Feishu $feishu)
    {
        $bots = Bot::where('user_id', '=', Auth::user()->id)->orderByDesc('id')->get();
        return view('bot.my', compact('bots'));
    }

    /**
     * @param Feishu $feishu
     * @param int $id
     * @return Application|Factory|View
     * @throws Exception
     */
    public function edit(Request $request, Feishu $feishu, int $id)
    {
        $bot = Bot::findOrFail($id);
        if ($bot->user_id != Auth::user()->id) {
            throw new Exception("滚");
        }
        $myGroups = $feishu->getUserGroupsHasBot(Auth::user()->open_id);
        return view('bot.create.url', compact('bot', 'myGroups'));
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return Application|Factory|View
     * @throws Exception
     */
    public function create(Request $request, Feishu $feishu)
    {
        if (in_array($request->type, ['url', 'ping', 'timer'])) {
            $myGroups = $feishu->getUserGroupsHasBot(Auth::user()->open_id);
            return view("bot.create.{$request->type}", compact('myGroups'));
        }
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @param int|null $id
     * @return Application|RedirectResponse|Redirector
     * @throws GuzzleException
     */
    public function store(Request $request, Feishu $feishu, int $id = null)
    {
        try {
            DB::beginTransaction();
            if ($id) {
                return $this->update($request, $feishu, $id);
            }
            $bot = Bot::create([
                'name' => $request->name,
                'user_id' => Auth::user()->id,
                'config' => $this->serializeConfig($request),
                'type' => $request->type
            ]);
            foreach ($request->subscribes as $subscribe) {
                BotSubscribe::create([
                    'bot_id' => $bot->id,
                    'to' => $subscribe
                ]);
                $message = $this->generateFirstMessage($request);
                $feishu->textMessageToUser($subscribe, $message);
            }
            DB::commit();
            return redirect(route('bot.my'));
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
        }
    }

    /**
     * @param Request $request
     * @param Feishu $feishu
     * @param int $id
     * @throws GuzzleException
     */
    private function update(Request $request, Feishu $feishu, int $id)
    {
        $bot = Bot::findOrFail($id);
        if ($bot->user_id != Auth::user()->id) {
            throw new Exception("滚");
        }
        $bot->name = $request->name;
        $bot->config = $this->serializeConfig($request);
        BotSubscribe::where('bot_id', '=', $bot->id)->delete();
        foreach ($request->subscribes as $subscribe) {
            BotSubscribe::create([
                'bot_id' => $bot->id,
                'to' => $subscribe
            ]);
            $message = $this->generateFirstMessage($request);
            $feishu->textMessageToUser($subscribe, $message);
        }
    }

    private function generateFirstMessage(Request $request)
    {
        if (Bot::TYPE['URL'] == $request->type) {
            if ($request->id) {
                $message = Auth::user()->name . "刚刚修改了 {$request->name} 的URL监控机器人：";
            } else {
                $message = Auth::user()->name . "刚刚创建了 {$request->name} 的URL监控机器人：";
            }
            $message .= "\n监控地址：{$request->url}";
            if ($request->host) {
                $message .= "\nhost地址：{$request->host}";
            }
            $message .= "\n超时时间：{$request->timeout}秒";
            if ($request->is_json) {
                $message .= "\n期望响应json格式内容";
            }
            if ('on' == $request->alert_modified) {
                $message .= "\n内容发生变动时发送提醒";
            }
            $message .= "\n期望响应状态码为{$request->status_code}";
            $message .= "\n打开 " . env('APP_URL') . " 来创建一个你自己的机器人";
            return $message;
        }
    }

    private function serializeConfig(Request $request)
    {
        if (Bot::TYPE['URL'] == $request->type) {
            return [
                'url' => $request->url,
                'host' => $request->host,
                'timeout' => $request->timeout,
                'headers' => $request->input('headers'),
                'alert_modified' => 'on' == $request->alert_modified,
                'assert' => [
                    'is_json' => $request->is_json,
                    'http_status_code' => $request->status_code
                ]
            ];
        }
    }

    public function destroy($id)
    {
        BotSubscribe::where('bot_id', '=', $id)->delete();
        Bot::where('id', '=', $id)->delete();
        return redirect(route('bot.my'));
    }
}
