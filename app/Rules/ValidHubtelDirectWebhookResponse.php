<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidHubtelDirectWebhookResponse implements ValidationRule
{
    private $missingFields = [];
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Reset the missing fields array
        $this->missingFields = [];

        // Validate the Hubtel direct web response here
        if (
            !isset($value['ResponseCode']) ||
            !isset($value['Message']) ||
            !isset($value['Data']) ||
            !is_array($value['Data']) ||
            !isset($value['Data']['Amount']) ||
            !isset($value['Data']['Charges']) ||
            !isset($value['Data']['AmountAfterCharges']) ||
            !isset($value['Data']['Description']) ||
            !isset($value['Data']['ClientReference']) ||
            !isset($value['Data']['TransactionId']) ||
            !isset($value['Data']['ExternalTransactionId']) ||
            !isset($value['Data']['AmountCharged']) ||
            !isset($value['Data']['OrderId'])
        ) {
            // Track missing fields

            $fail($this->trackMissingFields($value));
        }

    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message()
    {
        return 'The Hubtel direct web response is invalid or missing required fields: ' . implode(', ', $this->missingFields);
    }

    /**
     * Track missing fields.
     *
     * @param  array  $value
     * @return void
     */
    private function trackMissingFields($value)
    {
        $requiredFields = [
            'ResponseCode',
            'Message',
            'Data',
            'Amount',
            'Charges',
            'AmountAfterCharges',
            'Description',
            'ClientReference',
            'TransactionId',
            'ExternalTransactionId',
            'AmountCharged',
            'OrderId'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($value[$field])) {
                $this->missingFields[] = $field;
            }
        }
    }
}