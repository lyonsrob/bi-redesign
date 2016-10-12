<?php
namespace Craft;

class Charge_WebhookService extends BaseApplicationComponent
{
    private $eventMode = 'test';
    private $eventType = '';

    public function getMostRecent()
    {
        $logs = craft()->charge_log->getLogsByType('callback');
        if(empty($logs)) return false;
        return $logs[0];
    }


    public function handleEvent($eventMode, $eventType, $eventBody)
    {
        $this->eventMode = $eventMode;
        $this->eventType = $eventType;

        $name = 'handleEvent_'.implode( explode('.', $eventType), '_' );

        if(method_exists($this, $name)) {
            craft()->charge_log->callback($eventType, $eventMode);
            $this->$name($eventBody);
        } else {
            craft()->charge_log->callback($eventType .' (no handler for this event type, skipping)', $eventMode);
        }
    }

    
    /*
     * Handles an invoice payment succeeded event
     *
     * Invoices are created for any recurring subscription based payment
     * From this, we'll have the parent subscription id, the charge id, and customer id,
     * which is everything we'll need to figure out where to associate the payment
     *
     */
    private function handleEvent_invoice_payment_succeeded($body)
    {
        if(isset($body['data']['object'])) {
            craft()->charge_log->note('Recording new invoice from webhook', ['body' => $body]);
            $chargeArray = $body['data']['object'];
            craft()->charge_payment->recordPaymentFromInvoicePayment($chargeArray);
        }
    }

    /*
        * Handles an invoice payment succeeded event
        *
        * Invoices are created for any recurring subscription based payment
        * From this, we'll have the parent subscription id, the charge id, and customer id,
        * which is everything we'll need to figure out where to associate the payment
        *
        */
    private function handleEvent_customer_subscription_deleted($body)
    {
        if(isset($body['data']['object'])) {
            craft()->charge_log->note('Customer subscription has ended, checking subscribers', ['body' => $body]);
            $subscription = $body['data']['object'];

            craft()->charge_subscription->endSubscriptionFromWebhook($subscription);
        }
    }
}