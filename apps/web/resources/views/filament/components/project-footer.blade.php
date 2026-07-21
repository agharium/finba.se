@php
    use App\Support\ApplicationBuild;

    $creatorName = (string) config('finba.creator.name');
    $creatorUrl = config('finba.creator.url');
    $creatorUrl = is_string($creatorUrl) && filter_var($creatorUrl, FILTER_VALIDATE_URL)
        ? $creatorUrl
        : null;
@endphp

<footer class="finba-project-footer">
    <div class="finba-project-footer__text">
        <p class="finba-project-footer__line">Finba.se © {{ now()->year }}</p>
        <p class="finba-project-footer__line">
            Desenvolvido por
            @if ($creatorUrl)
                <a href="{{ $creatorUrl }}" class="finba-project-footer__link" target="_blank" rel="noopener noreferrer">
                    {{ $creatorName }}
                </a>
            @else
                <span>{{ $creatorName }}</span>
            @endif
        </p>
        <p class="finba-project-footer__line finba-project-footer__line--muted">
            {{ ApplicationBuild::stage() }} · {{ ApplicationBuild::displayVersion() }} · AGPL v3
        </p>
    </div>
</footer>
