<?php


namespace App\Services\Bots;

use App\Exceptions\Bots\ContentModifiedException;
use App\Exceptions\Bots\FormatException;
use App\Exceptions\Bots\JsonAttributeAssertException;
use App\Exceptions\Bots\StatusCodeException;
use Cache;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Log;
use stdClass;
use Str;

/**
 * Class UrlMonitor
 * @package App\Services\Bots
 * URL监视器
 */
class UrlMonitor
{
    public $url;

    public $host;

    public $timeout = 2;

    public $assertStatusCode = 200;

    public $assertIsJson = false;

    public $alertModified = false;

    public $headers;

    /**
     * @throws ContentModifiedException
     * @throws FormatException
     * @throws StatusCodeException
     * @throws GuzzleException
     */
    public function test()
    {
        $request = new Client();
        $options = [
            'timeout' => $this->timeout,
        ];
        if ($this->host) {
            $urlInfo = parse_url($this->url);
            $url = Str::replaceFirst($urlInfo['host'], $this->host, $this->url);
            $options['verify'] = false;
            $options[] = [
                'headers' => [
                    'Host' => $urlInfo['host']
                ]
            ];
        } else {
            $url = $this->url;
        }
        if ($this->headers) {
            $headers = explode("\n", $this->headers);
            foreach ($headers as $header) {
                $header = explode(':', $header);
                if (2 == count($header)) {
                    $options['headers'] = [$header[0] => trim($header[1])];
                }
            }
            $response = $request->request('GET', $url, $options);
        } else {
            $response = $request->get($url, $options);
        }
        $body = $response->getBody()->getContents();
        if ($this->assertStatusCode != $response->getStatusCode()) {
            throw new StatusCodeException("期望HTTP状态码是{$this->assertStatusCode}但是响应状态码为" . $response->getStatusCode());
        }
        if ($this->assertIsJson) {
            json_decode($body);
            if (json_last_error()) {
                throw new FormatException("期望响应内容为json，响应内容作为json解析时发生错误");
            }
        }
        if ($this->alertModified) {
            $key = md5($this->url . $this->host . json_encode($options));
            $lastGotContent = Cache::get("url-monitor:{$key}");
            if ($lastGotContent && $body !== $lastGotContent) {
                Cache::forever("url-monitor:{$key}", $body);
                Log::debug("内容变动，原内容：{$lastGotContent}");
                Log::debug("新内容：{$body}");
                throw new ContentModifiedException("内容发生了变动");
            }
            Cache::forever("url-monitor:{$key}", $body);
        }
    }
}