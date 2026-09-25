<?php

namespace App\Mail;

use App\Models\Orders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use SebastianBergmann\CodeCoverage\Report\Xml\Report;

class PaymentConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Orders $order,
        public string $currency,
        public string $paymentMethodLabel,
    ){}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - Order #' . $this->order->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $user = $this->order->user;

        return new Content(
            view: 'emails.payment-confirmed',
            with: [
                'userName' => $user->name,
                'amount'   => number_format($this->order->total_price, 2),
                'currency' => strtoupper($this->currency),
                'orderId'  => $this->order->id,
                'txnId'    => $this->order->payment_intent_id,
                'date'     => $this->order->updated_at->format('F j, Y, g:i a'),
                'method'   => $this->paymentMethodLabel,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
