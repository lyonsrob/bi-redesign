<?php
namespace Craft;

class Charge_ChargeService extends BaseApplicationComponent
{
    public $extraChargeAttrs = [];

    /**
     * The primary end point for all new charge creations
     * Internally it'll branch off depending on if it's a onetime or recurring payment
     *
     * @param string $type The type of payment to create. Recurring or one-off
     * @param Charge_CustomerModel $customer The customer for the payment.
     * @param int $paymentCardId the payment card id.
     * @param ChargeModel $chargeModel The full charge model with all the details of the inbound payment
     *
     * @return bool Status if the payment attempt worked
     */
    public function create($type, Charge_CustomerModel $customer, $paymentCardId, ChargeModel &$chargeModel)
    {
        if ($type == 'recurring') {
            $response = $this->handlePaymentRecurring($customer, $paymentCardId, $chargeModel);
        } else {
            // One-off
            $response = $this->handlePaymentOneoff($customer, $paymentCardId, $chargeModel);
        }

        if ($response) {
            // We might need to handle the post register actions now
            if(craft()->charge_userRegistration->registerUser == true && craft()->charge_userRegistration->account != null) {
                // We need to handle the postregister action
                craft()->charge_userRegistration->postRegisterGuest($chargeModel);
            }
        }

        return $response;
    }

    /**
     * Gets a charge by the id
     * 
     * @param int $id
     *
     * @return Charge_ChargeModel|null
     */
    public function getChargeById($id)
    {
        return craft()->elements->getElementById($id, 'Charge');
    }

    /**
     * @param Charge_ChargeModel $charge
     *
     * @return bool
     * @throws \CDbException
     */
    public function deleteCharge($charge)
    {
        craft()->charge_userRegistration->triggerEndOfLife($charge);
        return craft()->elements->deleteElementById($charge->id);
    }

