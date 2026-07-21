<?php

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Filament\Pages\SendFeedback;
use App\Mail\FeedbackSubmittedMail;
use App\Models\Feedback;
use App\Models\User;
use App\Services\FeedbackService;
use App\Support\ApplicationBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function feedbackUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Feedback User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'locale' => 'pt_BR',
        'settings' => [],
    ], $overrides));
}

it('allows authenticated users to access feedback page', function () {
    Livewire::actingAs(feedbackUser())
        ->test(SendFeedback::class)
        ->assertSuccessful()
        ->assertSee('Feedback');
});

it('denies guests access to feedback page', function () {
    $this->get(SendFeedback::getUrl())->assertRedirect();
});

it('persists valid feedback with user protocol type and status', function () {
    Mail::fake();
    config(['finba.feedback.email' => 'team@example.com']);

    $user = feedbackUser();

    $result = app(FeedbackService::class)->submit($user, [
        'type' => FeedbackType::BUG->value,
        'subject' => 'Botão não responde',
        'message' => 'Ao tocar em salvar nada acontece.',
        'attempted_action' => 'Salvar uma despesa',
        'include_technical_context' => true,
        'client_context' => [
            'screen_width' => 390,
            'screen_height' => 844,
            'password' => 'secret-should-not-persist',
            'authorization' => 'Bearer abc',
        ],
    ]);

    $feedback = $result['feedback'];

    expect($feedback)->toBeInstanceOf(Feedback::class)
        ->and($feedback->user_id)->toBe($user->id)
        ->and($feedback->type)->toBe(FeedbackType::BUG)
        ->and($feedback->status)->toBe(FeedbackStatus::OPEN)
        ->and($feedback->protocol)->toStartWith('FDB-')
        ->and($feedback->attempted_action)->toBe('Salvar uma despesa')
        ->and($feedback->context)->toHaveKey('user_id')
        ->and($feedback->context)->toHaveKey('screen')
        ->and($feedback->context)->toHaveKeys(['app_version', 'app_build', 'git_sha'])
        ->and($feedback->context)->not->toHaveKey('password')
        ->and($feedback->context)->not->toHaveKey('authorization')
        ->and(json_encode($feedback->context))->not->toContain('secret-should-not-persist');

    Mail::assertSent(FeedbackSubmittedMail::class);
});

it('attaches build metadata from ApplicationBuild', function () {
    Mail::fake();
    config([
        'finba.feedback.email' => 'team@example.com',
        'finba.app_version' => '0.1.0-alpha',
        'finba.app_build' => '2026.07.14.2',
        'finba.git_sha' => '84ac12f',
    ]);

    $feedback = app(FeedbackService::class)->submit(feedbackUser(), [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Meta',
        'message' => 'Contexto de build.',
        'include_technical_context' => false,
    ])['feedback'];

    expect($feedback->context)->toMatchArray(ApplicationBuild::toArray())
        ->and($feedback->context['app_version'])->toBe('0.1.0-alpha')
        ->and($feedback->context['app_build'])->toBe('2026.07.14.2')
        ->and($feedback->context['git_sha'])->toBe('84ac12f');

    Mail::assertSent(FeedbackSubmittedMail::class, function (FeedbackSubmittedMail $mail): bool {
        $html = $mail->render();

        return str_contains($html, '0.1.0-alpha')
            && str_contains($html, '2026.07.14.2')
            && str_contains($html, '84ac12f')
            && str_contains($html, 'Application');
    });
});

it('stores optional image attachments privately', function () {
    Mail::fake();
    Storage::fake('local');
    config([
        'finba.feedback.email' => null,
        'finba.storage.disk' => 'local',
    ]);

    $user = feedbackUser();
    $file = UploadedFile::fake()->image('bug.png', 800, 600);

    $feedback = app(FeedbackService::class)->submit($user, [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Captura',
        'message' => 'Segue print.',
        'include_technical_context' => false,
    ], $file)['feedback'];

    expect($feedback->attachment_path)->not->toBeNull()
        ->and($feedback->attachment_path)->toMatch('/^feedback\/'.$feedback->id.'\/[a-z0-9\-]+\.png$/')
        ->and(Storage::disk('local')->exists($feedback->attachment_path))->toBeTrue();
});

it('saves feedback when email is not configured', function () {
    Mail::fake();
    Log::spy();
    config(['finba.feedback.email' => null]);

    $user = feedbackUser();

    $result = app(FeedbackService::class)->submit($user, [
        'type' => FeedbackType::SUGGESTION->value,
        'subject' => 'Filtro por pessoa',
        'message' => 'Seria útil no dashboard.',
        'include_technical_context' => false,
    ]);

    expect(Feedback::query()->count())->toBe(1)
        ->and($result['mail_skipped'])->toBeTrue()
        ->and($result['mail_sent'])->toBeFalse();

    Mail::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});

it('keeps feedback when email delivery fails', function () {
    config(['finba.feedback.email' => 'team@example.com']);

    $pending = Mockery::mock();
    $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

    Mail::shouldReceive('to')->once()->with('team@example.com')->andReturn($pending);
    Log::spy();

    $user = feedbackUser();

    $result = app(FeedbackService::class)->submit($user, [
        'type' => FeedbackType::BUG->value,
        'subject' => 'Erro ao filtrar',
        'message' => 'Lista não atualiza.',
        'include_technical_context' => false,
    ]);

    expect(Feedback::query()->count())->toBe(1)
        ->and($result['mail_failed'])->toBeTrue()
        ->and($result['feedback']->exists)->toBeTrue();

    Log::shouldHaveReceived('error')->once();
});

it('rate limits feedback submissions per user', function () {
    Mail::fake();
    config([
        'finba.feedback.email' => null,
        'finba.feedback.rate_limit_per_hour' => 2,
    ]);

    $user = feedbackUser();
    $service = app(FeedbackService::class);
    RateLimiter::clear('feedback-submit:'.$user->id);

    $service->submit($user, [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Um',
        'message' => 'Primeiro',
        'include_technical_context' => false,
    ]);

    $service->submit($user, [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Dois',
        'message' => 'Segundo',
        'include_technical_context' => false,
    ]);

    expect(fn () => $service->submit($user, [
        'type' => FeedbackType::OTHER->value,
        'subject' => 'Três',
        'message' => 'Terceiro',
        'include_technical_context' => false,
    ]))->toThrow(ValidationException::class)
        ->and(Feedback::query()->count())->toBe(2);
});

it('does not expose a feedback listing route for users', function () {
    $this->actingAs(feedbackUser())
        ->get('/admin/feedback')
        ->assertNotFound();
});

it('submits feedback through the filament page', function () {
    Mail::fake();
    config(['finba.feedback.email' => 'team@example.com']);

    Livewire::actingAs(feedbackUser())
        ->test(SendFeedback::class)
        ->fillForm([
            'type' => FeedbackType::SUGGESTION->value,
            'subject' => 'Melhorar filtros',
            'message' => 'Gostaria de filtrar por cidade no dashboard.',
            'include_technical_context' => true,
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Feedback::query()->count())->toBe(1);
    Mail::assertSent(FeedbackSubmittedMail::class);
});
