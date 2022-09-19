<?php

namespace App\Http\Requests;

use App\Services\TransmissionChangeContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class TransmissionChangeRequest.
 *
 * @package App\Http\Requests
 */
class TransmissionChangeRequest extends FormRequest implements TransmissionChangeContract
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transmission' => 'required|int|min:0|max:5',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getTransmission(): int
    {
        return $this->validated('transmission', 0);
    }
}
