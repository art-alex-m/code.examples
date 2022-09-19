<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransmissionChangeRequest;
use App\Services\KamsgSendTransmission;

/**
 * Class TransmissionController.
 *
 * @package App\Http\Controllers
 */
class TransmissionController extends Controller
{
    /**
     * Update transmission.
     *
     * @param TransmissionChangeRequest $request
     * @param KamsgSendTransmission $service
     *
     * @return bool
     * @throws \Exception
     */
    public function update(TransmissionChangeRequest $request, KamsgSendTransmission $service)
    {
        return $service->update($request);
    }
}
