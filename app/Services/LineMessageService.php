<?php

namespace App\Services;

use App\Services\Line\LineSender;

class LineMessageService
{
    /** @var LineSender */
    private $sender;

    public function __construct(LineSender $sender)
    {
        $this->sender = $sender;
    }

    /**
     * 指定したユーザーにプッシュメッセージを送信する
     *
     * 実際の送信経路は config('services.line.driver') で決まる。
     *
     * @param string $userId
     * @param string $message
     * @return array
     */
    public function sendMessage($userId, $message)
    {
        return $this->sender->sendText($userId, $message);
    }
}
