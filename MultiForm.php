<?php

namespace brain90\multiform;

use phpDocumentor\Reflection\Types\Object_;
use ReflectionClass;
use Yii;
use yii\db\ActiveRecord;

/**
 * Мульти форма. Позволяет загружать, валидировать и сохранять данные с формы, который имеют иерархический вид.
 * Обрабатывает случам добавления, редактирования, удаления элементов во вложенных списках моделей.
 *
 * Действия создания и редактирования контроллера:
 * ```php
 * public function actionCreate()
 * {
 *      $form = MultiForm::forClassWithMeta(DomainModel::class, $this->getFormMetaData())->empty();
 *
 *      if ($form->load(Yii::$app->request->post()) && $form->save()) {
 *          return $this->redirect(['view', 'id' => $form->getModel()->id]);
 *      } else {
 *          return $this->render('create', [
 * 'model' => $form->getModel(),
 *          ]);
 *      }
 * }
 *
 * public function actionUpdate($id)
 * {
 *      $form = MultiForm::forClassWithMeta(DomainModel::class, $this->getFormMetaData())->one($id);
 *
 *      if ($form->load(Yii::$app->request->post()) && $form->save()) {
 *          return $this->redirect(['view', 'id' => $form->getModel()->id]);
 *      } else {
 *          return $this->render('update', [
 *              'model' => $form->getModel(),
 *          ]);
 *      }
 *  }
 * ```
 *
 * Метод $this->getFormMetaData() должен возвращать массив методанных для формы.
 * Формат методанных:
 * ```php
 * protected function getFormMetaData()
 * {
 *      return [
 *           // задает сценарий для корневой модели
 *          'scenario' => SomeModel::SCENARIO_CREATE,
 *          // вызывается до загрузки данных с формы
 *          'beforeLoad' => function (RootModel $model) {
 *          },
 *          // вызывается после загрузки данных с формы
 *          'afterLoad' => function (RootModel $model) {
 *          },
 *          // вызывается до сохранения модели
 *          'beforeSave' => function (RootModel $model) {
 *          },
 *          // вызывается после сохранения модели
 *          'afterSave' => function (RootModel $model) {
 *          },
 *          // связи корневой модели
 *          'relations' => [
 *              // связь hasMany, у родительской модели должен быть объявлен метод getQuestions который возвращает ActiveQuery используя метод hasMany
 *              'questions' => [
 *                  // задает сценарий для связной модели
 *                  'scenario' => SomeModel::SCENARIO_CREATE,
 *                  // тут возможно объявление хуков beforeLoad, afterLoad, beforeSave, afterSave аналогично корневой модели
 *                  // хук поиска существующих моделей, если не задан, то будет производится по id
 *                  'findExists' => function ($formData, $existItems) {
 *                      // искать каким либо образом существующую модель, если не получается найти, ножно создавать и возвращать новую модель
 *                  },
 *                  // переопределение получения связных элементов, работает только при вызове prefetchRelations
 *                  'getItems' => function (ParentModel $model) use ($slot) {
 *                      return $model->getBlocks()->andWhere(['slot' => $slot])->orderBy(['order' => SORT_ASC])->all();
 *                  },
 *                  // связи модели
 *                  'relations' => [
 *                      'answers' => [],
 *                  ],
 *              ],
 *              // свзязь hasOne, у родительсткой модели должен быть объявлен метод getCity который возвращает ActiveQuery используя метод hasOne
 *              // NOTE: в текущей реализации обработки hasOne есть ограничение, корректно обрабатывается случай нахождения foreign key в дочерней модели
 *              'city' => [
 *                  // задает сценарий для связной модели
 *                  'scenario' => SomeModel::SCENARIO_CREATE,
 *                  // тут возможно объявление хуков beforeLoad, afterLoad, beforeSave, afterSave аналогично корневой модели
 *                  // связи модели
 *                  'relations' => [
 *                      ...
 *                  ],
 *              ],
 *              // прочие связи модели
 *              'otherRelation' => [
 *                  ...
 *              ],
 *              ...
 *          ]
 *      ];
 * }
 * ```
 *
 */
class MultiForm
{
    /**
     * @var string название класса корневой модели
     */
    protected $rootModelClassName;

    /**
     * @var array информация о форме
     */
    protected $metaData = [];

    /**
     * @var ActiveRecord корневая модель формы
     */
    protected $rootModel;

