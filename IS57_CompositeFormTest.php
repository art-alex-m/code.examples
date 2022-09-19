<?php
/**
 * @date 19.01.2016
 * @time 13:05
 */

namespace tests\codeception\common\models;

use Codeception\Specify;
use Codeception\Util\Debug;
use common\models\Form;
use common\models\FormTemplate;
use common\models\FormProperty;
use common\models\PropertyValidator;
use tests\codeception\common\_support\CreateFormPropertyTrait;
use tests\codeception\common\_support\DropFormPropertyTrait;
use tests\codeception\common\unit\DbTestCase;
use tests\codeception\common\fixtures\UserFixture;
use Yii;
use yii\base\InvalidCallException;

/**
 * Class IS57_CompositeFormTest
 * @package tests\codeception\common\models
 */
class IS57_CompositeFormTest extends DbTestCase
{
    use Specify,
        DropFormPropertyTrait,
        CreateFormPropertyTrait;

    /** @var int Идентификатор анкеты */
    public $formId;
    /** @var int Идентификатор свойства */
    protected $propertyId;

    /**
     * Проверяет что создание анкеты пользователя с данными проходит правильно
     */
    public function testOrdinaryFormCreationWithData()
    {
        $e = null;
        $form = null;
        try {
            $form = new Form(['template_id' => 1]);
        } catch (\Exception $e) {
        }

        expect('ordinary form creation with data not throws error', $e)->null();
        expect('ordinary form creation generate new object', $form)
            ->isInstanceOf(Form::className());
    }

    /**
     * Проверяет верное создание анкеты через фабричный метод FormTemplate::createForm()
     */
    public function testFabricFormCreation()
    {
        $form = FormTemplate::instance()->createForm();
        expect('fabric form creation generate new object', $form)
            ->isInstanceOf(Form::className());
    }

    /**
     * Проверяет установку существующего свойства без сохранения анкеты в базе
     */
    public function testReadWriteFormProperty()
    {
        $form = FormTemplate::instance()->createForm();
        $slug = $this->createPropertyInternal()->slug;

        expect("has $slug property", $form->template->hasFormProperty($slug))->true();
        expect("get $slug init value", $form->$slug)->null();
        $testName = 'My test last name';
        $form->$slug = $testName;
        expect("set $slug value is correct", $form->$slug)->equals($testName);
    }

    /**
     * Проверяет установку несуществующего свойства анкеты
     */
    public function testReadWriteUnknownProperty()
    {
        $form = $this->createForm();
        $testProperty = __FUNCTION__ . '_' . hash('adler32', microtime());
        expect('has no expected property',
            $form->template->hasFormProperty($testProperty))->false();
        $e = null;
        try {
            $form->$testProperty;
        } catch (\Exception $e) {
        }
        expect('get unknown property ends with error', $e)
            ->isInstanceOf('yii\base\UnknownPropertyException');

        $testName = 'My test last name';
        $e = null;
        try {
            $form->$testProperty = $testName;
        } catch (\Exception $e) {
        }
        expect('set unknown property ends with error', $e)
            ->isInstanceOf('yii\base\UnknownPropertyException');
    }

    /**
     * Проверяет удаление значение свойства без сохранения модели
     */
    public function testUnsetPropertyWithoutSave()
    {
        $form = $this->createForm();
        $slug = $this->createPropertyInternal()->slug;
        expect("has $slug property", $form->template->hasFormProperty($slug))->true();
        expect("get $slug init value", $form->$slug)->null();
        $testName = 'My test last name';
        $form->$slug = $testName;
        expect("set $slug value is correct and not null", $form->$slug)->equals($testName);
        unset($form->$slug);
        expect("get null $slug value after unset", $form->$slug)->null();
    }

    /**
     * Проверяет правильное сохранение значения свойства в базе данных
     */
    public function testReadWriteFormValueWithSave()
    {
        $form = $this->createForm();
        $slug = $this->createPropertyInternal()->slug;
        $testName = 'My test last name';
        $form->$slug = $testName;
        expect("set $slug value is correct and not null", $form->$slug)->equals($testName);
        $form->save();

        Debug::debug('Form saved errors:');
        Debug::debug($form->getErrors());

        expect('form was saved', $form->getIsNewRecord())->false();
        expect('form id should be not null for tearDown()', $this->formId)->notNull();

        $dbForm = Form::findOne($form->id);
        expect('form is loaded', $dbForm)->isInstanceOf(Form::className());
        expect("form parameter $slug is expected value", $dbForm->$slug)->equals($testName);
    }

