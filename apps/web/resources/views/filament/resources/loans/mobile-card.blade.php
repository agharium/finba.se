@php
    /** @var \App\Models\Loan $record */
    $record ??= $getRecord();
    $card = \App\Filament\Resources\Loans\LoanResource::mobileCardData($record);
@endphp

<div class="finba-receivable-card">
    <div class="finba-receivable-card__header">
        <div class="finba-receivable-card__title-row">
            <div class="finba-receivable-card__title">{{ $card['description'] }}</div>
            <span @class([
                'finba-receivable-card__badge',
                'finba-receivable-card__badge--open' => $card['is_open'],
                'finba-receivable-card__badge--closed' => ! $card['is_open'],
            ])>
                {{ $card['status_label'] }}
            </span>
        </div>

        <div class="finba-receivable-card__meta">
            @if (filled($card['customer']))
                <span>{{ $card['customer'] }}</span>
            @endif
            @if (filled($card['created_at']))
                <span>{{ $card['created_at'] }}</span>
            @endif
        </div>
    </div>

    <div class="finba-receivable-card__amounts">
        <div class="finba-receivable-card__amount-row">
            <span>Original</span>
            <strong>{{ $card['original'] }}</strong>
        </div>
        <div class="finba-receivable-card__amount-row">
            <span>Recebido</span>
            <strong>{{ $card['received'] }}</strong>
        </div>
        <div class="finba-receivable-card__amount-row finba-receivable-card__amount-row--remaining">
            <span>Falta receber</span>
            <strong>{{ $card['remaining'] }}</strong>
        </div>
    </div>
</div>
