<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class OrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $agency;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->agency = $order->agency;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('新しい注文が登録されました')
                    ->view('emails.order-notification')
                    ->with([
                        'order' => $this->order,
                        'agency' => $this->agency,
                    ]);
    }
} 