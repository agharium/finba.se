<?php

use App\Enums\FeedbackType;
use App\Mail\FeedbackSubmittedMail;
use App\Models\Feedback;
use App\Models\User;
use App\Services\FeedbackService;
use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function storageFeedbackUser(): User
{
    return User::query()->create([
        'name' => 'Storage User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'default_country_code' => 'BR',
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('configures a dedicated private finba s3 disk', function () {
    expect(config('filesystems.disks.finba'))->toMatchArray([
        'driver' => 's3',
        'use_path_style_endpoint' => true,
        'visibility' => 'private',
        'throw' => true,
    ])->and(config('filesystems.disks.finba'))->toHaveKeys([
        'key',
        'secret',
        'region',
        'bucket',
        'endpoint',
    ]);
});

it('stores feedback attachments under feedback/{uuid}/generated-filename', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => null,
    ]);

    $feedback = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::BUG->value,
        'subject' => 'Path convention',
        'message' => 'Check nested path.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('User Screenshot.PNG', 640, 480))['feedback'];

    $path = (string) $feedback->attachment_path;

    expect($path)->toMatch('/^feedback\/'.$feedback->id.'\/[a-z0-9\-]+\.png$/')
        ->and($path)->not->toContain('User Screenshot')
        ->and($path)->not->toContain('\\')
        ->and($path)->not->toStartWith('/')
        ->and(realpath($path) ?: $path)->not->toContain(storage_path())
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('persists only the object path in the database', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => null,
    ]);

    $feedback = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Path only',
        'message' => 'No URLs.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('bug.webp'))['feedback'];

    $stored = Feedback::query()->findOrFail($feedback->id)->attachment_path;

    expect($stored)->toBe($feedback->attachment_path)
        ->and($stored)->not->toContain('http://')
        ->and($stored)->not->toContain('https://')
        ->and($stored)->not->toContain('storage/app')
        ->and($stored)->not->toContain(storage_path('app'));
});

it('builds email attachments from a non-local fake disk', function () {
    Storage::fake('finba');
    Mail::fake();
    config([
        'finba.storage.disk' => 'finba',
        'finba.feedback.email' => 'team@example.com',
    ]);

    $feedback = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::BUG->value,
        'subject' => 'Remote disk mail',
        'message' => 'Attach from finba disk.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('mail.png'))['feedback'];

    expect(Storage::disk('finba')->exists($feedback->attachment_path))->toBeTrue();

    Mail::assertSent(FeedbackSubmittedMail::class, function (FeedbackSubmittedMail $mail) use ($feedback): bool {
        return count($mail->attachments()) === 1
            && str_contains((string) $feedback->attachment_path, 'feedback/'.$feedback->id.'/');
    });
});

it('keeps feedback and attachment when email delivery fails', function () {
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => 'team@example.com',
    ]);

    $pending = Mockery::mock();
    $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
    Mail::shouldReceive('to')->once()->with('team@example.com')->andReturn($pending);

    $result = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::BUG->value,
        'subject' => 'Mail fail',
        'message' => 'Keep attachment.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('keep.png'));

    $feedback = $result['feedback'];

    expect($result['mail_failed'])->toBeTrue()
        ->and($feedback->attachment_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($feedback->attachment_path))->toBeTrue()
        ->and(Feedback::query()->whereKey($feedback->id)->exists())->toBeTrue();
});

it('removes orphaned attachment when persisting the path fails', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => null,
    ]);

    Feedback::saving(function (Feedback $model): void {
        if ($model->exists && $model->isDirty('attachment_path') && filled($model->attachment_path)) {
            throw new RuntimeException('persist path failed');
        }
    });

    try {
        expect(fn () => app(FeedbackService::class)->submit(storageFeedbackUser(), [
            'type' => FeedbackType::BUG->value,
            'subject' => 'Orphan cleanup',
            'message' => 'Delete uploaded object.',
            'include_technical_context' => false,
        ], UploadedFile::fake()->image('orphan.png')))->toThrow(RuntimeException::class);

        expect(Storage::disk('local')->allFiles('feedback'))->toBeEmpty();
    } finally {
        Feedback::flushEventListeners();
        Feedback::clearBootedModels();
    }
});

it('soft deletion preserves the attachment object', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => null,
    ]);

    $feedback = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Soft delete',
        'message' => 'Keep file.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('soft.png'))['feedback'];

    $path = (string) $feedback->attachment_path;
    $feedback->delete();

    expect(Feedback::withTrashed()->find($feedback->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('permanent deletion removes the attachment object', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.storage.disk' => 'local',
        'finba.feedback.email' => null,
    ]);

    $feedback = app(FeedbackService::class)->submit(storageFeedbackUser(), [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Force delete',
        'message' => 'Remove file.',
        'include_technical_context' => false,
    ], UploadedFile::fake()->image('force.png'))['feedback'];

    $path = (string) $feedback->attachment_path;
    $feedback->forceDelete();

    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(Feedback::withTrashed()->whereKey($feedback->id)->exists())->toBeFalse();
});

it('runs storage-check write read and delete probe', function () {
    Storage::fake('local');
    config(['finba.storage.disk' => 'local']);

    $this->artisan('finba:storage-check')
        ->assertSuccessful()
        ->expectsOutputToContain('Storage check passed.');

    expect(Storage::disk('local')->allFiles('health'))->toBeEmpty();
});

it('does not expose supabase storage credentials to frontend assets', function () {
    $frontendRoots = [
        resource_path('js'),
        resource_path('css'),
        resource_path('views'),
    ];

    foreach ($frontendRoots as $root) {
        if (! File::isDirectory($root)) {
            continue;
        }

        foreach (File::allFiles($root) as $file) {
            $contents = File::get($file->getPathname());

            expect($contents)
                ->not->toContain('SUPABASE_STORAGE_ACCESS_KEY_ID')
                ->not->toContain('SUPABASE_STORAGE_SECRET_ACCESS_KEY')
                ->not->toContain('VITE_SUPABASE_STORAGE');
        }
    }

    expect(config('finba.storage.disk'))->not->toBeNull();
    expect(app(FileStorageService::class)->diskName())->toBeString();
});
