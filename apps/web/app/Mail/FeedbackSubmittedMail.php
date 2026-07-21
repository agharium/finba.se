<?php

namespace App\Mail;

use App\Models\Feedback;
use App\Services\FileStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Feedback $feedback) {}

    public function envelope(): Envelope
    {
        $type = $this->feedback->type->getLabel();

        return new Envelope(
            subject: "[Finba.se] Novo feedback: {$type} — {$this->feedback->subject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.feedback-submitted',
            with: [
                'feedback' => $this->feedback,
                'user' => $this->feedback->user,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->feedback->hasAttachment()) {
            return [];
        }

        $storage = app(FileStorageService::class);
        $path = (string) $this->feedback->attachment_path;

        if (! $storage->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($storage->diskName(), $path)
                ->as(basename($path))
                ->withMime($storage->mimeType($path) ?: 'application/octet-stream'),
        ];
    }
}