    /**
     * Проверяет правильное сохранение значения свойства через setAttribute
     */
    public function testGetSetAttribute()
    {
        $form = $this->createForm();
        $slug = $this->createPropertyInternal()->slug;

        expect("has $slug property", $form->template->hasFormProperty($slug))->true();
        expect("get $slug init value", $form->$slug)->null();
        $testName = 'My test last name';
        $form->setAttribute($slug, $testName);
        expect("setAttribute $slug value is correct", $form->$slug)->equals($testName);
        expect("getAttribute $slug is correct", $form->getAttribute($slug))->equals($testName);
    }

    /**
     * Проверяет правильное сохранение значения свойства через setAttributes
     */
    public function testGetSetAttributesWithSave()
    {
        $form = $this->createForm();
        $slug = $this->createPropertyInternal()->slug;

        expect("has $slug property", $form->template->hasFormProperty($slug))->true();
        expect("get $slug init value", $form->$slug)->null();
        $testName = 'My test last name';
        $form->setAttributes([
            $slug => $testName,
            'updated_at' => 123456789,
        ], false);
        expect("setAttributes $slug value is correct", $form->$slug)->equals($testName);
        expect('form db attribute was set', $form->updated_at)->equals(123456789);
        expect("getAttributes [$slug] is correct", $form->getAttributes([$slug, 'template_id']))
            ->equals([$slug => $testName, 'template_id' => 1]);

        $form->save();
        Debug::debug('Form saved errors:');
        Debug::debug($form->getErrors());

        expect('form was saved', $form->getIsNewRecord())->false();
        expect('form id should be not null for tearDown()', $this->formId)->notNull();

        /** @var Form $dbForm */
        $dbForm = Form::findOne($form->id);
        expect('form is loaded', $dbForm)->isInstanceOf(Form::className());
        $attributes = $dbForm->getAttributes([$slug, 'template_id']);
        expect("from attributes is array and has {$slug} as key", $attributes)->hasKey($slug);
        expect("form attributes key $slug is expected value", $attributes[$slug])
            ->equals($testName);
    }

    /**
     * Проверяет получение значение атрибута в списке getAttributes()
     */
    public function testGetAttributesWithNullNames()
    {
        $form = $this->createForm();
        $slug = $this->createPropertyInternal()->slug;
        $form->$slug = __FUNCTION__ . '_' . hash('adler32', microtime());
        $attributes = $form->getAttributes();

        Debug::debug('From attributes by default:');
        Debug::debug($attributes);

        expect('form attributes is array and has expected slug', $attributes)->hasKey($slug);
        expect('form template has property', FormTemplate::instance()->hasFormProperty($slug))
            ->true();
    }

    /**
     * Проверяет валидацию анкеты пользователя со свойствами
     */
    public function testFormValidateAndGetErrors()
    {
        $slug = $this->createPropertyInternal()->slug;
        $validator = new PropertyValidator($this->getRequiredValidatorData());
        $validator->save();

        $form = $this->createForm();
        $form->$slug = '';
        $result = $form->validate();

        expect('form has validation error', $result)->false();

        $errors = $form->getErrors();
        Debug::debug('Form validation errors:');
        Debug::debug($errors);

        expect("form has required validation error with $slug", $errors)->hasKey($slug);

        $errors = $form->getErrors($slug);
        expect("form has only one error when call with attribute", $errors)->count(1);
    }

    /**
     * Тестирует функционал hasErrors()
     */
    public function testHasErrors()
    {
        $slug = $this->createPropertyInternal()->slug;
        $validator = new PropertyValidator($this->getRequiredValidatorData());
        $validator->save();

        $form = $this->createForm();
        $form->$slug = '';
        expect('form has no errors before validate', $form->hasErrors())->false();
        expect("form has no errors by $slug before validate", $form->hasErrors($slug))->false();

        $form->validate();
        $errors = $form->getErrors();
        Debug::debug('Form validation errors:');
        Debug::debug($errors);

        expect('form has errors', $form->hasErrors())->true();
        expect("form has errors with $slug", $form->hasErrors($slug))->true();
    }

