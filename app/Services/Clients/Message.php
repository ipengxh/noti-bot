<?php


namespace App\Services\Clients;


interface Message
{
    public function send(string $toUser, string $content, string $description);
}