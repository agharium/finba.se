@php
    /** @var \App\Models\Transaction $record */
    $record ??= $getRecord();
    $card = \App\Filament\Resources\Transactions\TransactionResource::mobileCardData($record);
    $category = $record->category;
    $parentCategory = $category?->parent;
    $categoryName = $parentCategory?->name ?? $category?->name;
    $subcategoryName = $parentCategory ? $category?->name : null;
    $personName = $record->person?->name;
    $cityName = $record->city?->name;
    $metadataRows = collect([
        filled($categoryName) ? ['label' => 'Categoria:', 'value' => $categoryName] : null,
        filled($subcategoryName) ? ['label' => 'Subcategoria:', 'value' => $subcategoryName] : null,
        filled($personName) ? ['label' => 'Pessoa:', 'value' => $personName] : null,
        filled($cityName) ? ['label' => 'Cidade:', 'value' => $cityName] : null,
    ])->filter()->values();
    $lastMetadataRow = $metadataRows->pop();
    $hasMetadata = filled($personName)
        || filled($cityName)
        || filled($categoryName)
        || filled($subcategoryName)
        || filled($card['date']);
@endphp

<div class="finba-transaction-card">
    @if (filled($card['description']))
        <div class="finba-transaction-card__header">
            <div>
                <div class="finba-transaction-card__title">{{ $card['description'] }}</div>
            </div>
        </div>
    @endif

    <div class="finba-transaction-card__amount">{{ $card['amount'] }}</div>

    @if ($hasMetadata)
        <div class="finba-transaction-card__details">
            @foreach ($metadataRows as $metadataRow)
                <div class="finba-transaction-card__detail-row">
                    <span>{{ $metadataRow['label'] }}</span>
                    <strong>{{ $metadataRow['value'] }}</strong>
                </div>
            @endforeach

            <div class="finba-transaction-card__footer-row">
                <div class="finba-transaction-card__detail-row">
                    @if ($lastMetadataRow)
                        <span>{{ $lastMetadataRow['label'] }}</span>
                        <strong>{{ $lastMetadataRow['value'] }}</strong>
                    @endif
                </div>

                @if (filled($card['date']))
                    <span class="finba-transaction-card__date">{{ $card['date'] }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
