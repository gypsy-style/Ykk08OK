<?php

namespace App\Services\Line;

interface LineSender
{
    /**
     * 指定したLINEユーザーにテキストメッセージを送信する
     *
     * @param string $lineUserId
     * @param string $message
     * @return array ['status' => 'success'|'error', 'message' => string, ...]
     */
    public function sendText($lineUserId, $message);
}
