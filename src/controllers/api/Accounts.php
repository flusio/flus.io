<?php

namespace Website\controllers\api;

use Website\models;
use Website\utils;

/**
 * @author Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Accounts
{
    /**
     * @request_header string PHP_AUTH_USER
     * @request_param string email
     *
     * @response 401
     *     if the auth header is invalid
     * @response 400
     *     if the account doesn’t exist and email is invalid
     * @response 200
     *     on success
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function show($request)
    {
        $auth_token = $request->header('PHP_AUTH_USER', '');
        $private_key = \Minz\Configuration::$application['flus_private_key'];
        if (!hash_equals($private_key, $auth_token)) {
            return \Minz\Response::unauthorized();
        }

        $email = utils\Email::sanitize($request->param('email', ''));
        $account = models\Account::findBy([
            'email' => $email,
        ]);

        if (!$account) {
            $account = models\Account::init($email);

            $errors = $account->validate();
            if ($errors) {
                $output = new \Minz\Output\Text(implode(' ', $errors));
                return new \Minz\Response(400, $output);
            }

            $account->save();
        }

        $json_output = json_encode([
            'id' => $account->id,
            'expired_at' => $account->expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);

        $output = new \Minz\Output\Text($json_output);
        $response = new \Minz\Response(200, $output);
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @request_header string PHP_AUTH_USER
     * @request_param string account_id
     * @request_param string service
     *     The name of the service making the request ('flusio' or 'freshrss').
     *     If the variable is invalid, it defaults to 'flusio'.
     *
     * @response 401
     *     if the auth header is invalid
     * @response 404
     *     if the account_id doesn't exist
     * @response 200
     *     on success
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function loginUrl($request)
    {
        $auth_token = $request->header('PHP_AUTH_USER', '');
        $private_key = \Minz\Configuration::$application['flus_private_key'];
        if (!hash_equals($private_key, $auth_token)) {
            return \Minz\Response::unauthorized();
        }

        $account_id = $request->param('account_id');
        $service = $request->param('service');

        $account = models\Account::find($account_id);
        if (!$account) {
            return \Minz\Response::notFound();
        }

        if ($service !== 'flusio' && $service !== 'freshrss') {
            $service = 'flusio';
        }

        $token = models\Token::init(10, 'minutes');
        $token->save();

        $account->access_token = $token->token;
        $account->preferred_service = $service;
        $account->save();

        $login_url = \Minz\Url::absoluteFor('account login', [
            'account_id' => $account->id,
            'access_token' => $account->access_token,
        ]);
        $json_output = json_encode([
            'url' => $login_url,
        ]);

        $output = new \Minz\Output\Text($json_output);
        $response = new \Minz\Response(200, $output);
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @request_header string PHP_AUTH_USER
     * @request_param string account_id
     *
     * @response 401
     *     if the auth header is invalid
     * @response 404
     *     if the account_id doesn't exist
     * @response 200
     *     on success
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function expiredAt($request)
    {
        $auth_token = $request->header('PHP_AUTH_USER', '');
        $private_key = \Minz\Configuration::$application['flus_private_key'];
        if (!hash_equals($private_key, $auth_token)) {
            return \Minz\Response::unauthorized();
        }

        $account_id = $request->param('account_id');

        $account = models\Account::find($account_id);
        if (!$account) {
            return \Minz\Response::notFound();
        }

        $json_output = json_encode([
            'expired_at' => $account->expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);

        $output = new \Minz\Output\Text($json_output);
        $response = new \Minz\Response(200, $output);
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }
}