    /**
     * @var array ActiveRecord[] элементы для удаления
     */
    protected $itemsToDelete = [];

    /**
     * Инициализирует форму классом модели.
     * @param $className
     * @param $metadata
     * @return MultiForm
     */
    public static function forClassWithMeta($className, $metadata)
    {
        $formLoader = new MultiForm();
        $formLoader->rootModelClassName = $className;
        $formLoader->metaData = $metadata;
        return $formLoader;
    }

    /**
     * Инициализирует пустую форму.
     * @return $this
     */
    public function empty()
    {
        $this->rootModel = new $this->rootModelClassName;
        return $this;
    }

    /**
     * Инициализирует форму моделью.
     * @param $id
     * @return $this
     */
    public function one($id)
    {
        $this->rootModel = $this->rootModelClassName::findOne($id);
        return $this;
    }

    /**
     * @return ActiveRecord
     */
    public function getModel()
    {
        return $this->rootModel;
    }

    /**
     * Устанавливает корневую модель.
     *
     * @param Object $rootModel
     */
    public function setModel($rootModel)
    {
        $this->rootModel = $rootModel;
    }

    /**
     * Возвращает элемнты для удаления.
     *
     * @return ActiveRecord[]
     */
    public function getItemsToDelete()
    {
        return $this->itemsToDelete;
    }

    /**
     * Предварительно извлекает связные данные.
     * Используется с getItems для связных моделей, это нужно для обработки части связных данных.
     * @throws \ReflectionException
     */
    public function prefetchRelations()
    {
        if (!empty($this->metaData['relations'])) {
            foreach ($this->metaData['relations'] as $relationName => $metaItem) {
                list(, , $isHasMany) = $this->getMetaInfo($this->rootModel, $relationName, $metaItem);
                if ($isHasMany) {
                    $items = null;
                    if (!empty($metaItem['getItems'])) {
                        $items = $metaItem['getItems']($this->rootModel);
                    } else {
                        $items = $this->rootModel->$relationName;
                    }
                    $this->processPrefetchRelations($metaItem, $items);
                    $this->rootModel->populateRelation($relationName, $items);
                }
            }
        }
    }

    /**
     * @param $metaData
     * @param $items
     * @throws \ReflectionException
     */
    protected function processPrefetchRelations($metaData, $items)
    {
        if (!empty($metaData['relations'])) {
            foreach ($items as $item) {
                foreach ($metaData['relations'] as $relationName => $metaItem) {
                    list(, , $isHasMany) = $this->getMetaInfo($item, $relationName, $metaItem);
                    if ($isHasMany) {
                        $items = null;
                        if (!empty($metaItem['getItems'])) {
                            $items = $metaItem['getItems']($item);
                        } else {
                            $items = $item->$relationName;
                        }
                        $this->processPrefetchRelations($metaItem, $items);
                        $item->populateRelation($relationName, $items);
                    }
                }
            }
        }
    }

    /**
     * Загружает данные из формы.
     * @param $data
     * @return bool
     * @throws \ReflectionException
     */
    public function load($data)
    {
        if (empty($data)) {
            return false;
        }

        // задает сценарий если указан
        if (!empty($this->metaData['scenario'])) {
            $this->rootModel->scenario = $this->metaData['scenario'];
        }

        // вызывает хук beforeLoad если задан
        if (!empty($this->metaData['beforeLoad'])) {
            call_user_func($this->metaData['beforeLoad'], $this->rootModel);
        }

        // загружает корневую модель
        $this->rootModel->load($data);

        // вызывает хук afterLoad если задан
        if (!empty($this->metaData['afterLoad'])) {
            call_user_func($this->metaData['afterLoad'], $this->rootModel);
        }

        // вызывает хук beforeLoadRelations если задан
        if (!empty($this->metaData['beforeLoadRelations'])) {
            call_user_func($this->metaData['beforeLoadRelations'], $this->rootModel);
        }

        // обрабатывает дочерние элементы
        foreach ($this->metaData['relations'] as $relationName => $metaItem) {
            $relationItems = null;
            list($relationClassName, $relationForm, $isHasMany) = $this->getMetaInfo($this->rootModel, $relationName, $metaItem);
            if ($isHasMany) {
                list($relationItems, $itemsToDelete) = $this->loadHasManyRelation($data, $metaItem, $this->rootModel->$relationName, $data[$relationForm] ?? null, $relationClassName);
            } else {
                list($relationItems, $itemsToDelete) = $this->loadHasOneRelation($data, $metaItem, $this->rootModel->$relationName, $data[$relationForm] ?? null, $relationClassName);
            }

            $this->rootModel->populateRelation($relationName, $relationItems);
            $this->itemsToDelete = array_merge($this->itemsToDelete, $itemsToDelete);
        }

        // вызывает хук afterLoadRelations если задан
        if (!empty($this->metaData['afterLoadRelations'])) {
            call_user_func($this->metaData['afterLoadRelations'], $this->rootModel);
        }

        return true;
    }

