<?php

namespace Craft;

class Charge_PaymentService extends BaseApplicationComponent
{
    public $errors = [];

    public function getAll()
    {
        $records = Charge_PaymentRecord::model()->findAll(['order' => 'id desc']);

        return Charge_PaymentModel::populateModels($records);
    }


    /*
     * Record a payment triggered from an invoice payment webhook event
     *
     * This payment may already be recorded if this is the first payment
     * In addition, before fully recording the details we need to try to match
     * the details to any customer or subscriptions, and trigger any
     * additional logic we might need from that
     *
     */
    public function recordPaymentFromInvoicePayment($paymentDetails)
    {
        $chargeId = null;
        $customerId = null;
        $subscriptionId = null;
        $parentChargeId = null;

        if (isset($paymentDetails['customer'])) {
            $customerId = $paymentDetails['customer'];
        }
        if (isset($paymentDetails['subscription'])) {
            $subscriptionId = $paymentDetails['subscription'];
        }
        if (isset($paymentDetails['charge'])) {
            $chargeId = $paymentDetails['charge'];
        }

        // First off, find if we already have this recorded, and bail if so
        if ($chargeId == null) {
            return false;
        }

        $existingPayment = $this->findPaymentByStripeId($chargeId);
        if ($existingPayment != null) {
            craft()->charge_log->note('Found existing payment recorded with matching id, skipping', ['payment' => $existingPayment]);

            return true;
        }

        // It's a new payment.
        // We should get the customer details and figure out what charge to associate this with.
        craft()->charge_log->note('Recording new payment', ['payment' => $paymentDetails]);

        // Ok. Now clean up the payment details into something we can actually use
        $subscription = craft()->charge_subscription->findByStripeId($subscriptionId);
        if ($subscription != null) {
            craft()->charge_log->note('Found matching subscription for payment', ['subscription' => $subscription]);
            $paymentDetails['chargeId'] = $subscription->chargeId;
            $parentChargeId = $subscription->chargeId;

            if (isset($paymentDetails['period_start']) && isset($paymentDetails['period_end'])) {
                $subscription->currentPeriodStart = $paymentDetails['period_start'];
                $subscription->currentPeriodEnd = $paymentDetails['period_end'];
                craft()->charge_subscription->updateSubscription($subscription);
            }
        }

        $paymentDetails['amount'] = $paymentDetails['total'];
        $paymentDetails['id'] = $chargeId;
        $paymentModel = $this->recordPayment($paymentDetails);

        if ($paymentModel !== false) {
            // Trigger any recurring events too
            $chargeModel = craft()->charge_charge->getChargeById($parentChargeId);
            if ($chargeModel !== null) {
                craft()->charge_actions->fireOnRecurring($chargeModel);
                craft()->charge_subscriber->findAndFireRecurringAction($chargeModel);
            }
        }
    }

    public function findPaymentByStripeId($stripePaymentId)
    {
        $model = Charge_PaymentRecord::model()->findByAttributes(['stripeId' => $stripePaymentId]);

        return $model;
    }

    public function recordPayment($paymentDetails, $chargeModel = null)
    {
        $paymentModel = new Charge_PaymentModel();

        $map = [
            'id'             => 'stripeId',
            'currency'       => 'currency',
            'mode'           => 'mode',
            'amount'         => 'amount',
            'customer'       => 'customerId',
            'status'         => 'status',
            'refunded'       => 'refunded',
            'paid'           => 'paid',
            'captured'       => 'captured',
            'invoiceId'      => 'invoiceId',
            'status'         => 'status',
            'receiptEmail'   => 'receiptEmail',
            'failureCode'    => 'failureCode',
            'failureMessage' => 'failureMessage',
            'chargeId'       => 'chargeId'
        ];

        $mapCharge = [
            'id'                 => 'chargeId',
            'mode'               => 'mode',
            'userId'             => 'userId',
            'cardType'           => 'cardType',
            'cardLast4'          => 'cardLast4',
            'cardName'           => 'cardName',
            'cardExpMonth'       => 'cardExpMonth',
            'cardExpYear'        => 'cardExpYear',
            'cardAddressLine1'   => 'cardAddressLine1',
            'cardAddressLine2'   => 'cardAddressLine2',
            'cardAddressCity'    => 'cardAddressCity',
            'cardAddressState'   => 'cardAddressState',
            'cardAddressZip'     => 'cardAddressZip',
            'cardAddressCountry' => 'cardAddressCountry'];

        foreach ($map as $key => $val) {
            if (isset($paymentDetails[$key])) {
                $paymentModel->setAttribute($val, $paymentDetails[$key]);
            }
        }

        if (!is_null($chargeModel)) {
            foreach ($mapCharge as $key => $val) {
                if (isset($chargeModel->$key)) {
                    $paymentModel->setAttribute($val, $chargeModel->$key);
                }
            }
        }


        if (craft()->elements->saveElement($paymentModel, false)) {
            $record = new Charge_PaymentRecord();

            $record->id = $paymentModel->id;

            foreach ($mapCharge as $key => $val) {
                $record->$val = $paymentModel->$val;
            }
            foreach ($map as $key => $val) {
                $record->$val = $paymentModel->$val;
            }
            $record->insert();

            $paymentModel->id = $record->id;

        } else {
            return false;
        }


        craft()->search->indexElementAttributes($paymentModel);

        return $paymentModel;
    }

    /**
     * @param int $id
     *
     * @return Charge_PaymentModel|null
     */
    public function getPaymentById($id)
    {
        return craft()->elements->getElementById($id, 'Charge_Payment');
    }


    public function refundPayment(Charge_PaymentModel $paymentModel)
    {
        // Do some quick validation to make sure the charge is ok to attempt a refund
        if ($paymentModel->refunded == true) {
            $this->refreshPaymentRecord($paymentModel);
            craft()->charge_log->success('Payment was already refunded', ['payment' => $paymentModel]);

            return true;
        }

        $stripeId = $paymentModel->stripeId;
        // For now, we'll just refund the whole thing.
        try {
            craft()->charge_log->note('Creating refund for payment', ['payment' => $paymentModel]);
            $refund = craft()->charge->stripe->refunds()->create($stripeId);

            if ($refund['status'] == 'succeeded') {
                $this->refreshPaymentRecord($paymentModel);
                craft()->charge_log->success('Payment Refunded', ['payment' => $paymentModel]);

                return true;
            }

        } catch (\Exception $e) {
            // Failed
            craft()->charge_log->error('Failed to refund payment', ['error' => $e->getMessage()]);
            $this->errors[] = $e->getMessage();
            $this->refreshPaymentRecord($paymentModel); // Just in case it's already been refunded.
            return false;
        }

        craft()->charge_log->error('Failed to refund payment with an unknown error');

        return false;
    }


    private function refreshPaymentRecord(Charge_PaymentModel $paymentModel)
    {
        // The refund has succeeded, we need to update our local record of the payment.
        // We'll get the full charge and update it
        try {
            $updated = craft()->charge->stripe->charges()->find($paymentModel->stripeId);

            // Add the charge
            $record = Charge_PaymentRecord::model()->findById($paymentModel->id);
            $record->amountRefunded = $updated['amount_refunded'];

            if ($updated['amount_refunded'] >= $updated['amount']) {
                $status = 'refunded';
            } else if ($updated['amount_refunded'] == 0) {
                $status = 'succeeded';
            } else {
                $status = 'partially_refunded';
            }

            $record->status = $status;
            $record->refunded = $updated['refunded'];
            $record->save();

        } catch (\Exception $e) {
        }
    }

}