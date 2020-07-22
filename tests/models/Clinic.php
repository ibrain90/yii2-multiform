<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "clinic".
 *
 * @property int $id
 * @property string $name
 * @property string $description
 */
class Clinic extends ActiveRecord
{
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
        return 'clinic';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'safe'],
            [['name', 'description'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return ['id', 'name', 'description'];
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
    public function getClinicContact()
    {
        return $this->hasOne(ClinicContact::class, ['clinic_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getClinicLocation()
    {
        return $this->hasMany(ClinicLocation::class, ['clinic_id' => 'id']);
    }

    public function __get($name)
    {
        $relatedRecords = $this->getRelatedRecords();

        // Необходимо для работы теста без обращения к БД.
        if($name === 'clinicContact') {
            if (isset($relatedRecords[$name]) || array_key_exists($name, $relatedRecords)) {
                return $relatedRecords[$name];
            } else {
                return null;
            }
        }

        if($name === 'clinicLocation') {
            if (isset($relatedRecords[$name]) || array_key_exists($name, $relatedRecords)) {
                return $relatedRecords[$name];
            } else {
                return [];
            }
        }

        return parent::__get($name);
    }
}
