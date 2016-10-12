<?php
namespace Craft;

class Charge_ConnectController extends Charge_BaseController
{
    public $allowAnonymous = ['actionOauthCallback'];

    public function actionIndex(array $variables = [])
    {
        $this->requireAdmin();
        $variables['accounts'] = craft()->charge_connect->getAll();

        $this->renderTemplate('charge/connect/index', $variables);
    }


    public function actionDisconnectAccount()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $user = craft()->userSession->getUser();

        // Get this user's connected account
        $account = craft()->charge_connect->getAccountForUser($user->id);
        if(!is_null($account)) {

            craft()->charge_connect->disconnectAccount($account);
            craft()->userSession->setNotice(Craft::t('Stripe Account disconnected'));

            $this->redirectToPostedUrl();

        } else {
            craft()->userSession->setError(Craft::t('No Connected Account found for your user'));
        }
    }

    public function actionOauthCallback()
    {
        $code = craft()->request->getQuery('code');
        if($code == '') {
            craft()->userSession->setError(Craft::t('Sorry, something went wrong authorising your account.'));
        } else {
            $result = craft()->charge_connect->handleOauthCallback($code);
            if($result) {
                craft()->userSession->setNotice(Craft::t('Stripe account connected'));
                $this->redirect('merchant/account');
            } else {
                craft()->userSession->setError(Craft::t('Sorry, something went wrong authorising your account.'));
            }
        }

        $this->redirect('merchant/account');
    }



}
