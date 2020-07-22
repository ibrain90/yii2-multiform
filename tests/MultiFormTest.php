<?php

namespace brain90\multiform\tests;

require __DIR__ . '/_bootstrap.php';

use brain90\multiform\MultiForm;
use brain90\multiform\tests\models\Clinic;
use brain90\multiform\tests\models\ClinicContact;
use brain90\multiform\tests\models\ClinicContactPhone;
use brain90\multiform\tests\models\ClinicLocation;
use brain90\multiform\tests\models\ClinicLocationContact;
use brain90\multiform\tests\models\DoctorInClinicLocation;
use PHPUnit\Framework\TestCase;

class MultiFormTest extends TestCase
{
    public function testMultiForm()
    {
        // Корневая модель
        $clinic = new Clinic();
        $clinic->load([
            'Clinic' => [
                'id' => 1,
                'name' => 'Мега Клиник',
                'description' => 'Описание Мега Клиник'
            ]
        ]);

        // Инициализация мультиформы
        $multiForm = MultiForm::forClassWithMeta(Clinic::class, $this->getFormMetaData())->empty();
        $multiForm->setModel($clinic);
        $multiForm->prefetchRelations();

        /* Проверка инициализации */
        // Проверка инициализации корневой модели
        $clinic = $multiForm->getModel();
        $relatedRecords = $clinic->relatedRecords;
        $this->assertEquals('Мега Клиник', $clinic->name);
        $this->assertEquals('Описание Мега Клиник', $clinic->description);
        
        // Проверка инициализации элементов 'getItems'
        $clinicLocationFirst = $relatedRecords['clinicLocation'][0];
        $this->assertEquals(1, $clinicLocationFirst->id);
        $this->assertEquals(1, $clinicLocationFirst->clinic_id);
        $this->assertEquals('Мега Клиник Филиал 1', $clinicLocationFirst->name);
        $this->assertEquals('Первый филиал', $clinicLocationFirst->description);
        
        $clinicLocationSecond = $relatedRecords['clinicLocation'][1];
        $this->assertEquals(2, $clinicLocationSecond->id);
        $this->assertEquals(1, $clinicLocationSecond->clinic_id);
        $this->assertEquals('Мега Клиник Филиал 2', $clinicLocationSecond->name);
        $this->assertEquals('Второй филиал', $clinicLocationSecond->description);

        $doctorInClinicLocation = $clinicLocationSecond->doctorInClinicLocation[0];
        $this->assertEquals(2, $doctorInClinicLocation->clinic_location_id);
        $this->assertEquals('Ай Болит', $doctorInClinicLocation->name);
        $this->assertEquals('Терапевт', $doctorInClinicLocation->specialization);

        // POST данные для загрузки в мультиформу
        $postData = [
            'Clinic' => [
                'id' => 1,
                'name' => 'Новое имя клиники',
                'description' => 'Новое описание'
            ],
            'ClinicContact' => [
                'id' => 'gigi90khk7sgh',
                'address' => 'Адрес клиники'
            ],
            'ClinicContactPhone' => [
                // Ключ массива - временный id родительской записи
                'gigi90khk7sgh' => [
                    'jhjhj1khjha2sdf' => [
                        'id' => 'jhjhj1khjha2sdf',
                        'phone' => '+7 264 121 78 96'
                    ],
                    'pplkkkkkkk23lk' => [
                        'id' => 'pplkkkkkkk23lk',
                        'phone' => '+7 621 477 78 32'
                    ]
                ]
            ],
            'ClinicLocation' => [
                // Обновляет существующую запись
                '2' => [
                    'id' => 2,
                    'clinic_id' => 1,
                    'name' => 'Филиал 2 новое имя',
                    'description' => 'Филиал 2 новое описание'
                ],
                // Добавляет новую запись
                'asdfs3455d2' => [
                    'id' => 'asdfs3455d2',
                    'clinic_id' => 1,
                    'name' => 'Филиал 3',
                    'description' => 'Третий филиал'
                ]
            ],
            'ClinicLocationContact' => [
                '2' => [
                    'id' => 'lmm4kmklk7lm',
                    'address' => 'Адрес филиала 2',
                    'phone' => ''
                ],
                // Ключ - временный id родительской записи филиала
                'asdfs3455d2' => [
                    'id' => 'fg3hjhjdf55gj',
                    'address' => 'Адрес филиала 3',
                    'phone' => '+7 412 563 21 47'
                ]
            ],
            'DoctorInClinicLocation' => [
                // Добавляет врача для флилиала 2
                '2' => [
                    'safs2ad7foj' => [
                        'id' => 'safs2ad7foj',
                        'name' => 'Сидоров (Филиал 2)',
                        'specialization' => 'Терапевт'
                    ]
                ],
                // Ключ - временный id родительской записи филиала
                'asdfs3455d2' => [
                    'sdfj7jsfjjsd7af' => [
                        'id' => 'sdfj7jsfjjsd7af',
                        'name' => 'Иванов (Филиал 3)',
                        'specialization' => 'Лор'
                    ],
                    'fjsk1fdjlkjs1kfj' => [
                        'id' => 'fjsk1fdjlkjs1kfj',
                        'name' => 'Петров (Филиал 3)',
                        'specialization' => 'Хирург'
                    ],
                ]
            ],
        ];

        // Загрузка POST данных
        $multiForm->load($postData);

        /* Проверка загрузки POST данных */
        // Проверка загрузки корневой модели
        $clinic = $multiForm->getModel();
        $relatedRecords = $clinic->relatedRecords;
        $this->assertEquals('Новое имя клиники', $clinic->name);
        $this->assertEquals('Новое описание', $clinic->description);

        // Проверка загрузки дочерних моделей
        $clinicContact = $relatedRecords['clinicContact'];
        $this->assertEquals('Адрес клиники', $clinicContact->address);
        $this->assertEquals(ClinicContact::SCENARIO_CREATE, $clinicContact->scenario);

        $clinicContactPhone1 = $clinicContact->relatedRecords['clinicContactPhone'][0];
        $this->assertEquals('+7 264 121 78 96', $clinicContactPhone1->phone);

        $clinicContactPhone2 = $clinicContact->relatedRecords['clinicContactPhone'][1];
        $this->assertEquals('+7 621 477 78 32', $clinicContactPhone2->phone);

        $clinicLocationSecond = $relatedRecords['clinicLocation'][0];
        $this->assertEquals(2, $clinicLocationSecond->id);
        $this->assertEquals(1, $clinicLocationSecond->clinic_id);
        $this->assertEquals('Филиал 2 новое имя', $clinicLocationSecond->name);
        $this->assertEquals('Филиал 2 новое описание', $clinicLocationSecond->description);

        $clinicLocationContact = $clinicLocationSecond->relatedRecords['clinicLocationContact'];
        $this->assertEquals('Адрес филиала 2', $clinicLocationContact->address);
        $this->assertEquals('', $clinicLocationContact->phone);

        $doctorInClinicLocation = $clinicLocationSecond->relatedRecords['doctorInClinicLocation'][0];
        $this->assertEquals('Сидоров (Филиал 2)', $doctorInClinicLocation->name);
        $this->assertEquals('Терапевт', $doctorInClinicLocation->specialization);


        $clinicLocationThree = $relatedRecords['clinicLocation'][1];
        $this->assertEquals(1, $clinicLocationThree->clinic_id);
        $this->assertEquals('Филиал 3', $clinicLocationThree->name);
        $this->assertEquals('Третий филиал', $clinicLocationThree->description);

        $clinicLocationContact = $clinicLocationThree->relatedRecords['clinicLocationContact'];
        $this->assertEquals('Адрес филиала 3', $clinicLocationContact->address);
        $this->assertEquals('+7 412 563 21 47', $clinicLocationContact->phone);

        $doctorInClinicLocation1 = $clinicLocationThree->relatedRecords['doctorInClinicLocation'][0];
        $this->assertEquals('Иванов (Филиал 3)', $doctorInClinicLocation1->name);
        $this->assertEquals('Лор', $doctorInClinicLocation1->specialization);

        $doctorInClinicLocation2 = $clinicLocationThree->relatedRecords['doctorInClinicLocation'][1];
        $this->assertEquals('Петров (Филиал 3)', $doctorInClinicLocation2->name);
        $this->assertEquals('Хирург', $doctorInClinicLocation2->specialization);

        // Проверка элементов на удаление
        $itemsToDelete = $multiForm->getItemsToDelete();
        
        $this->assertEquals('Ай Болит', $itemsToDelete[0]->name);
        $this->assertEquals('Терапевт', $itemsToDelete[0]->specialization);

        $this->assertEquals(1, $itemsToDelete[1]->id);
        $this->assertEquals(1, $itemsToDelete[1]->clinic_id);
        $this->assertEquals('Мега Клиник Филиал 1', $itemsToDelete[1]->name);
        $this->assertEquals('Первый филиал', $itemsToDelete[1]->description);


        // Сохранение данных
        $multiForm->save();

        /* Проверка сохранения данных */
        // Проверка валидации и сохранения корневой модели
        $clinic = $multiForm->getModel();
        $relatedRecords = $clinic->relatedRecords;
        $this->assertTrue($clinic->isValidate);
        $this->assertTrue($clinic->isSave);

        // Проверка валидации и сохранения дочерних моделей
        $clinicContact = $relatedRecords['clinicContact'];
        $this->assertTrue($clinicContact->isValidate);
        $this->assertTrue($clinicContact->isSave);

        $clinicContactPhone1 = $clinicContact->relatedRecords['clinicContactPhone'][0];
        $this->assertTrue($clinicContactPhone1->isValidate);
        $this->assertTrue($clinicContactPhone1->isSave);

        $clinicContactPhone2 = $clinicContact->relatedRecords['clinicContactPhone'][1];
        $this->assertTrue($clinicContactPhone2->isValidate);
        $this->assertTrue($clinicContactPhone2->isSave);

        $clinicLocationSecond = $relatedRecords['clinicLocation'][0];
        $this->assertTrue($clinicLocationSecond->isValidate);
        $this->assertTrue($clinicLocationSecond->isSave);

        $clinicLocationContact = $clinicLocationSecond->relatedRecords['clinicLocationContact'];
        $this->assertTrue($clinicLocationContact->isValidate);
        $this->assertTrue($clinicLocationContact->isSave);

        $doctorInClinicLocation = $clinicLocationSecond->relatedRecords['doctorInClinicLocation'][0];
        $this->assertTrue($doctorInClinicLocation->isValidate);
        $this->assertTrue($doctorInClinicLocation->isSave);

        $clinicLocationThree = $relatedRecords['clinicLocation'][1];
        $this->assertTrue($clinicLocationThree->isValidate);
        $this->assertTrue($clinicLocationThree->isSave);

        $clinicLocationContact = $clinicLocationThree->relatedRecords['clinicLocationContact'];
        $this->assertTrue($clinicLocationContact->isValidate);
        $this->assertTrue($clinicLocationContact->isSave);

        $doctorInClinicLocation1 = $clinicLocationThree->relatedRecords['doctorInClinicLocation'][0];
        $this->assertTrue($doctorInClinicLocation1->isValidate);
        $this->assertTrue($doctorInClinicLocation1->isSave);

        $doctorInClinicLocation2 = $clinicLocationThree->relatedRecords['doctorInClinicLocation'][1];
        $this->assertTrue($doctorInClinicLocation2->isValidate);
        $this->assertTrue($doctorInClinicLocation2->isSave);

        // Проверка удаления элементов
        $itemsToDelete = $multiForm->getItemsToDelete();
        $this->assertTrue($itemsToDelete[0]->isDelete);
        $this->assertTrue($itemsToDelete[1]->isDelete);
    }