    /**
     * Проверка работы функции getFirstError()
     */
    public function testGetFirstError()
    {
        $slug = $this->createPropertyInternal()->slug;
        $vData = $this->getRequiredValidatorData();
        $validator1 = new PropertyValidator($vData);
        $validator1->save();
        $vData['priority'] = 2;
        $validator2 = new PropertyValidator($vData);
        $validator2->save();

        $form = $this->createForm();
        $form->$slug = '';
        expect('form has no errors before validate', $form->hasErrors())->false();
        expect("form has no errors by $slug before validate", $form->hasErrors($slug))->false();

        $form->validate();
        $errors = $form->getErrors();
        Debug::debug('Form validation errors:');
        Debug::debug($errors);
        Debug::debug("Form $slug validation errors:");
        Debug::debug($form->getErrors($slug));

        expect('form has errors', $form->hasErrors($slug))->true();
        expect("form getErrors('$slug') has 2 errors", $form->getErrors($slug))->count(2);
        expect("form getFirstError('$slug') get 1 error", $form->getFirstError($slug))->notNull();
    }

    /**
     * Проверяет работу функции возвращения описания для имени атрибута-свойства
     */
    public function testGetAttributeLabel()
    {
        $property = $this->createPropertyInternal();
        $form = $this->createForm();
        $labels = $form->attributeLabels();
        expect('form has property label', $labels)->hasKey($property->slug);
        expect('form property label is expected', $labels[$property->slug])
            ->equals($property->title);
        expect(
            'form getAttributeLabel() return expected label',
            $form->getAttributeLabel($property->slug)
        )
            ->equals($property->title);
    }

    /**
     * Проверяет заполение обязательных свойств анкеты пользователя
     */
    public function testUnsetButRequiredFormValue()
    {
        $form = $this->createForm();

        $slug = $this->createPropertyInternal()->slug;
        $validator = new PropertyValidator($this->getRequiredValidatorData());
        $validator->save();

        expect('form has no errors before validate', $form->hasErrors())->false();
        expect("form has no errors by $slug before validate", $form->hasErrors($slug))->false();

        $form->validate();
        $errors = $form->getErrors();
        Debug::debug('Form validation errors:');
        Debug::debug($errors);

        expect('form has errors', $errors)->notEmpty();
        expect("form has errors with $slug", $errors)->hasKey($slug);
    }

    /**
     * Тестирует корректное получение родительских магических атрибутов на примере Model::scenario
     */
    public function testGetScenarioAttribute()
    {
        $form = $this->createForm();
        expect('form scenario is default scenario', $form->scenario)
            ->equals(Form::SCENARIO_DEFAULT);
    }

    /**
     * @inheritdoc
     */
    public function fixtures()
    {
        return [
            'user' => [
                'class' => UserFixture::className(),
                'dataFile' => '@common_tests/unit/fixtures/data/models/user.php'
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        $this->dropFormProperty($this->propertyId);
        $db = Yii::$app->getDb();
        $db->createCommand()
            ->delete(Form::tableName(), ['id' => $this->formId])
            ->execute();

        $this->propertyId = null;
        $this->formId = null;

        parent::tearDown();
    }

    /**
     * Создает объект формы пользователя
     * @return Form
     */
    protected function createForm()
    {
        $form = FormTemplate::instance()->createForm($this->getFormData());
        $form->on(Form::EVENT_AFTER_INSERT, function ($event) {
            Debug::debug("Form id is: #{$event->sender->id}");
            $this->formId = $event->sender->id;
        });
        return $form;
    }

    /**
     * Созадет тестовое свойство парметра анкеты
     * @return FormProperty
     */
    protected function createPropertyInternal()
    {
        $property = $this->createFakeProperty();
        $property->save();
        $this->propertyId = $property->getPrimaryKey();
        return $property;
    }

    /**
     * Возвращает базовые параметры для создания записи валидатора свойства
     * @return array
     */
    protected function getRequiredValidatorData()
    {
        if (is_null($this->propertyId)) {
            throw new InvalidCallException(
                'First should be property created with createPropertyInternal()');
        }

        return [
            'property_id' => $this->propertyId,
            'priority' => 1,
            'title' => 'Test required validator',
            'class' => 'required',
            'params' => [
                'skipOnError' => false,
            ],
        ];
    }

    /**
     * Возвращает параметры для создания формы пользователя
     * @return array
     */
    protected function getFormData()
    {
        return require Yii::getAlias('@common_tests/templates/fixtures/form.php');
    }
}