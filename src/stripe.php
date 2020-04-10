<?php

namespace Website\controllers\stripe;

use Website\models;

/**
 * Handle the checkout.session.completed event from Stripe
 *
 * @see https://stripe.com/docs/payments/checkout/fulfillment#webhooks
 *
 * @param \Minz\Request $request
 *
 * @return \Minz\Response
 */
function hooks($request)
{
    $payload = $request->param('@input');
    $signature = $request->header('HTTP_STRIPE_SIGNATURE');
    $hook_secret = \Minz\Configuration::$application['stripe_webhook_secret'];

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $hook_secret);
    } catch (\UnexpectedValueException $e) {
        \Minz\Log::error($e->getMessage());
        return \Minz\Response::badRequest();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        \Minz\Log::error($e->getMessage());
        return \Minz\Response::badRequest();
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $payment_dao = new \Website\models\dao\Payment();
        $raw_payment = $payment_dao->findBy([
            'payment_intent_id' => $session->payment_intent,
        ]);

        if ($raw_payment) {
            $payment = new models\Payment($raw_payment);
            $payment->complete();
            $payment_dao->save($payment);
        } else {
            \Minz\Log::warning("Payment {$session->payment_intent} completed, not in database.");
        }
    }

    return \Minz\Response::ok();
}