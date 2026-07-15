<?php

namespace App\Services;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Mail\FeedbackSubmittedMail;
use App\Models\Feedback;
use App\Models\User;
use App\Support\ApplicationBuild;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FeedbackService
{
    public function __construct(
        private readonly FileStorageService $files,
    ) {}

    /**
     * @param  array{
     *     type: string|FeedbackType,
     *     subject: string,
     *     message: string,
     *     attempted_action?: string|null,
     *     include_technical_context?: bool,
     *     client_context?: array<string, mixed>|null,
     * }  $data
     * @return array{feedback: Feedback, mail_sent: bool, mail_skipped: bool, mail_failed: bool}
     */
    public function submit(User $user, array $data, ?UploadedFile $attachment = null): array
    {
        $this->ensureWithinRateLimit($user);

        $includeContext = (bool) ($data['include_technical_context'] ?? true);

        $type = $data['type'] instanceof FeedbackType
            ? $data['type']
            : FeedbackType::from((string) $data['type']);

        $feedback = Feedback::query()->create([
            'user_id' => $user->id,
            'protocol' => $this->generateProtocol(),
            'type' => $type,
            'status' => FeedbackStatus::OPEN,
            'subject' => trim((string) $data['subject']),
            'message' => trim((string) $data['message']),
            'attempted_action' => filled($data['attempted_action'] ?? null)
                ? trim((string) $data['attempted_action'])
                : null,
            'context' => $includeContext
                ? $this->buildSafeContext($user, $data['client_context'] ?? [])
                : $this->buildMinimalContext($user),
        ]);

        $attachmentPath = null;

        try {
            if ($attachment !== null) {
                $attachmentPath = $this->storeAttachment($feedback, $attachment);
                $feedback->update([
                    'attachment_path' => $attachmentPath,
                ]);
            }
        } catch (Throwable $exception) {
            if ($attachmentPath !== null) {
                $this->files->delete($attachmentPath);
            }

            throw $exception;
        }

        $mailResult = $this->notifyTeam($feedback->fresh(['user']));

        return [
            'feedback' => $feedback->fresh(['user']),
            'mail_sent' => $mailResult['sent'],
            'mail_skipped' => $mailResult['skipped'],
            'mail_failed' => $mailResult['failed'],
        ];
    }

    public function generateProtocol(): string
    {
        do {
            $protocol = sprintf('FDB-%s-%s', now()->format('Y'), strtoupper(Str::random(8)));
        } while (Feedback::query()->where('protocol', $protocol)->exists());

        return $protocol;
    }

    /**
     * @param  array<string, mixed>  $clientContext
     * @return array<string, mixed>
     */
    public function buildSafeContext(User $user, array $clientContext = []): array
    {
        $request = request();

        $safeClient = collect($clientContext)
            ->only([
                'screen_width',
                'screen_height',
                'viewport_width',
                'viewport_height',
                'timezone',
                'platform',
            ])
            ->map(function (mixed $value): mixed {
                if (is_numeric($value)) {
                    return (int) $value;
                }

                if (is_string($value)) {
                    return Str::limit(strip_tags($value), 120, '');
                }

                return null;
            })
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();

        $context = array_filter([
            'url' => $request?->headers->get('referer') ?: $request?->fullUrl(),
            'path' => $request?->path(),
            'route' => optional($request?->route())->getName(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
            'locale' => app(LocationDefaultsService::class)->getLocale($user),
            'timezone' => $safeClient['timezone'] ?? null,
            'platform' => $safeClient['platform'] ?? null,
            'screen' => $this->dimensionPair($safeClient, 'screen_width', 'screen_height'),
            'viewport' => $this->dimensionPair($safeClient, 'viewport_width', 'viewport_height'),
            'user_id' => $user->id,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(['local', 'testing', 'staging'])
                ? app()->environment()
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            ...$context,
            ...ApplicationBuild::toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMinimalContext(User $user): array
    {
        return [
            'user_id' => $user->id,
            'timestamp' => now()->toIso8601String(),
            ...ApplicationBuild::toArray(),
        ];
    }

    public function storeAttachment(Feedback $feedback, UploadedFile $attachment): string
    {
        $directory = trim((string) config('finba.feedback.attachment_directory', 'feedback'), '/');
        $directory .= '/'.$feedback->id;

        return $this->files->storePrivateUpload($attachment, $directory);
    }

    public function deleteAttachment(Feedback $feedback): void
    {
        if (! $feedback->hasAttachment()) {
            return;
        }

        $this->files->delete($feedback->attachment_path);
    }

    /**
     * @return array{sent: bool, skipped: bool, failed: bool}
     */
    public function notifyTeam(Feedback $feedback): array
    {
        $recipient = config('finba.feedback.email');

        if (blank($recipient)) {
            Log::warning('Feedback saved but FINBA_FEEDBACK_EMAIL is not configured.', [
                'feedback_id' => $feedback->id,
                'protocol' => $feedback->protocol,
            ]);

            return ['sent' => false, 'skipped' => true, 'failed' => false];
        }

        try {
            Mail::to($recipient)->send(new FeedbackSubmittedMail($feedback));

            return ['sent' => true, 'skipped' => false, 'failed' => false];
        } catch (Throwable $exception) {
            Log::error('Failed to send feedback notification email.', [
                'feedback_id' => $feedback->id,
                'protocol' => $feedback->protocol,
                'exception' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'skipped' => false, 'failed' => true];
        }
    }

    private function ensureWithinRateLimit(User $user): void
    {
        $key = 'feedback-submit:'.$user->id;
        $maxAttempts = max(1, (int) config('finba.feedback.rate_limit_per_hour', 8));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'data.type' => 'Você enviou feedback demais recentemente. Tente novamente em alguns minutos.',
            ]);
        }

        RateLimiter::hit($key, 60 * 60);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{width: int, height: int}|null
     */
    private function dimensionPair(array $values, string $widthKey, string $heightKey): ?array
    {
        if (! isset($values[$widthKey], $values[$heightKey])) {
            return null;
        }

        return [
            'width' => (int) $values[$widthKey],
            'height' => (int) $values[$heightKey],
        ];
    }
}
