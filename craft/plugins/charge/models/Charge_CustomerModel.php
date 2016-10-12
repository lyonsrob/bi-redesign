<?php
namespace Craft;

class Charge_CustomerModel extends BaseModel
{
    protected function defineAttributes()
    {
        $attributes = [
            'id'          => [AttributeType::Number, 'required' => true],
            'stripeId'    => [AttributeType::Number, 'required' => true],
            'mode'        => [AttributeType::Enum, 'values' => 'test, live', 'default' => 'test'],
            'userId'      => [AttributeType::Number],
            'email'       => [AttributeType::String],
            'name'        => [AttributeType::String],
            'stripe'      => [AttributeType::Mixed],
            'dateCreated' => [AttributeType::String]
        ];

        return $attributes;
    }


    public function update($data = [])
    {
        if (empty($data)) return;

        $arr = [];
        foreach (['account_balance', 'coupon', 'description', 'email', 'metadata', 'source'] as $key) {
            if (isset($data[$key]) && $data[$key] != '') {
                $arr[$key] = $data[$key];
            }
        }

        try {
            craft()->charge->stripe->customers()->update(
                $this->stripeId,
                $arr);
        } catch (\Exception $e) {
            // Some other api error
            craft()->charge_log->exception('We have a local customer record, but the customer does not exist on the Stripe API. Wiping our local customer reference, and creating a new customer instead');
            return null;
        }

        if (isset($data['name']) && $data['name'] != '') {
            $this->name = $data['name'];

            $customerRecord = craft()->charge_customer->getCustomerRecordById($this->id);
            $customerRecord->name = $this->name;

            if ($customerRecord->validate()) {
                $customerRecord->save();
            }
        }

        return $this;
    }

    public function getPaymentSource(ChargeModel $model)
    {
        if ($model->cardToken != '') {
            return $this->addCard($model->cardToken);
        } else {
            // No token passed, We must use the card on record (if it exists)
            return $this->getCard($model->cardId);
        }
    }


    public function addCard($cardToken, $cardExtra = [])
    {
        try {
            ChargePlugin::log('API - adding a new card to customer');
            // We have to update the actual customer record to make this new card default
            $customer = craft()->charge->stripe->customers()->update($this->stripeId, ['source' => $cardToken]);

            // Set the customer details on the model too
            $this->stripe = $customer;

            return $customer['default_source'];

        } catch (\Exception $e) {

            ChargePlugin::log('Customer - failed adding new card to customer', LogLevel::Error);
            ChargePlugin::log($e->getMessage(), LogLevel::Error);
            craft()->charge->addRequestError('Adding New Card', $e->getMessage());

            return false;
        }
    }

    public function getCard($cardId = '')
    {
        ChargePlugin::log('Customer - getting existing card for custoemr');
        if ($cardId == '') {
            if (!isset($this->stripe['default_source']) || $this->stripe['default_source'] == '') {
                ChargePlugin::log('Customer - no default card exists for customer');

                return false;
            }
            $cardId = $this->stripe['default_source'];
            ChargePlugin::log('Customer - found default card with id - ' . $cardId);
        }

        try {
            ChargePlugin::log('API - Cards, finding by customer/card id : ' . $this->stripeId . ', Card Id : ' . $cardId);
            $card = craft()->charge->stripe->cards()->find($this->stripeId, $cardId);

            if ($card != null) {
                return $cardId;
            }
        } catch (\Exception $e) {
            ChargePlugin::log('API - Cards - failed to find card');
            ChargePlugin::log($e->getMessage());

            return false;
        }
    }


    public function getSavedCards()
    {
        try {
            $cards = craft()->charge->stripe->cards()->all($this->stripeId);

            if (isset($cards['data'])) {
                $data = $cards['data'];

                foreach ($data as $key => $arr) {
                    $data[$key]['formattedCard'] = craft()->charge->formatCard($arr['last4'], $arr['brand']);
                }

                return $data;
            }
        } catch (\Exception $e) {
            return [];
        }
    }


    public function stripeLink()
    {
        $base = 'https://dashboard.stripe.com/';//payments/';
        if ($this->mode == 'test') {
            $base .= 'test/';
        }
        $base .= 'customers/';


        return $base . $this->stripeId;
    }


}
