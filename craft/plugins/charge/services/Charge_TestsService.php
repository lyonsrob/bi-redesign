<?php
namespace Craft;

class Charge_TestsService extends BaseApplicationComponent
{
    public $plugin;
    public $settings;
    public $enabled = false;

    public function init()
    {
        $this->plugin = craft()->plugins->getPlugin('charge');
        $this->settings = (isset($this->plugin->settings['tests']) ? $this->plugin->settings['tests'] : []);

        if (isset($this->settings['enabled']) && $this->settings['enabled'] == true) {
            $this->enabled = true;
        }
    }

    public function getTestsEnabledStatus()
    {
        return $this->enabled;
    }


    /**
     * Test Recurring Trigger
     *
     * Used to test if the recurring triggers are correctly running the actions specified for a charge
     * First creates a new charge, with an onRecurring action specified, then passes a dummy trigger that it's
     * recurred to trigger the action.
     */
    public function testRecurringTrigger()
    {
        // 1. Create a dummy charge
        $chargeModel = new ChargeModel();
        $chargeModel->customerEmail = 'test-recurringTrigger@example.com';
        $chargeModel->planAmount = '99.99';
        $chargeModel->planInterval = 'day';
        $chargeModel->planIntervalCount = 1;
        $chargeModel->description = 'Dummy payment used to test recurring trigger actions';
        $chargeModel->actions = ['onRecurring' => ['example' => true, 'email' => ['test-email']]];

        $subId = 'sub_dummy00000_'.StringHelper::randomString(6);

        $extra['subscription'] = [];
        $extra['subscription']['id'] = $subId;
        $extra['subscription']['status'] = 'active';
        $extra['subscription']['object'] = 'subscription';


        if(!craft()->charge_charge->record('recurring', $chargeModel, $extra)) {
            // Failed to create dummy charge model.
            // @todo
        }

        // 2. Create a dummy subscription record to match this charge

        // 2. Spoof out an invoice payment for this charge.
        $paymentArray = [];
        $paymentArray['charge'] = 'ch_dummy000000000000_'.$chargeModel->id;
        $paymentArray['total'] = '123';
        $paymentArray['subscription'] = $subId;

        craft()->charge_payment->recordPaymentFromInvoicePayment($paymentArray);

        return;
    }

}