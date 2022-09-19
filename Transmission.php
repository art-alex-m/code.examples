<?php

namespace App\Models;

/**
 * Class Transmission.
 *
 * @package App\Models
 */
enum Transmission: int
{
    case SPEED_0 = 0;
    case SPEED_1 = 1;
    case SPEED_2 = 2;
    case SPEED_3 = 3;
    case SPEED_4 = 4;
    case SPEED_5 = 5;

    /**
     * Timeout for selected transmission.
     *
     * @return float
     */
    public function timeout(): float
    {
        $min = $this->getMinTimeout();
        $max = $this->getMaxTimeout();
        $step = ($max - $min) / (count(self::cases()) - 1);

        return match ($this) {
            self::SPEED_0 => -1,
            self::SPEED_1 => $max,
            self::SPEED_5 => $min,
            default => $max - $step * $this->value,
        };
    }

    /**
     * Timestamp with timeout.
     *
     * @return float
     */
    public function microtime(): float
    {
        return microtime(true) + $this->timeout();
    }

    /**
     * Min timeout from config.
     *
     * @return float
     */
    public function getMinTimeout(): float
    {
        return config('kafka.transmission.min_timeout');
    }

    /**
     * Max timeout from config.
     *
     * @return float
     */
    public function getMaxTimeout(): float
    {
        return config('kafka.transmission.max_timeout');
    }
}
