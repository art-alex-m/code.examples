<?php
/**
 * DecodeJob.php
 *
 * Created by PhpStorm.
 * @date 03.07.19
 * @time 11:42
 */

namespace coder\models;

use coder\components\QueueExecLimitException;
use coder\components\QueueInfo;
use common\components\ImplodePathTrait;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use yii\base\BaseObject;
use Yii;
use yii\queue\RetryableJobInterface;

/**
 * Class DecodeJob
 * Перекодирует полученные файлы
 * @package coder\models
 * @property-read string $savedFile Путь нового файла
 */
class DecodeJob extends BaseObject implements RetryableJobInterface
{
    use ImplodePathTrait;

    /** @var string Путь к файлу для перекодирования */
    public $file;
    /** @var string Путь для сохранения нового файла */
    protected $path;
    /** @var int Количество одновлеменно выполняющихся заданий */
    protected $execLimit = 2;
    /** @var int Время, на которое откладывается задание при превышении количества процессов кодирования */
    protected $execLimitTime = 120;
    /** @var string Кодек аудио */
    protected $audioCodec = 'aac';
    /** @var string Кодек видео */
    protected $videoCodec = 'libx264';
    /** @var string Контейнер для видео */
    protected $videoContainer = 'mp4';

    /**
     * {@inheritdoc}
     */
    public function execute($queue)
    {
        $info = new QueueInfo(['queue' => $queue]);
        if ($info->getReservedCount() >= $this->execLimit) {
            throw new QueueExecLimitException(
                Yii::t('app', '{0} tasks is already running', $this->execLimit));
        }

        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($this->file);
        $video->save(new X264($this->audioCodec, $this->videoCodec), $this->savedFile);
        unlink($this->file);
    }

    /**
     * Имя нового файла для сохранения
     * @return bool|string
     * @throws \Exception
     */
    public function getSavedFile()
    {
        if (empty($this->path)) {
            $name = basename($this->file);
            $date = new \DateTime();
            $path = Yii::getAlias($this->implodePath(DIRECTORY_SEPARATOR, [
                Yii::$app->params['decoderDir'],
                [$date->format('m'), $date->format('d')],
            ]));
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
            $this->path = $path . DIRECTORY_SEPARATOR . $name . '.' . $this->videoContainer;
        }
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function canRetry($attempt, $error)
    {
        return $error instanceof QueueExecLimitException;
    }

    /**
     * {@inheritdoc}
     */
    public function getTtr()
    {
        return $this->execLimitTime;
    }
}