<?php

namespace Craft;

class Charge_LogService extends BaseApplicationComponent
{
    private $requestKey = null;
    private $pageLimit = 50;
    private $plugin;
    private $settings;
    public $enabled = true; // default to enabled
    public $retention = 24; // default to 24 hours
    private $tableName = 'charge_logs';

    private $clearStateKey = 'chargeLogsCleared';

    public function init()
    {
        $this->tableName = craft()->db->addTablePrefix($this->tableName);
        $this->plugin = craft()->plugins->getPlugin('charge');
        $this->settings = $this->plugin->settings['logs'];

        if (isset($this->settings['enabled']) && $this->settings['enabled'] == true) {
            $this->enabled = true;
        }


        if (isset($this->settings['retention']) && $this->settings['retention'] != '') {
            $this->retention = $this->settings['retention'];

            $this->clearLogsIfRequired();
        }
    }

    public function getLogEnabledStatus()
    {
        return $this->enabled;
    }


    public function note($name, $extra = array(), $mode = null)
    {
        $this->_log('note', $name, $extra, $mode);
        return;
    }

    public function debug($name, $extra = array(), $mode = null)
    {
        if (craft()->config->get('devMode')) {
            $this->_log('debug', $name, $extra, $mode);
            return;
        }
    }

    public function email($name, $extra = array(), $mode = null)
    {
        $this->_log('email', $name, $extra, $mode);
        return;
    }

    public function action($name, $extra = array(), $mode = null)
    {
        $this->_log('action', $name, $extra, $mode);
        return;
    }

    public function test($name, $extra = array(), $mode = null)
    {
        $this->_log('test', $name, $extra, $mode);
        return;
    }

    public function error($name, $extra = array(), $mode = null)
    {
        $this->_log('error', $name, $extra, $mode);
        return;
    }
    public function exception($name, $extra = array(), $mode = null)
    {
        $this->_log('exception', $name, $extra, $mode);
        return;
    }
    public function success($name, $extra = array(), $mode = null)
    {
        $this->_log('success', $name, $extra, $mode);
        return;
    }
    public function info($name, $extra = array(), $mode = null)
    {
        $this->_log('info', $name, $extra, $mode);
        return;
    }
    public function api($name, $extra = array(), $mode = null)
    {
        $this->_log('api', $name, $extra, $mode);
        return;
    }
    public function request($name, $extra = array(), $mode = null)
    {
        $this->_log('request', $name, $extra, $mode);
        return;
    }
    public function callback($name, $extra = array(), $mode = null)
    {
        $this->_log('callback', $name, $extra, $mode);
        return;
    }


    private function _log($level = 'note', $name, $extra = array(), $mode = null)
    {
        if(!$this->enabled) return; // Sorry boyo

        if($this->requestKey == null) {
            $this->requestKey = StringHelper::randomString();
        }

        if($mode == null) {
            $mode = craft()->charge->getMode();
        }

        $record = new Charge_LogRecord();
        $record->mode = $mode;
        if($mode != null) {
            $record->mode = $mode;
        }
        $record->level = $level;
        $record->requestKey = $this->requestKey;

        $record->type = $name;
        $record->extra = $extra;

        $record->insert();

        return;
    }




    public function getAll()
    {
        $records = Charge_LogRecord::model()->findAll(['order' => 'id desc']);

        return Charge_LogModel::populateModels($records);
    }



    public function getTotalThreadedPagesCount()
    {
        $sql = "SELECT count(DISTINCT requestKey) c FROM ".$this->tableName;
        $row = craft()->db->createCommand($sql)->queryRow();

        if($row['c'] == 0) return 1;

        return ceil($row['c'] / $this->pageLimit);
    }


    public function getAllThreaded($page = 1)
    {
        $offset = ($page - 1) * $this->pageLimit;

        $records = Charge_LogRecord::model()->findAll(['order' => 'id desc', 'group' => 'requestKey', 'limit' => $this->pageLimit, 'offset' => $offset]);
        $models = Charge_LogModel::populateModels($records);

        // also get the items per thread
        $temp = [];
        foreach($records as $record) {
            $temp[] = $record['requestKey'];
        }

        $subRecords = Charge_LogRecord::model()->findAllByAttributes(['requestKey' => $temp],['order' => 'id desc']);
        $subModels = Charge_LogModel::populateModels($subRecords);

        $return = [];

        foreach($models as $model) {
            $t = [];
            foreach($subModels as $subModel) {
                if($subModel->requestKey == $model->requestKey) {
                    $t[] = $subModel;
                }
            }
            $return[] = $t;
        }

        return $return;
    }

    public function apiError($eventName, $errorMessage, $extra = array())
    {
        Craft::log('API Error : ' . $eventName . ', with message : "' . $errorMessage . '"', LogLevel::Error);
    }


    public function getLogById($id)
    {
        $record = Charge_LogRecord::model()->findById($id);
        if($record == null) return null;

        $model = Charge_LogModel::populateModel($record);
        return $model;
    }

    public function getLogsByRequestKey($key)
    {
        $records = Charge_LogRecord::model()->findAllByAttributes(['requestKey' => $key], ['order' => 'id desc']);
        return Charge_LogModel::populateModels($records);
    }

    public function getLogsByType($key, $limit = 1)
    {
        $records = Charge_LogRecord::model()->findAllByAttributes(['level' => $key], ['order' => 'id desc', 'limit' => $limit]);
        return Charge_LogModel::populateModels($records);
    }


    public function deleteById($id)
    {
        $record = Charge_LogRecord::model()->findById($id);
        return $record->deleteByPk($id);
    }

    public function deleteAll()
    {
        $record = new Charge_LogRecord();
        $affectedRows = craft()->db->createCommand()->delete($record->getTableName());
        return (bool) $affectedRows;
    }


    public function deleteAllByAge($ageInSeconds)
    {
        // Do this with a straigh sql query for SPEEDZ
        $sql = "DELETE FROM ".$this->tableName." WHERE dateCreated <= (NOW() - INTERVAL ".$ageInSeconds." SECOND)";
        craft()->db->createCommand($sql)->execute();

        return;
    }

    public function deleteByRequestKey($key)
    {
        $records = Charge_LogRecord::model()->findAllByAttributes(['requestKey' => $key]);

        foreach($records as $record)
        {
            $record->deleteByPk($record->id);
        }

        return;
    }

    private function clearLogsIfRequired()
    {
        if($this->retention > 0) {
            // Check the last time we actually cleared them
              if (!craft()->cache->get($this->clearStateKey)) {

                  // Clear logs older than our marker
                  $cacheLength = $this->retention * 3600;
                  $this->deleteAllByAge($cacheLength);

                  craft()->cache->set($this->clearStateKey, true, $cacheLength);
              }
        }
    }
}
