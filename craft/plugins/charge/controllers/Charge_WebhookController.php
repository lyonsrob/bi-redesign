<?php
namespace Craft;

class Charge_WebhookController extends Charge_BaseController
{
    protected $allowAnonymous = true;
    private $requiredKeys = ['id', 'type', 'livemode'];

    private $eventType;
    private $eventId;
    private $eventMode;
    private $eventBody = null;

    public function actionCallback()
    {
        if(!$this->_validateCallback())
        {
            craft()->charge_log->error('Callback failed validation');
            http_response_code(400);
            exit();
        }

        craft()->charge_webhook->handleEvent($this->eventMode, $this->eventType, $this->eventBody);
        http_response_code(200);
        exit();
    }

    private function _validateCallback()
    {
        $body = @file_get_contents('php://input');
        $json = json_decode($body, true);

        if(!is_array($json)) {
            craft()->charge_log->request('Invalid Callback Received', ['body' => $json]);
            return false;
        }

        // Validate we have all the required keys
        foreach($this->requiredKeys as $key)
        {
            if(!isset($json[$key])) {
                craft()->charge_log->error('Callback missing '.$key.' key', ['body' => $json]);
                return false;
            }
        }

        $this->eventMode = 'test';
        if($json['livemode'] === true) $this->eventMode = 'live';

        $this->eventId = $json['id'];
        $this->eventType = $json['type'];
        $this->eventBody = $json;
        return true;
    }

}
