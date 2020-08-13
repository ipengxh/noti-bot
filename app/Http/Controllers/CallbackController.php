<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Clients\Drivers\Feishu;
use Cache;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use stdClass;
use Str;

class CallbackController extends Controller
{
    /**
     * @param Request $request
     * @param Feishu $feishu
     * @return bool|JsonResponse|void
     * @throws GuzzleException
     */
    public function index(Request $request, Feishu $feishu)
    {
        $cipher = hash('sha256', env('FEISHU_CALLBACK_ENCRYPT_KEY'), true);
        $iv = substr(base64_decode($request->encrypt), 0, 16);
        $decryptedString = openssl_decrypt(substr(base64_decode($request->encrypt), 16), 'AES-256-CBC', $cipher, OPENSSL_RAW_DATA, $iv);

        $response = json_decode($decryptedString);
        Log::debug($decryptedString);

        if ('url_verification' === $response->type) {
            return $this->urlVerification($response);
        }
        if ('event_callback' === $response->type) {
            if ('message' === $response->event->type) {
                return $this->eventCallback($response, $feishu);
            }
            if ('message_read' === $response->event->type) {
                Log::debug("消息已读");
                return response()->json('got');
            }
            if ('add_bot' === $response->event->type) {
                return $this->eventAddBot($response, $feishu);
            }
        }
        return response()->json('unknown response');
    }

    /**
     * @param stdClass $response
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws GuzzleException
     */
    private function eventAddBot(stdClass $response, Feishu $feishu)
    {
        Log::debug("机器人被添加入群： {$response->event->operator_name} 将机器人加入群组 {$response->event->chat_name}");
        $feishu->textMessageToUser($response->event->open_chat_id, "大家好，我是飞书通知机器人，一个对开发人员非常友好的通知工具。\n打开 " . env('APP_URL') . " 看看我有哪些功能。");
        return response()->json('got');
    }

    /**
     * @param stdClass $response
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws Exception
     * @throws GuzzleException
     */
    private function eventCallback(stdClass $response, Feishu $feishu)
    {
        $user = User::where('open_id', '=', $response->event->open_id)->first();
        Log::debug("用户 {$user->name} 发送： {$response->event->text}");
        $learn = $this->learn($response, $user);
        if ($response->event->is_mention) {
            if ($learn) {
                $feishu->textMessageToUser($response->event->open_chat_id ?: $response->event->open_id, "学到了", $response->event->open_message_id);
            } else {
                $question = trim(str_replace('@noti-bot', '', strip_tags($response->event->text)));
                $feishu->textMessageToUser($response->event->open_chat_id ?: $response->event->open_id, Cache::get($question) ?: "哈？", $response->event->open_message_id);
            }
        } else {
            if ($learn) {
                $feishu->textMessageToUser($response->event->open_chat_id ?: $response->event->open_id, "学到了");
            } else {
                if ('boot' === $response->event->text || '开机' === $response->event->text) {
                    return $this->bootUserMachine($response, $feishu);
                }
                $feishu->textMessageToUser($response->event->open_chat_id ?: $response->event->open_id, Cache::get($response->event->text) ?: "你说啥我不太懂");
            }
        }
        return response()->json('got');
    }

    private function learn(stdClass $response, User $user)
    {
        $questionAndAnswer = explode("\n", $response->event->text);
        if (2 > count($questionAndAnswer)) {
            return false;
        }
        $questionAndAnswer[0] = trim(str_replace('@noti-bot', '', strip_tags($questionAndAnswer[0])));
        if (!Str::startsWith($questionAndAnswer[0], "问：") && !Str::startsWith($questionAndAnswer[0], "问:") && !Str::startsWith($questionAndAnswer[0], "问 ")) {
            return false;
        }
        if (!Str::startsWith($questionAndAnswer[1], "答：") && !Str::startsWith($questionAndAnswer[1], "答:") && !Str::startsWith($questionAndAnswer[1], "答 ")) {
            return false;
        }
        $question = ltrim(ltrim(ltrim(ltrim($questionAndAnswer[0], "问"), ":"), "："));
        $answer = ltrim(ltrim(ltrim(ltrim($questionAndAnswer[1], "答"), ":"), "："));
        Log::debug("用户 {$user->name} 教学： {$question} -> {$answer}");
        Cache::forever($question, $answer);
        return true;
    }

    /**
     * @param stdClass $response
     * @return JsonResponse
     */
    private function urlVerification(stdClass $response)
    {
        if ($response->token !== env('FEISHU_CALLBACK_VERIFICATION_TOKEN')) {
            Log::debug('response token not matched');
            return response()->json('token denied');
        }
        return response()->json([
            'challenge' => $response->challenge
        ]);
    }

    /**
     * @param stdClass $response
     * @param Feishu $feishu
     * @return JsonResponse
     * @throws GuzzleException
     */
    private function bootUserMachine(stdClass $response, Feishu $feishu)
    {
        $user = User::where('open_id', '=', $response->event->open_id)->first();
        if (!$user->mac) {
            $feishu->textMessageToUser($user->open_id, "你还没有设置MAC地址，无法启动电脑");
            return response()->json('boot failed');
        }
        exec("wol {$user->mac}");
        Log::debug("用户 {$user->name} 远程唤醒自己的机器 {$user->mac}");
        $feishu->textMessageToUser($user->open_id, "已发送开机指令");
        return response()->json('boot command sent');
    }
}
