<?php
namespace Craft;

class Charge_EmailController extends Charge_BaseCpController
{
    public function actionAll(array $variables = [])
    {
        $variables['emails'] = craft()->charge_email->getAll();
        $variables['isPro'] = craft()->charge_license->isProEdition();

        $this->renderTemplate('charge/settings/email/index', $variables);
    }


    public function actionDeleteEmail()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');
        $return = craft()->charge_email->deleteEmailById($id);

        return $this->returnJson(['success' => $return]);
    }


    public function actionEdit(array $variables = [])
    {
        $charge = new ChargePlugin;

        if (!isset($variables['email'])) {

            if (isset($variables['emailId'])) {
                $emailId = $variables['emailId'];
                $variables['email'] = craft()->charge_email->getEmailById($emailId);
            } else {
                // New email, load a blank object
                $variables['email'] = new Charge_EmailModel();
            }
        }

        $this->renderTemplate('charge/settings/email/_edit', $variables);
    }


    public function actionSave()
    {
        $this->requirePostRequest();

        $email = new Charge_EmailModel();

        // Shared attributes
        $email->id = craft()->request->getPost('emailId');
        $email->name = craft()->request->getPost('name');
        $email->handle = craft()->request->getPost('handle');
        $email->subject = craft()->request->getPost('subject');
        $email->to = craft()->request->getPost('to');
        $email->bcc = craft()->request->getPost('bcc');
        $email->enabled = craft()->request->getPost('enabled');
        $email->templatePath = craft()->request->getPost('templatePath');

        // Save it
        if (craft()->charge_email->saveEmail($email)) {
            craft()->userSession->setNotice(Craft::t('Email saved.'));
            $this->redirectToPostedUrl($email);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save email.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['email' => $email]);
    }


}
