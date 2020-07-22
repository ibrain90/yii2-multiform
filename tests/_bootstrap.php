<?php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_APP_BASE_PATH') or define('YII_APP_BASE_PATH', __DIR__.'/../../../../');

require YII_APP_BASE_PATH . 'vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../MultiForm.php';
require_once __DIR__ . '/models/Clinic.php';
require_once __DIR__ . '/models/ClinicContact.php';
require_once __DIR__ . '/models/ClinicContactPhone.php';
require_once __DIR__ . '/models/ClinicLocation.php';
require_once __DIR__ . '/models/ClinicLocationContact.php';
require_once __DIR__ . '/models/DoctorInClinicLocation.php';