    /**
     * Обрабатывает загрузку связи и всех вложенных связей модели.
     * @param $allFormData
     * @param $metaData
     * @param $databaseItems
     * @param $formData
     * @param $className
     * @return array
     * @throws \ReflectionException
     */
    protected function loadHasManyRelation($allFormData, $metaData, $databaseItems, $formData, $className)
    {
        if (empty($formData)) {
            $formData = [];
        }

        $itemsById = [];
        foreach ($databaseItems as $item) {
            $itemsById[$item->id] = $item;
        }

        $actualItems = [];
        foreach ($formData as $key => $item) {
            $model = null;
            if (!empty($metaData['findExists'])) {
                $model = call_user_func($metaData['findExists'], $item, $databaseItems);
                if (!empty($model->id)) {
                    unset($itemsById[$model->id]);
                }
            } else {
                // ищет существующие модели по id. механизм по умолчанию
                /** @var $model ActiveRecord */
                if (empty($item['id']) || !array_key_exists($item['id'], $itemsById)) {
                    $model = new $className;
                } else {
                    $model = $itemsById[$item['id']];
                    unset($itemsById[$item['id']]);
                }
            }

            // обрабатывает загрузку данных в модель
            $this->processLoadModel($allFormData, $model, $metaData, $item, $key);

            $actualItems[] = $model;
        }

        // возвращает массив актуальных данных и данных для удаления
        return [$actualItems, array_values($itemsById)];
    }

    /**
     * @param $allFormData
     * @param $metaData
     * @param $databaseItem
     * @param $formData
     * @param $className
     * @return array
     * @throws \ReflectionException
     */
    protected function loadHasOneRelation($allFormData, $metaData, $databaseItem, $formData, $className)
    {
        // обрабатывает случай удаления модели
        if (empty($formData)) {
            return [null, !empty($databaseItem) ? [$databaseItem] : []];
        }

        /** @var $model ActiveRecord */
        $model = $databaseItem ?? (new $className);

        // обрабатывает загрузку данных в модель
        $this->processLoadModel($allFormData, $model, $metaData, $formData);

        return [$model, []];
    }

    /**
     * @param array $allFormData
     * @param ActiveRecord $model
     * @param array $metaData
     * @param $formData
     * @param $key
     * @throws \ReflectionException
     */
    protected function processLoadModel($allFormData, $model, $metaData, $formData, $key = null)
    {
        // задаёт сценарий если он указан
        if (!empty($metaData['scenario'])) {
            $model->scenario = $metaData['scenario'];
        }

        // вызывает хук до загрузки если задан
        if (!empty($metaData['beforeLoad'])) {
            call_user_func($metaData['beforeLoad'], $model);
        }

        $model->load($formData, '');

        // вызывает хук после загрузки если задан
        if (!empty($metaData['afterLoad'])) {
            call_user_func($metaData['afterLoad'], $model);
        }

        // обрабатывает дочерние элементы
        if (!empty($metaData['relations'])) {
            // вызывает хук beforeLoadRelations если задан
            if (!empty($metaData['beforeLoadRelations'])) {
                call_user_func($metaData['beforeLoadRelations'], $model);
            }

            foreach ($metaData['relations'] as $relationName => $metaItem) {
                list($relationClassName, $relationForm, $isHasMany) = $this->getMetaInfo($model, $relationName, $metaItem);

                // Необходимо в случае загрузки связи hasMany после связи hasOneRelation
                // т.к. в этом случае $key получается не задан
                if($isHasMany && is_null($key) && isset($formData['id'])) {
                    $formDataId = $formData['id'];
                    if (isset($allFormData[$relationForm][$formDataId])) {
                        $key = $formDataId;
                    }
                }

                $relationFormData = !is_null($key) ? $allFormData[$relationForm][$key] ?? null : $allFormData[$relationForm] ?? null;
                if ($isHasMany) {
                    list($relationItems, $itemsToDelete) = $this->loadHasManyRelation($allFormData, $metaItem, $model->$relationName, $relationFormData, $relationClassName);
                } else {
                    list($relationItems, $itemsToDelete) = $this->loadHasOneRelation($allFormData, $metaItem, $model->$relationName, $relationFormData, $relationClassName);
                }

                $model->populateRelation($relationName, $relationItems);
                $this->itemsToDelete = array_merge($this->itemsToDelete, $itemsToDelete);
            }

            // вызывает хук afterLoadRelations если задан
            if (!empty($metaData['afterLoadRelations'])) {
                call_user_func($metaData['afterLoadRelations'], $model);
            }
        }
    }