    private function getFormMetaData()
    {
        $test = $this;
        return [
            'relations' => [
                'clinicContact' => [
                    'scenario' => ClinicContact::SCENARIO_CREATE,
                    'afterLoad' => function (ClinicContact $clinicContact) use ($test) {
                        // Проверяет установку сценария внутри хука
                        $test->assertEquals(ClinicContact::SCENARIO_CREATE, $clinicContact->scenario);
                    },
                    'relations' => [
                        'clinicContactPhone' => [
                            'afterSave' => function (ClinicContactPhone $clinicContactPhone) use ($test) {
                                // Проверяет сохранение внутри хука
                                $test->assertTrue($clinicContactPhone->isValidate);
                                $test->assertTrue($clinicContactPhone->isSave);
                                
                                return true;
                            },
                        ]
                    ]
                ],
                'clinicLocation' => [
                    'getItems' => function (Clinic $clinic) {
                        $fixtureData = [
                            [
                                // Элемент будет удален, т.к. не присутствует в POST данных.
                                'ClinicLocation' => [
                                    'id' => 1,
                                    'clinic_id' => 1,
                                    'name' => 'Мега Клиник Филиал 1',
                                    'description' => 'Первый филиал'
                                ]
                            ],
                            [
                                'ClinicLocation' => [
                                    'id' => 2,
                                    'clinic_id' => 1,
                                    'name' => 'Мега Клиник Филиал 2',
                                    'description' => 'Второй филиал'
                                ]
                            ]
                        ];

                        $items = [];
                        foreach ( $fixtureData as $clinicLocationData) {
                            $clinicLocation = new ClinicLocation;
                            $clinicLocation->load($clinicLocationData);

                            $items[] = $clinicLocation;
                        }
                        return $items;
                    },
                    'relations' => [
                        'clinicLocationContact' => [],
                        'doctorInClinicLocation' => [
                            'getItems' => function (ClinicLocation $clinicLocation) {
                                if ($clinicLocation->id == 2) {
                                    $doctorInClinicLocation = new DoctorInClinicLocation;
                                    $doctorInClinicLocation->load([
                                        'DoctorInClinicLocation' => [
                                            'id' => 1,
                                            'clinic_location_id' => 2,
                                            'name' => 'Ай Болит',
                                            'specialization' => 'Терапевт'
                                        ]
                                    ]);
                                    return [$doctorInClinicLocation];
                                } else {
                                    return [];
                                }
                            },
                        ]
                    ]
                ],
            ]
        ];
    }

