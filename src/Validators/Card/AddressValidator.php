<?php

/**
 * AddressValidator class
 *
 * PHP version 7.3
 *
 * @author    WeDev Brasil Team <contato@wedev.software>
 * @author    Rafael Teixeira <rafaeldemeirateixeira@gmail.com>
 * @copyright 2020 We Dev Tecnologia Ltda
 * @link      https://github.com/wedevBr/bankly-laravel/
 */

namespace WeDevBr\Bankly\Validators\Card;

use InvalidArgumentException;
use WeDevBr\Bankly\Types\Card\Address;

class AddressValidator
{
    private Address $address;

    /**
     * AddressValidator constructor.
     * @param Address $address
     */
    public function __construct(Address $address)
    {
        $this->address = $address;
    }

    /**
     * This validate the card
     */
    public function validate(): void
    {
        $this->validateZipCode();
        $this->validateAddress();
        $this->validateNumber();
        $this->validateNeighborhood();
        $this->validateCity();
        $this->validateState();
        $this->validateCountry();
    }

    /**
     * This validate a zip code
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateZipCode(): void
    {
        $zipCode = $this->address->zipCode;
        if (empty($zipCode) || !is_string($zipCode) || !is_numeric($zipCode)) {
            throw new InvalidArgumentException('zip code should be a numeric string');
        }
    }

    /**
     * This validates a address
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateAddress(): void
    {
        $address = $this->address->address;
        if (empty($address) || !is_string($address)) {
            throw new InvalidArgumentException('address should be a string');
        }
    }

    /**
     * This validate a address number
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateNumber(): void
    {
        $number = $this->address->number;
        if (empty($number)) {
            throw new InvalidArgumentException('number should be a numeric or string');
        }
    }

    /**
     * This validate a virtual card neighborhood
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateNeighborhood(): void
    {
        $neighborhood = $this->address->neighborhood;
        if (empty($neighborhood) || !is_string($neighborhood)) {
            throw new InvalidArgumentException('neighborhood should be a string');
        }
    }

    /**
     * This validate a virtual card city
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateCity(): void
    {
        $city = $this->address->city;
        if (empty($city) || !is_string($city)) {
            throw new InvalidArgumentException('city should be a string');
        }
    }

    /**
     * This validate a virtual card state
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateState(): void
    {
        $state = $this->address->state;
        if (empty($state) || !is_string($state)) {
            throw new InvalidArgumentException('state should be a string');
        }
    }

    /**
     * This validate a virtual card country
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateCountry(): void
    {
        $country = $this->address->country;
        if (empty($country) || !is_string($country)) {
            throw new InvalidArgumentException('country should be a string');
        }
    }
}
