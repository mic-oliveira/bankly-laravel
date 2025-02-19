<?php

namespace WeDevBr\Bankly;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use TypeError;
use WeDevBr\Bankly\Auth\Auth;
use WeDevBr\Bankly\Inputs\Customer;
use WeDevBr\Bankly\Inputs\DocumentAnalysis;
use WeDevBr\Bankly\Support\Contracts\CustomerInterface;
use WeDevBr\Bankly\Support\Contracts\DocumentInterface;
use WeDevBr\Bankly\Types\Billet\DepositBillet;
use WeDevBr\Bankly\Types\Pix\PixEntries;
use WeDevBr\Bankly\Contracts\Pix\PixCashoutInterface;
use WeDevBr\Bankly\Inputs\BusinessCustomer;
use WeDevBr\Bankly\Types\Billet\CancelBillet;
use WeDevBr\Bankly\Types\Customer\PaymentAccount;
use WeDevBr\Bankly\Types\Pix\PixDynamicQrCode;
use WeDevBr\Bankly\Types\Pix\PixQrCodeData;
use WeDevBr\Bankly\Types\Pix\PixStaticQrCode;

/**
 * Class Bankly
 * @author Adeildo Amorim <adeildo@wedev.software>
 * @package WeDevBr\Bankly
 */
class Bankly
{
    /** @var string */
    public $api_url;

    /** @var string */
    private $mtlsCert;

    /** @var string */
    private $mtlsKey;

    /** @var string */
    private $mtlsPassphrase;

    /** @var string */
    private $token = null;

    /** @var string */
    private $api_version = '1';

    /** @var array */
    private $headers;

    /**
     * Bankly constructor.
     *
     * @param null|string $mtlsPassphrase
     */
    public function __construct(string $mtlsPassphrase = null)
    {
        $this->headers = ['api-version' => $this->api_version];

        $this->api_url = config('bankly')['api_url'];
        $this->mtlsPassphrase = $mtlsPassphrase;
    }

    /**
     * Set token
     *
     * @param string $token
     * @return void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Return token
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string $passPhrase
     * @return self
     */
    public function setPassphrase(string $passPhrase): self
    {
        $this->mtlsPassphrase = $passPhrase;
        return $this;
    }

    /**
     * Set the cert.crt file path
     * @param string $path
     * @return self
     */
    public function setCertPath(string $path): static
    {
        $this->mtlsCert = $path;
        return $this;
    }

