<?php

namespace shqear\lib;

use kartik\alert\Alert;
use kartik\growl\Growl;
use Yii;
use yii\base\Exception;

/**
 * Created by PhpStorm.
 * User: ShqearAxein
 * Date: 02/12/2015
 * Time: 03:34 م
 */
class Notify
{
    const GROWL = 'GROWL';
    const BOOTSTRAP = 'BOOTSTRAP';
    const FOUNDATION = 'FOUNDATION';

    private static $growl_timing = 0;
    private static $growl_delay = 3000;

    private static $bootstrap_timing = 3000;
    private static $bootstrap_delay = 3000;

    private static $alert_counter = 0;

    private static $bootstrapDefaults = [
        'id' => null,
        'title' => null,
        'body' => null,
        'delay' => 0,
        'disposable' => null,
        'showSeparator' => false,
        'customPosition' => false,
    ];

    /**
     * Configurations:
     * type = [success,warning,info,alert,secondary] or null (primary)
     * border = [radius,round] or null
     */
    private static $foundationDefaults = [
        'id' => null,
        'body' => '',
        'border' => false,
        'type' => '',
        'customPosition' => false,
        'showClose' => true,
    ];

    private static $growlDefaults = [
        'id' => null,
        'title' => null,
        'body' => '',
        'delay' => null,
        'showSeparator' => true,
        'disposable' => null,
        'customPosition' => false,
        'pluginOptions' => [
            'showProgressbar' => false,
            'placement' => ['from' => 'top', 'align' => 'right',]
        ]
    ];

    private function registerAlert($id, $type, $config, $disposable = true, $exceptions, $customPosition)
    {
        Yii::$app->session->setFlash($id, [
            'type' => $type,
            'config' => $config,
            'keep' => !$disposable,
            'exceptions' => $exceptions,
            'customPosition' => $customPosition,
        ]);
    }

    static function addAlert($class, $alert_type, $config)
    {
        //Loading dynamic default variables
        switch ($class) {
            case self::GROWL :
                $config = array_merge(['delay' => self::getGrowlTiming()], $config);
                break;
            case self::BOOTSTRAP :
                if (isset($config['delay']) && is_bool($config['delay']))
                    if ($config['delay']) {
                        $config['delay'] = self::getBootstrapTiming();
                    }
        }

        //load defaults:
        switch ($class) {
            case self::GROWL:
                $config = array_merge(static::$growlDefaults, $config);
                static::setIfNot($config, 'icon', self::getIcon($class, $alert_type));
                break;
            case self::BOOTSTRAP:
                $config = array_merge(static::$bootstrapDefaults, $config);
                static::setIfNot($config, 'icon', self::getIcon($class, $alert_type));
                break;
            case self::FOUNDATION:
                $config = array_merge(static::$foundationDefaults, $config, ['type' => $alert_type]);
                break;
        }
        $customPosition = self::getAndRemove($config, 'customPosition');
        if ($customPosition && is_null($config['id'])) {
            throw new Exception('you must set "id" with "customPosition"');
        }
        $disposable = static::getAndRemove($config, 'disposable');
        $exceptions = self::getAndRemove($config, 'exceptions');
        $id = self::getAndRemove($config, 'id');


        //******** prepare common default variables ***********//
        if (!is_string($id)) {
            $id = 'alert-' . microtime() . '-' . static::getAlertCounter();
        }

        if (is_null($disposable)) $disposable = true;
        //***********************************************//


        self::registerAlert(
            $id,
            $class,
            array_merge(['type' => $alert_type,], $config),
            $disposable,
            $exceptions,
            $customPosition
        );
    }

