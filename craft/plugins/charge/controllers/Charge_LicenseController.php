<?php
namespace Craft;

class Charge_LicenseController extends BaseController
{

    public function actionEdit()
    {
        $licenseKey = craft()->charge_license->getLicenseKey();

        $this->renderTemplate('charge/settings/license', [
            'hasLicenseKey' => ($licenseKey !== null)
        ]);
    }

    public function actionGetLicenseInfo()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        return $this->sendResponse(craft()->charge_license->getLicenseInfo());
    }

    public function actionUpdateLicenseKey()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $licenseKey = craft()->request->getRequiredPost('licenseKey');

        // Are we registering a new license key?
        if ($licenseKey) {
            // Record the license key locally
            try {
                craft()->charge_license->setLicenseKey($licenseKey);
            } catch (InvalidLicenseKeyException $e) {
                $this->returnErrorJson(Craft::t('That license key is invalid.'));
            }

            return $this->sendResponse(craft()->charge_license->registerPlugin($licenseKey));
        } else {
            // Just clear our record of the license key
            craft()->charge_license->setLicenseKey(null);
            craft()->plugins->setPluginLicenseKeyStatus('Charge', LicenseKeyStatus::Unknown);
            return $this->sendResponse();

        }
    }




    public function actionUnregister()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        return $this->sendResponse(craft()->charge_license->unregisterLicenseKey());
    }



    public function actionTransfer()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        return $this->sendResponse(craft()->charge_license->transferLicenseKey());
    }



    private function sendResponse($success = true)
    {
        if($success) {
            $this->returnJson([
                'success'          => true,
                'licenseKey'       => craft()->charge_license->getLicenseKey(),
                'licenseKeyStatus' => craft()->plugins->getPluginLicenseKeyStatus('Charge'),
            ]);
        } else {
            $this->returnErrorJson(craft()->charge_license->error);
        }
    }
}
