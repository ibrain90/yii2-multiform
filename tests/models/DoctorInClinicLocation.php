<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "doctor_in_clinic_location".
 *
 * @property int $id
 * @property int $clinic_location_id
 * @property string $name
 * @property string $specialization
 */
class DoctorInClinicLocation extends ActiveRecord
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
        return 'doctor_in_clinic_location';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clinic_location_id'], 'integer'],
            [['name', 'specialization'], 'string'],
        ];
    }

    public function attributes()
    {
        return ['id', 'clinic_location_id', 'name', 'specialization'];
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
}
