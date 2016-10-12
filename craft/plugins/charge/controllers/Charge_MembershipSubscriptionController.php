<?php
namespace Craft;

class Charge_MembershipSubscriptionController extends Charge_BaseCpController
{
    public function actionAll(array $variables = [])
    {
        $variables['subscriptions'] = craft()->charge_membershipSubscription->getAllMembershipSubscriptions();
        $variables['isPro'] = craft()->charge_license->isProEdition();

        $this->renderTemplate('charge/settings/subscription/index', $variables);
    }


    public function actionEdit(array $variables = [])
    {
        if (!isset($variables['subscription'])) {

            if (isset($variables['subscriptionId'])) {
                $subscriptionId = $variables['subscriptionId'];
                $variables['subscription'] = craft()->charge_membershipSubscription->getMembershipSubscriptionById($subscriptionId);
            } else {
                // New email, load a blank object
                $variables['subscription'] = new Charge_MembershipSubscriptionModel();
            }
        }

        $emails = craft()->charge_email->getAll(['order' => 'name']);
        $variables['emails'] = \CHtml::listData($emails, 'id', 'name');

        $this->renderTemplate('charge/settings/subscription/_edit', $variables);
    }



    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $subscription = new Charge_MembershipSubscriptionModel();

        // Shared attributes
        $subscription->id = craft()->request->getPost('subscriptionId');
        $subscription->name = craft()->request->getPost('name');
        $subscription->handle = craft()->request->getPost('handle');
        $subscription->activeUserGroup = craft()->request->getPost('activeUserGroup');
        $subscription->enabled = craft()->request->getPost('enabled');
        $successEmailIds = craft()->request->getPost('successEmails', []);
        $recurringEmailIds = craft()->request->getPost('recurringEmails', []);
        $failureEmailIds = craft()->request->getPost('failureEmails', []);

        // Save it
        if (craft()->charge_membershipSubscription->saveSubscription($subscription, $successEmailIds, $recurringEmailIds, $failureEmailIds)) {
            craft()->userSession->setNotice(Craft::t('Subscription saved.'));
            $this->redirectToPostedUrl($subscription);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save subscription.'));
        }

        craft()->urlManager->setRouteVariables(compact('subscription', 'successEmailIds','recurringEmailIds', 'failureEmailIds'));
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requireAjaxRequest();

        $subscriptionId = craft()->request->getRequiredPost('id');

        if (craft()->charge_membershipSubscription->deleteMembershipSubscriptionById($subscriptionId)) {
            $this->returnJson(['success' => true]);
        };
    }

}
