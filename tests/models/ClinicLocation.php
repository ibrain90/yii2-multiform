<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "clinic_location".
 *
 * @property int $id
 * @property int $clinic_id
 * @property string $name
 * @property string $description
 */
class ClinicLocation extends ActiveRecord
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
     * Вызван ли метод удаления.
     *
     * @var bool
     */
    public $isDelete = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clinic_location';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'safe'],
            [['clinic_id'], 'integer'],
            [['name', 'description'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return ['id', 'clinic_id', 'name', 'description'];
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
     * {@inheritdoc}
     */
    public function delete()
    {
        $this->isDelete = true;
        return 1;
    }

    /**
     * @return ActiveQuery
     */
    public function getClinicLocationContact()
    {
        return $this->hasOne(ClinicLocationContact::class, ['clinic_location_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getDoctorInClinicLocation()
    {
        return $this->hasMany(DoctorInClinicLocation::class, ['clinic_location_id' => 'id']);
    }

    public function __get($name)
    {
        $relatedRecords = $this->getRelatedRecords();
        
        // Необходимо для работы теста без обращения к БД.
        if($name === 'clinicLocationContact') {
            if (isset($relatedRecords[$name]) || array_key_exists($name, $relatedRecords)) {
                return $relatedRecords[$name];
            } else {
                return null;
            }
        }
        
        if($name === 'doctorInClinicLocation') {
            if (isset($relatedRecords[$name]) || array_key_exists($name, $relatedRecords)) {
                return $relatedRecords[$name];
            } else {
                return [];
            }
        }

        return parent::__get($name);
    }
}
