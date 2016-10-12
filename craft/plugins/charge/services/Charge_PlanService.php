<?php
namespace Craft;


class Charge_PlanService extends BaseApplicationComponent
{

    public function findOrCreate(ChargeModel $model)
    {
        $plan = new Charge_PlanModel();
        $plan->interval = $model->planInterval;
        $plan->intervalCount = $model->planIntervalCount;
        $plan->currency = $model->planCurrency;
        $plan->amount = $model->planAmount;
        $plan->amountInCents = $model->planAmountInCents;
        $plan->name = $model->planName;


        if ($plan->validate()) {
            // We have a plan->stripeName now
            // See if we have one on the remote api
            if ($plan->name == null || $plan->name == '') {
                // Oh noes!
                return false;
            }

            $stripePlan = $this->_findPlan($plan->name);

            if ($stripePlan == false) {
                // No plan, create one
                $stripePlan = $this->_createPlan($plan);
            }


            if ($stripePlan === false || $stripePlan === null || !isset($stripePlan['id'])) {
                return false;
            }

            $plan->stripeId = $stripePlan['id'];
        }

        return $plan;
    }


    private function _findPlan($name)
    {
        try {
            $stripePlan = craft()->charge->stripe->plans()->find($name);
            craft()->charge_log->api('Found existing plan', 'Found existing plan with name "' . $name . '" on Stripe');
        } catch (\Exception $e) {
            // No plan, so return false, and we'll create one in a bit
            craft()->charge_log->info('Plan not found on Stripe, creating', 'Plan named "' . $name . '" does\'t exist on Stripe. Will need to create');
            return false;
        }

        return $stripePlan;
    }

    private function _createPlan(Charge_PlanModel $plan)
    {
        try {
            $arr = ['id'                => $plan->name,
                    'amount'            => $plan->amount,
                    'interval'          => $plan->interval,
                    'interval_count'    => $plan->intervalCount,
                    'name'              => $plan->name,
                    'currency'          => $plan->currency,
                    'trial_period_days' => null];
            $stripePlan = craft()->charge->stripe->plans()->create($arr);

            craft()->charge_log->api('Created a new plan', ['plan' => $stripePlan]);

            return $stripePlan;

        } catch (\Exception $e) {
            // Failed to create

            craft()->charge_log->exception('Failed creating new plan', ['apiError' => $e->getMessage(), 'plan' => $plan]);
            craft()->charge->errors[] = $e->getMessage();

            return false;
        }

        return false;
    }
}