<?php
namespace Craft;

class ChargeModel extends BaseElementModel
{
    protected $elementType = 'Charge';
    private $statusLabel = '';
    private $payment = null;
    public $subscription = null;

    public static function populateModel($row)
    {
        $model = parent::populateModel($row);

        // Also extract the request info
        if (is_array($model->request)) {
            foreach ($model->request as $key => $val) {
                $model->setAttribute($key, $val);
            }
        }

        // Adjust planCoupon to be coupon for legacy 1.x tempaltes
        $model->setAttribute('planCoupon', $model->coupon);

        return $model;
    }

    /**
     * @return string
     */
    function __toString()
    {
        return $this->id;
    }

    /**
     * @inheritDoc BaseElementModel::getStatus()
     *
     * @return string|null
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        return $this->mode;
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('charge/detail/' . $this->id);
    }

    public function formatCard($char = '&#183;')
    {
        return craft()->charge->formatCard($this->cardLast4, $this->cardType, $char);
    }

    public function currency()
    {
        return $this->planCurrency;
    }

    public function amount()
    {
        return $this->planAmount;
    }

    public function amountInCents()
    {
        return $this->planAmountInCents;
    }

    public function discountAmount()
    {
        return $this->planDiscount;
    }

    public function discountAmountFormatted()
    {
        return $this->formatDiscountAmount('symbol');
    }

    public function formatDiscountAmount($format = 'symbol')
    {
        return $this->_formatAmount($this->planDiscount, $this->planCurrency, $format);
    }

    private function _formatAmount($amount, $currency, $format = 'symbol')
    {
        $charset = craft()->templates->getTwig()->getCharset();

        $currency = ChargePlugin::getCurrencies($currency);

        return new \Twig_Markup(html_entity_decode($currency[$format] . $amount), $charset);
    }

    public function amountFormatted()
    {
        return $this->formatPlanName('symbol');
    }

    public function formatPlanName($format = 'safe')
    {
        $charset = craft()->templates->getTwig()->getCharset();

        $plan = new Charge_PlanModel();
        $plan->interval = $this->planInterval;
        $plan->intervalCount = $this->planIntervalCount;
        $plan->currency = $this->planCurrency;
        $plan->amount = $this->planAmount;
        $plan->amountInCents = $this->planAmountInCents;
        $plan->name = $this->planName;

        $name = $plan->constructPlanName($format);

        return new \Twig_Markup(html_entity_decode($name, ENT_QUOTES), $charset);
    }

    public function formatPlanAmount($format = 'symbol')
    {
        return $this->_formatAmount($this->planAmount, $this->planCurrency, $format);
    }

    public function formatPlanFullAmount($format = 'symbol')
    {
        return $this->_formatAmount($this->planFullAmount, $this->planCurrency, $format);
    }

    public function validate($attributes = null, $clear = true)
    {
        if ($this->planCoupon != '') {
            $this->coupon = $this->planCoupon;
        }

        // if we have a 'plan' attribute, check we have a planOpts
        if ($this->planChoice != '') {
            $this->validatePlanChoice();
        }

        if ($this->planCurrency == '') {
            $this->planCurrency = craft()->charge_stripe->defaultCurrency;
        } else {
            // Validate the currency code is valid.
            $this->validatePlanCurrency();
        }

        parent::validate($attributes, false);

        if ($this->coupon != '') craft()->charge_coupon->handleCoupon($this);

        // Setup the planAmountInCents value
        if (is_numeric($this->planAmount)) {
            $this->planAmountInCents = floor($this->planAmount * 100);
        }

        $event = new Event($this, array(
            'charge' => &$this
        ));

        craft()->charge->onValidate($event);

        // Is the event giving us the go-ahead?
        if ($event->performAction === false) {
            craft()->charge_log->error('Request stopped from plugin via the onValidate event');

            return false;
        }

        return !$this->hasErrors();
    }

    private function validatePlanChoice()
    {
        if ($this->planOpts == '' || $this->planOpts == null || !isset($this->planOpts[$this->planChoice])) {
            $this->addError('plan', 'You must specify a valid plan');
        }

        // Valid on the surface, so assign to attributes
        $selectedPlan = $this->planOpts[$this->planChoice];

        foreach ($selectedPlan as $key => $val) {
            if (in_array($key, ['planAmount', 'planCurrency', 'planInterval', 'planIntervalCount', 'planType',
                'planName'])) {
                $this->$key = $val;
            } else {
                //$this->meta = $this->meta[] = array($key => $val); // @todo handle extra attributes
            }
        }

        return;
    }

    private function validatePlanCurrency()
    {
        if ($this->planCurrency == '') return;


        $currencyDetails = craft()->charge->getCurrency($this->planCurrency);

        if ($currencyDetails == false) {
            $this->addError('planCurrency', 'Please specify a valid currency');
        }

        return;
    }

    public function isRecurring()
    {
        return ($this->paymentType() == 'recurring' ? true : false);
    }

    public function paymentType()
    {
        if ($this->planIntervalCount >= 1) {
            return 'recurring';
        }

        return 'one-off';
    }

    public function isOneOff()
    {
        return ($this->paymentType() == 'recurring' ? false : true);
    }

    public function payments()
    {
        $criteria = craft()->elements->getCriteria('Charge_Payment');
        $criteria->chargeId = $this->id;
        $criteria->order = 'id desc';

        $res = $criteria->find();

        return $res;
    }

    public function customer()
    {
        return craft()->charge_customer->findById($this->customerId);
    }

    public function getUrlFormat()
    {
        return craft()->charge_charge->getUrlFormat();
    }

    public function shortname()
    {
        return $this->getShortname();
    }

    public function getShortname()
    {
        if ($this->type == 'one-off') {

            if ($this->payment == null) {
                $this->payment = $this->payment();
            }

            if ($this->payment != null) {
                return $this->payment->formatAmount();
            }

            return ChargePlugin::formatAmount($this->planAmountInCents, $this->planCurrency);
        } else {
            $this->subscription = $this->subscription();
            return $this->subscription->formatPlanNameShort();
        }
    }

    public function payment()
    {
        $criteria = craft()->elements->getCriteria('Charge_Payment');
        $criteria->chargeId = $this->id;
        $criteria->order = 'id desc';

        $res = $criteria->first();

        return $res;
    }

    public function subscription()
    {
        return craft()->charge_subscription->findByChargeId($this->id);
    }

    public function getHtmlStatusLabel()
    {
        $label = $this->getStatusLabel();

        if ($label == '') return '';

        return '<span class="chargeStatusLabel"><span class="status ' . $this->getStatusColor() . '"></span> ' . ucfirst($this->getStatusLabel()) . '</span>';
    }

    public function getStatusLabel()
    {
        if ($this->type == 'recurring') {
            if ($this->subscription == null) {
                $this->subscription = $this->subscription();
            }

            if ($this->subscription != null) {
                return $this->subscription->getStatusLabel();
            }

        } else {
            // Get the status of the first payment.
            if ($this->payment == null) {
                $this->payment = $this->payment();
            }

            if ($this->payment != null) {
                return $this->payment->getStatusLabel();
            }
        }

        return '';
    }


    public function getStatusColor()
    {
        $label = $this->getStatusLabel();

        if ($label == 'refunded') return 'yellow';

        if ($label == 'paid') return 'green';

        if ($label == 'cancelled') return 'blue';

        if ($label == 'active') return 'green';

        return 'white';
    }

    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'customerId'           => [AttributeType::Number],
            'userId'               => [AttributeType::Number, 'label' => 'User ID'],
            'type'                 => [AttributeType::Enum, 'values' => 'one-time, recurring', 'label' => 'Charge Type'],
            'mode'                 => [AttributeType::Enum, 'values' => 'test,live', 'label' => 'Transaction Mode'],
            'customerName'         => [AttributeType::String, 'label' => 'Name'],
            'customerEmail'        => [AttributeType::Email, 'required' => true, 'label' => 'Email'],
            'description'          => [AttributeType::String, 'label' => 'Description'],
            'hash'                 => [AttributeType::String, 'label' => 'Hash'],
            'notes'                => [AttributeType::String],
            'meta'                 => [AttributeType::Mixed],
            'sourceUrl'            => [AttributeType::Url, 'label' => 'Source URL'],
            'timestamp'            => [AttributeType::DateTime, 'label' => 'Time'],
            'cardId'               => [AttributeType::String, 'label' => 'Payment Card Id'],
            'cardToken'            => [AttributeType::String, 'required' => true, 'label' => 'Stripe Card Token'],
            'cardName'             => [AttributeType::String, 'label' => 'Cardholder Name'],
            'cardAddressLine1'     => [AttributeType::String, 'label' => 'Card Address 1'],
            'cardAddressLine2'     => [AttributeType::String, 'label' => 'Card Address 2'],
            'cardAddressCity'      => [AttributeType::String, 'label' => 'Card Address City'],
            'cardAddressState'     => [AttributeType::String, 'label' => 'Card Address State'],
            'cardAddressZip'       => [AttributeType::String, 'label' => 'Card Address Zip'],
            'cardAddressCountry'   => [AttributeType::String, 'label' => 'Card Address Country'],
            'cardLast4'            => [AttributeType::String, 'label' => 'Card Last 4'],
            'cardType'             => [AttributeType::String, 'label' => 'Card Type'],
            'cardExpMonth'         => [AttributeType::String, 'label' => 'Card Expiry Month'],
            'cardExpYear'          => [AttributeType::String, 'label' => 'Card Expiry Year'],
            'planAmount'           => [AttributeType::Number, 'required' => true, 'label' => 'Amount', 'decimals' => 2],
            'planAmountInCents'    => [AttributeType::Number],
            'planCurrency'         => [AttributeType::String, 'label' => 'Currency'],
            'planInterval'         => [AttributeType::String, 'label' => 'Plan Interval'],
            'planIntervalCount'    => [AttributeType::Number, 'label' => 'Plan Interval Count'],
            'planName'             => [AttributeType::String],
            'planDiscount'         => [AttributeType::Number],
            'planFullAmount'       => [AttributeType::Number],
            'planChoice'           => [AttributeType::String, 'label' => 'Plan Name'],
            'planOpts'             => [AttributeType::Mixed, 'label' => 'Plan Options'],
            'hasDiscount'          => [AttributeType::Bool, 'label' => 'Has a Discount?'],
            'coupon'               => [AttributeType::String],
            'planCoupon'           => [AttributeType::String],
            'couponStripeId'       => [AttributeType::String],
            'stripeAccountBalance' => [AttributeType::Number, 'label' => 'Account Balance'],
            'actions'              => [AttributeType::Mixed],
            'content'              => [AttributeType::Mixed],
            'request'              => [AttributeType::Mixed],
            'createAccount'        => [AttributeType::String],
            'user'                 => [AttributeType::Mixed],
        ]);
    }


}