    public function testError()
    {
        // Корневая модель
        $clinic = new Clinic();
        $clinic->load([
            'Clinic' => [
                'id' => 1,
                'name' => 'Мега Клиник',
                'description' => 'Описание Мега Клиник'
            ]
        ]);

        // Инициализация мультиформы
        $multiForm = MultiForm::forClassWithMeta(Clinic::class, $this->getFormMetaData())->empty();
        $multiForm->setModel($clinic);
        $multiForm->prefetchRelations();

        // POST данные для загрузки в мультиформу
        $postData = [
            'Clinic' => [
                'id' => 1,
                // Ошибка 3. Имя должно быть строкой.
                'name' => 105,
                'description' => 'Новое описание'
            ],
            'ClinicContact' => [
                'id' => 'gigi90khk7sgh',
                // Ошибка 2. Имя должно быть строкой.
                'address' => 107,
            ],
            'ClinicLocation' => [
                // Обновляет существующую запись
                '2' => [
                    'id' => 2,
                    // Ошибка 3. Внешний ключ должен быть целым числом.
                    'clinic_id' => 'asfasdfsdaf',
                    'name' => 'Филиал 2',
                    'description' => 'Филиал 2 новое описание'
                ],
            ],
        ];

        $multiForm->load($postData);
        $multiForm->save();

        $clinic = $multiForm->getModel();
        $clinicErrors = $clinic->getErrorSummary(true);
        $relatedRecords = $clinic->relatedRecords;

        // Проверяет, что выполнилась валидация, но не выполнилось сохранение, проверяет ошибку.
        $this->assertTrue($clinic->isValidate);
        $this->assertFalse($clinic->isSave);
        $this->assertEquals('Name must be a string.', $clinicErrors[0]);

        $clinicContact = $relatedRecords['clinicContact'];
        $clinicContactErrors = $clinicContact->getErrorSummary(true);
        $this->assertTrue($clinicContact->isValidate);
        $this->assertFalse($clinicContact->isSave);
        $this->assertEquals('Address must be a string.', $clinicContactErrors[0]);

        $clinicLocationSecond = $relatedRecords['clinicLocation'][0];
        $this->assertTrue($clinicLocationSecond->isValidate);
        $this->assertFalse($clinicLocationSecond->isSave);
    }
}