    static function showAlerts($id = null)
    {
        $alerts = Yii::$app->session->allFlashes;
        $result = '';

        foreach ($alerts as $key => $alert) {
            if (!is_array($alert)) {
                //if not array : not suitable for this class : show default output
                $result .= Alert::widget([
                    'type' => static::getTypeFromString(static::BOOTSTRAP, $key),
                    'icon' => self::getIcon(static::BOOTSTRAP, $key),
                    'body' => $alert,
                ]);
            } else {
                // constructed by our class
                // show the alert if not excepted in the current page (controller + action)
                if (!static::isPageExcepted($alert)) {
                    if ((!$id && !$alert['customPosition']) || ($alert['customPosition'] && $id && ($key == $id))) {
                        switch ($alert['type']) {
                            case self::GROWL:
                                $result .= Growl::widget($alert['config']);
                                break;
                            case self::BOOTSTRAP:
                                $result .= Alert::widget($alert['config']);
                                break;
                            case self::FOUNDATION:
                                $result .= self::parseFoundationAlert($key, $alert['config']);
                                break;
                        }
                    }
                }

                if ($alert['keep']) {
                    Yii::$app->session->setFlash($key, $alert);
                }
            }
        }
        return $result;
    }

    static function getGrowlTiming()
    {
        $timing = self::$growl_timing;
        self::$growl_timing += self::$growl_delay;
        return $timing;
    }

    static function getBootstrapTiming()
    {
        $time = self::$bootstrap_timing;
        self::$bootstrap_timing += self::$bootstrap_delay;
        return $time;
    }

    private function getIcon($class, $alert_type, $image_url = null)
    {
        if ($class == self::BOOTSTRAP) {
            switch ($alert_type) {
                case 'success' :
                case Alert::TYPE_SUCCESS:
                    return 'glyphicon glyphicon-ok-sign';
                case 'info':
                case Alert::TYPE_INFO:
                    return 'glyphicon glyphicon-info-sign';
                case 'primary':
                case Alert::TYPE_PRIMARY:
                    return 'glyphicon glyphicon-question-sign';
                case Alert::TYPE_DEFAULT:
                    return 'glyphicon glyphicon-plus-sign';
                case 'warning':
                case Alert::TYPE_WARNING:
                    return 'glyphicon glyphicon-exclamation-sign';
                case 'error':
                case Alert::TYPE_DANGER:
                    return 'glyphicon glyphicon-remove-sign';
            }
        } elseif ($class == self::GROWL) {
            switch ($alert_type) {
                case Growl::TYPE_SUCCESS:
                    return 'glyphicon glyphicon-ok-sign';
                case Growl::TYPE_INFO:
                    return 'glyphicon glyphicon-info-sign';
                case Growl::TYPE_WARNING:
                    return 'glyphicon glyphicon-exclamation-sign';
                case Growl::TYPE_DANGER:
                    return 'glyphicon glyphicon-remove-sign';
                case Growl::TYPE_GROWL:
                    return $image_url;
            }
        }
        return '';
    }


    private function getTypeFromString($class, $alert_type /*, $image_url = null*/)
    {
        if ($class == self::BOOTSTRAP) {
            switch ($alert_type) {
                case 'success' :
                    return Alert::TYPE_SUCCESS;
                case 'primary':
                    return Alert::TYPE_INFO;
                case 'warning':
                    return Alert::TYPE_WARNING;
                case 'error':
                    return Alert::TYPE_DANGER;
            }
        } elseif ($class == self::GROWL) {
            switch ($alert_type) {
                case 'success' :
                    return Growl::TYPE_SUCCESS;
                case 'primary':
                    return Growl::TYPE_INFO;
                case 'warning':
                    return Growl::TYPE_WARNING;
                case 'error':
                    return Growl::TYPE_DANGER;
            }
        }
        return '';
    }

    /**
     * @param $config array configuration array
     * @param $class string notification class
     * <code>
     * Notify::addErrorAlert(
     *     Notify::GROWL,
     *     [
     *         'id' => 'user.verifyEmail',
     *         'title' => 'Warning!',
     *         'body' => 'Please verify your email by clicking this {link}',
     *         'delay' => 3000,
     *         'customPosition' => false,
     *         'disposable' => false,
     *         'exceptions' => [
     *                  ['site' , 'sms-verify']
     *              ],
     *     ]
     * );
     * </code>
     */
    static function addErrorAlert($class, $config)
    {
        switch ($class) {
            case self::GROWL:
                self::addAlert($class, Growl::TYPE_DANGER, $config);
                break;
            case self::BOOTSTRAP:
                self::addAlert($class, Alert::TYPE_DANGER, $config);
                break;
            case self::FOUNDATION:
                self::addAlert($class, 'alert', $config);
                break;
        }
    }

