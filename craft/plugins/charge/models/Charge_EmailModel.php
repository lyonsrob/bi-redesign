<?php
namespace Craft;

class Charge_EmailModel extends BaseModel
{
    protected function defineAttributes()
    {
        return [
            'id'           => [AttributeType::Number],
            'name'         => [AttributeType::String, 'required' => true],
            'handle'       => [AttributeType::String, 'required' => true],
            'subject'      => [AttributeType::String, 'required' => true],
            'to'           => [AttributeType::String, 'required' => true, 'default' => '{{ charge.customerEmail }}'],
            'bcc'          => [AttributeType::String],
            'templatePath' => [AttributeType::String, 'required' => true],
            'enabled'      => [AttributeType::Bool, 'required' => true, 'default' => true],
        ];
    }

    public function __toString()
    {
        return (string) $this->name;
    }

    public function send($params = [])
    {
        if($this->enabled != true) return;

        $charge = null;
        if(isset($params['charge'])) {
            $charge = $params['charge'];
        }

        //sending emails
        $renderVariables = [
            'charge' => $charge
        ];
        foreach($params as $key => $arr) {
            $renderVariables[$key] = $arr;
        }

        // Just in case this is being triggered from the CP
        $oldPath = craft()->path->getTemplatesPath();
        $newPath = craft()->path->getSiteTemplatesPath();
        craft()->path->setTemplatesPath($newPath);

        $craftEmail = new EmailModel();

        try {
            $craftEmail->toEmail = $to = craft()->templates->renderString($this->to, $renderVariables);
        }
        catch (\Exception $e) {
            $error = Craft::t('Email template parse error for email “{email}” in “To:”. Charge: “{charge}”. Template error: “{message}”',
                ['email' => $this->name, 'charge' => $charge->id, 'message' => $e->getMessage()]);
            craft()->charge_log->error($error, ['message' => $error]);
            return false;
        }

        // BCC:
        try {
            $bcc = craft()->templates->renderString($this->bcc, $renderVariables);
            $bcc = str_replace(';',',',$bcc);
            $bcc = explode(',',$bcc);
            $bccEmails = [];
            foreach ($bcc as $bccEmail)
            {
                $bccEmails[] = ['email' => $bccEmail];
            }
            $craftEmail->bcc = $bccEmails;
        }
        catch (\Exception $e)
        {
            $error = Craft::t('Email template parse error for email “{email}” in “BCC:”. Charge: “{charge}”. Template error: “{message}”',
                ['email' => $this->name, 'charge' => $charge->id, 'message' => $e->getMessage()]);
            craft()->charge_log->error($error, ['message' => $error]);
            return false;
        }

        // Subject:
        try
        {
            $craftEmail->subject = craft()->templates->renderString($this->subject, $renderVariables);
        }
        catch (\Exception $e)
        {
            $error = Craft::t('Email template parse error for email “{email}” in “Subject:”. Charge: “{charge}”. Template error: “{message}”',
                ['email' => $this->name, 'charge' => $charge->id, 'message' => $e->getMessage()]);
            craft()->charge_log->error($error, ['message' => $error]);
            return false;
        }

        // Email Body
        if (!craft()->templates->doesTemplateExist($this->templatePath))
        {
            $error = Craft::t('Email template does not exist at “{templatePath}” for email “{email}”. Charge: “{charge}”.',
                ['templatePath' => $this->templatePath, 'email' => $this->name, 'charge' => $charge->id]);
            craft()->charge_log->error($error, ['message' => $error]);
            return false;
        }
        else
        {
            try
            {
                $craftEmail->body = $craftEmail->htmlBody = craft()->templates->render($this->templatePath,
                    $renderVariables);
            }
            catch (\Exception $e)
            {
                $error = Craft::t('Email template parse error for email “{email}”. Charge: “{charge}”. Template error: “{message}”',
                    ['email' => $this->name, 'charge' => $charge->id, 'message' => $e->getMessage()]);
                craft()->charge_log->error($error, ['message' => $error]);
                return false;
            }
        }

        try {
            if (!craft()->email->sendEmail($craftEmail)) {
                $error = Craft::t('Email “{email}” could not be sent for charge “{charge}”. Errors: {errors}',
                    ['errors' => implode(", ", $this->getAllErrors()), 'email' => $this->name, 'charge' => $charge->id]);
                craft()->charge_log->error($error, ['message' => $error]);
            } else {
                craft()->charge_log->email($this->handle.' email sent to : '.$to, ['email' => $craftEmail]);

            }
        }
        catch(\Exception $e) {
            $error = Craft::t('Send email exception “{email}”. Charge: “{charge}”. PHPMailerException error: “{message}”',
                ['email' => $this->name, 'charge' => $charge->id, 'message' => $e->getMessage()]);
            craft()->charge_log->error($error, ['message' => $error]);
            return false;
        }


        craft()->path->setTemplatesPath($oldPath);
    }

}
