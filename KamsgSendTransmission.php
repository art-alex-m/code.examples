<?php

namespace App\Services;

use Artalexm\KafkaedCommon\Messages\Transmission;
use Artalexm\KafkaedCommon\Models\KafkaConfig;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

/**
 * Class KamsgSendTransmission.
 *
 * @package App\Services
 */
class KamsgSendTransmission
{
    /**
     * Send message to change transmission on producer.
     *
     * @param TransmissionChangeContract $contract
     *
     * @return bool
     * @throws \Exception
     */
    public function update(TransmissionChangeContract $contract): bool
    {
        $transmissionMsg = new Transmission(microtime(true), $contract->getTransmission());

        return Kafka::publishOn(KafkaConfig::KAFKAED_1_CONTROL)
            ->withMessage(new Message(body: $transmissionMsg))
            ->send();
    }
}