    /**
     * Set the cert.pem file path
     * @param string $path
     * @return self
     */
    public function setKeyPath(string $path): static
    {
        $this->mtlsKey = $path;
        return $this;
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function getBankList($product = 'None'): mixed
    {
        return $this->get('/banklist', [
            'product' => $product
        ]);
    }

    /**
     * Retrieve your balance account
     * @param string $branch
     * @param string $account
     * @return array|mixed
     * @throws RequestException
     * @note If you have a RequestException on this endpoint in staging environment, please use getAccount() method instead.
     */
    public function getBalance(string $branch, string $account): mixed

    {
        return $this->get('/account/balance', [
            'branch' => $branch,
            'account' => $account
        ]);
    }

    /**
     * @param string $account
     * @param string $includeBalance
     * @return array|mixed
     * @throws RequestException
     * @note This method on this date (2020-10-21) works only on staging environment. Contact Bankly/Acesso for more details
     */
    public function getAccount(string $account, string $includeBalance = 'true'): mixed
    {
        return $this->get('/accounts/' . $account, [
            'includeBalance' => $includeBalance,
        ]);
    }

    /**
     * Returns the income report for a given year
     *
     * @param string $account
     * @param string|null $year If not informed, the previous year will be used
     * @return array|mixed
     * @throws RequestException
     */
    public function getIncomeReport(string $account, string $year = null): mixed
    {
        return $this->get('/accounts/' . $account . '/income-report', [
            'calendar' => $year
        ]);
    }

    /**
     * Returns the PDF of the income report for a given year in base64 format
     *
     * @param string $account
     * @param string|null $year If not informed, the previous year will be used
     * @return array|mixed
     * @throws RequestException
     */
    public function getIncomeReportPrint(string $account, string $year = null): mixed
    {
        return $this->get('/accounts/' . $account . '/income-report/print', [
            'calendar' => $year
        ]);
    }

    /**
     * @param $branch
     * @param $account
     * @param int $offset
     * @param int $limit
     * @param string $details
     * @param string $detailsLevelBasic
     * @return array|mixed
     * @throws RequestException
     */
    public function getStatement(
        $branch,
        $account,
        $offset = 1,
        $limit = 20,
        string $details = 'true',
        string $detailsLevelBasic = 'true'
    ): mixed
    {
        return $this->get('/account/statement', array(
            'branch' => $branch,
            'account' => $account,
            'offset' => $offset,
            'limit' => $limit,
            'details' => $details,
            'detailsLevelBasic' => $detailsLevelBasic
        ));
    }

    /**
     * @param string $branch
     * @param string $account
     * @param int $page
     * @param int $pagesize
     * @param string $include_details
     * @param string[] $cardProxy
     * @param string|null $begin_date
     * @param string|null $end_date
     * @return array|mixed
     * @throws RequestException
     * @note This endpoint has been deprecated for some clients.
     * You need to check with Acesso/Bankly if your environment has different parameters also.
     * The response of this request does not have a default interface between environments.
     * Pay attention when use this in your project.
     */
    public function getEvents(
        string $branch,
        string $account,
        int $page = 1,
        int $pagesize = 20,
        string $include_details = 'true',
        array $cardProxy = [],
        string $begin_date = null,
        string $end_date = null
    ): mixed
    {
        $query = [
            'branch' => $branch,
            'account' => $account,
            'page' => $page,
            'pageSize' => $pagesize,
            'includeDetails' => $include_details
        ];

        if (!empty($cardProxy)) {
            $query['cardProxy'] = $cardProxy;
        }

        if ($begin_date) {
            $query['beginDateTime'] = $begin_date;
        }

        if ($end_date) {
            $query['endDateTime'] = $end_date;
        }

        return $this->get(
            '/events',
            $query
        );
    }

    /**
     * @param int $amount
     * @param string $description
     * @param array $sender
     * @param array $recipient
     * @param string|null $correlation_id
     * @return array|mixed
     * @throws RequestException
     */
    public function transfer(
        int $amount,
        string $description,
        array $sender,
        array $recipient,
        string $correlation_id = null
    ): mixed
    {
        if ($sender['bankCode']) {
            unset($sender['bankCode']);
        }

        return $this->post(
            '/fund-transfers',
            [
                'amount' => $amount,
                'description' => $description,
                'sender' => $sender,
                'recipient' => $recipient
            ],
            $correlation_id,
            true
        );
    }

    /**
     * Get transfer funds from an account
     * @param string $branch
     * @param string $account
     * @param int $pageSize
     * @param string|null $nextPage
     * @return array|mixed
     * @throws RequestException
     */
    public function getTransferFunds(string $branch, string $account, int $pageSize = 10, string $nextPage = null): mixed
    {
        $queryParams = [
            'branch' => $branch,
            'account' => $account,
            'pageSize' => $pageSize
        ];
        if ($nextPage) {
            $queryParams['nextPage'] = $nextPage;
        }
        return $this->get('/fund-transfers', $queryParams);
    }

    /**
     * Get Transfer Funds By Authentication Code
     * @param string $branch
     * @param string $account
     * @param string $authenticationCode
     * @return array|mixed
     * @throws RequestException
     */
    public function findTransferFundByAuthCode(string $branch, string $account, string $authenticationCode): mixed
    {
        $queryParams = [
            'branch' => $branch,
            'account' => $account
        ];
        return $this->get('/fund-transfers/' . $authenticationCode, $queryParams);
    }

    /**
     * @param string $branch
     * @param string $account
     * @param string $authentication_id
     * @return array|mixed
     * @throws RequestException
     */
    public function getTransferStatus(string $branch, string $account, string $authentication_id): mixed
    {
        return $this->get('/fund-transfers/' . $authentication_id . '/status', [
            'branch' => $branch,
            'account' => $account
        ]);
    }

    /**
     * @param string $documentNumber
     * @param DocumentAnalysis $document
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function documentAnalysis(
        string $documentNumber,
        $document,
        string $correlationId = null
    ): mixed
    {
        if (!$document instanceof DocumentInterface) {
            throw new TypeError('The document must be an instance of DocumentInterface');
        }

        return $this->postDocument(
            "/document-analysis/{$documentNumber}/deepface",
            [
                'documentType' => $document->getDocumentType(),
                'documentSide' => $document->getDocumentSide(),
                'provider' => $document->getProvider(),
                'providerMetadata' => json_encode($document->getProviderMetadata())
            ],
            $correlationId,
            true,
            true,
            $document
        );
    }

    /**
     * @param string $documentNumber
     * @param array $tokens
     * @param string $resultLevel
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     * @throws RequestException
     */
    public function getDocumentAnalysis(
        string $documentNumber,
        array $tokens = [],
        string $resultLevel = 'ONLY_STATUS',
        string $correlationId = null
    ): mixed
    {
        $query = collect($tokens)
            ->map(function ($token) {
                return "token={$token}";
            })
            ->concat(["resultLevel={$resultLevel}"])
            ->implode('&');

        return $this->get(
            "/document-analysis/{$documentNumber}",
            $query,
            $correlationId
        );
    }

    /**
     * Customer register
     *
     * @param string $documentNumber
     * @param Customer $customer
     * @param string $correlationId
     * @return array|mixed
     * @throws TypeError|RequestException
     */
    public function customer(
        string $documentNumber,
        $customer,
        string $correlationId = null
    ): mixed
    {
        if (!$customer instanceof CustomerInterface) {
            throw new TypeError('The customer must be an instance of CustomerInterface');
        }

        return $this->put("/customers/{$documentNumber}", $customer->toArray(), $correlationId, true);
    }

    /**
     * Business customer register
     *
     * @param string $documentNumber
     * @param BusinessCustomer $customer
     * @param string|null $correlationId
     * @return array|mixed
     * @throws TypeError|RequestException
     */
    public function businessCustomer(
        string $documentNumber,
        BusinessCustomer $customer,
        string $correlationId = null
    ): mixed
    {
        return $this->put("/business/{$documentNumber}", $customer->toArray(), $correlationId, true);
    }

    /**
     * Close account
     *
     * @param string $account
     * @param string|null $reason HOLDER_REQUEST|COMMERCIAL_DISAGREEMENT
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function closeAccount(string $account, string $reason = 'HOLDER_REQUEST', string $correlationId = null): mixed
    {
        return $this->patch('/accounts/' . $account . '/closure', [
            'reason' => $reason
        ], $correlationId, true);
    }

    /**
     * Customer offboarding
     *
     * @param string $documentNumber
     * @param string|null $reason HOLDER_REQUEST|COMMERCIAL_DISAGREEMENT
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function cancelCustomer(string $documentNumber, string $reason = 'HOLDER_REQUEST', string $correlationId = null): mixed
    {
        return $this->patch('/customers/' . $documentNumber . '/cancel', [
            'reason' => $reason
        ], $correlationId, true);
    }

    /**
     * Business offboarding
     *
     * @param string $documentNumber
     * @param string|null $reason HOLDER_REQUEST|COMMERCIAL_DISAGREEMENT
     * @param string|null $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function cancelBusiness(
        string $documentNumber,
        ?string $reason = 'HOLDER_REQUEST',
        string $correlationId = null
    ): mixed
    {
        return $this->patch('/business/' . $documentNumber . '/cancel', [
            'reason' => $reason
        ], $correlationId, true);
    }

    /**
     * Get customer
     *
     * @param string $documentNumber
     * @param string $resultLevel
     * @return array|mixed
     * @throws RequestException
     */
    public function getCustomer(string $documentNumber, string $resultLevel = 'DETAILED'): mixed
    {
        return $this->get("/customers/{$documentNumber}?resultLevel={$resultLevel}");
    }

    /**
     * Get customer
     *
     * @param string $documentNumber
     * @param string $resultLevel
     * @return array|mixed
     * @throws RequestException
     */
    public function getBusinessCustomer(string $documentNumber, string $resultLevel = 'DETAILED'): mixed
    {
        return $this->get("/business/{$documentNumber}?resultLevel={$resultLevel}");
    }

    /**
     * @param string $documentNumber
     * @return array|mixed
     * @throws RequestException
     */
    public function getCustomerAccounts(string $documentNumber): mixed
    {
        return $this->get("/customers/{$documentNumber}/accounts");
    }

    /**
     * @param string $documentNumber
     * @return array|mixed
     * @throws RequestException
     */
    public function getBusinessCustomerAccounts(string $documentNumber): mixed
    {
        return $this->get("/business/{$documentNumber}/accounts");
    }

    /**
     * @param string $documentNumber
     * @param PaymentAccount $paymentAccount
     * @return array|mixed
     * @throws RequestException
     */
    public function createCustomerAccount(string $documentNumber, PaymentAccount $paymentAccount): mixed
    {
        return $this->post(
            "/customers/{$documentNumber}/accounts",
            $paymentAccount->toArray(),
            null,
            true
        );
    }

    /**
     * @param string $documentNumber
     * @param PaymentAccount $paymentAccount
     * @return array|mixed
     * @throws RequestException
     */
    public function createBusinessCustomerAccount(string $documentNumber, PaymentAccount $paymentAccount): mixed
    {
        return $this->post(
            "/business/{$documentNumber}/accounts",
            $paymentAccount->toArray(),
            null,
            true
        );
    }

    /**
     * Validate of boleto or dealership
     *
     * @param string $code - Digitable line
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function paymentValidate(string $code, string $correlationId): mixed
    {
        return $this->post('/bill-payment/validate', ['code' => $code], $correlationId, true);
    }

    /**
     * Confirmation of payment of boleto or dealership
     *
     * @param BillPayment $billPayment
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     */
    public function paymentConfirm(
        BillPayment $billPayment,
        string $correlationId
    ): mixed
    {
        return $this->post('/bill-payment/confirm', $billPayment->toArray(), $correlationId, true);
    }

    /**
     * @param DepositBillet $depositBillet
     * @return array|mixed
     * @throws RequestException
     */
    public function depositBillet(DepositBillet $depositBillet): mixed
    {
        return $this->post('/bankslip', $depositBillet->toArray(), null, true);
    }

    /**
     * @param string $authenticationCode
     * @return mixed
     * @throws RequestException
     */
    public function printBillet(string $authenticationCode): mixed
    {
        return $this->get("/bankslip/{$authenticationCode}/pdf", null, null, false);
    }

    /**
     * @param string $branch
     * @param string $accountNumber
     * @param string $authenticationCode
     * @return array|mixed
     * @throws RequestException
     */
    public function getBillet(string $branch, string $accountNumber, string $authenticationCode): mixed
    {
        return $this->get("/bankslip/branch/{$branch}/number/{$accountNumber}/{$authenticationCode}");
    }

    /**
     * @param string $datetime
     * @return array|mixed
     * @throws RequestException
     */
    public function getBilletByDate(string $datetime): mixed
    {
        return $this->get("/bankslip/searchstatus/{$datetime}");
    }

    /**
     * @param string $barcode
     * @return array|mixed
     * @throws RequestException
     */
    public function getBilletByBarcode(string $barcode): mixed
    {
        return $this->get("/bankslip/{$barcode}");
    }

    /**
     * @param CancelBillet $cancelBillet
     * @return array|mixed
     * @throws RequestException
     */
    public function cancelBillet(CancelBillet $cancelBillet): mixed
    {
        return $this->delete('/bankslip/cancel', $cancelBillet->toArray());
    }

    /**
     * Create a new PIX key link with account.
     *
     * @param PixEntries $pixEntries
     * @return array|mixed
     * @throws RequestException
     */
    public function registerPixKey(PixEntries $pixEntries): mixed
    {
        return $this->post('/pix/entries', [
            'addressingKey' => $pixEntries->addressingKey->toArray(),
            'account' => $pixEntries->account->toArray(),
        ], null, true);
    }

    /**
     * Gets the list of address keys linked to an account.
     *
     * @param string $accountNumber
     * @return array|mixed
     * @throws RequestException
     */
    public function getPixAddressingKeys(string $accountNumber): mixed
    {
        return $this->get("/accounts/$accountNumber/addressing-keys");
    }

    /**
     * Gets details of the account linked to an addressing key.
     *
     * @param string $documentNumber
     * @param string $addressinKeyValue
     * @return array|mixed
     * @throws RequestException
     */
    public function getPixAddressingKeyValue(string $documentNumber, string $addressinKeyValue): mixed
    {
        $this->setHeaders(['x-bkly-pix-user-id' => $documentNumber]);
        return $this->get("/pix/entries/$addressinKeyValue");
    }

    /**
     * Delete a key link with account.
     *
     * @param string $addressingKeyValue
     * @return array|mixed
     * @throws RequestException
     * @throws RequestException
     */
    public function deletePixAddressingKeyValue(string $addressingKeyValue): mixed
    {
        return $this->delete("/pix/entries/$addressingKeyValue");
    }

    /**
     * @param PixCashoutInterface $pixCashout
     * @param string $correlationId
     * @return array|mixed
     * @throws RequestException
     * @throws RequestException
     */
    public function pixCashout(PixCashoutInterface $pixCashout, string $correlationId): mixed
    {
        return $this->post('/pix/cash-out', $pixCashout->toArray(), $correlationId, true);
    }

    /**
     * @param PixCashoutInterface $pixRefund
     * @return array|mixed
     * @throws RequestException
     */
    public function pixRefund(PixCashoutInterface $pixRefund): mixed
    {
        return $this->post('/pix/cash-out:refund', $pixRefund->toArray(), null, true);
    }

    /**
     * @param string $documentNumber
     * @param PixStaticQrCode $data
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function qrCode(string $documentNumber, PixStaticQrCode $data): array
    {
        $this->setHeaders(['x-bkly-pix-user-id' => $documentNumber]);
        return $this->post('/pix/qrcodes/static/transfer', $data->toArray(), null, true);
    }

    /**
     * @param string $documentNumber
     * @param PixDynamicQrCode $data
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function dynamicQrCode(string $documentNumber, PixDynamicQrCode $data): array
    {
        $this->setHeaders(['x-bkly-pix-user-id' => $documentNumber]);
        return $this->post('/pix/qrcodes/dynamic/payment', $data->toArray(), null, true);
    }

    /**
     * @param PixQrCodeData $data
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function qrCodeDecode(PixQrCodeData $data): array
    {
        $qrCode = $data->toArray();

        $this->setHeaders([
            'x-bkly-pix-user-id' => $qrCode['documentNumber'],
        ]);

        return $this->post('/pix/qrcodes/decode', $qrCode, null, true);
    }

    /**
     * Get webhooks processed messages
     *
     * @param string $startDate
     * @param string $endDate
     * @param string|null $state
     * @param string|null $eventName
     * @param string|null $context
     * @param integer $page
     * @param integer $pagesize
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function getWebhookMessages(
        string $startDate,
        string $endDate,
        string $state = null,
        string $eventName = null,
        string $context = null,
        int $page = 1,
        int $pagesize = 100
    ): array
    {
        $query = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'state' => $state,
            'eventName' => $eventName,
            'context' => $context,
            'page' => $page,
            'pageSize' => $pagesize,
        ];

        return $this->get(
            '/webhooks/processed-messages',
            $query
        );
    }

    /**
     * Reprocess webhook message
     *
     * @param string $idempotencyKey
     * @return array|null
     * @throws RequestException
     */
    public function reprocessWebhookMessage(string $idempotencyKey): ?array
    {
        return $this->post('/webhooks/processed-messages/' . $idempotencyKey, [], null, true);
    }

    /**
     * Get limits by feature
     *
     * @param string $documentNumber
     * @param string $limitType
     * @param string $featureName
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function getFeatureLimits(string $documentNumber, string $limitType, string $featureName): array
    {
        return $this->get('/holders/' . $documentNumber . '/limits/' . $limitType . '/features/' . $featureName);
    }

    /**
     * Update customer limits by feature
     *
     * @param string $documentNumber
     * @param array|mixed $data
     * @return array
     * @throws RequestException
     * @throws RequestException
     */
    public function updateCustomerLimits(string $documentNumber, array $data): array
    {
        return $this->put('/holders/' . $documentNumber . '/max-limits', $data);
    }

    /**
     * @param string $endpoint
     * @param array|string|null $query
     * @param mixed|null $correlation_id
     * @param bool $responseJson
     * @return array|mixed
     * @throws RequestException
     */
    private function get(
        string $endpoint,
        array|string $query = null,
        mixed $correlation_id = null,
        bool $responseJson = true
    ): mixed
    {
        if (is_null($correlation_id) && $this->requireCorrelationId($endpoint)) {
            $correlation_id = Uuid::uuid4()->toString();
        }

        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders(['x-correlation-id' => $correlation_id]));

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        $request = $request->get($this->getFinalUrl($endpoint), $query)
            ->throw();

        return ($responseJson) ? $request->json() : $request;
    }

