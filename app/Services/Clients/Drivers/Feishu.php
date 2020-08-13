<?php

namespace App\Services\Clients\Drivers;

use Cache;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Str;

class Feishu
{
    /**
     * @return mixed
     * @throws Exception
     */
    private function getAccessToken()
    {
        if ($cache = Cache::get("feishu-token")) {
            return $cache;
        }
        $url = "https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal/";
        $response = Http::post($url, [
            'app_id' => env('FEISHU_APP_ID'),
            'app_secret' => env('FEISHU_APP_SECRET')
        ]);
        if ($response->successful()) {
            $token = $response->json()['tenant_access_token'];
            Cache::put("feishu-token", $token, 100); // expire in 100 minutes
            return $token;
        } else {
            throw new Exception("could not get access token");
        }
    }

    private function getUserAccessToken(string $openId)
    {
        return Cache::get("user:{$openId}:access-token");
    }

    /**
     * @return object
     * @throws Exception
     */
    public function getAccesses()
    {
        $url = 'https://open.feishu.cn/open-apis/contact/v1/scope/get';
        $client = new Client();
        $response = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => "Bearer " . $this->getAccessToken()
            ]
        ]);
        return json_decode($response->getBody()->getContents())->data;
    }

    /**
     * @throws Exception
     */
    public function getUsersInfo()
    {
        $accesses = $this->getAccesses();
        $employees = $accesses->authed_open_ids;
        $url = 'https://open.feishu.cn/open-apis/contact/v1/user/batch_get?open_ids=';
        $url .= implode('&open_ids=', $employees);
        $client = new Client();
        $response = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => "Bearer " . $this->getAccessToken()
            ]
        ]);
        $users = json_decode($response->getBody()->getContents())->data->user_infos;
        $users = (array)$users;
        if ($accesses->authed_departments) {
            foreach ($accesses->authed_departments as $department) {
                $users = array_merge($users, (array)$this->getDepartmentUsers($department));
            }
        }
        return json_decode(json_encode($users));
    }

    /**
     * @throws Exception
     */
    public function getUsersDetails()
    {
        $accesses = $this->getAccesses();
        $employees = $accesses->authed_open_ids;
        $url = 'https://open.feishu.cn/open-apis/contact/v1/user/batch_get?open_ids=';
        $url .= implode('&open_ids=', $employees);
        $client = new Client();
        $response = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => "Bearer " . $this->getAccessToken()
            ]
        ]);
        $users = json_decode($response->getBody()->getContents())->data->user_infos;
        $users = (array)$users;
        if ($accesses->authed_departments) {
            foreach ($accesses->authed_departments as $department) {
                $users = array_merge($users, (array)$this->getDepartmentUsersDetails($department));
            }
        }
        return json_decode(json_encode($users));
    }

    /**
     * @param string $departmentId
     * @return array
     * @throws Exception
     */
    public function getDepartmentUsers(string $departmentId)
    {
        $url = "https://open.feishu.cn/open-apis/contact/v1/department/user/list";
        $params = [
            'department_id' => $departmentId,
            'page_size' => 100,
            'fetch_child' => 'true'
        ];
        $client = new Client();
        $hasMore = true;
        $pageToken = null;
        $users = [];
        while ($hasMore) {
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            } else {
                if (isset($params['page_token'])) {
                    unset($params['page_token']);
                }
            }
            $response = $client->request('GET', $url . "?" . http_build_query($params), [
                'headers' => [
                    'Authorization' => "Bearer " . $this->getAccessToken()
                ]
            ]);
            $responseJson = json_decode($response->getBody()->getContents());
            $users = array_merge($users, $responseJson->data->user_list);
            $hasMore = $responseJson->data->has_more;
            $pageToken = $responseJson->data->page_token ?? null;
        }
        return $users;
    }

    /**
     * @param string $departmentId
     * @return array
     * @throws Exception
     */
    public function getDepartmentUsersDetails(string $departmentId)
    {
        $url = "https://open.feishu.cn/open-apis/contact/v1/department/user/detail/list";
        $params = [
            'department_id' => $departmentId,
            'page_size' => 100,
            'fetch_child' => 'true'
        ];
        $client = new Client();
        $hasMore = true;
        $pageToken = null;
        $users = [];
        while ($hasMore) {
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            } else {
                if (isset($params['page_token'])) {
                    unset($params['page_token']);
                }
            }
            $response = $client->request('GET', $url . "?" . http_build_query($params), [
                'headers' => [
                    'Authorization' => "Bearer " . $this->getAccessToken()
                ]
            ]);
            $responseJson = json_decode($response->getBody()->getContents());
            $users = array_merge($users, $responseJson->data->user_infos);
            $hasMore = $responseJson->data->has_more;
            $pageToken = $responseJson->data->page_token ?? null;
        }
        return $users;
    }

    /**
     * @param string $toUser
     * @param string $content
     * @param string $rootId
     * @return string
     * @throws GuzzleException
     */
    public function textMessageToUser(string $toUser, string $content, string $rootId = null)
    {
        if (Str::startsWith($toUser, 'oc')) {
            $idKey = "chat_id";
        } elseif (Str::startsWith($toUser, 'ou')) {
            $idKey = "open_id";
        }
        $url = "https://open.feishu.cn/open-apis/message/v4/send/";
        $client = new Client();
        $body = [
            $idKey => $toUser,
            'msg_type' => 'text',
            'content' => [
                'text' => $content
            ]
        ];
        if ($rootId) {
            $body['root_id'] = $rootId;
        }
        $response = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer " . $this->getAccessToken(),
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body)
        ]);
        return $response->getBody()->getContents();
    }

    /**
     * @param string $toUser
     * @param array $contents
     * @param string|null $title
     * @return string
     * @throws Exception
     */
    public function htmlMessageToUser(string $toUser, array $contents, string $title = null)
    {
        if (Str::startsWith($toUser, 'oc')) {
            $idKey = "chat_id";
        } elseif (Str::startsWith($toUser, 'ou')) {
            $idKey = "open_id";
        } else {
            throw new Exception("to id incorrect");
        }
        $url = "https://open.feishu.cn/open-apis/message/v4/send/";
        $client = new Client();
        $body = json_encode([
            $idKey => $toUser,
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $title,
                        'content' => $contents
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer " . $this->getAccessToken(),
                'Content-Type' => 'application/json'
            ],
            'body' => $body
        ]);
        return $response->getBody()->getContents();
    }

    /**
     * @param string $code
     * @return string
     * @throws Exception
     */
    public function getLoginUserInfo(string $code)
    {
        $url = "https://open.feishu.cn/open-apis/authen/v1/access_token";
        $client = new Client();
        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'app_access_token' => $this->getAccessToken(),
                'grant_type' => 'authorization_code',
                'code' => $code
            ])
        ]);
        return json_decode($response->getBody()->getContents())->data;
    }

    /**
     * @param string $openId
     * @return array
     * @throws Exception
     */
    public function getUserGroups(string $openId)
    {
        $url = "https://open.feishu.cn/open-apis/user/v4/group_list";
        $params = [
            'open_ids' => $openId,
            'page_size' => 100,
            'page_token' => ""
        ];
        $client = new Client();
        $hasMore = true;
        $pageToken = null;
        $groups = [];
        while ($hasMore) {
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            } else {
                if (isset($params['page_token'])) {
                    unset($params['page_token']);
                }
            }
            $response = $client->request('GET', $url . "?" . http_build_query($params), [
                'headers' => [
                    'Authorization' => "Bearer " . $this->getUserAccessToken($openId)
                ]
            ]);
            $responseJson = json_decode($response->getBody()->getContents());
            $groups = array_merge($groups, $responseJson->data->groups);
            $hasMore = $responseJson->data->has_more;
            $pageToken = $responseJson->data->page_token ?? null;
        }
        return $groups;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getBotGroups()
    {
        $url = 'https://open.feishu.cn/open-apis/chat/v4/list';
        $params = [
            'page_size' => 100,
            'page_token' => ""
        ];
        $client = new Client();
        $hasMore = true;
        $pageToken = null;
        $groups = [];
        while ($hasMore) {
            if ($pageToken) {
                $params['page_token'] = $pageToken;
            } else {
                if (isset($params['page_token'])) {
                    unset($params['page_token']);
                }
            }
            $response = $client->request('GET', $url . "?" . http_build_query($params), [
                'headers' => [
                    'Authorization' => "Bearer " . $this->getAccessToken()
                ]
            ]);
            $responseJson = json_decode($response->getBody()->getContents());
            $groups = array_merge($groups, $responseJson->data->groups);
            $hasMore = $responseJson->data->has_more;
            $pageToken = $responseJson->data->page_token ?? null;
        }
        return $groups;
    }

    /**
     * @param string $openId
     * @return array
     * @throws Exception
     */
    public function getUserGroupsHasBot(string $openId)
    {
        try {
            $userGroups = $this->getUserGroups($openId);
            $botGroups = $this->getBotGroups();
        } catch (Exception $exception) {
            \Auth::logout();
            return redirect('/');
        }
        $userGroupsHasBot = [];
        foreach ($userGroups as $userGroup) {
            foreach ($botGroups as $botGroup) {
                if ($botGroup->chat_id === $userGroup->chat_id) {
                    $userGroupsHasBot[] = $userGroup;
                }
            }
        }
        return $userGroupsHasBot;
    }
}