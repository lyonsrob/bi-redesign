<?php
namespace Craft;

class Charge_LogController extends Charge_BaseController
{
    public function init()
    {
        if (!craft()->config->get('devMode'))
        {
            craft()->templates->getTwig()->addExtension(new \Twig_Extension_Debug());
        }
    }

    public function actionAll(array $variables = [])
    {
        $page = craft()->request->getQuery('p');
        if($page == '') { $page = 1; }
        $variables['threaded'] = craft()->charge_log->getAllThreaded($page);
        $variables['totalPages'] = craft()->charge_log->getTotalThreadedPagesCount();

        $variables['currentPage'] = $page;
        $variables['nextPage'] = false;
        $variables['prevPage'] = false;

        if($page > 1) {
            $variables['prevPage'] = $page-1;
        }

        if($page < $variables['totalPages']) {
            $variables['nextPage'] = $page+1;
        }

        $this->renderTemplate('charge/log/_index', $variables);
    }


    public function actionView(array $variables = [])
    {
        if(!isset($variables['logId'])) {
            $this->redirect('charge/logs');
        }

        $log = craft()->charge_log->getLogById($variables['logId']);

        if($log == null) {
            $this->redirect('charge/logs');
        }

        $relatedLogs = craft()->charge_log->getLogsByRequestKey($log->requestKey);

        $variables['log'] = $log;
        $variables['relatedLogs'] = $relatedLogs;

        $this->renderTemplate('charge/log/_view', $variables);
    }



    public function actionDeleteLog()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        craft()->userSession->requireAdmin();

        $id = craft()->request->getRequiredPost('id');
        $return = craft()->charge_log->deleteById($id);

        return $this->returnJson(['success' => $return]);
    }


    public function actionClearAll()
    {
        $this->requirePostRequest();
        craft()->userSession->requireAdmin();

        craft()->charge_log->deleteAll();

        craft()->userSession->setNotice(Craft::t('Charge logs cleared'));
        return $this->redirect('charge/logs');
    }


    public function actionClearByRequest()
    {
        $this->requirePostRequest();
        craft()->userSession->requireAdmin();

        $key = craft()->request->getRequiredPost('requestKey');
        craft()->charge_log->deleteByRequestKey($key);

        return $this->redirect('charge/logs');
    }



}