    /**
     * @param string $endpoint
     * @param array|null $body
     * @param string|null $correlation_id
     * @param bool $asJson
     * @return array|mixed
     * @throws RequestException
     */
    private function post(
        string $endpoint,
        array $body = null,
        string $correlation_id = null,
        bool $asJson = false
    ): mixed
    {
        if (is_null($correlation_id) && $this->requireCorrelationId($endpoint)) {
            $correlation_id = Uuid::uuid4()->toString();
        }

        $body_format = $asJson ? 'json' : 'form_params';
        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders(['x-correlation-id' => $correlation_id]))
            ->bodyFormat($body_format);

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        return $request->post($this->getFinalUrl($endpoint), $body)
            ->throw()
            ->json();
    }

    /**
     * @param string $endpoint
     * @param array|null $body
     * @param string|null $correlation_id
     * @param bool $asJson
     * @param bool $attachment
     * @param DocumentAnalysis $document
     * @param string $fieldName
     * @return array|mixed
     * @throws RequestException
     */
    private function put(
        string $endpoint,
        array $body = [],
        string $correlation_id = null,
        bool $asJson = false,
        bool $attachment = false,
        DocumentAnalysis $document = null
    ): mixed
    {
        if (is_null($correlation_id) && $this->requireCorrelationId($endpoint)) {
            $correlation_id = Uuid::uuid4()->toString();
        }

        $body_format = $asJson ? 'json' : 'form_params';
        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders(['x-correlation-id' => $correlation_id]))
            ->bodyFormat($body_format);

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        if ($attachment) {
            $request->attach($document->getFieldName(), $document->getFileContents(), $document->getFileName());
        }

        return $request->put($this->getFinalUrl($endpoint), $body)
            ->throw()
            ->json();
    }

    /**
     * @param string $endpoint
     * @param array|null $body
     * @param string|null $correlation_id
     * @param bool $asJson
     * @param bool $attachment
     * @param DocumentAnalysis $document
     * @param string $fieldName
     * @return array|mixed
     * @throws RequestException
     */
    private function postDocument(
        string $endpoint,
        array $body = [],
        string $correlation_id = null,
        bool $asJson = false,
        bool $attachment = false,
        DocumentAnalysis $document = null
    ): mixed
    {
        if (is_null($correlation_id) && $this->requireCorrelationId($endpoint)) {
            $correlation_id = Uuid::uuid4()->toString();
        }

        $body_format = $asJson ? 'json' : 'form_params';
        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders(['x-correlation-id' => $correlation_id]))
            ->bodyFormat($body_format);

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        if ($attachment) {
            $request->attach($document->getFieldName(), $document->getFileContents(), $document->getFileName());
        }

        return $request->post($this->getFinalUrl($endpoint), $body)
            ->throw()
            ->json();
    }

    /**
     * @param string $endpoint
     * @param array|null $body
     * @param string|null $correlation_id
     * @param bool $asJson
     * @return array|mixed
     * @throws RequestException
     */
    private function patch(
        string $endpoint,
        array $body = [],
        string $correlation_id = null,
        bool $asJson = false
    ): mixed
    {
        if (is_null($correlation_id) && $this->requireCorrelationId($endpoint)) {
            $correlation_id = Uuid::uuid4()->toString();
        }

        $body_format = $asJson ? 'json' : 'form_params';
        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders(['x-correlation-id' => $correlation_id]))
            ->bodyFormat($body_format);

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        return $request->patch($this->getFinalUrl($endpoint), $body)
            ->throw()
            ->json();
    }

    /**
     * Http delete method.
     *
     * @param string $endpoint
     * @param array|null $body
     * @return array|mixed
     * @throws RequestException
     */
    private function delete(string $endpoint, array $body = []): mixed
    {
        $token = $this->getToken() ?? Auth::login()->getToken();
        $request = Http::withToken($token)
            ->withHeaders($this->getHeaders($this->headers));

        if ($this->mtlsCert && $this->mtlsKey && $this->mtlsPassphrase) {
            $request = $this->setRequestMtls($request);
        }

        return $request->delete($this->getFinalUrl($endpoint), $body)
            ->throw()
            ->json();
    }

    /**
     * @param string $version API version
     * @return $this
     */
    private function setApiVersion($version = '1.0'): static
    {
        $this->api_version = $version;
        return $this;
    }

    /**
     * Add cert options to request
     *
     * @param PendingRequest $request
     * @return PendingRequest
     */
    private function setRequestMtls(PendingRequest $request): PendingRequest
    {
        return $request->withOptions([
            'cert' => $this->mtlsCert,
            'ssl_key' => [$this->mtlsKey, $this->mtlsPassphrase]
        ]);
    }

    /**
     * @param array $headers
     * @return array|string[]
     */
    private function getHeaders($headers = []): array
    {
        $default_headers = $this->headers;

        if (count($headers) > 0) {
            $default_headers = array_merge($headers, $default_headers);
        }

        return $default_headers;
    }

    /**
     * @param array $header
     * @return void
     */
    private function setHeaders($header): void
    {
        $this->headers = array_merge($this->headers, $header);
    }

    /**
     * @param string $endpoint
     * @return bool
     */
    private function requireCorrelationId(string $endpoint): bool
    {
        $not_required_endpoints = [
            '/banklist',
            '/connect/token'
        ];

        return !in_array($endpoint, $not_required_endpoints);
    }

    /**
     * @param string $endpoint
     * @return string
     */
    private function getFinalUrl(string $endpoint): string
    {
        return $this->api_url . $endpoint;
    }
}
