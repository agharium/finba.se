@php
    $context = $feedback->context ?? [];
    $build = \App\Support\ApplicationBuild::toArray();
    $appVersion = $context['app_version'] ?? $build['app_version'];
    $appBuild = $context['app_build'] ?? $build['app_build'];
    $gitSha = $context['git_sha'] ?? $build['git_sha'];
    $gitDisplay = filled($gitSha) ? substr((string) $gitSha, 0, 7) : null;
@endphp
<x-mail::message>
# Novo feedback no Finba.se

**Protocolo:** {{ $feedback->protocol }}

**Tipo:** {{ $feedback->type->getLabel() }}

**Assunto:** {{ $feedback->subject }}

**Status:** {{ $feedback->status->getLabel() }}

**Enviado em:** {{ $feedback->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}

@if ($user)
**Usuário:** {{ $user->name }} ({{ $user->email }}) — ID `{{ $user->id }}`
@else
**Usuário:** visitante não autenticado
@endif

## Mensagem

{{ $feedback->message }}

@if (filled($feedback->attempted_action))
## O que a pessoa estava tentando fazer

{{ $feedback->attempted_action }}
@endif

## Anexo

@if ($feedback->hasAttachment())
Há um anexo de imagem. Ele segue anexado a este e-mail quando disponível (`{{ $feedback->attachment_path }}`).
@else
Nenhum anexo.
@endif

## Application

**Version:**  
{{ filled($appVersion) ? $appVersion : '—' }}

**Build:**  
{{ filled($appBuild) ? $appBuild : '—' }}

**Git:**  
{{ filled($gitDisplay) ? $gitDisplay : '—' }}

@if (! empty($context))
## Contexto técnico

@foreach ($context as $key => $value)
@continue(in_array($key, ['app_version', 'app_build', 'git_sha'], true))
- **{{ $key }}:** `@json($value)`
@endforeach
@endif

</x-mail::message>