    /**
     * Возвращает мета-информацию о связи.
     * @param $model
     * @param $relationName
     * @param $item
     * @return array
     * @throws \ReflectionException
     */
    protected function getMetaInfo($model, $relationName, $item)
    {
        $formName = null;
        $childRelations = null;

        $getterMethod = 'get' . $relationName;
        /** @var $query \yii\db\ActiveQuery */
        $query = $model->$getterMethod();

        if (!empty($item['formName'])) {
            $formName = $item['formName'];
        } elseif (empty($formName)) {
            $formName = (new ReflectionClass($query->modelClass))->getShortName();
        }
        return [$query->modelClass, $formName, $query->multiple];
    }

    /**
     * Возвращает информацию о связи модели.
     * @param ActiveRecord $model модель связь которой нужно получить.
     * @param string $relation название связи
     * @return array
     */
    protected function getRelationInfo($model, $relation)
    {
        $getterMethod = 'get' . $relation;
        /** @var $query \yii\db\ActiveQuery */
        $query = $model->$getterMethod();
        return $query->link;
    }

    /**
     * Производит валидацию.
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function validate()
    {
        $hasError = false;

        // вызывает хук beforeValidate если задан
        if (!empty($metaData['beforeValidate'])) {
            $hasError = !call_user_func($metaData['beforeValidate'], $this->rootModel) || $hasError;
        }

        $hasError = !$this->rootModel->validate() || $hasError;

        // вызывает хук afterValidate если задан
        if (!empty($this->metaData['afterValidate'])) {
            $hasError = !call_user_func($this->metaData['afterValidate'], $this->rootModel) || $hasError;
        }

        // вызывает хук beforeValidateRelations если задан
        if (!empty($this->metaData['beforeValidateRelations'])) {
            $hasError = !call_user_func($this->metaData['beforeValidateRelations'], $this->rootModel) || $hasError;
        }

        foreach ($this->metaData['relations'] as $relationName => $metaItem) {
            list(, , $isHasMany) = $this->getMetaInfo($this->rootModel, $relationName, $metaItem);
            if ($isHasMany) {
                $hasError = $this->validateHasManyRelation($this->rootModel, $relationName, $metaItem) || $hasError;
            } else {
                $hasError = $this->validateHasOneRelation($this->rootModel, $relationName, $metaItem) || $hasError;
            }
        }

        // вызывает хук afterValidateRelations если задан
        if (!empty($this->metaData['afterValidateRelations'])) {
            $hasError = !call_user_func($this->metaData['afterValidateRelations'], $this->rootModel) || $hasError;
        }

        return !$hasError;
    }

    /**
     * Производит малидацию связи и всех вложенных связей модели.
     * @param ActiveRecord $model
     * @param string $relation свя
     * @param array $metaData
     * @return bool
     * @throws \ReflectionException
     */
    protected function validateHasManyRelation($model, $relation, $metaData)
    {
        $hasError = false;
        $relationInfo = $this->getRelationInfo($model, $relation);
        $foreignKey = key($relationInfo);
        /** @var ActiveRecord $relationModel */
        foreach ($model->$relation as $relationModel) {
            $hasError = $this->processValidation($relationModel, $foreignKey, $metaData, $hasError);
        }
        return $hasError;
    }