    /**
     * @param Charge_ChargeModel $charge
     *
     * @return bool
     * @throws \Exception
     */
    public function saveCharge($charge)
    {
        if (!$charge->id) {
            $chargeRecord = new ChargeRecord();
        } else {
            $chargeRecord = ChargeRecord::model()->findById($charge->id);

            if (!$chargeRecord) {
                throw new Exception(Craft::t('No charge exists with the ID “{id}”',
                    ['id' => $charge->id]));
            }
        }

        $chargeRecord->validate();
        $charge->addErrors($chargeRecord->getErrors());

        try {
            if (!$charge->hasErrors()) {
                if (craft()->elements->saveElement($charge)) {

                    $chargeRecord->id = $charge->id;

                    $chargeRecord->save(false);
                    $charge->id = $chargeRecord->id;

                    return true;
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return false;
    }

    public function getUrlFormat()
    {
        $plugin = craft()->plugins->getPlugin('charge');
        $settings = $plugin->getSettings()->charges;

        if (isset($settings['urlFormat'])) return $settings['urlFormat'];

        return 'thanks/{hash}';
    }

    public function getElementTemplate()
    {
        $plugin = craft()->plugins->getPlugin('charge');
        $settings = $plugin->getSettings()->charges;

        if (isset($settings['template'])) return $settings['template'];

        return '';
    }

    public function updateChargeNotes($id, $notes)
    {
        $record = ChargeRecord::model()->findByPk($id);
        $record->notes = $notes;

        return $record->save();
    }

    // -------------------------------------------------------------------------------------------------
    // ---- PRIVATE METHODS ----------------------------------------------------------------------------
    // -------------------------------------------------------------------------------------------------

    private function handlePaymentRecurring(Charge_CustomerModel $customer, $paymentCardId, $model)
    {
        $plan = craft()->charge_plan->findOrCreate($model);

        if (!$plan) {
            craft()->charge_log->error('Failed to find a plan for recurring payment', ['model' => $model]);

            return false;
        }

        try {
            $attr = ['plan'     => $plan->stripeId,
                     'source'   => $paymentCardId,
                     'metadata' => $model->meta];

            // Do we have a coupon?
            if ($model->couponStripeId != '') {
                $attr['coupon'] = $model->couponStripeId;
            }

            $response = craft()->charge->stripe->subscriptions()->create(
                $customer->stripeId, $attr);

            // Now - because we don't explcitly know if we got a payment from this event
            // we'll run off and get the most recent payment by this customer
            // for a period of 100 ms. If this charge event resulted in a payment
            // it'll be present and we can record it.
            // This is all to work around situations where the webhooks aren't configured
            // which will be most local setups. It'll save a ton of support later on
            $params = [
                'limit'    => 1,
                'customer' => $customer->stripeId,
            ];
            $charges = craft()->charge->stripe->charges()->all($params);
            $thisCharge = null;
            foreach ($charges['data'] as $charge) {
                if ($charge['created'] >= ($response['current_period_start'] - 100)) {
                    $thisCharge = $charge;
                }
            }

            // Record this
            $this->record('recurring', $model, [
                'plan'         => $plan,
                'customer'     => $customer,
                'subscription' => $response,
                'payment'      => $thisCharge
            ]);

            return $response;

        } catch (\Exception $e) {
            craft()->charge_log->exception('Failed adding customer to plan', [
                'plan'      => $plan,
                'customer'  => $customer,
                'exception' => $e->getMessage()]);

            return false;
        }
    }

    private function handlePaymentOneoff(Charge_CustomerModel $customer, $paymentCardId, $model)
    {

        try {
            $chargeAttr = [
                'customer'    => $customer->stripeId,
                'amount'      => $model->planAmount,
                'currency'    => $model->planCurrency,
                'source'      => $paymentCardId,
                'description' => $model->description,
                'metadata'    => $model->meta];

            if (!empty($this->extraChargeAttrs)) {
                foreach ($this->extraChargeAttrs as $key => $val) {
                    if (!is_array($val)) {
                        $chargeAttr[$key] = $val;
                    }
                }
            }

            $response = craft()->charge->stripe->charges()->create($chargeAttr);
            $this->record('one-off', $model, ['customer' => $customer, 'payment' => $response]);

            return $response;

        } catch (\Exception $e) {
            ChargePlugin::log('API - failed during charge creation with message : ' . $e->getMessage());

            return false;
        }
    }

    public function record($type = 'one-off', ChargeModel $model, $extra = array())
    {
        $user = craft()->userSession->getUser();

        // Now handle all our base fields
        $model->hash = md5(uniqid(mt_rand(), true));
        $model->sourceUrl = craft()->request->getPath();
        $model->type = $type;
        $model->mode = craft()->charge->getMode();
        $model->timestamp = new DateTime();
        $model->slug = $model->hash;
        $model->request = $this->setRequestForCharge($model);
        if ($user) $model->userId = $user->id;

        if (isset($extra['customer'])) {
            $model->customerId = $extra['customer']->id;
        }

        if (isset($extra['plan'])) {
            //     $model->plan = $extra['plan']->id;
        }


        if (craft()->elements->saveElement($model, false)) {

            craft()->elements->updateElementSlugAndUri($model, false, false);

            $record = new ChargeRecord();
            $record->id = $model->id;
            $record->type = $model->type;
            $record->hash = $model->hash;
            $record->sourceUrl = $model->sourceUrl;
            $record->mode = $model->mode;
            $record->timestamp = $model->timestamp;
            $record->description = $model->description;
            $record->customerId = $model->customerId;
            $record->userId = $model->userId;
            $record->meta = $model->meta;
            $record->request = $model->request;
            $record->actions = $model->actions;
            $record->insert();

            $model->id = $record->id;

            if (isset($extra['payment'])) {
                craft()->charge_payment->recordPayment($extra['payment'], $model);
            }

            if (isset($extra['subscription'])) {
                $this->_recordSubscription($extra['subscription'], $model);
            }

        } else {
            return false;
        }

        craft()->search->indexElementAttributes($model);

        return true;
    }

    private function _recordSubscription($subscriptionDetails, $chargeModel)
    {
        $record = new Charge_SubscriptionRecord();

        if (!isset($subscriptionDetails['id']) || !isset($subscriptionDetails['object']) || $subscriptionDetails['object'] !=
            'subscription'
        ) {
            return false;
        }

        $record->chargeId = $chargeModel->id;
        $record->userId = $chargeModel->userId;

        $record->stripeId = (isset($subscriptionDetails['id']) ? $subscriptionDetails['id'] : null);
        $record->status = (isset($subscriptionDetails['status']) ? $subscriptionDetails['status'] : null);
        $record->start = (isset($subscriptionDetails['start']) ? $subscriptionDetails['start'] : null);
        $record->customerId = (isset($subscriptionDetails['customer']) ? $subscriptionDetails['customer'] : null);

        $record->cancelAtPeriodEnd = (isset($subscriptionDetails['cancel_at_period_end']) ? $subscriptionDetails['cancel_at_period_end'] : null);
        $record->currentPeriodStart = (isset($subscriptionDetails['current_period_start']) ? $subscriptionDetails['current_period_start'] : null);
        $record->currentPeriodEnd = (isset($subscriptionDetails['current_period_end']) ? $subscriptionDetails['current_period_end'] : null);

        $record->endedAt = (isset($subscriptionDetails['ended_at']) ? $subscriptionDetails['ended_at'] : null);
        $record->trialStart = (isset($subscriptionDetails['trial_start']) ? $subscriptionDetails['trial_start'] : null);
        $record->trialEnd = (isset($subscriptionDetails['trial_end']) ? $subscriptionDetails['trial_end'] : null);
        $record->canceledAt = (isset($subscriptionDetails['canceled_at']) ? $subscriptionDetails['canceled_at'] : null);
        $record->quantity = (isset($subscriptionDetails['quantity']) ? $subscriptionDetails['quantity'] : null);
        $record->applicationFeePercent = (isset($subscriptionDetails['application_fee_percent']) ? $subscriptionDetails['application_fee_percent'] : null);
        $record->discount = (isset($subscriptionDetails['discount']) ? $subscriptionDetails['discount'] : null);
        $record->taxPercent = (isset($subscriptionDetails['tax_percent']) ? $subscriptionDetails['tax_percent'] : null);

        if (isset($subscriptionDetails['plan'])) {
            $plan = $subscriptionDetails['plan'];
            $record->planInterval = (isset($plan['interval']) ? $plan['interval'] : null);
            $record->planName = (isset($plan['name']) ? $plan['name'] : null);
            $record->planAmount = (isset($plan['amount']) ? $plan['amount'] : null);
            $record->planCurrency = (isset($plan['currency']) ? $plan['currency'] : null);
            $record->planStripeId = (isset($plan['id']) ? $plan['id'] : null);
            $record->planIntervalCount = (isset($plan['interval_count']) ? $plan['interval_count'] : null);
            $record->planTrialPeriodDays = (isset($plan['trial_period_days']) ? $plan['trial_period_days'] : null);
        }

        $record->insert();

        return true;
    }

    /**
     * Creates a new ElementRecord, saves and returns it.
     *
     * @access private
     * @return ElementRecord
     */
    private function _createNewElementRecord()
    {
        $elementRecord = new ElementRecord();
        $elementRecord->type = 'Charge';
        $elementRecord->enabled = 1;
        $elementRecord->save();

        return $elementRecord;
    }

    private function setRequestForCharge(ChargeModel $model)
    {
        $request = [];
        $request['planAmount'] = $model->planAmount;
        $request['planCurrency'] = $model->planCurrency;
        $request['planAmountInCents'] = $model->planAmountInCents;
        $request['planName'] = $model->planName;
        $request['planDiscount'] = $model->planDiscount;
        $request['planFullAmount'] = $model->planFullAmount;
        $request['planChoice'] = $model->planChoice;
        $request['planOpts'] = $model->planOpts;
        $request['planInterval'] = $model->planInterval;
        $request['planIntervalCount'] = $model->planIntervalCount;

        $request['cardName'] = $model->cardName;
        $request['cardAddressLine1'] = $model->cardAddressLine1;
        $request['cardAddressLine2'] = $model->cardAddressLine2;
        $request['cardAddressCity'] = $model->cardAddressCity;
        $request['cardAddressState'] = $model->cardAddressState;
        $request['cardAddressZip'] = $model->cardAddressZip;
        $request['cardAddressCountry'] = $model->cardAddressCountry;
        $request['cardLast4'] = $model->cardLast4;
        $request['cardType'] = $model->cardType;
        $request['cardExpMonth'] = $model->cardExpMonth;
        $request['cardExpYear'] = $model->cardExpYear;

        $request['coupon'] = $model->coupon;
        $request['couponStripeId'] = $model->couponStripeId;
        $request['stripeAccountBalance'] = $model->stripeAccountBalance;

        return $request;
    }

}