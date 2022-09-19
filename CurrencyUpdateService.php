<?php

namespace App\Services;

use App\Enums\DateFormatEnum;
use App\Models\Currency;
use App\Models\CurrencyCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CurrencyUpdateService
{
    protected CurrencyCbrClient $client;

    /**
     * @param CurrencyCbrClient $client
     */
    public function __construct(CurrencyCbrClient $client)
    {
        $this->client = $client;
    }

    /**
     * Обновляет записи о курсах валю на указанную дату.
     *
     * @param Carbon $date
     *
     */
    public function update(Carbon $date): void
    {
        Log::debug('Receive new date for currency update', ['date' => $date->toString()]);

        $collection = $this->client->getList($date);

        Log::debug('Collection of rates', $collection->toArray());

        $activeCodes = CurrencyCode::query()->active()->get('char_code')
            ->map(fn($item) => $item->char_code)
            ->flip();

        $filtered = $collection->only(['Valute'])
            ->flatten(1)
            ->filter(fn($item) => $activeCodes->has($item->CharCode ?? -1));

        Log::debug('Filtered rates', $filtered->toArray());

        if ($filtered->isEmpty()) {
            return;
        }

        $this->createBase($date);

        $filtered->each(fn($item) => $this->createCurrency($item, $date));
    }

    /**
     * Создает или обновляет данные о курсе обмена валюты
     *
     * @param object $valute
     * @param Carbon $date
     *
     */
    protected function createCurrency(object $valute, Carbon $date): void
    {
        /** @var Currency $rate */
        $rate = Currency::updateOrCreate([
            'date' => $date->format(DateFormatEnum::DB->value),
            'char_code' => $valute->CharCode
        ], [
            'value' => (float)str_replace(',', '.', $valute->Value),
            'nominal' => (int)$valute->Nominal,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Создает запись о курсе рубля на указанную дату.
     *
     * @param Carbon $date
     */
    protected function createBase(Carbon $date): void
    {
        /** @var Currency $rate */
        $rate = Currency::updateOrCreate([
            'date' => $date->format(DateFormatEnum::DB->value),
            'char_code' => CurrencyCode::DEFAULT_BASE
        ], [
            'value' => 1,
            'nominal' => 1,
            'updated_at' => Carbon::now(),
        ]);
    }
}
