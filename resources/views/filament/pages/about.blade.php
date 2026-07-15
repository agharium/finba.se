@php
    use App\Support\ApplicationBuild;

    $creator = $this->getCreator();
@endphp

<x-filament-panels::page>
    <div class="finba-about mx-auto w-full max-w-3xl">
        <section class="finba-about__card">
            <h2 class="finba-about__title">O que é o Finba.se</h2>
            <p class="finba-about__text">
                O Finba.se é uma plataforma flexível de finanças pessoais para quem precisa de mais liberdade do que os aplicativos tradicionais costumam oferecer.
            </p>
            <p class="finba-about__text">
                Ele apoia o dia a dia financeiro com receitas e despesas, categorias e subcategorias, pessoas, contas a receber e parcelamentos - tudo com uma organização adaptável ao seu jeito de trabalhar.
            </p>
        </section>

        <section class="finba-about__card">
            <h2 class="finba-about__title">Estado atual</h2>
            <p class="finba-about__badge">{{ ApplicationBuild::stage() }}</p>
            <p class="finba-about__version">{{ ApplicationBuild::displayVersion() }}</p>
            <p class="finba-about__text">O Finba encontra-se em fase beta.</p>
            <p class="finba-about__text">
                Esta primeira versão pública já está disponível e continuará recebendo melhorias, refinamentos de experiência e novas funcionalidades até a primeira versão estável.
            </p>
            <div class="finba-about__links">
                <a href="{{ $this->getChangelogUrl() }}" class="finba-about__link">Changelog</a>
                <a href="{{ $this->getRoadmapUrl() }}" class="finba-about__link">Roadmap</a>
            </div>
        </section>

        <section class="finba-about__card">
            <h2 class="finba-about__title">Criador</h2>
            <p class="finba-about__creator">
                {{ $creator['name'] }}
            </p>
            <p class="finba-about__text">Software Engineer e criador do Finba.se.</p>

            @if ($creator['linkedin_url'] || $creator['github_url'] || $creator['url'])
                <div class="finba-about__links">
                    @if ($creator['linkedin_url'])
                        <a
                            href="{{ $creator['linkedin_url'] }}"
                            class="finba-about__link finba-about__link--blue"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            LinkedIn
                        </a>
                    @endif
                    @if ($creator['github_url'])
                        <a href="{{ $creator['github_url'] }}" class="finba-about__link" target="_blank" rel="noopener noreferrer">
                            GitHub
                        </a>
                    @endif
                    @if ($creator['url'])
                        <a href="{{ $creator['url'] }}" class="finba-about__link" target="_blank" rel="noopener noreferrer">
                            Portfólio
                        </a>
                    @endif
                </div>
            @endif
        </section>

        <section class="finba-about__card">
            <h2 class="finba-about__title">Open source</h2>
            <p class="finba-about__text">
                O Finba.se é distribuído sob a licença GNU AGPL v3: você pode inspecionar, modificar e hospedar a própria instância.
                Quando o software for disponibilizado em rede, a mesma liberdade precisa permanecer acessível a quem o utiliza.
            </p>
            @if ($creator['github_url'])
                <div class="finba-about__links">
                    <a href="{{ $creator['github_url'] }}" class="finba-about__link" target="_blank" rel="noopener noreferrer">
                        Repositório e licença
                    </a>
                </div>
            @endif
        </section>

        <section class="finba-about__card">
            <h2 class="finba-about__title">Build in public</h2>
            <p class="finba-about__text">
                A evolução do produto e as decisões principais ficam registradas publicamente no Changelog e no Roadmap.
            </p>
        </section>
    </div>
</x-filament-panels::page>
