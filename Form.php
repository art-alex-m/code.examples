<?php
/**
 * Form
 *
 * Created by PhpStorm.
 * @date 04.01.16
 * @time 14:30
 */

namespace common\models;

use common\components\FormTemplateGetterTrait;
use common\components\GetAvatarUrlBehavior;
use common\components\GetFormFullNameBehavior;
use common\components\NoDepressiveDeleteTrait;
use common\components\sql\WorkflowActiveRecord;
use common\components\GetCurrentDbStatusTrait;
use common\components\GetDbStatusHistoryTrait;
use common\components\workflow2\Validator as WorkflowValidator;
use common\components\workflow2\Behavior;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * Class Form
 *
 * Анкета пользователя
 *
 * Для создания новой анкеты пользователя следует использовать один из способов:
 * - фабричный метод [[FormTemplate::createForm()]] FormTemplate::instance()->createForm($attributes)
 * - создавать анкету с параметром template_id, new Form([template_id => 1]) и затем устанавливать
 *   атрибуты анкеты.
 *
 * @see FormTemplate::createForm()
 * @package common\models
 *
 * Статичные данные
 * @property int $user_id Идентификатор пользователя
 * @property int $template_id Идентификатор шаблона анкеты
 *
 * @property null|FormStatus $currentStatus Сохраненный в БД статус модели
 * @property []|FormStatus[] $statusHistory Сохраненные в БД все статусы модели
 * @property []|FormValue[] $formValues Значения сойств параметров анкеты пользователя
 *
 * @property string|null $avatarUrl Возвращает урл аватарки пользователя. Предоствляется поведением
 * [[\common\components\GetAvatarUrlBehavior]]
 * @property User $user Учетная запись пользователя сервиса
 *
 * @method string getFullName(string $glue = null) Возвращает полное имя пользователя из анкетных
 * данных
 */
class Form extends WorkflowActiveRecord
{
    use NoDepressiveDeleteTrait,
        GetCurrentDbStatusTrait,
        GetDbStatusHistoryTrait,
        FormTemplateGetterTrait;

