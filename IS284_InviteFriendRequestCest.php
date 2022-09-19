<?php
/**
 * Class IS284_InviteFriendRequestCest
 *
 * @date 15.09.2016
 * @time 12:51
 */

namespace tests\codeception\api;

use Codeception\Util\Debug;
use common\models\Event;
use common\models\SmsNotification;
use common\models\User;
use tests\codeception\api\_pages\LoginQuery;
use tests\codeception\api\FunctionalTester;
use Yii;

/**
 * Class IS284_InviteFriendRequestCest
 * Тестирует кейсы приглашения участника по смс
 * @package tests\codeception\api
 */
class IS284_InviteFriendRequestCest
{
    /** @var string Компонент очереди смс сообщений */
    protected $smsQueue = 'smsNotificationsQueue';

    /**
     * Запрос должен быть только для авторизованых пользователей
     * @param \tests\codeception\api\FunctionalTester $I
     */
    public function shouldBe403ForAnonymous(FunctionalTester $I)
    {
        $url = $I->getUrl(['various/invite-friend']);
        $I->sendPOST($url, []);
        $I->seeResponseCodeIs(403);
        $I->seeResponseIsJson();
    }

    /**
     * Проверяет, что доступны только запросы типа POST и OPTIONS
     * @param \tests\codeception\api\FunctionalTester $I
     */
    public function checkOnlyPOSTRequestIsValid(FunctionalTester $I)
    {
        $url = $I->getUrl(['various/invite-friend']);
        $I->sendGET($url, []);
        $I->seeResponseCodeIs(405);
        $I->seeResponseIsJson();
        $I->sendPATCH($url, []);
        $I->seeResponseCodeIs(405);
        $I->seeResponseIsJson();
        $I->sendDELETE($url, []);
        $I->seeResponseCodeIs(405);
        $I->seeResponseIsJson();
        $I->sendOPTIONS($I->getUrl(['various/options']));
        $I->seeResponseCodeIs(200);
        $I->seeResponseEquals("");
    }

    /**
     * Проверяет, что приглашающее друга смс создано для мероприятия в статусе "Активно"
     * @param \tests\codeception\api\FunctionalTester $I
     */
    public function invitationShouldBeCreated(FunctionalTester $I)
    {
        $data = [
            'event_id' => 2,
            'phone' => '79046652277',
        ];
        $login = LoginQuery::openBy($I);
        $login->login('bayer.hudson', 'password_0');
        $url = $I->getUrl(['various/invite-friend']);
        $I->sendPOST($url, $data);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'request' => true,
        ]);

        /** @var \common\models\User $user */
        $user = User::findByUsername('bayer.hudson');
        /** @var \common\models\Event $event */
        $event = Event::findOne($data['event_id']);
        /** @var \common\components\redis\RedisSortedSet $queue */
        $queue = Yii::$app->get($this->smsQueue);
        $I->assertEquals(1, $queue->zcard(), 'Sms queue should has 1 notification');
        $smsId = $queue->zRangeByScore()[0];
        /** @var \common\models\SmsNotification $sms */
        $sms = SmsNotification::findById($smsId);
        Debug::debug($sms->toArray());

        $I->assertEquals($data['phone'], $sms->recipient, 'Phone should be as send');
        $I->assertContains($user->form->first_name, $sms->body);
        $I->assertContains($user->form->last_name, $sms->body);
        $I->assertContains($event->header, $sms->body, 'Event title should present in message');

        $login->logout();
    }

    /**
     * Проверяет, что приглашающее друга смс создано для мероприятия в статусе "Новое"
     * @param \tests\codeception\api\FunctionalTester $I
     */
    public function invitationShouldBeCreatedForNewEvent(FunctionalTester $I)
    {
        $data = [
            'event_id' => 1,
            'phone' => '79046652277',
        ];
        $login = LoginQuery::openBy($I);
        $login->login('bayer.hudson', 'password_0');
        $url = $I->getUrl(['various/invite-friend']);
        $I->sendPOST($url, $data);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'request' => true,
        ]);
    }

    /**
     * Смс не должно быть создано для неактивного события
     * @param \tests\codeception\api\FunctionalTester $I
     */
    public function shouldBeErrorWithNoneActiveEvent(FunctionalTester $I)
    {
        $data = [
            'event_id' => 4,
            'phone' => '79046652277',
        ];
        $login = LoginQuery::openBy($I);
        $login->login('bayer.hudson', 'password_0');
        $url = $I->getUrl(['various/invite-friend']);
        $I->sendPOST($url, $data);
        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'field' => 'event_id',
            'message' => 'Event id is invalid.'
        ]);
        $login->logout();
    }

    /**
     * Убираем после тестов
     * @param \tests\codeception\api\FunctionalTester $I
     * @throws \yii\db\Exception
     */
    public function _after(FunctionalTester $I)
    {
        $redis = Yii::$app->get('redis');
        $redis->flushdb();
    }
}
