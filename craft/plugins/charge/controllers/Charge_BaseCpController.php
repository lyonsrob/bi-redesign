<?php
namespace Craft;

class Charge_BaseCpController extends Charge_BaseController
{
    protected $allowAnonymous = false;

    public function init()
    {
        /*
        if(!craft()->userSession->isAdmin()) {
            craft()->userSession->requirePermission('accessPlugin-charge');
        }*/
    }
}
