@php
    /** @var \App\Models\Transaction $record */
    $record ??= $getRecord();
    $card = \App\Filament\Resources\Transactions\TransactionResource::mobileCardData($record);
    $category = $record->category;
    $parentCategory = $category?->parent;
    $categoryName = $parentCategory?->name ?? $category?->name ?? '-';
    $subcategoryName = $parentCategory ? $category?->name : null;
    $personName = $record->person?->name ?? '-';
@endphp

<div class="finba-transaction-card">
    <div class="finba-transaction-card__header">
        <div>
            <div class="finba-transaction-card__title">{{ $card['description'] }}</div>
        </div>
    </div>

    <div class="finba-transaction-card__amount">{{ $card['amount'] }}</div>

    <div class="finba-transaction-card__details">
        <div class="finba-transaction-card__detail-row">
            <span>Pessoa:</span>
            <strong>{{ $personName }}</strong>
        </div>

        @if (filled($subcategoryName))
            <div class="finba-transaction-card__detail-row">
                <span>Categoria:</span>
                <strong>{{ $categoryName }}</strong>
            </div>

            <div class="finba-transaction-card__footer-row">
                <div class="finba-transaction-card__detail-row">
                    <span>Subcategoria:</span>
                    <strong>{{ $subcategoryName }}</strong>
                </div>

                <span class="finba-transaction-card__date">{{ $card['date'] }}</span>
            </div>
        @else
            <div class="finba-transaction-card__footer-row">
                <div class="finba-transaction-card__detail-row">
                    <span>Categoria:</span>
                    <strong>{{ $categoryName }}</strong>
                </div>

                <span class="finba-transaction-card__date">{{ $card['date'] }}</span>
            </div>
        @endif
    </div>
</div>
