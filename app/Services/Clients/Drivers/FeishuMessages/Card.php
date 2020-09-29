<?php


namespace App\Services\Clients\Drivers\FeishuMessages;


class Card
{
    public array $config = [
        'wide_screen_mode' => false,
        'enable_forward' => false
    ];

    public array $header = [
        'title' => 'plain_text',
        'template' => ''
    ];

    public array $elements = [];

    public function appendElements()
    {
        //
    }

    public function appendContentElement(string $content)
    {

    }
}