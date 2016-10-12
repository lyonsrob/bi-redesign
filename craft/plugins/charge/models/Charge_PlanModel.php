<?php
namespace Craft;

class Charge_PlanModel extends BaseModel
{
    protected function defineAttributes()
    {
        $attributes = [
            'id'            => [AttributeType::Number, 'required' => true],
            'stripeId'      => [AttributeType::String],
            'amount'        => [AttributeType::Number, 'required' => true, 'label' => 'Amount', 'decimals' => 2],
            'amountInCents' => [AttributeType::Number],
            'currency'      => [AttributeType::String],
            'interval'      => [AttributeType::String],
            'intervalCount' => [AttributeType::Number],
            'name'          => [AttributeType::String]
        ];

        return $attributes;
    }


    public function validate()
    {
        $this->name = $this->constructPlanName();
        return true;
    }


    public function constructPlanName($format = 'safe')
    {
        // 75 Every [x] Month(s)
        if($format == 'symbol') {
            $planName[] = craft()->charge->getCurrencySymbol($this->currency).number_format($this->amount, 2);
        } else {
            $planName[] = number_format($this->amount, 2);
            $planName[] = strtoupper($this->currency);
        }

        if($this->intervalCount != null) {
            if ($this->interval == '') $this->interval = 'month';

            if ($this->intervalCount > 1) {
                // every [x] [period]s
                $planName[] = 'Every ' . $this->intervalCount . ' ' . ucwords($this->interval . 's');
            } else {
                if ($this->interval == 'day') {
                    $planName[] = 'Daily';
                } else {
                    $planName[] = ucwords($this->interval . 'ly');
                }
            }
        }

        return implode(' ', $planName);
    }


    private function _constructPlanDescription($period, $period_count)
    {
        $planName = array();

        if ($period_count > 1) {
            // every [x] [period]s
            $planName[] = 'Every ' . $period_count . ' ' . ucwords($period . 's');
        } else {
            if($period == 'day') {
                $planName[] = 'Daily';
            }
            else {
                $planName[] = ucwords($period . 'ly');
            }
        }

        return implode(' ', $planName);
    }

}
