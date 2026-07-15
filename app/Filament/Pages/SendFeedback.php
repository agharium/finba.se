<?php

namespace App\Filament\Pages;

use App\Enums\FeedbackType;
use App\Services\FeedbackService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class SendFeedback extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $title = 'Feedback';

    protected static ?string $slug = 'feedback';

    protected static ?int $navigationSort = 1030;

    protected string $view = 'filament.pages.send-feedback';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => FeedbackType::BUG->value,
            'include_technical_context' => true,
            'client_context' => [],
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Relate problemas, sugestões ou ideias para melhorar o Finba.se.';
    }

    public function form(Schema $schema): Schema
    {
        $maxKilobytes = max(100, (int) config('finba.feedback.max_attachment_kilobytes', 2048));

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Seu relato')
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->options(collect(FeedbackType::cases())
                                ->mapWithKeys(fn (FeedbackType $type): array => [$type->value => $type->getLabel()])
                                ->all())
                            ->required()
                            ->native(false),

                        TextInput::make('subject')
                            ->label('Assunto')
                            ->required()
                            ->maxLength(120)
                            ->autocomplete(false),

                        Textarea::make('message')
                            ->label('Descrição')
                            ->required()
                            ->rows(5)
                            ->maxLength(5000)
                            ->helperText('Conte o que aconteceu, o que você esperava ou como acredita que o Finba.se poderia melhorar.'),

                        Textarea::make('attempted_action')
                            ->label('O que você estava tentando fazer?')
                            ->rows(3)
                            ->maxLength(2000)
                            ->helperText('Especialmente útil para problemas. Opcional para os demais tipos.'),

                        FileUpload::make('attachment')
                            ->label('Anexo')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize($maxKilobytes)
                            ->storeFiles(false)
                            ->helperText('PNG, JPG ou WEBP. Máximo de '.number_format($maxKilobytes / 1024, 1).' MB.'),

                        Toggle::make('include_technical_context')
                            ->label('Incluir informações técnicas')
                            ->default(true)
                            ->helperText('Inclui dados como página atual, navegador e versão do aplicativo. Nenhum dado financeiro é enviado automaticamente.'),

                        Hidden::make('client_context'),
                    ]),
            ]);
    }

    public function submit(FeedbackService $feedbackService): void
    {
        $state = $this->form->getState();
        $attachment = $state['attachment'] ?? null;

        if (is_array($attachment)) {
            $attachment = $attachment[0] ?? null;
        }

        $uploadedFile = $attachment instanceof TemporaryUploadedFile
            ? $attachment
            : null;

        $result = $feedbackService->submit(
            auth()->user(),
            [
                'type' => $state['type'],
                'subject' => $state['subject'],
                'message' => $state['message'],
                'attempted_action' => $state['attempted_action'] ?? null,
                'include_technical_context' => (bool) ($state['include_technical_context'] ?? true),
                'client_context' => is_array($state['client_context'] ?? null)
                    ? $state['client_context']
                    : [],
            ],
            $uploadedFile,
        );

        $protocol = $result['feedback']->protocol;

        if ($result['mail_failed']) {
            Notification::make()
                ->title('Feedback salvo')
                ->body("Seu relato foi registrado com o protocolo {$protocol}, mas a notificação por e-mail falhou. A equipe ainda poderá consultá-lo.")
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Feedback enviado com sucesso. Obrigado por ajudar a melhorar o Finba.se.')
                ->body("Protocolo: {$protocol}")
                ->success()
                ->persistent()
                ->send();
        }

        $this->form->fill([
            'type' => FeedbackType::BUG->value,
            'subject' => null,
            'message' => null,
            'attempted_action' => null,
            'attachment' => null,
            'include_technical_context' => true,
            'client_context' => [],
        ]);
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-feedback-page',
        ];
    }
}
