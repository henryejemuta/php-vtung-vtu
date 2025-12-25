<?php

namespace HenryEjemuta\Vtung;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    /**
     * The base URL for the VTU.ng API.
     */
    private const BASE_URL = 'https://vtu.ng/wp-json/';

    /**
     * The API Token.
     *
     * @var string|null
     */
    private $token;

    /**
     * The Guzzle HTTP Client instance.
     *
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * Client constructor.
     *
     * @param string|null $token The API Token.
     * @param array $config Configuration options (base_url, timeout, etc.).
     */
    public function __construct(?string $token = null, array $config = [])
    {
        $this->token = $token;

        $baseUrl = $config['base_url'] ?? self::BASE_URL;
        // Ensure base URL ends with a slash
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }

        $timeout = $config['timeout'] ?? 30;
        $retries = $config['retries'] ?? 3;

        $handlerStack = $config['handler_stack'] ?? \GuzzleHttp\HandlerStack::create();

        $handlerStack->push(\GuzzleHttp\Middleware::retry(
            function ($retriesCount, $request, $response = null, $exception = null) use ($retries) {
                // Retry on connection exceptions
                if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                    return true;
                }

                if ($exception instanceof \GuzzleHttp\Exception\RequestException && $exception->hasResponse()) {
                    $response = $exception->getResponse();
                }

                // Retry on server errors (5xx)
                if ($response && $response->getStatusCode() >= 500) {
                    // Check retries count before deciding to retry
                    if ($retriesCount >= $retries) {
                        return false;
                    }

                    return true;
                }

                return false;
            },
            function ($retriesCount) {
                // Exponential backoff
                return pow(2, $retriesCount - 1) * 1000;
            }
        ));

        $guzzleConfig = [
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'handler' => $handlerStack,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($this->token) {
            $guzzleConfig['headers']['Authorization'] = 'Bearer '.$this->token;
        }

        $this->httpClient = new GuzzleClient($guzzleConfig);
    }

    /**
     * Authenticate and retrieve a token.
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws VtungException
     */
    public function authenticate(string $username, string $password): array
    {
        $response = $this->request('POST', 'jwt-auth/v1/token', [
            'json' => [
                'username' => $username,
                'password' => $password,
            ],
        ], false);

        if (isset($response['token'])) {
            $this->token = $response['token'];
            // Re-initialize client to include token in headers for future requests
            // Or mostly users might just use the returned token to instantiate a new Client or we can update a property if we made headers mutable.
            // But Guzzle clients are immutable regarding config.
            // However, we can simply pass the token in each request if we stored it, or tell the user to re-instantiate.
            // For now, let's just return the response. Ideally, the user should instantiate with the token.
            // But for convenience, let's allow updating the token on the fly if we want, but simpler is returning it.
        }

        return $response;
    }

    /**
     * Make a request to the API.
     *
     * @param string $method
     * @param string $uri
     * @param array $options
     * @param bool $authRequired
     * @return array
     * @throws VtungException
     */
    private function request(string $method, string $uri, array $options = [], bool $authRequired = true): array
    {
        if ($authRequired && ! $this->token) {
            throw new VtungException('API Token is required for this request.');
        }

        // If we acquired a token after construction via authenticate() and didn't rebuild the client,
        // we need to inject the Authorization header manually if it's missing.
        // But since we can't easily modify the client default headers, we merge it here.
        if ($authRequired && $this->token) {
            $options['headers']['Authorization'] = 'Bearer '.$this->token;
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $content = $response->getBody()->getContents();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new VtungException('Failed to decode JSON response: '.json_last_error_msg());
            }

            return $data;
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($responseBody, true);
                if (isset($errorData['message'])) {
                    $message = $errorData['message'];
                } elseif (isset($errorData['code'])) {
                    $message = $errorData['code']; // vtu.ng sometimes returns code/message structure
                }
            }
            throw new VtungException('API Request Failed: '.$message, $e->getCode(), $e);
        }
    }

    /**
     * Get wallet balance.
     *
     * @return array
     * @throws VtungException
     */
    public function getBalance(): array
    {
        return $this->request('GET', 'api/v2/balance');
    }

    /**
     * Purchase airtime.
     *
     * @param string $network The network ID (e.g., 'mtn', 'glo').
     * @param string $phone The phone number.
     * @param float $amount The amount to top up.
     * @param string $requestId Unique request ID.
     * @return array
     * @throws VtungException
     */
    public function purchaseAirtime(string $network, string $phone, float $amount, string $requestId): array
    {
        return $this->request('POST', 'api/v2/airtime', [
            'json' => [
                'request_id' => $requestId,
                'service_id' => $network,
                'phone' => $phone,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Get data variations.
     *
     * @param string|null $serviceId Optional service ID to filter (e.g., 'mtn').
     * @return array
     * @throws VtungException
     */
    public function getDataVariations(?string $serviceId = null): array
    {
        $options = [];
        if ($serviceId) {
            $options['query'] = ['service_id' => $serviceId];
        }

        return $this->request('GET', 'api/v2/variations/data', $options, false);
    }

    /**
     * Purchase data.
     *
     * @param string $serviceId The service ID (e.g., 'mtn').
     * @param string $phone The phone number.
     * @param string $variationId The variation ID.
     * @param string $requestId Unique request ID.
     * @return array
     * @throws VtungException
     */
    public function purchaseData(string $serviceId, string $phone, string $variationId, string $requestId): array
    {
        return $this->request('POST', 'api/v2/data', [
            'json' => [
                'request_id' => $requestId,
                'service_id' => $serviceId,
                'phone' => $phone,
                'variation_id' => $variationId,
            ],
        ]);
    }

    /**
     * Verify customer (Electricity, Cable TV, Betting).
     *
     * @param string $customerId The customer ID / Meter Number / Smartcard Number.
     * @param string $serviceId The service ID.
     * @param string|null $variationId Optional variation ID (required for electricity).
     * @return array
     * @throws VtungException
     */
    public function verifyCustomer(string $customerId, string $serviceId, ?string $variationId = null): array
    {
        $payload = [
            'customer_id' => $customerId,
            'service_id' => $serviceId,
        ];
        if ($variationId) {
            $payload['variation_id'] = $variationId;
        }

        return $this->request('POST', 'api/v2/verify-customer', [
            'json' => $payload,
        ]);
    }

    /**
     * Purchase electricity.
     *
     * @param string $requestId Unique request ID.
     * @param string $customerId The customer ID / Meter Number.
     * @param string $serviceId The service ID (e.g., 'ikeja-electric').
     * @param string $variationId The variation ID (e.g., 'prepaid').
     * @param float $amount The amount to purchase.
     * @return array
     * @throws VtungException
     */
    public function purchaseElectricity(string $requestId, string $customerId, string $serviceId, string $variationId, float $amount): array
    {
        return $this->request('POST', 'api/v2/electricity', [
            'json' => [
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'variation_id' => $variationId,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Fund betting account.
     *
     * @param string $requestId Unique request ID.
     * @param string $customerId The customer ID.
     * @param string $serviceId The service ID (e.g., 'Bet9ja').
     * @param float $amount The amount to fund.
     * @return array
     * @throws VtungException
     */
    public function fundBettingAccount(string $requestId, string $customerId, string $serviceId, float $amount): array
    {
        return $this->request('POST', 'api/v2/betting', [
            'json' => [
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Get cable TV variations.
     *
     * @param string|null $serviceId The service ID (e.g., 'dstv').
     * @return array
     * @throws VtungException
     */
    public function getCableVariations(?string $serviceId = null): array
    {
        $options = [];
        if ($serviceId) {
            $options['query'] = ['service_id' => $serviceId];
        }

        return $this->request('GET', 'api/v2/variations/tv', $options, false);
    }

    /**
     * Purchase Cable TV subscription.
     *
     * @param string $requestId Unique request ID.
     * @param string $customerId The Smartcard/IUC number.
     * @param string $serviceId The service ID (e.g., 'dstv').
     * @param string $variationId The variation ID (plan).
     * @return array
     * @throws VtungException
     */
    public function purchaseCableTV(string $requestId, string $customerId, string $serviceId, string $variationId): array
    {
        return $this->request('POST', 'api/v2/tv', [
            'json' => [
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'variation_id' => $variationId,
            ],
        ]);
    }

    /**
     * Purchase ePINs.
     *
     * @param string $requestId Unique request ID.
     * @param string $serviceId The service ID (e.g., 'mtn').
     * @param int $value The value of the card (e.g., 100, 200, 500).
     * @param int $quantity The quantity to purchase.
     * @return array
     * @throws VtungException
     */
    public function purchaseEPins(string $requestId, string $serviceId, int $value, int $quantity): array
    {
        return $this->request('POST', 'api/v2/epins', [
            'json' => [
                'request_id' => $requestId,
                'service_id' => $serviceId,
                'value' => $value,
                'quantity' => $quantity,
            ],
        ]);
    }

    /**
     * Requery order status.
     *
     * @param string $requestId The request ID of the order.
     * @return array
     * @throws VtungException
     */
    public function requeryOrder(string $requestId): array
    {
        return $this->request('POST', 'api/v2/requery', [
            'json' => [
                'request_id' => $requestId,
            ],
        ]);
    }
}
