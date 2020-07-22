Мультиформа
=========
Расширение позволяет загружать, валидировать и сохранять данные с формы, которые имеют иерархический вид.  Обрабатывает случаи добавления, редактирования, удаления элементов во вложенных списках моделей.

***Для использования мультиформы необходимо выполнить слудующие шаги:***
1. Описать массив метаданных мультиформы. Массив метаданных - это массив содержащий структуру связей моделей, их сценарии и хуки.
2. Создать объект мультиформы в контроллере. Объект мультиформы может быть пустым или содержать какие-либо данные корневой модели и вложенных моделей.
3. Сформировать правильную структуру данных, передаваемых методом POST из формы при сохранении.
4. Загрузить POST-данные в мультиформу и сохранить, используя стандартные методы load() и save().

Массив метаданных
---
 Метод $this->getFormMetaData() должен возвращать массив метаданных для мультиформы.  
 **Формат метаданных:**
 ```php
  protected function getFormMetaData()
  {
      return [
          // задает сценарий для корневой модели
          'scenario' => SomeModel::SCENARIO_CREATE,
          // вызывается до загрузки данных с формы
          'beforeLoad' => function (RootModel $model) {
          },
          // вызывается после загрузки данных с формы
          'afterLoad' => function (RootModel $model) {
          },
          // вызывается до сохранения модели
          'beforeSave' => function (RootModel $model) {
          },
          // вызывается после сохранения модели
          'afterSave' => function (RootModel $model) {
          },
          // связи корневой модели
          'relations' => [
               // связь hasMany, у родительской модели должен быть объявлен метод getQuestions который возвращает ActiveQuery используя метод hasMany
               'questions' => [
                   // задает сценарий для связной модели
                   'scenario' => SomeModel::SCENARIO_CREATE,
                   // тут возможно объявление хуков beforeLoad, afterLoad, beforeSave, afterSave аналогично корневой модели
                   // хук поиска существующих моделей, если не задан, то будет производится по id
                   'findExists' => function ($formData, $existItems) {
                       // искать каким-либо образом существующую модель, если не получается найти, можно создавать и возвращать новую модель
                   },
                   // переопределение получения связных элементов, работает только при вызове $multiForm->prefetchRelations
                   'getItems' => function (ParentModel $model) use ($slot) {
                       return $model->getBlocks()->andWhere(['slot' => $slot])->orderBy(['order' => SORT_ASC])->all();
                   },
                   // связи модели
                   'relations' => [
                       'answers' => [],
                   ],
               ],
               // свзязь hasOne, у родительсткой модели должен быть объявлен метод getCity который возвращает ActiveQuery используя метод hasOne
               // NOTE: в текущей реализации обработки hasOne есть ограничение, корректно обрабатывается случай нахождения foreign key в дочерней модели
               'city' => [
                   // задает сценарий для связной модели
                   'scenario' => SomeModel::SCENARIO_CREATE,
                   // тут возможно объявление хуков beforeLoad, afterLoad, beforeSave, afterSave аналогично корневой модели
                   // связи модели
                   'relations' => [
                       ...
                   ],
               ],
               // прочие связи модели
               'otherRelation' => [
                   ...
               ],
               ...
           ]
       ];
  }
```

Объект мультиформы
---
Для примера рассмотрим действия создания и редактирования контроллера:
```php
public function actionCreate()
{
    // Создает пустой объект мультиформы
    // DomainModel - пустой объект корневой модели. 
    $form = MultiFormLoader::forClassWithMeta(DomainModel::class, $this->getFormMetaData())->empty();   
    if ($form->load(Yii::$app->request->post()) && $form->save()) {
        return $this->redirect(['view', 'id' => $form->getModel()->id]);
    } else {
        return $this->render('create', [
            // Возвращает корневую модель
            'model' => $form->getModel(),
        ]);
    }
}
 
public function actionUpdate($id)
{
    $form = MultiFormLoader::forClassWithMeta(DomainModel::class, $this->getFormMetaData())->one($id);
    
    if ($form->load(Yii::$app->request->post()) && $form->save()) {
        return $this->redirect(['view', 'id' => $form->getModel()->id]);
    } else {
        return $this->render('update', [
            'model' => $form->getModel(),
        ]);
    }
}
 ```
POST-данные
---
В качестве примера рассмотрим массив POST-данных из теста. Он имитирует POST-данные, которые приходят на сервер в случае сабмита формы для следующей структуры таблиц:
```
clinic - клиника, корневая моедль
    -id
    -name
    -description
clinic_contact - связь один к одному
    -id
    -clinic_id
    -address
clinic_contact_phone - вложенная связь один ко многим после связи один к одному
    -id
    -clinic_contact_id
    -phone
clinic_location - филиал, связь один ко многим
    -id
    -clinic_id
    -name
    -description
clinic_location_contact - вложенная связь один к одному
    -id
    -clinic_location_id
    -address
    -phone
doctor_in_clinic_location - вложенная связь один ко многим
    -id
    -clinic_location_id
    -name
    -specialization
```

Пример POST-данных:
```php
$postData = [
    'Clinic' => [
        'id' => 1,
        'name' => 'Новое имя клиники',
        'description' => 'Новое описание'
    ],
    'ClinicContact' => [
        // Временный id должен формироваться на стороне представления.
        // Необходим для связи в мультиформе еще не сохраненных записей.
        // Пример JS-функции для формирования временного id элемента приведен ниже.
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
        // Используется существующий id
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
        // Связывает новую запись контакта с существующей записью филиала
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
        // Связывает две новые записи докторов с новой записью филиала
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
```
*Пример JS-функции для формирования временного id элемента*
```js
function getId(item)
{
    if(item.hasOwnProperty('id') && item.id.length !== 0) {
        return item.id;
    }
    if(!item.hasOwnProperty('tid')) {
        item.tid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            let r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    return item.tid;
}
```

Описание теста
---
Наглядно рассмотреть сохранение данных с помощью мультиформы можно в приложенном тесте - tests\MultiformTest. Модели для описанной выше структуры таблиц находятся в  директории tests\models.
В приведенном тесте выполняются следующие действия:
1. Метод getFormMetaData() возвращает массив метаданных структуры мультиформы. В нем прописана структура связей моделей, сценарии, хуки.  
 В ключах 'getItems' определены элементы, которые будут загружны в мультиформу после вызова метода prefetchRelations(). Наличие данных ключей в метаданных и вызов метода prefetchRelations() не обязательно, данная возможность используется в тесте для загрузки исходных данных в мультиформу без использования базы данных.
2. Создается пустая мультиформа в коорую загружается, предварительно созданная, корневая модель. Загружаются исходные данные, определенные в ключах 'getItems' массива мета данных - путем вызова метода prefetchRelations().
3. Проверяются загруженные исходные данные.
4. Иммитируется массив POST-данных.
5. Выполняется загрузка POST-данных в мультиформу с помощью метода load(). С помощью хука 'afterLoad' проверяется установка сценария и работа самого хука.
6. Проверяются данные, загруженные в мультиформу.
7. Выполняется сохранение данных с помощью метода save().
8. Проверяется, что вызваны методы валидации и сохранения для всех моделей.
9. Проверяется, что вызваны методы удаления для записей, которые должны быть удалены.
10. Отдельным методом проверяется случай, когда POST-данные содержат данные, которые не проходят валидацию. Проверяются возвращаемые ошибки.

Запуск теста
---
```
vagrant ssh
cd /app
vendor/bin/phpunit vendor/brain90/yii2-multiform/tests/MultiFormTest.php
```