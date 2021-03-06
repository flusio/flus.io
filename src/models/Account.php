<?php

namespace Website\models;

use Website\utils;

/**
 * @author Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Account extends \Minz\Model
{
    use DaoConnector;

    public const PROPERTIES = [
        'id' => [
            'type' => 'string',
            'required' => true,
        ],

        'created_at' => [
            'type' => 'datetime',
        ],

        'expired_at' => [
            'type' => 'datetime',
            'required' => true,
        ],

        'email' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\Website\utils\Email::validate',
        ],

        'access_token' => [
            'type' => 'string',
        ],

        'preferred_frequency' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\Website\models\Account::validateFrequency',
        ],

        'preferred_payment_type' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\Website\models\Account::validatePaymentType',
        ],

        'preferred_service' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\Website\models\Account::validateServiceName',
        ],

        'reminder' => [
            'type' => 'boolean',
            'required' => true,
        ],

        'address_first_name' => [
            'type' => 'string',
        ],

        'address_last_name' => [
            'type' => 'string',
        ],

        'address_address1' => [
            'type' => 'string',
        ],

        'address_postcode' => [
            'type' => 'string',
        ],

        'address_city' => [
            'type' => 'string',
        ],

        'address_country' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\Website\utils\Countries::isSupported',
        ],

        'company_vat_number' => [
            'type' => 'string',
            'validator' => '\Website\models\Account::validateVatNumber',
        ],
    ];

    /**
     * Initialize an Account
     *
     * @param string $email
     *
     * @return \Website\models\Account
     */
    public static function init($email)
    {
        return new self([
            'id' => bin2hex(random_bytes(16)),
            'email' => utils\Email::sanitize($email),
            'expired_at' => \Minz\Time::fromNow(1, 'month'),
            'preferred_frequency' => 'month',
            'preferred_payment_type' => 'card',
            'preferred_service' => 'flusio',
            'reminder' => false,
            'address_country' => 'FR',
        ]);
    }

    /**
     * Extend the subscription period by the given frequency
     *
     * @param string $frequency (`month` or `year`)
     */
    public function extendSubscription($frequency)
    {
        if ($this->isFree()) {
            // Free accounts don't need to be extended
            return;
        }

        $today = \Minz\Time::now();
        $new_expired_at = max($today, $this->expired_at);
        if ($frequency === 'year') {
            $new_expired_at->modify('+1 year');
        } else {
            $new_expired_at->modify('+1 month');
        }

        $this->expired_at = $new_expired_at;
    }

    /**
     * @param string $access_token
     *
     * @return boolean True if the given token is valid, false else
     */
    public function checkAccess($access_token)
    {
        if (!$this->access_token || !$access_token) {
            return false;
        }

        $equals = hash_equals($this->access_token, $access_token);
        if (!$equals) {
            return false;
        }

        $token = Token::find($this->access_token);
        return $token->isValid();
    }

    /**
     * Return the address information as an array
     *
     * @return array
     */
    public function address()
    {
        return [
            'first_name' => $this->address_first_name,
            'last_name' => $this->address_last_name,
            'address1' => $this->address_address1,
            'postcode' => $this->address_postcode,
            'city' => $this->address_city,
            'country' => $this->address_country,
        ];
    }

    /**
     * @param array $address
     */
    public function setAddress($address)
    {
        $this->address_first_name = trim($address['first_name']);
        $this->address_last_name = trim($address['last_name']);
        $this->address_address1 = trim($address['address1']);
        $this->address_postcode = trim($address['postcode']);
        $this->address_city = trim($address['city']);
        $this->address_country = trim($address['country']);
    }

    /**
     * Return whether the user needs to set its address or not.
     *
     * @return boolean
     */
    public function mustSetAddress()
    {
        return !$this->address_first_name;
    }

    /**
     * Return whether the account has a free subscription or not
     *
     * @return boolean
     */
    public function isFree()
    {
        return $this->expired_at->getTimestamp() === 0;
    }

    /**
     * Return whether the subscription has expired or not
     *
     * @return boolean
     */
    public function hasExpired()
    {
        return !$this->isFree() && $this->expired_at <= \Minz\Time::now();
    }

    /**
     * Return the list of payments associated to this account
     *
     * @return \Website\models\Payment[]
     */
    public function payments()
    {
        return Payment::listBy([
            'account_id' => $this->id,
        ]);
    }

    /**
     * Return an ongoing payment associated to this account, if any
     *
     * @return \Website\models\Payment|null
     */
    public function ongoingPayment()
    {
        return Payment::daoToModel('findOngoingForAccount', $this->id);
    }

    /**
     * Validate a model and return formated errors
     *
     * @return string[]
     */
    public function validate()
    {
        $formatted_errors = [];

        foreach (parent::validate() as $property => $error) {
            $code = $error['code'];

            if ($property === 'email' && $code === \Minz\Model::ERROR_REQUIRED) {
                $formatted_error = 'L’adresse courriel est obligatoire.';
            } elseif ($property === 'email') {
                $formatted_error = 'L’adresse courriel que vous avez fournie est invalide.';
            } elseif ($property === 'address_country') {
                $formatted_error = 'Le pays que vous avez renseigné est invalide.';
            } else {
                $formatted_error = $error['description']; // @codeCoverageIgnore
            }

            $formatted_errors[$property] = $formatted_error;
        }

        return $formatted_errors;
    }

    /**
     * @param string $type
     *
     * @return boolean Returns true if the value is either `common_pot` or `card`
     */
    public static function validatePaymentType($type)
    {
        return $type === 'common_pot' || $type === 'card';
    }

    /**
     * @param string $frequency
     *
     * @return boolean Returns true if the value is either `month` or `year`
     */
    public static function validateFrequency($frequency)
    {
        return $frequency === 'month' || $frequency === 'year';
    }

    /**
     * @param string $type
     *
     * @return boolean Returns true if the value is either `flusio` or `freshrss`
     */
    public static function validateServiceName($service)
    {
        return $service === 'flusio' || $service === 'freshrss';
    }

    /**
     * @param string $vat_number
     *
     * @return boolean Returns true if the number LOOKS good
     */
    public static function validateVatNumber($vat_number)
    {
        $length = strlen(trim($vat_number));
        // what a tremendous verification! This could be improved, but I don't
        // plan to let anyone to set its vat number himself, so this is fine
        // for now.
        return $length >= 10 && $length <= 20;
    }
}
