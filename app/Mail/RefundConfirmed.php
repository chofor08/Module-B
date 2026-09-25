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

class RefundConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Orders $order,
        public string $currency,
        public float $refundAmount,
        public ?string $reason = null,
    ){}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Refund Processed - Order #' . $this->order->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $user = $this->order->user;

        return new Content(
            view: 'emails.refund-confirmed',
            with: [
                'userName'      => $user->name,
                'refundAmount'  => number_format($this->refundAmount, 2),
                'currency'      => strtoupper($this->currency),
                'orderId'       => $this->order->id,
                'refundId'      => $this->order->refund_id,
                'date'          => now()->format('F j, Y, g:i a'),
                'reason'        => $this->reason,
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
