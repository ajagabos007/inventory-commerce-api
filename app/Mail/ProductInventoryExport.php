<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductInventoryExport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $filePath,
        public string $filename,
        public int $totalProducts,
        public int $totalRecords,
        public array $stores,
        public bool $isZip = false
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Product Inventory Export - '.now()->format('Y-m-d H:i'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.product-inventory-export',
            with: [
                'totalProducts' => $this->totalProducts,
                'totalRecords' => $this->totalRecords,
                'stores' => $this->stores,
                'filename' => $this->filename,
                'isZip' => $this->isZip,
                'exportDate' => now()->format('F j, Y \a\t g:i A'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as($this->filename)
                ->withMime($this->isZip ? 'application/zip' : 'text/csv'),
        ];
    }
}
