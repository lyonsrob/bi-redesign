<?php
namespace Craft;

class Charge_TestsController extends Charge_BaseCpController
{

    public function actionRunTest()
    {
        $this->requirePostRequest();
        $type = craft()->request->getRequiredPost('type');

        $name = 'runTest_'.$type;

        if(method_exists($this, $name)) {
            craft()->charge_log->test($name);
            $this->$name();
        }


        $this->redirectToPostedUrl();
    }



    private function runTest_recurringTrigger()
    {
        craft()->charge_tests->testRecurringTrigger();

    }

}