    /**
     * Производит малидацию связи и всех вложенных связей модели.
     * @param ActiveRecord $model
     * @param string $relation свя
     * @param array $metaData
     * @return bool
     * @throws \ReflectionException
     */
    protected function validateHasOneRelation($model, $relation, $metaData)
    {
        $hasError = false;
        /** @var ActiveRecord $relationModel */
        $relationInfo = $this->getRelationInfo($model, $relation);
        $foreignKey = key($relationInfo);

        $hasError = $this->processValidation($model->$relation, $foreignKey, $metaData, $hasError);

        return $hasError;
    }

    /**
     * Обрабатывает валидацию модели.
     * @param ActiveRecord $model
     * @param $foreignKey
     * @param $metaData
     * @param $hasError
     * @return bool
     * @throws \ReflectionException
     */
    protected function processValidation($model, $foreignKey, $metaData, $hasError)
    {
        if (is_null($model)) {
            return $hasError;
        }
        $validateAttributes = $model->activeAttributes();
        $foreignKeyIndex = array_search($foreignKey, $validateAttributes);
        unset($validateAttributes[$foreignKeyIndex]);

        // вызывает хук beforeValidate если задан
        if (!empty($metaData['beforeValidate'])) {
            $hasError = !call_user_func($metaData['beforeValidate'], $model) || $hasError;
        }

        // валидирует модель
        $hasError = !$model->validate($validateAttributes) || $hasError;

        // вызывает хук afterValidate если задан
        if (!empty($metaData['afterValidate'])) {
            $hasError = !call_user_func($metaData['afterValidate'], $model) || $hasError;
        }

        // обрабатывает дочерние элементы
        if (!empty($metaData['relations'])) {
            // вызывает хук beforeValidateRelations если задан
            if (!empty($metaData['beforeValidateRelations'])) {
                $hasError = !call_user_func($metaData['beforeValidateRelations'], $model) || $hasError;
            }

            foreach ($metaData['relations'] as $relationName => $metaItem) {
                list(, , $isHasMany) = $this->getMetaInfo($model, $relationName, $metaItem);
                if ($isHasMany) {
                    $hasError = $this->validateHasManyRelation($model, $relationName, $metaItem) || $hasError;
                } else {
                    $hasError = $this->validateHasOneRelation($model, $relationName, $metaItem) || $hasError;
                }
            }

            // вызывает хук afterValidateRelations если задан
            if (!empty($metaData['afterValidateRelations'])) {
                $hasError = !call_user_func($metaData['afterValidateRelations'], $model) || $hasError;
            }
        }
        return $hasError;
    }

    /**
     * Выполняет сохранение данных.
     * @param bool $runValidation
     * @return bool
     * @throws \ReflectionException
     * @throws \yii\db\Exception
     */
    public function save($runValidation = true)
    {
        if ($runValidation && !$this->validate()) {
            return false;
        }

        // Нужно для теста, т.к. в тесте не используется БД.
        if(!YII_ENV_TEST) {
            $transaction = Yii::$app->db->beginTransaction();
        } else {
            $transaction = null;
        }

        $hasError = false;

        // вызывает хук beforeSave если задан
        if (!empty($this->metaData['beforeSave'])) {
            $hasError = !call_user_func($this->metaData['beforeSave'], $this->rootModel) || $hasError;
        }

        // сохраняет корневую модель
        $hasError = !$this->rootModel->save() || $hasError;

        // вызывает хук afterSave если задан
        if (!empty($this->metaData['afterSave'])) {
            $hasError = !call_user_func($this->metaData['afterSave'], $this->rootModel) || $hasError;
        }

        // вызывает хук beforeSaveRelations если задан
        if (!empty($this->metaData['beforeSaveRelations'])) {
            $hasError = !call_user_func($this->metaData['beforeSaveRelations'], $this->rootModel) || $hasError;
        }

        // инициирует сохранение дочерних
        foreach ($this->metaData['relations'] as $relationName => $metaItem) {
            list(, , $isHasMany) = $this->getMetaInfo($this->rootModel, $relationName, $metaItem);
            if ($isHasMany) {
                if ($this->saveHasManyRelation($this->rootModel, $relationName, $metaItem)) {
                    $hasError = true;
                    break;
                }
            } else {
                if ($this->saveHasOneRelation($this->rootModel, $relationName, $metaItem)) {
                    $hasError = true;
                    break;
                }
            }
        }

        // вызывает хук afterSaveRelations если задан
        if (!empty($this->metaData['afterSaveRelations'])) {
            $hasError = !call_user_func($this->metaData['afterSaveRelations'], $this->rootModel) || $hasError;
        }

        // удаляет элементы
        foreach ($this->itemsToDelete as $item) {
            $item->delete();
        }

        if(!YII_ENV_TEST) {
            if (!$hasError) {
                $transaction->commit();
            } else {
                $transaction->rollBack();
            }
        }
        return !$hasError;
    }