    static function addSuccessAlert($class, $config)
    {
        switch ($class) {
            case self::GROWL:
                self::addAlert($class, Growl::TYPE_SUCCESS, $config);
                break;
            case self::BOOTSTRAP:
                self::addAlert($class, Alert::TYPE_SUCCESS, $config);
                break;
            case self::FOUNDATION:
                self::addAlert($class, 'success', $config);
                break;
        }
    }

    static function addWarningAlert($class, $config)
    {
        switch ($class) {
            case self::GROWL:
                self::addAlert($class, Growl::TYPE_WARNING, $config);
                break;
            case self::BOOTSTRAP:
                self::addAlert($class, Alert::TYPE_WARNING, $config);
                break;
            case self::FOUNDATION:
                self::addAlert($class, 'warning', $config);
                break;
        }
    }

    static function addInfoAlert($class, $config)
    {
        switch ($class) {
            case self::GROWL:
                self::addAlert($class, Growl::TYPE_INFO, $config);
                break;
            case self::BOOTSTRAP:
                self::addAlert($class, Alert::TYPE_INFO, $config);
                break;
            case self::FOUNDATION:
                self::addAlert($class, 'info', $config);
                break;
        }
    }

    static function addDefaultAlert($class, $config)
    {
        switch ($class) {
            case self::BOOTSTRAP:
                self::addAlert(Notify::BOOTSTRAP, Alert::TYPE_DEFAULT, $config);
                break;
            case self::FOUNDATION:
                self::addAlert(Notify::FOUNDATION, 'secondary', $config);
                break;
            default:
                throw new Exception('not allowed to use this alert class here');
        }
    }

    static function addPrimaryAlert($class, $config)
    {
        switch ($class) {
            case self::BOOTSTRAP:
                self::addAlert(Notify::BOOTSTRAP, Alert::TYPE_PRIMARY, $config);
                break;
            case self::FOUNDATION:
                self::addAlert(Notify::FOUNDATION, 'primary', $config);
                break;
            default:
                throw new Exception('not allowed to use this alert class here');
        }
    }

    private static function isPageExcepted($alert)
    {
        // alerts will not show in this pages :
        if (isset($alert['exceptions']) && is_array($alert['exceptions'])) {
            foreach ($alert['exceptions'] as $exception) {
                $controller = Yii::$app->controller->id;
                $action = Yii::$app->controller->action->id;
                if ($controller == $exception[0] && $action == $exception[1]) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public static function getAlertCounter()
    {
        $count = self::$alert_counter;
        self::$alert_counter++;
        return $count;
    }

    private static function getAndRemove(array &$config, $attribute)
    {
        if (!array_key_exists($attribute, $config)) return null;
        $value = $config[$attribute];
        unset($config[$attribute]);
        return $value;
    }

    private static function setIfNot(&$config, $attribute, $value)
    {
        if (!isset($config[$attribute]))
            $config[$attribute] = $value;
    }

    private function parseFoundationAlert($id, $config)
    {
        ob_start();
        ?>
        <div id="<?= $id ?>"
             data-alert
             class="alert-box <?= $config['type'] ? $config['type'] : '' ?> <?= $config['border'] ? $config['border'] : '' ?>">
            <?php if ($config['showClose']) { ?>
                <a href="#" class="close float-<?= LanguageHelpers::ifEnglish('right', 'left') ?>">&times;</a>
            <?php } ?>
            <?= $config['body'] ?>
        </div>
        <?php
        return ob_get_clean();
    }

}