<?php


namespace App\Services\Clients\Drivers;

use Cache;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;

class Wechat
{
    /**
     * @return mixed
     * @throws Exception
     */
    public function getAccessToken()
    {
        if ($cache = Cache::get("work-wechat-token")) {
            return $cache;
        }
        $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=%s&corpsecret=%s";
        $realUrl = sprintf($url, env('WORK_WECHAT_ID'), env('WORK_WECHAT_SECRET'));
        $response = Http::get($realUrl);
        if ($response->successful()) {
            $token = $response->json()['access_token'];
            Cache::put("work-wechat-token", $token, 100); // expire in 100 minutes
            return $token;
        } else {
            throw new Exception("could not get access token");
        }
    }

    /**
     * @param array $payload
     * @return bool
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendMessage(array $payload)
    {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $this->getAccessToken();
        $client = new Client();
        $response = $client->request('POST', $url, ['body' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        if (200 === $response->getStatusCode() && 0 === json_decode($response->getBody())->errcode) {
            return true;
        } else {
            throw new Exception("send failed");
        }
    }

    /**
     * @param string $toUser
     * @param string $content
     * @param bool $duplicateCheck
     * @param int $duplicateCheckInterval
     * @param bool $safe
     * @return bool
     * @throws Exception
     */
    public function textMessageToUser(string $toUser, string $content, bool $duplicateCheck = false, int $duplicateCheckInterval = 1800, bool $safe = false)
    {
        $payload = [
            'touser' => $toUser,
            'msgtype' => 'text',
            'agentid' => env('WORK_WECHAT_APP_ID'),
            'text' => [
                'content' => $content,
            ],
            'enable_id_trans' => $safe,
            'enable_duplicate_check' => $duplicateCheck,
            'duplicate_check_interval' => $duplicateCheckInterval
        ];
        return $this->sendMessage($payload);
    }

    /**
     * @param string $toUser
     * @param string $title
     * @param string $description
     * @param string $url
     * @param string|null $button
     * @param bool $duplicateCheck
     * @param int $duplicateCheckInterval
     * @param bool $safe
     * @return bool
     * @throws Exception
     */
    public function textCardMessageToUser(string $toUser,
                                          string $title,
                                          string $description,
                                          string $url,
                                          string $button = null,
                                          bool $duplicateCheck = false,
                                          int $duplicateCheckInterval = 1800,
                                          bool $safe = false)
    {
        $payload = [
            'touser' => $toUser,
            'msgtype' => 'textcard',
            'agentid' => env('WORK_WECHAT_APP_ID'),
            "textcard" => [
                "title" => $title,
                "description" => $description,
                "url" => $url,
                "btntxt" => $button ?: ""
            ],
            'enable_id_trans' => $safe,
            'enable_duplicate_check' => $duplicateCheck,
            'duplicate_check_interval' => $duplicateCheckInterval
        ];
        return $this->sendMessage($payload);
    }

    /**
     * @param int $departmentId
     * @param bool $fetchChild
     * @return mixed
     * @throws Exception
     */
    public function getDepartmentUsers(int $departmentId = 1, bool $fetchChild = false)
    {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token=%s&department_id=%s";
        $realUrl = sprintf($url, $this->getAccessToken(), $departmentId);
        if ($fetchChild) {
            $realUrl .= "&fetch_child=1";
        }
        $response = Http::get($realUrl);
        return $response->json()['userlist'];
    }
}