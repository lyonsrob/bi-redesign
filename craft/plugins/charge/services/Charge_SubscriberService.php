<?php
namespace Craft;

class Charge_SubscriberService extends BaseApplicationComponent
{
    private $user;

    public function startNewSubscription($subscriptionName, ChargeModel $chargeModel)
    {
        craft()->charge_log->action('Starting a new Subscription for User', ['subscriptionName' => $subscriptionName]);
        // Subscriptions can only be applied to logged in users. Duh!
        // If they're not logged in, we'll have to just skip this
        $this->user = craft()->userSession->getUser();
        if (is_null($this->user)) {
            craft()->charge_log->error('User is a guest, can\'t be added to a subscription');

            return false;
        }

        $subscription = craft()->charge_membershipSubscription->getMembershipSubscriptionByHandle($subscriptionName);
        if (is_null($subscription)) {
            craft()->charge_log->error('Failed to find a matching membership subscription with the handle : "' . $subscriptionName . '"');

            return false;
        }

        // Ok, looks ok to go ahead.
        $subscriber = $this->createNewSubscriberAndAssign($this->user, $subscription, $chargeModel);

        return $subscriber;
    }


    public function createNewSubscriberAndAssign(UserModel $user, Charge_MembershipSubscriptionModel $subscription, ChargeModel $charge = null)
    {
        $subscriberModel = new Charge_SubscriberModel();

        $subscriberModel->status = 'active';
        $subscriberModel->userId = $user->id;
        $subscriberModel->membershipSubscriptionId = $subscription->id;
        if ($charge != null) {
            $subscriberModel->chargeId = $charge->id;
        }

        if (craft()->elements->saveElement($subscriberModel, false)) {
            $record = new Charge_SubscriberRecord();

            $record->id = $subscriberModel->id;
            $record->status = $subscriberModel->status;
            $record->userId = $subscriberModel->userId;
            $record->membershipSubscriptionId = $subscriberModel->membershipSubscriptionId;
            $record->chargeId = $subscriberModel->chargeId;
            $record->insert();

        } else {
            return false;
        }

        $groupIds = [];
        foreach ($user->getGroups() as $group) {
            $groupIds[] = $group->id;
        }
        if (!in_array($subscription->activeUserGroup, $groupIds)) {
            $groupIds[] = $subscription->activeUserGroup;
        }

        craft()->userGroups->assignUserToGroups($user->id, $groupIds);
        craft()->charge_log->action('Subscription started for "' . $user->email . '". User has been added to the active user group : ' . $subscription->activeUserGroup);

        // Now trigger the end emails
        $emails = $subscription->successEmails;
        if (!empty($emails)) {
            craft()->charge_log->action('Sending subscription start emails', ['emails' => $emails]);

            foreach ($emails as $email) {
                craft()->charge_email->sendByHandle($email, $charge, ['user' => $user, 'subscriber' => $subscriberModel, 'membershipSubscription' => $subscription]);
            }
        }

        return $subscriberModel;
    }


    public function findAndFireRecurringAction(ChargeModel $charge)
    {
        // Let's push on, the
        // Get the subscriber (if they're even in the subscriber set)
        $subscriber = craft()->charge_subscriber->getSubscriberByChargeId($charge->id);
        if (is_null($subscriber)) {
            //craft()->charge_log->note('No matching subscriber for parent charge', ['charge' => $charge]);
            return false;
        }

        // Ok. We have it all.
        // Let's fire any recurring action
        return $this->fireSubscribersRecurringAction($subscriber, $charge);
    }


    public function findAndEndSubscibersSubscription(ChargeModel $charge)
    {
        craft()->charge_log->note('Finding subscriber details to end subscription', ['charge' => $charge]);
        // Let's push on, the
        // Get the subscriber (if they're even in the subscriber set)
        $subscriber = craft()->charge_subscriber->getSubscriberByChargeId($charge->id);
        if (is_null($subscriber)) {
            craft()->charge_log->note('No matching subscriber for parent charge', ['charge' => $charge]);

            return false;
        }

        // Ok. We have it all.
        // Let's end the subscriber's subscription
        return $this->endSubscribersSubscription($subscriber, $charge);
    }


