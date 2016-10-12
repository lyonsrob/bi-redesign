<?php
namespace Craft;

class Charge_CouponController extends Charge_BaseController
{
	protected $allowAnonymous = true;

    public function actionValidate()
    {
    	$this->requireAjaxRequest();
    	$this->requirePostRequest();

        $ret = [];

        $coupon = craft()->request->getPost('coupon','');
        if($coupon == '') {
            $ret['valid'] = false;
            $ret['message'] = 'You must supply a coupon code to validate';
            return $this->returnJson($ret);
        }

        $coupon = craft()->charge_coupon->getCouponByCode($coupon);

        if($coupon === false) {
            $ret['valid'] = false;
            $ret['message'] = 'The coupon code is invalid';
            return $this->returnJson($ret);
        }

        $ret['valid'] = true;
        $ret['message'] = 'The coupon is valid';
        $ret['paymentType'] = $coupon->paymentType;
        $ret['coupon'] = $coupon;

        return $this->returnJson($ret);
    }

   	public function actionAll(array $variables = [])
    {
		$variables['coupons'] = craft()->charge_coupon->getAll();

        $this->renderTemplate('charge/settings/coupon/_index', $variables);
    }


    public function actionDeleteCoupon()
    {
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requirePermission('accessPlugin-Charge');

        $id = craft()->request->getRequiredPost('id');
     	$return = craft()->charge_coupon->deleteCouponById($id);

        return $this->returnJson(['success' => $return]);
    }

   	public function actionEdit(array $variables = [])
    {
		craft()->userSession->requirePermission('accessPlugin-Charge');
		$charge = new ChargePlugin;

		if(!isset($variables['coupon'])) {

	    	if(isset($variables['couponId'])) {
	    		$couponId = $variables['couponId'];
	    		$variables['coupon'] = craft()->charge_coupon->getCouponById($couponId);
	    	} else {
	    		// New coupon, load a blank object
	    		$variables['coupon'] = new Charge_CouponModel();
	    	}
		}

		$variables['paymentTypes'] = ['one-off' => 'One-Off','recurring' => 'Recurring'];
		$variables['couponTypes'] = ['percentage' => 'Percentage Off', 'amount' => 'Fixed Amount'];
		$variables['durations'] = ['once' => 'Once', 'forever' => 'Forever', 'repeating' => 'Repeating'];

		foreach(ChargePlugin::getCurrencies() as $key => $row)
		{
			$variables['currencies'][$key] = strtoupper($key) . ' - ' .$row['name'];
		}


		// Revert the coupon amount just in case
        if($variables['coupon']->amountOff > 0){
        	$variables['coupon']->amountOff = number_format($variables['coupon']->amountOff / 100, 2);
        }

        $this->renderTemplate('charge/settings/coupon/_settings', $variables);
    }




    public function actionSave(array $variables = [])
    {
		craft()->userSession->requirePermission('accessPlugin-Charge');
		$this->requirePostRequest();

		$existingCouponId = craft()->request->getPost('couponId');

		if ($existingCouponId)
		{
			$coupon = craft()->charge_coupon->getCouponById($existingCouponId);
		}
		else
		{
			$coupon = new Charge_CouponModel();
		}

		$coupon->stripeId = craft()->request->getPost('stripeId');
		$coupon->name = craft()->request->getPost('name');
		$coupon->code = craft()->request->getPost('code');
		$coupon->paymentType = craft()->request->getPost('paymentType');
		$coupon->couponType = craft()->request->getPost('couponType');
        $coupon->percentageOff = craft()->request->getPost('percentageOff');
        $coupon->amountOff = craft()->request->getPost('amountOff');
        $coupon->currency = craft()->request->getPost('currency');
        $coupon->duration = craft()->request->getPost('duration');
        $coupon->durationInMonths = craft()->request->getPost('durationInMonths');
        $coupon->maxRedemptions = craft()->request->getPost('maxRedemptions');
        $coupon->redeemBy = craft()->request->getPost('redeemBy');

        // amountOff is passed as a double. Turn it into cents/pence
        if($coupon->amountOff > 0){
        	$coupon->amountOff = floor($coupon->amountOff * 100);
        }

		// Did it save?
		if (craft()->charge_coupon->saveCoupon($coupon))
		{
			craft()->userSession->setNotice(Craft::t('Coupon saved.'));
			$this->redirectToPostedUrl();
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save coupon.'));
		}

		// Revert the coupon amount just in case
        if($coupon->amountOff > 0){
        	$coupon->amountOff = $coupon->amountOff / 100;
        }

		// Send the source back to the template
		craft()->urlManager->setRouteVariables(['coupon' => $coupon]);
	}




}
