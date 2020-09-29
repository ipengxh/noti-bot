<?php


namespace App\Services\Clients\Drivers\FeishuMessages\Elements;


class Text
{
    public string $tag = 'plain_text';

    public string $content = '';

    public int $lines = 1;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'content' => $this->content,
            'lines' => $this->lines
        ];
    }
}