    public function fireSubscribersRecurringAction(Charge_SubscriberModel $subscriber, ChargeModel $chargeModel)
    {
        craft()->charge_log->note('Finding subscriber details to trigger recurring subscriber action', ['charge' => $charge]);

        $user = $subscriber->user();
        if (is_null($user)) {
            craft()->charge_log->error('Cant find the user to match subscriber records', ['subscriber' => $subscriber]);

            return false;
        }

        // Get the actual membership subscription for the subscriber
        $membershipSubscription = craft()->charge_membershipSubscription->getMembershipSubscriptionById($subscriber->membershipSubscriptionId);
        if (is_null($membershipSubscription)) {
            craft()->charge_log->error('Cant find the membership subscription for subscriber', ['subscriber' => $subscriber]);

            return false;
        }

        // Now trigger the recurring emails
        $emails = $membershipSubscription->recurringEmails;
        if (!empty($emails)) {
            craft()->charge_log->action('Sending subscription recurring emails', ['emails' => $emails]);

            foreach ($emails as $email) {
                craft()->charge_email->sendByHandle($email, $chargeModel, ['user' => $user, 'subscriber' => $subscriber, 'membershipSubscription' => $membershipSubscription]);
            }
        }

        return true;
    }


    public function endSubscribersSubscription(Charge_SubscriberModel $subscriber, ChargeModel $chargeModel)
    {
        $user = $subscriber->user();
        if (is_null($user)) {
            craft()->charge_log->error('Cant find the user to match subscriber records', ['subscriber' => $subscriber]);

            return false;
        }

        // Get the actual membership subscription for the subscriber
        $membershipSubscription = craft()->charge_membershipSubscription->getMembershipSubscriptionById($subscriber->membershipSubscriptionId);
        if (is_null($membershipSubscription)) {
            craft()->charge_log->error('Cant find the membership subscription for subscriber', ['subscriber' => $subscriber]);

            return false;
        }

        $subscriber->status = 'expired';

        if (craft()->elements->saveElement($subscriber, false)) {
            $subscriberRecord = Charge_SubscriberRecord::model()->findById($subscriber->id);
            $subscriberRecord->status = 'expired';
            $subscriberRecord->save(false);
        }


        $groupIds = [];
        // Remove the active user group
        foreach ($user->getGroups() as $group) {
            if ($group->id !== $membershipSubscription->activeUserGroup) {
                $groupIds[] = $group->id;
            }
        }
        craft()->userGroups->assignUserToGroups($user->id, $groupIds);
        craft()->charge_log->action('Subscription ended for "' . $user->email . '". User has been removed from the active user group : ' . $membershipSubscription->activeUserGroup);

        // Now trigger the end emails
        $emails = $membershipSubscription->failureEmails;
        if (!empty($emails)) {
            craft()->charge_log->action('Sending subscription end emails', ['emails' => $emails]);

            foreach ($emails as $email) {
                craft()->charge_email->sendByHandle($email, $chargeModel, ['user' => $user, 'subscriber' => $subscriber, 'membershipSubscription' => $membershipSubscription]);
            }
        }

        return true;
    }

    /**
     * @param int $id
     *
     * @return Charge_SubscriberModel|null
     */
    public function getSubscriberById($id)
    {
        return craft()->elements->getElementById($id, 'Charge_Subscriber');
    }

    /**
     * @param int $id
     *
     * @return Charge_SubscriberModel|null
     */
    public function getSubscriberByChargeId($id)
    {
        $criteria = craft()->elements->getCriteria('Charge_Subscriber');
        $criteria->chargeId = $id;

        return $criteria->first();
    }
}
