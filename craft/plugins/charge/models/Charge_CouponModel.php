<?php
namespace Craft;

class Charge_CouponModel extends BaseModel
{
    protected function defineAttributes()
    {
        return [
            'id'                => [AttributeType::Number],
            'stripeId'          => [AttributeType::String],
			'name'   	        => [AttributeType::String, 'required' => true],
            'code'              => [AttributeType::String, 'required' => true],
            'paymentType'       => [AttributeType::String, 'required' => true],
            'couponType'        => [AttributeType::Enum, 'values' => 'amount,percentage', 'required' => true],
            'percentageOff'     => [AttributeType::Number, 'min' => 0, 'max' => 100],
            'amountOff'         => [AttributeType::Number, 'min' => 0],
            'currency'          => [AttributeType::String],
            'duration'          => [AttributeType::Enum, 'values' => 'forever,once,repeating'],
            'durationInMonths'  => [AttributeType::Number, 'min' => 0],
            'maxRedemptions'    => [AttributeType::Number, 'min' => 0],
            'redeemBy'          => [AttributeType::Number]
        ];
    }


   /**
     * @param null $attributes
     * @param bool $clearErrors
     * @return bool|void
     */
    public function validate($attributes = null, $clearErrors = true)
    {
        // Don't allow whitespace in the code.
      /*  if (preg_match('/\s+/', $this->code)) {
            $this->addError('code', Craft::t('Spaces are not allowed in the coupon code.'));
        }*/

        if($this->couponType == 'percentage' AND $this->percentageOff == '' ) {
            $this->addError('percentageOff', Craft::t('Percentage Off is required'));
        }

        if($this->couponType == 'amount' AND $this->amountOff == '') {
            $this->addError('amountOff', Craft::t('Amount Off is required'));
        }

        if($this->couponType == 'amount' AND $this->amountOff == '') {
            $this->addError('amountOff', Craft::t('Amount Off is required'));
        }

        if($this->couponType == 'amount' AND $this->amountOff == '0') {
            $this->addError('amountOff', Craft::t('Amount Off must be more than 0'));
        }

        if($this->duration == 'repeating' AND ($this->durationInMonths == '' OR $this->durationInMonths == '0'))  {
            $this->addError('durationInMonths', Craft::t('Duration in Months is required if the Duration is set to \'Repeating\'. Set to \'Forever\' for no limit'));
        }



        return parent::validate($attributes, false);
    }

}
