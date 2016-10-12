<?php
namespace Craft;

require __DIR__ . '/vendor/autoload.php';

class ChargePlugin extends BasePlugin
{
    public function init()
    {
        craft()->charge_license->ping();

        if (craft()->request->isCpRequest()) {
            $this->includeCpResources();
            craft()->templates->hook('charge.prepCpTemplate', [$this, 'prepCpTemplate']);
            craft()->templates->hook('charge.prepCpSettingsTemplate', [$this, 'prepCpSettingsTemplate']);
        }
    }

    function getName()
    {
        return Craft::t('Charge');
    }

    function getVersion()
    {
        return '2.1.3';
    }

    public function getSchemaVersion()
    {
        return '2.1.0';
    }

    function getDeveloper()
    {
        return 'Square Bit';
    }

    function getDeveloperUrl()
    {
        return 'https://squarebit.co.uk';
    }

    function getDocumentationUrl()
    {
        return 'https://squarebit.co.uk/software/craft/charge';
    }

    public function getDescription()
    {
        return 'Stripe payments for Craft.';
    }

    function getReleaseFeedUrl()
    {
        return 'https://squarebit.co.uk/software/craft/charge/updates.json';
    }

    public function hasCpSection()
    {
        return true;
    }

    function getSettingsUrl()
    {
        return 'charge/settings';
    }


    public function registerCpRoutes()
    {
        return [
            'charge'                                                => ['action' => 'charge/charges/index'],
            'charge/payments'                                       => ['action' => 'charge/payments'],
            'charge/payments/(?P<paymentId>\d+)'                    => ['action' => 'charge/viewPayment'],
            'charge/subscribers'                                    => ['action' => 'charge/subscribers/index'],
            'charge/subscribers/(?P<subscriberId>\d+)'              => ['action' => 'charge/subscribers/view'],
            'charge/detail/(?P<chargeId>\d+)'                       => ['action' => 'charge/charges/view'],
            'charge/logs'                                           => ['action' => 'charge/log/all'],
            'charge/logs/(?P<logId>\d+)'                            => ['action' => 'charge/log/view'],
            'charge/connect'                                        => ['action' => 'charge/connect/index'],
            'charge/settings'                                       => ['action' => 'charge/settings/index'],
            'charge/settings/stripe'                                => ['action' => 'charge/settings/index'],
            'charge/settings/stripe/credentials'                    => ['action' => 'charge/settings/stripeCredentials'],
            'charge/settings/stripe/mode'                           => ['action' => 'charge/settings/stripeMode'],
            'charge/settings/stripe/webhook'                        => ['action' => 'charge/settings/stripeWebhook'],
            'charge/settings/emails'                                => ['action' => 'charge/email/all'],
            'charge/settings/emails/new'                            => ['action' => 'charge/email/edit'],
            'charge/settings/emails/(?P<emailId>\d+)'               => ['action' => 'charge/email/edit'],
            'charge/settings/subscriptions'                         => ['action' => 'charge/membershipSubscription/all'],
            'charge/settings/subscriptions/new'                     => ['action' => 'charge/membershipSubscription/edit'],
            'charge/settings/subscriptions/(?P<subscriptionId>\d+)' => ['action' => 'charge/membershipSubscription/edit'],
            'charge/settings/logs'                                  => ['action' => 'charge/settings/logs'],
            'charge/settings/charges'                               => ['action' => 'charge/settings/charges'],
            'charge/settings/coupons'                               => ['action' => 'charge/coupon/all'],
            'charge/settings/coupons/new'                           => ['action' => 'charge/coupon/edit'],
            'charge/settings/coupons/(?P<couponId>\d+)'             => ['action' => 'charge/coupon/edit'],
            'charge/settings/fields'                                => ['action' => 'charge/settings/editFields'],
            'charge/settings/userreg'                               => ['action' => 'charge/settings/userReg'],
            'charge/settings/connect'                               => ['action' => 'charge/settings/connect'],
            'charge/settings/tests'                                 => ['action' => 'charge/settings/tests'],
            'charge/settings/license'                               => ['action' => 'charge/license/edit'],

        ];
    }

