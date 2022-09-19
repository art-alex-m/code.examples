<?php

namespace App\Services;


use App\Http\Dto\CurrencyDto;
use App\Interfaces\CurrencyListParamsContract;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    /**
     * Возвращает список курсов обмена валют.
     *
     * @param CurrencyListParamsContract $params
     *
     * @return array|CurrencyDto[]
     * @throws \Spatie\DataTransferObject\Exceptions\UnknownProperties
     */
    public function getCurrencyList(CurrencyListParamsContract $params): array
    {
        $date = $params->getDate();
        $base = $params->getBase();
        $indexKey = 'char_code';

        /** @var Collection $currentRates */
        $currentRates = Currency::query()->active()->date($date)->get()->keyBy($indexKey);
        /** @var Collection $prevRates */
        $prevRates = Currency::query()->active()->date($date->modify('-1 day'))->get()->keyBy($indexKey);
        $baseRate = $currentRates->get($base);

        $dtoList = [];

        foreach ($currentRates as $key => $item) {
            $prevItem = $prevRates->get($key);
            $dto = new CurrencyDto([
                'charCode' => $key,
                'date' => $item->date,
                'nominal' => $item->nominal,
                'value' => $item->value,
                'diffBase' => $this->calculateDiffBase($item, $baseRate),
                'diffYesterday' => $this->calculateDiffYesterday($item, $prevItem),
            ]);

            $dtoList[] = $dto;
        }

        return $dtoList;
    }

    /**
     * Вычисляет отношение к базовому курсу.
     *
     * @param Currency|null $item
     * @param Currency|null $baseRate
     *
     * @return float|null
     */
    protected function calculateDiffBase(?Currency $item, ?Currency $baseRate): ?float
    {
        if (null === $item || null === $baseRate) {
            return null;
        }

        $value = ($item->value * $baseRate->nominal) / ($baseRate->value * $item->nominal);

        return round($value, 5);
    }

    /**
     * Вычисляет динамику курса между двумя днями.
     *
     * @param Currency|null $item
     * @param Currency|null $prevItem
     *
     * @return float|null
     */
    protected function calculateDiffYesterday(?Currency $item, ?Currency $prevItem): ?float
    {
        if (null === $item || null === $prevItem) {
            return null;
        }

        return round($item->value - $prevItem->value, 4);
    }
}
