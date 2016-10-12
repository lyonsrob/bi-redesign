<?php
namespace Craft;

class Charge_SubscriptionModel extends BaseModel
{
    protected function defineAttributes()
    {
        $attributes = [
            'id'                    => [AttributeType::Number, 'required' => true],
            'chargeId'              => [AttributeType::String, 'required' => true],
            'customerId'            => [AttributeType::String, 'required' => true],
            'stripeId'              => [AttributeType::String, 'required' => true],
            'mode'                  => [AttributeType::Enum, 'values' => 'test, live', 'default' => 'test'],
            'active'                => [AttributeType::Bool],
            'status'                => [AttributeType::String],
            'start'                 => [AttributeType::Number],
            'cancelAtPeriodEnd'     => [AttributeType::Bool],
            'currentPeriodStart'    => [AttributeType::Number],
            'currentPeriodEnd'      => [AttributeType::Number],
            'endedAt'               => [AttributeType::Number],
            'trialStart'            => [AttributeType::Number],
            'trialEnd'              => [AttributeType::Number],
            'canceledAt'            => [AttributeType::Number],
            'quantity'              => [AttributeType::Number],
            'applicationFeePercent' => [AttributeType::Number],
            'discount'              => [AttributeType::Number],
            'taxPercent'            => [AttributeType::Number],

            'planAmount'            => [AttributeType::Number],
            'planName'              => [AttributeType::String],
            'planInterval'          => [AttributeType::String],
            'planIntervalCount'     => [AttributeType::Number],
            'planTrialPeriodDays'   => [AttributeType::Number],
            'planCurrency'          => [AttributeType::String],
            'planStripeId'          => [AttributeType::String]];

        return $attributes;
    }



    public function stripeLink()
    {
        $base = 'https://dashboard.stripe.com/';
        if ($this->mode == 'test') {
            $base .= 'test/';
        }
        $base .= 'plans/';

        return $base . $this->planStripeId;
    }

    public function formatPlanNameShort()
    {
        return $this->formatPlanName();
    }

    private function _formatAmount($amount, $currency, $format = 'symbol')
    {
        $charset = craft()->templates->getTwig()->getCharset();

        $currency = ChargePlugin::getCurrencies($currency);

        return new \Twig_Markup(html_entity_decode($currency[$format] . $amount), $charset);
    }


    public function formatPlanName()
    {
        $charset = craft()->templates->getTwig()->getCharset();

        $plan = new Charge_PlanModel();
        $plan->interval = $this->planInterval;
        $plan->intervalCount = $this->planIntervalCount;
        $plan->currency = $this->planCurrency;
        $plan->amount = $this->planAmount / 100;
        $plan->name = $this->planName;

        $name = $plan->constructPlanName('symbol');

        return new \Twig_Markup(html_entity_decode($name, ENT_QUOTES), $charset);
    }

    public function getColorLabel()
    {
        if($this->status == 'cancelled') return 'yellow';

        if($this->status == 'active') return 'green';

        return 'white';
    }

    public static function populateModel($values)
    {
        if($values->cancelAtPeriodEnd == true) {
            $values->status = 'cancelled';
        }
        return parent::populateModel($values);
    }
    
    public function getStatusLabel()
    {
        return $this->status;
    }
}
