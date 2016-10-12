<?php
namespace Craft;

class Charge_SettingsController extends Charge_BaseCpController
{
    public $allowAnonymous = false;
    private $plugin;

    public function init()
    {
        craft()->userSession->requirePermission('accessplugin-charge');
        $this->plugin = craft()->plugins->getPlugin('charge');
    }

    public function actionIndex()
    {
        $configured = false;
        if(craft()->charge_charge->getUrlFormat() != '') $configured = true;

        $variables = [
            'configured' => $configured,
            'status'     => craft()->charge_stripe->validateConnectionModes(),
            'mode'       => craft()->charge->getMode(),
            'callback'   => craft()->charge_webhook->getMostRecent(),
            'edition'    => craft()->charge_license->getEdition(),
            'connect'    => craft()->charge_connect->getConnectEnabledStatus()];

        $this->renderTemplate('charge/settings/_index', $variables);
    }


    public function actionLogs()
    {
        $logLevels = ['1' => 'Exceptions & Errors only', '10' => 'Full logging (primarily for use during development & testing)'];
        $logRetention = ['-1' => 'Forever', '1' => '1 Hour', '24' => '24 Hours', '168' => '7 Days', '720' => '30 Days', '2160' => '90 Days'];


        $baseSettings = $this->plugin->getSettings()->logs;
        $enabled = craft()->charge_log->enabled;
        $retention = craft()->charge_log->retention;

        if (!isset($baseSettings['enabled'])) {
            $baseSettings['enabled'] = $enabled;
        }

        if (!isset($baseSettings['retention'])) {
            $baseSettings['retention'] = $retention;
        }

        $variables = [
            'logs'             => $baseSettings,
            'logLevels'        => $logLevels,
            'logRetention'     => $logRetention,
            'settingsEditable' => !$this->plugin->isConfigOverridden('logs'),
        ];

        $this->renderTemplate('charge/settings/_logs', $variables);
    }

    public function actionSaveLogs()
    {
        $this->saveSettings('logs');
    }

    private function saveSettings($group, $data = [])
    {
        $this->requirePostRequest();
        $settings = craft()->request->getPost($group);

        $settings = [$group => $settings];

        if (craft()->plugins->savePluginSettings($this->plugin, $settings)) {
            craft()->userSession->setNotice(Craft::t('Settings saved.'));
            $this->redirectToPostedUrl();
        }
        craft()->userSession->setError(Craft::t('Couldn\'t save the settings.'));

        // Send the plugin back to the template
        craft()->urlManager->setRouteVariables([]);
    }

    public function actionStripeWebhook(array $variables = [])
    {
        $path = 'charge/webhook/callback';
        $path = craft()->config->get('actionTrigger') . '/' . trim($path, '/');
        $variables = [
            'url'      => UrlHelper::getSiteUrl($path),
            'callback' => craft()->charge_webhook->getMostRecent()
        ];

        $this->renderTemplate('charge/settings/stripe/webhook', $variables);
    }

    public function actionStripeCredentials(array $variables = [])
    {
        $currencies = [];

        foreach (craft()->charge->getCurrencies('all') as $key => $currency) {
            $currencies[strtoupper($key)] = strtoupper($key) . ' - ' . $currency['name'];
        }

        $variables = [
            'credentials'      => $this->plugin->getSettings()->credentials,
            'settingsEditable' => !$this->plugin->isConfigOverridden('credentials'),
            'currencies'       => $currencies];

        $this->renderTemplate('charge/settings/stripe/index', $variables);
    }

    public function actionCharges(array $variables = [])
    {
        $variables = [
            'charges'          => $this->plugin->getSettings()->charges,
            'settingsEditable' => !$this->plugin->isConfigOverridden('charges')];

        $this->renderTemplate('charge/settings/_charges', $variables);
    }

    public function actionSaveChargeSettings(array $variables = [])
    {
        // Make sure we have the urlFormat and template
        $template = craft()->request->getPost('charges.template');
        $urlFormat = craft()->request->getPost('charges.urlFormat');

        $errors = [];

        if($template == '') {
            $errors['template'] = 'Required Field';
        }
        if($urlFormat == '') {
            $errors['urlFormat'] = 'Required Field';
        }

        if(!empty($errors)) {

            $charges = $this->plugin->getSettings()->charges;

            $charges['template'] = $template;
            $charges['urlFormat'] = $urlFormat;

            // Send the source back to the template
            craft()->urlManager->setRouteVariables(array(
                'charges' => $charges,
                'errors' => $errors
            ));

            craft()->userSession->setError(Craft::t('Couldn’t save settings.'));
            $this->redirect('charge/settings/charges');

        } else {
            $this->saveSettings('charges');
        }
    }

    public function actionStripeMode(array $variables = [])
    {
        $variables = [
            'mode'             => $this->plugin->getSettings()->mode,
            'settingsEditable' => !$this->plugin->isConfigOverridden('mode'),
            'accountModes'     => ['test' => 'Test Mode', 'live' => 'Live Mode']];

        $this->renderTemplate('charge/settings/stripe/mode', $variables);
    }

    public function actionSaveMode(array $variables = [])
    {
        $this->saveSettings('mode');
    }

    public function actionSaveCredentials(array $variables = [])
    {
        craft()->cache->delete('chargeStripeConnectionModes');
        $this->saveSettings('credentials');
        craft()->charge_stripe->validateConnection(true);
    }

    public function actionTests(array $variables = [])
    {
        $this->renderTemplate('charge/settings/tests/index', $variables);
    }

    public function actionConnect(array $variables = [])
    {
        $variables = [
            'connect'          => $this->plugin->getSettings()->connect,
            'settingsEditable' => !$this->plugin->isConfigOverridden('connect'),
            'modes'            => ['false' => 'Disabled', 'true' => 'Enabled'],
            'callbackUrl'      => craft()->charge_connect->getRedirectUrl()];

        $this->renderTemplate('charge/settings/_connect', $variables);
    }

    public function actionSaveConnect()
    {
        $this->requirePostRequest();
        $this->saveSettings('connect');
    }

    public function actionEmails(array $variables = [])
    {
        $variables['emails'] = craft()->charge_email->getAll();
        $variables['isPro'] = craft()->charge_license->isProEdition();

        $this->renderTemplate('charge/settings/email/index', $variables);
    }

    public function actionUserReg(array $variables = [])
    {
        $variables['userreg'] = $this->plugin->getSettings()->userreg;
        $variables['isPro'] = craft()->charge_license->isProEdition();
        $variables['settingsEditable'] = !$this->plugin->isConfigOverridden('connect');

        $this->renderTemplate('charge/settings/_userreg', $variables);
    }

    public function actionSaveUserReg()
    {
        $this->requirePostRequest();
        $this->saveSettings('userreg');
    }

    /**
     * Template layout edit
     */
    public function actionEditFields()
    {
        $variables['title'] = 'Edit Charge Fields';
        $variables['item'] = new ChargeModel();
        $variables['isPro'] = craft()->charge_license->isProEdition();

        $this->renderTemplate('charge/settings/fields/_edit', $variables);
    }


    /**
     * Template layout edit
     */
    public function actionSaveLayout()
    {
        $template = new ChargeModel();

        // Set the field layout
        $fieldLayout = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Charge';
        craft()->fields->deleteLayoutsByType('Charge');

        if (craft()->fields->saveLayout($fieldLayout)) {
            craft()->userSession->setNotice(Craft::t('Charge fields saved.'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save charge fields.'));
        }


        // Send the feature type back to the template
        craft()->urlManager->setRouteVariables(array(
            'template' => $template
        ));
    }

}