    protected function defineSettings()
    {
        return [
            'credentials' => [AttributeType::Mixed],
            'mode'        => [AttributeType::Mixed],
            'webhook'     => [AttributeType::Mixed],
            'charges'     => [AttributeType::Mixed],
            'edition'     => [AttributeType::Mixed],
            'connect'     => [AttributeType::Mixed],
            'userreg'     => [AttributeType::Mixed],
            'tests'       => [AttributeType::Mixed],
            'logs'        => [AttributeType::Mixed],
            'licenseKey'  => [AttributeType::String],
            'edition'     => [AttributeType::Mixed]];
    }


    public function getSettings()
    {
        $settings = parent::getSettings();

        $base = $this->defineSettings();
        foreach ($base as $key => $row) {
            $override = craft()->config->get($key, 'charge');
            if (!is_null($override)) {
                $settings->$key = $override;
            }
        }

        return $settings;
    }


    public function isConfigOverridden($group)
    {
        $state = false;

        $override = craft()->config->get($group, 'charge');
        if (!is_null($override)) {
            $state = true;
        }

        return $state;
    }


    public function initAutoloader()
    {
        require(__DIR__ . '/vendor/autoload.php');
    }


    public static function getCurrencies($key = 'all')
    {
        return craft()->charge->getCurrencies($key);
    }


    public function prepCpTemplate(&$context)
    {
        $user = craft()->userSession->getUser();

        $context['subnav']['charge'] = ['label' => Craft::t('Charges'), 'url' => 'charge'];

        if (craft()->charge_membershipSubscription->systemHasAnySubscriptions()) {
            $context['subnav']['subscribers'] = ['label' => Craft::t('Subscribers'), 'url' => 'charge/subscribers'];
        }

        if (craft()->charge_connect->getConnectEnabledStatus()) {
            $context['subnav']['connect'] = ['label' => Craft::t('Accounts'), 'url' => 'charge/connect'];
        }

        if (craft()->userSession->isAdmin() || $user->can('accessPlugin-charge')) {
            if (craft()->charge_log->getLogEnabledStatus()) {
                $context['subnav']['logs'] = ['label' => Craft::t('Logs'), 'url' => 'charge/logs'];
            }
            $context['subnav']['settings'] = ['label' => Craft::t('Settings'), 'url' => 'charge/settings'];
        }
    }


    public function prepCpSettingsTemplate(&$context)
    {
        $context['selectedItem'] = craft()->request->getSegment(3, 'stripe');

        $context['navItems']['license'] = ['title' => Craft::t('License')];
        $context['navItems']['settings'] = ['heading' => Craft::t('Settings')];
        $context['navItems']['stripe'] = ['title' => Craft::t('Stripe')];
        $context['navItems']['charges'] = ['title' => Craft::t('General')];

        $context['navItems']['features'] = ['heading' => Craft::t('Features')];
        $context['navItems']['coupons'] = ['title' => Craft::t('Coupons')];
        $context['navItems']['emails'] = ['title' => Craft::t('Emails')];
        $context['navItems']['subscriptions'] = ['title' => Craft::t('Subscriptions')];
        $context['navItems']['fields'] = ['title' => Craft::t('Fields')];
        $context['navItems']['userreg'] = ['title' => Craft::t('Guest Registration')];
        if (craft()->charge_connect->getConnectEnabledStatus()) {
            $context['navItems']['connect'] = ['title' => Craft::t('Connect')];
        }

        $context['navItems']['data'] = ['heading' => Craft::t('Data')];
        $context['navItems']['logs'] = ['title' => Craft::t('Logs')];
        if (craft()->charge_tests->getTestsEnabledStatus()) {
            $context['navItems']['tests'] = ['title' => Craft::t('Testing')];
        }
    }


    /**
     * Includes front end resources for Control Panel requests.
     */
    private function includeCpResources()
    {
        $templatesService = craft()->templates;
        $templatesService->includeCssResource('charge/cp/css/charge.css');
    }

    public function registerUserPermissions()
    {
        return array(
            'charge-manageSettings' => array('label' => Craft::t('Manage settings')),
            'charge-manageCharges'  => array('label' => Craft::t('Manage charges'))
        );
    }

    public static function formatAmount($amount, $currency, $format = 'symbol')
    {
        $charset = craft()->templates->getTwig()->getCharset();
        $currency = ChargePlugin::getCurrencies($currency);

        return new \Twig_Markup(html_entity_decode($currency[$format] . number_format($amount / 100, 2), ENT_QUOTES), $charset);
    }


}
