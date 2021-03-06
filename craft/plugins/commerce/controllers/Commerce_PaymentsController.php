<?php
namespace Craft;

/**
 * Class Commerce_PaymentsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PaymentsController extends Commerce_BaseFrontEndController
{
	/**
	 * @throws HttpException
	 */
	public function actionPay()
	{
		$this->requirePostRequest();

		$customError = '';

		if (($number = craft()->request->getParam('orderNumber')) !== null)
		{
			$order = craft()->commerce_orders->getOrderByNumber($number);
			if (!$order)
			{
				$error = Craft::t('Can not find an order to pay.');
				if (craft()->request->isAjaxRequest())
				{
					$this->returnErrorJson($error);
				}
				else
				{
					craft()->userSession->setFlash('error', $error);
				}

				return;
			}
		}

		// Get the cart if no order number was passed.
		if (!isset($order) && !$number)
		{
			$order = craft()->commerce_cart->getCart();
		}

		// These are used to compare if the order changed during it's final
		// recalculation before payment.
		$originalTotalPrice = $order->outstandingBalance();
		$originalTotalQty = $order->getTotalQty();
		$originalTotalAdjustments = count($order->getAdjustments());

		// Allow setting the payment method at time of submitting payment.
		$paymentMethodId = craft()->request->getParam('paymentMethodId');
		if ($paymentMethodId)
		{
			if (!craft()->commerce_cart->setPaymentMethod($order, $paymentMethodId, $error))
			{
				if (craft()->request->isAjaxRequest())
				{
					$this->returnErrorJson($error);
				}
				else
				{
					craft()->userSession->setFlash('error', $error);
				}

				return;
			}
		}

		$paymentMethod = $order->getPaymentMethod();

		if (!$paymentMethod)
		{
			$error = Craft::t("There is no payment method selected for this order.");
			if (craft()->request->isAjaxRequest())
			{
				$this->returnErrorJson($error);
			}
			else
			{
				craft()->userSession->setFlash('error', $error);
			}
			return;
		}

		// Get the payment method' gateway adapter's expected form model
		$paymentForm = $paymentMethod->getPaymentFormModel();
		$paymentForm->populateModelFromPost(craft()->request->getPost());

		$order->setContentFromPost('fields');

		// Check email address exists on order.
		if (!$order->email)
		{
			$customError = Craft::t("No customer email address exists on this cart.");
			if (craft()->request->isAjaxRequest())
			{
				$this->returnErrorJson($customError);
			}
			else
			{
				craft()->userSession->setFlash('error', $customError);
				craft()->urlManager->setRouteVariables(compact('paymentForm'));
			}

			return;
		}

		// Do one final save to confirm the price does not change out from under the customer.
		// This also confirms the products are available and discounts are current.
		if (craft()->commerce_orders->saveOrder($order))
		{
			$totalPriceChanged = $originalTotalPrice != $order->outstandingBalance();
			$totalQtyChanged = $originalTotalQty != $order->getTotalQty();
			$totalAdjustmentsChanged = $originalTotalAdjustments != count($order->getAdjustments());

			// Has the order changed in a significant way?
			if ($totalPriceChanged || $totalQtyChanged || $totalAdjustmentsChanged)
			{
				if ($totalPriceChanged)
				{
					$order->addError('totalPrice', Craft::t("The total price of the order changed."));
				}

				if ($totalQtyChanged)
				{
					$order->addError('totalQty', Craft::t("The total quantity of items within the order changed."));
				}

				if ($totalAdjustmentsChanged)
				{
					$order->addError('totalAdjustments', Craft::t("The total number of order adjustments changed."));
				}

				$customError = Craft::t('Something changed with the order before payment, please review your order and submit payment again.');
				if (craft()->request->isAjaxRequest())
				{
					$this->returnErrorJson($customError);
				}
				else
				{
					craft()->userSession->setFlash('error', $customError);
					craft()->urlManager->setRouteVariables(compact('paymentForm'));
				}

				return;
			}
		}

		// Save the return and cancel URLs to the order
		$returnUrl = craft()->request->getPost('redirect');
		$cancelUrl = craft()->request->getPost('cancelUrl');

		if ($returnUrl !== null || $cancelUrl !== null)
		{
			$order->returnUrl = craft()->templates->renderObjectTemplate($returnUrl, $order);
			$order->cancelUrl = craft()->templates->renderObjectTemplate($cancelUrl, $order);
			craft()->commerce_orders->saveOrder($order);
		}

		$paymentForm->validate();
		if (!$paymentForm->hasErrors())
		{
			$success = craft()->commerce_payments->processPayment($order, $paymentForm, $redirect, $customError);
		}
		else
		{
			$customError = Craft::t('Payment information submitted is invalid.');
			$success = false;
		}


		if ($success)
		{
			if (craft()->request->isAjaxRequest())
			{
				$response = ['success' => true];
				if ($redirect !== null)
				{
					$response['redirect'] = $redirect;
				}
				$this->returnJson($response);
			}
			else
			{
				if ($redirect !== null)
				{
					$this->redirect($redirect);
				}
				else
				{
					if ($order->returnUrl)
					{
						$this->redirect($order->returnUrl);
					}
					else
					{
						$this->redirectToPostedUrl($order);
					}
				}
			}
		}
		else
		{
			if (craft()->request->isAjaxRequest())
			{
				$this->returnJson(['error' => $customError, 'paymentForm' => $paymentForm->getErrors()]);
			}
			else
			{
				craft()->userSession->setFlash('error', $customError);
				craft()->urlManager->setRouteVariables(compact('paymentForm'));
			}
		}
	}

	/**
	 * Process return from off-site payment
	 *
	 * @throws Exception
	 * @throws HttpException
	 */
	public function actionCompletePayment()
	{
		$id = craft()->request->getParam('commerceTransactionHash');

		$transaction = craft()->commerce_transactions->getTransactionByHash($id);

		if (!$transaction)
		{
			throw new HttpException(400, Craft::t("Can not complete payment for missing transaction."));
		}

		$customError = "";
		$success = craft()->commerce_payments->completePayment($transaction, $customError);

		if ($success)
		{
			$this->redirect($transaction->order->returnUrl);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $customError]));
			$this->redirect($transaction->order->cancelUrl);
		}
	}
}
