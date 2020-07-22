<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "clinic_contact".
 *
 * @property int $id
 * @property int $clinic_id
 * @property string $address
 */
class ClinicContact extends ActiveRecord
{
    /**
     * Сценарий создания.
     */
    const SCENARIO_CREATE = 'create';

    /**
     * Вызван ли метод сохранения.
     *
     * @var bool
     */
    public $isSave = false;

    /**
     * Вызван ли метод валидации.
     *
     * @var bool
     */
    public $isValidate = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clinic_contact';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clinic_id'], 'integer'],
            [['address'], 'string'],
        ];
    }

    public function attributes()
    {
        return ['id', 'clinic_id', 'address'];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CREATE] = ['clinic_id', 'address'];
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $this->isSave = true;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        $this->isValidate = true;
        return parent::validate($attributeNames, $clearErrors);
    }

    /**
     * @return ActiveQuery
     */
    public function getClinicContactPhone()
    {
        return $this->hasMany(ClinicContactPhone::class, ['clinic_contact_id' => 'id']);
    }

    public function __get($name)
    {
        // Необходимо для работы теста без обращения к БД.
        if($name === 'clinicContactPhone') {
            $relatedRecords = $this->getRelatedRecords();
            if (isset($relatedRecords[$name]) || array_key_exists($name, $relatedRecords)) {
                return $relatedRecords[$name];
            } else {
                return [];
            }
        }

        return parent::__get($name);
    }
}
