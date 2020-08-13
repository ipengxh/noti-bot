<?php

namespace App\Console\Commands;

use App\Exceptions\Bots\ContentModifiedException;
use App\Models\Bot;
use App\Services\Bots\UrlMonitor;
use App\Services\Clients\Drivers\Feishu;
use Cache;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Psr\SimpleCache\InvalidArgumentException;

class BotScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run bots';

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
     * @return mixed
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function handle()
    {
        $bots = $this->getBots();
        foreach ($bots as $bot) {
            $this->runBot($bot);
        }
    }

    private function getBots()
    {
        return Bot::all();
    }

    /**
     * @param Bot $bot
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function runBot(Bot $bot)
    {
        if (Bot::TYPE['URL'] == $bot->type) {
            $this->runUrlBot($bot);
        }
    }

    /**
     * @param Bot $bot
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    private function runUrlBot(Bot $bot)
    {
        $urlMonitor = app(UrlMonitor::class);
        $urlMonitor->url = $bot->config->url;
        $urlMonitor->host = $bot->config->host;
        $urlMonitor->timeout = $bot->config->timeout;
        $urlMonitor->headers = $bot->config->headers ?? null;
        $urlMonitor->assertIsJson = $bot->config->assert->is_json;
        $urlMonitor->alertModified = $bot->config->alert_modified ?? false;
        $urlMonitor->assertStatusCode = $bot->config->assert->http_status_code;
        try {
            $this->test($urlMonitor, 2);
            if (Cache::get("bot:{$bot->id}:has-notified")) { // notify recover message
                Cache::delete("bot:{$bot->id}:has-notified");
                $feishu = app(Feishu::class);
                foreach ($bot->subscribes as $subscribe) {
                    $feishu->textMessageToUser($subscribe->to, $bot->name . " 恢复正常");
                }
            }
        } catch (Exception $exception) {
            if (!Cache::get("bot:{$bot->id}:has-notified")) {
                if (!($exception instanceof ContentModifiedException)) {
                    Cache::put("bot:{$bot->id}:has-notified", true, 86400);
                }
                $feishu = app(Feishu::class);
                foreach ($bot->subscribes as $subscribe) {
                    $feishu->textMessageToUser($subscribe->to, $bot->name . " 异常：\n" . $exception->getMessage() . "\n{$bot->config->url}");
                }
            }
        }
    }

    /**
     * @param UrlMonitor $urlMonitor
     * @param int $times
     * @param Exception|null $exception
     * @throws ContentModifiedException
     * @throws GuzzleException
     * @throws Exception
     */
    private function test(UrlMonitor $urlMonitor, int $times, Exception $exception = null)
    {
        if ($times <= 0) {
            throw $exception;
        }
        try {
            $urlMonitor->test();
        } catch (Exception $exception) {
            if ($exception instanceof ContentModifiedException) {
                throw $exception;
            }
            $this->test($urlMonitor, --$times, $exception);
        }
    }
}
