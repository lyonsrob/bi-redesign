<?php
namespace Craft;

class Charge_SubscribersController extends Charge_BaseCpController
{
    public function actionIndex(array $variables = [])
    {
        $this->renderTemplate('charge/subscribers/index', $variables);
    }



    public function actionView(array $variables = [])
    {
        $subscriberId = $variables['subscriberId'];
        $subscriber = craft()->charge_subscriber->getSubscriberById($subscriberId);

        if ($subscriber == null) $this->redirect('charge/subscribers');

        $variables['subscriber'] = $subscriber;
        $variables['charge'] = $subscriber->charge();

        $this->renderTemplate('charge/subscribers/_view', $variables);
    }



}
