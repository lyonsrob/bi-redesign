<?php
namespace Craft;

class Charge_ActionsService extends BaseApplicationComponent
{
    public $baseActions = ['example','email', 'subscription'];


    public function fireOnFailure(ChargeModel $chargeModel)
    {
        $eventDetails = $this->validateEvent($chargeModel, 'onFailure');
        if ($eventDetails == false) return;

        $this->triggerAction($eventDetails, $chargeModel);
    }


    public function fireOnSuccess(ChargeModel $chargeModel)
    {
        $eventDetails = $this->validateEvent($chargeModel, 'onSuccess');
        if ($eventDetails == false) return;

        $this->triggerAction($eventDetails, $chargeModel);
    }

    public function fireOnRecurring(ChargeModel $chargeModel)
    {
        $eventDetails = $this->validateEvent($chargeModel, 'onRecurring');
        if ($eventDetails == false) return;

        craft()->charge_log->action('Recurring action triggered for Charge', ['eventDetails' => $eventDetails, 'charge' => $chargeModel]);
        $this->triggerAction($eventDetails, $chargeModel);
    }

    private function triggerAction($eventDetails, ChargeModel $chargeModel)
    {
        foreach ($eventDetails as $type => $details) {
            if (!in_array($type, $this->baseActions)) continue;
            // Ok valid type too. Pass over the details to a specific handler.
            $this->triggerActionByType($type, $details, $chargeModel);
        }
    }


    private function triggerActionByType($type, $details, ChargeModel $chargeModel)
    {
        switch ($type) {
            case 'example' : {
                $this->triggerActionExample($details, $chargeModel);
                break;
            }
            case 'email' : {
                $this->triggerActionEmail($details, $chargeModel);
                break;
            }
            case 'subscription' : {
                $this->triggerActionSubscription($details, $chargeModel);
                break;
            }
        }
    }


    private function triggerActionExample($details, ChargeModel $chargeModel)
    {
        // This is an example action trigger. It's used to confirm the actions are triggering
        craft()->charge_log->action('Example action triggered successfully');

        return true;
    }

    private function triggerActionEmail($details, ChargeModel $chargeModel)
    {
        $emails = [];
        craft()->charge_log->action('Email action triggered', ['details' => $details]);

        if (!is_array($details)) {
            // Special case for a passed string.
            $emails[] = $details;
        } else {
            foreach ($details as $detail) {
                $emails[] = $detail;
            }
        }

        foreach ($emails as $email) {
            craft()->charge_email->sendByHandle($email, $chargeModel);
        }
    }



    private function triggerActionSubscription($details, ChargeModel $chargeModel)
    {
        craft()->charge_log->action('Membership subscription action triggered', ['details' => $details]);
        craft()->charge_subscriber->startNewSubscription($details, $chargeModel);
    }


    private function validateEvent(ChargeModel $chargeModel, $eventType)
    {
        if (!isset($chargeModel->actions[$eventType])) {
            return false;
        }

        $details = $chargeModel->actions[$eventType];
        if (!is_array($details) || empty($details)) return false;

        return $details;
    }

}