    /**
     * Сохраняет связь и все вложенные связи модели.
     * @param ActiveRecord $model модель связные сущности которой будут сохраняться.
     * @param string $relation название связи.
     * @param array $metaData массив метаданных для получения информации о связях.
     * @return bool в случае успеха вернёт false, если хотябы одна модель не сохраниться, то сразу вернется true.
     * @throws \ReflectionException
     */
    protected function saveHasManyRelation($model, $relation, $metaData)
    {
        /** @var ActiveRecord $relationModel */
        $relationInfo = $this->getRelationInfo($model, $relation);
        $foreignKey = key($relationInfo);
        $pk = $relationInfo[$foreignKey];
        $hasError = false;
        foreach ($model->$relation as $relationModel) {
            $relationModel->$foreignKey = $model->$pk;

            // вызывает хук beforeSave если задан
            if (!empty($metaData['beforeSave'])) {
                $hasError = !call_user_func($metaData['beforeSave'], $relationModel) || $hasError;
            }

            // сохраняет модель
            if (!$relationModel->save()) {
                return true;
            }

            // вызывает хук afterSave если задан
            if (!empty($metaData['afterSave'])) {
                $hasError = !call_user_func($metaData['afterSave'], $relationModel) || $hasError;
            }

            // обрабатывает дочерние элементы
            $hasError = $this->processSaveRelations($relationModel, $metaData) || $hasError;
        }

        return $hasError;
    }

    /**
     * Сохраняет связь и все вложенные связи модели.
     * @param ActiveRecord $model модель связные сущности которой будут сохраняться.
     * @param string $relation название связи.
     * @param array $metaData массив метаданных для получения информации о связях.
     * @return bool в случае успеха вернёт false, если хотябы одна модель не сохраниться, то сразу вернется true.
     * @throws \ReflectionException
     */
    protected function saveHasOneRelation($model, $relation, $metaData)
    {
        if (is_null($model->$relation)) {
            return false;
        }

        $hasError = false;

        $relationInfo = $this->getRelationInfo($model, $relation);
        $foreignKey = key($relationInfo);
        $pk = $relationInfo[$foreignKey];

        $model->$relation->$foreignKey = $model->$pk;

        // вызывает хук beforeSave если задан
        if (!empty($metaData['beforeSave'])) {
            $hasError = !call_user_func($metaData['beforeSave'], $model->$relation) || $hasError;
        }

        // сохраняет модель
        if (!$model->$relation->save()) {
            return true;
        }

        // вызывает хук afterSave если задан
        if (!empty($metaData['afterSave'])) {
            $hasError = !call_user_func($metaData['afterSave'], $model->$relation) || $hasError;
        }

        // обрабатывает дочерние элементы
        return $this->processSaveRelations($model->$relation, $metaData) || $hasError;
    }

    /**
     * Обрабатывает сохранение дочерних элементов.
     * @param $model
     * @param $metaData
     * @return bool
     * @throws \ReflectionException
     */
    protected function processSaveRelations($model, $metaData)
    {
        $hasError = false;
        if (!empty($metaData['relations'])) {

            // вызывает хук beforeSaveRelations если задан
            if (!empty($metaData['beforeSaveRelations'])) {
                $hasError = !call_user_func($metaData['beforeSaveRelations'], $model) || $hasError;
            }

            foreach ($metaData['relations'] as $relationName => $metaItem) {
                list(, , $isHasMany) = $this->getMetaInfo($model, $relationName, $metaItem);
                if ($isHasMany) {
                    if ($this->saveHasManyRelation($model, $relationName, $metaItem)) {
                        return true;
                    }
                } else {
                    if ($this->saveHasOneRelation($model, $relationName, $metaItem)) {
                        return true;
                    }
                }
            }

            // вызывает хук afterSaveRelations если задан
            if (!empty($metaData['afterSaveRelations'])) {
                $hasError = !call_user_func($metaData['afterSaveRelations'], $model) || $hasError;
            }
        }
        return $hasError;
    }
}