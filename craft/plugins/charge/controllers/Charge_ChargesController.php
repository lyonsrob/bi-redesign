<?php
namespace Craft;

class Charge_ChargesController extends Charge_BaseCpController
{

    public function actionIndex(array $variables = [])
    {
        $this->renderTemplate('charge/index', $variables);
    }


    public function actionView(array $variables = [])
    {
        $chargeId = $variables['chargeId'];
        $charge = craft()->charge->getChargeById($chargeId);
        if ($charge == null) $this->redirect('charge');
        $variables['charge'] = $charge;
        $variables['chargeModel'] = new ChargeModel();

        $variables['tabs'] = [];

        foreach ($variables['chargeModel']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($variables['charge']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['charge']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }
            $variables['tabs'][] = [
                'label' => Craft::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }

        $this->renderTemplate('charge/payment/_view', $variables);
    }

    public function actionSaveCharge()
    {
        $this->requirePostRequest();

        $charge = $this->_setChargeFromPost();
        $this->_setContentFromPost($charge);

        if (craft()->charge_charge->saveCharge($charge)) {
            $this->redirectToPostedUrl($charge);
        }

        craft()->userSession->setError(Craft::t("Couldn’t save charge."));
        craft()->urlManager->setRouteVariables([
            'charge' => $charge
        ]);
    }


    public function actionUpdateNotes()
    {
        $this->requirePostRequest();

        $charge = $this->_setChargeFromPost();
        $notes = craft()->request->getPost('notes');


        if (craft()->charge_charge->updateChargeNotes($charge->id, $notes)) {
            $this->redirectToPostedUrl($charge);
        }

        craft()->userSession->setError(Craft::t("Couldn’t save charge."));
        craft()->urlManager->setRouteVariables([
            'charge' => $charge
        ]);
    }


    /**
     * @return Charge_ChargeModel
     * @throws Exception
     */
    private function _setChargeFromPost()
    {
        $chargeId = craft()->request->getPost('chargeId');

        if ($chargeId) {
            $charge = craft()->charge_charge->getChargeById($chargeId);

            if (!$charge) {
                throw new Exception(Craft::t('No charge with the ID “{id}”',
                    ['id' => $chargeId]));
            }
        }

        return $charge;
    }

    /**
     * @param Charge_ChargeModel $charge
     */
    private function _setContentFromPost($charge)
    {
        $charge->setContentFromPost('fields');
    }


    public function actionEndSubscription()
    {
        $this->requirePostRequest();
        $this->requireElevatedSession();
        craft()->charge_log->request('Subscription end request started in CP');

        $subscriptionId = craft()->request->getRequiredPost('subscriptionId');
        if (craft()->charge_subscription->endSubscription($subscriptionId)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => true]);
            } else {
                craft()->userSession->setNotice(Craft::t('Subscription ended.'));
                $this->redirectToPostedUrl();
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => false]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t end subscription.'));
            }
        }
    }

    /**
     * Deletes a charge.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionDeleteCharge()
    {
        $this->requirePostRequest();

        $chargeId = craft()->request->getRequiredPost('chargeId');
        $charge = craft()->charge_charge->getChargeById($chargeId);

        if (!$charge) {
            throw new Exception(Craft::t('No charge exists with the ID “{id}”.',
                ['id' => $chargeId]));
        }

        if (craft()->charge_charge->deleteCharge($charge)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => true]);
            } else {
                craft()->userSession->setNotice(Craft::t('Charge deleted.'));
                $this->redirectToPostedUrl($charge);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => false]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t delete charge.'));
                craft()->urlManager->setRouteVariables(['charge' => $charge]);
            }
        }
    }

    /**
     * Refunds a payment.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionRefundPayment()
    {
        $this->requirePostRequest();
        $this->requireElevatedSession();

        $paymentId = craft()->request->getRequiredPost('paymentId');
        $payment = craft()->charge_payment->getPaymentById($paymentId);


        if (craft()->charge_payment->refundPayment($payment)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => true]);
            } else {
                craft()->userSession->setNotice(Craft::t('Payment refunded.'));
                $this->redirectToPostedUrl($payment);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => false]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t refund payment.'));
                craft()->urlManager->setRouteVariables(['payment' => $payment]);
            }
        }

    }

}
