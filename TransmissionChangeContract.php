<?php

namespace App\Services;

/**
 * Interface TransmissionChangeContract.
 *
 * @package App\Services
 */
interface TransmissionChangeContract
{
    /**
     * Returns transmission value.
     *
     * @return int
     */
    public function getTransmission(): int;
}