    /** @var FormValue[] Список значений свойств анкеты пользователя, сгруппированный по слагу */
    protected $mapProperty2Value = [];

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (isset($this->mapProperty2Value[$name])) {
            return $this->mapProperty2Value[$name]->value;
        } elseif ($this->loadFormValue($name)) {
            return $this->mapProperty2Value[$name]->value;
        } elseif ($this->hasFormProperty($name)) {
            return null;
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (!$this->setFormValue($name, $value)) {
            parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function __unset($name)
    {
        if ($this->hasFormProperty($name)) {
            unset($this->mapProperty2Value[$name]);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Связь с таблицей пользователей
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Возвращает значение свойства параметра анкеты как объект класса FormValue
     * @param string $slug
     * @return FormValue|null
     */
    public function value($slug)
    {
        if (isset($this->mapProperty2Value[$slug])) {
            return $this->mapProperty2Value[$slug];
        } elseif ($this->loadFormValue($slug)) {
            return $this->mapProperty2Value[$slug];
        } elseif ($this->hasFormProperty($slug)) {
            $this->mapProperty2Value[$slug] = $this->createNewFormValue($slug);
            return $this->mapProperty2Value[$slug];
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($name, $value)
    {
        if (!$this->setFormValue($name, $value)) {
            parent::setAttribute($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($name)
    {
        $value = parent::getAttribute($name);
        if (is_null($value) and !$this->hasAttribute($name)) {
            if (isset($this->mapProperty2Value[$name]) or $this->loadFormValue($name)) {
                return $this->mapProperty2Value[$name]->value;
            }
        }
        return $value;
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        if (is_array($values)) {
            $properties = [];
            foreach ($values as $name => $value) {
                if (is_string($name) and $this->setFormValue($name, $value)) {
                    $properties[$name] = 1;
                }
            }
            parent::setAttributes(array_diff_key($values, $properties), $safeOnly);
        }
    }

    /**
     * @inheritdoc
     */
    public function getAttributes($names = null, $except = [])
    {
        if (is_null($names)) {
            $names = array_merge(
                array_keys($this->getPropertiesInternal()),
                $this->attributes()
            );
        }
        return parent::getAttributes($names);
    }

    /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        $result = parent::validate($attributeNames, $clearErrors);

        $propertiesNames = array_keys($this->getPropertiesInternal());
        if (is_array($attributeNames)) {
            $propertiesNames = array_intersect($attributeNames, $propertiesNames);
        }
        foreach ($propertiesNames as $name) {
            if (isset($this->mapProperty2Value[$name]) and
                ($this->mapProperty2Value[$name] instanceof FormValue)
            ) {
                $result &= $this->mapProperty2Value[$name]->validate('value', $clearErrors);
            }
        }

        return (bool)$result;
    }

    /**
     * @inheritdoc
     */
    public function getErrors($attribute = null)
    {
        $errors = parent::getErrors($attribute);
        if (null === $attribute) {
            foreach ($this->mapProperty2Value as $prop => $formValue) {
                if ($formValue instanceof FormValue) {
                    if ($formValue->hasErrors('value')) {
                        $errors[$prop] = $formValue->getErrors('value');
                    }
                }
            }
        } else if (!$this->hasAttribute($attribute)) {
            if (
                $this->hasFormProperty($attribute) and
                isset($this->mapProperty2Value[$attribute])
            ) {
                return $this->mapProperty2Value[$attribute]->getErrors('value');
            }
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function getFirstError($attribute)
    {
        $errors = $this->getErrors($attribute);
        return count($errors) > 0 ? reset($errors) : null;
    }

    /**
     * @inheritdoc
     */
    public function getFirstErrors()
    {
        $rawErrors = $this->getErrors();
        if (!empty($rawErrors)) {
            $errors = [];
            foreach ($rawErrors as $name => $es) {
                if (!empty($es)) {
                    $errors[$name] = reset($es);
                }
            }
            return $errors;
        }
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasErrors($attribute = null)
    {
        $errors = $this->getErrors($attribute);
        return !empty($errors);
    }

    /**
     * Прокси метод для FormTemplate::hasFormProperty()
     * @param string $name
     * @return bool
     */
    public function hasFormProperty($name)
    {
        return
            isset($this->getPropertiesInternal()[$name]);
    }

    /**
     * Значения свойств параметров анкеты пользователя без использования кеширования свойств
     * @return \yii\db\ActiveQuery
     */
    public function getRawFormValues()
    {
        return $this->hasMany(FormValue::class, ['form_id' => 'id']);
    }

    /**
     * Геттер для [[formValues]]
     * @return FormValue[]
     */
    public function getFormValues()
    {
        return $this->mapProperty2Value;
    }

    /**
     * @inheritdoc
     */
    public function allowedChangedOnDeleteAttributes()
    {
        return ['updated_at', 'updated_by', 'status', 'status_id'];
    }

    /**
     * @inheritdoc
     */
    public function getDbStatusModelClass()
    {
        return FormStatus::class;
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return ArrayHelper::merge(
            parent::behaviors(),
            [
                'workflow' => [
                    'class' => Behavior::class,
                    'statusAttribute' => 'status',
                    'collectionName' => 'workflow2Collection',
                    'workflowId' => '\common\models\workflow2\FormWorkflow',
                    'statusAccessorConf' => [
                        'class' => 'common\components\WorkflowStatusAccessor',
                        'statusClass' => $this->getDbStatusModelClass(),
                    ],
                ],
                'avatarUrl' => [
                    'class' => GetAvatarUrlBehavior::class,
                    'attribute' => 'self_photo_file',
                ],
                'fullName' => [
                    'class' => GetFormFullNameBehavior::class,
                ],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        $this->initRequiredFormValues();
        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['user_id', 'required'],
            ['user_id', 'exist', 'targetClass' => 'common\models\User', 'targetAttribute' => 'id'],
            ['status_id', 'safe'],
            ['status', WorkflowValidator::class],
            ['template_id', 'default', 'value' => 1], /// FIXME: Шаблон анкеты только один
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(
            parent::attributeLabels(),
            $this->attributeLabels4NoDepressiveDelete(),
            $this->propertyLabels(),
            [
                'user_id' => Yii::t('common', 'User id'),
                'status' => Yii::t('common', 'Status'),
                'template_id' => Yii::t('common', 'Template id'),
            ]
        );
    }

    /**
     * Поля возвращаемые дополнительно при сериализации в массив
     * @return array
     */
    public function extraFields()
    {
        return [
            'status_label',
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        $savedValues = $this->saveFormValues();
        parent::afterSave($insert, array_merge($changedAttributes, $savedValues));
    }

    /**
     * Возвращает список имен атрибутов-свойств анкеты
     * @return array
     */
    public function propertyLabels()
    {
        $labels = [];
        foreach ($this->getPropertiesInternal() as $key => $prop) {
            if ($prop instanceof FormProperty) {
                $labels[$key] = $prop->title;
            }
        }
        return $labels;
    }

    /**
     * Возвращает список имен свойств параметров анкеты
     * @return FormProperty[]
     */
    public function getPropertiesInternal()
    {
        $names = [];
        if ($this->getTemplate()) {
            $names = $this->getTemplate()->getProperties();
        }
        return $names;
    }

    /**
     * Инициирует свойства анкеты обязательные к заполению
     */
    protected function initRequiredFormValues()
    {
        foreach ($this->getPropertiesInternal() as $name => $prop) {
            if (
                ($prop->isRequired() or $prop->isFile()) and
                !isset($this->mapProperty2Value[$name])
            ) {
                if (!$this->loadFormValue($name)) {
                    $this->mapProperty2Value[$name] = $this->createNewFormValue($name);
                }
            }
        }
    }

    /**
     * Сохраняет значения параметров анкеты
     * @return array Старые значения свойств. Обратно совместимо с $changedAttributes в afterSave()
     */
    protected function saveFormValues()
    {
        $saved = [];
        foreach ($this->mapProperty2Value as $name => $formValue) {
            if ($formValue instanceof FormValue) {
                $formValue->form_id = $this->id;
                $oldValue = $formValue->getOldAttribute('value');
                if ($formValue->save()) {
                    $saved[$name] = $oldValue;
                }
            }
        }
        return $saved;
    }

    /**
     * Устанавливает значение свойства параметра анкеты пользователя по слагу
     * @param string $name Слаг свойства параметра
     * @param mixed $value
     * @return bool
     */
    protected function setFormValue($name, $value)
    {
        if ($this->hasFormProperty($name)) {
            if (!isset($this->mapProperty2Value[$name])) {
                if (!$this->loadFormValue($name)) {
                    $this->mapProperty2Value[$name] = $this->createNewFormValue($name);
                }
            }
            $this->mapProperty2Value[$name]->value = $value;
            return true;
        }
        return false;
    }

    /**
     * Загружает и кеширует значение свойства параметра анкеты
     * @param string $propSlug
     * @return bool|FormValue
     */
    protected function loadFormValue($propSlug)
    {
        if ($this->hasFormProperty($propSlug)) {
            if (!$this->getIsNewRecord()) {
                $property = $this->getTemplate()->getPropertyBySlug($propSlug);
                $formValue = FormValue::findOne([
                    'form_id' => $this->id,
                    'property_id' => $property->id
                ]);
                if ($formValue) {
                    $this->mapProperty2Value[$propSlug] = $formValue;
                    return $formValue;
                }
            }
        }
        return false;
    }

    /**
     * Создает новое значение свойства параметра анкеты пользователя
     * @param string $propSlug
     * @return bool|FormValue
     */
    protected function createNewFormValue($propSlug)
    {
        if ($this->hasFormProperty($propSlug)) {
            $formId = null;
            $formValue = new FormValue([
                'form_id' => $formId,
                'property_id' => $this->getTemplate()->getPropertyBySlug($propSlug)->id,
            ]);
            $formValue->loadDefaultValues();
            return $formValue;
        }
        return false;
    }
}