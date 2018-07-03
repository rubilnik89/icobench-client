<?php

namespace Kami\IcoBench;

use GuzzleHttp\Client as HttpClient;
use Kami\IcoBench\Exception\IcoBenchException;
use Psr\Http\Message\ResponseInterface;

class Client implements ClientInterface
{
    /**
     * @var string
     */
    protected $privateKey;

    /**
     * @var string
     */
    protected $publicKey;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * Client constructor.
     *
     * @param string $privateKey
     * @param string $publicKey
     * @param array $options
     */
    public function __construct($privateKey, $publicKey, $options = [])
    {
        $this->privateKey = $privateKey;
        $this->publicKey  = $publicKey;
        $this->httpClient = new HttpClient(array_merge(['base_uri' => self::API_URL], $options));
    }

    /**
     * {@inheritdoc}
     */
    public function getIcos($type = 'all', $data = [])
    {
        return $this->request(sprintf('icos/%s', $type), $data, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getIco($id, $data = [])
    {
        return $this->request(sprintf('ico/%s', $id), $data, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getOther($type)
    {
        return $this->request(sprintf('other/%s', $type), [], true);
    }

    /**
     * {@inheritdoc}
     */
    public function getPeople($type = 'registered', $data = [])
    {
        return $this->request(sprintf('people/%s', $type), $data, true);
    }

    /**
     * @param $action
     * @param $data
     * @param boolean $async
     *
     * @return array | string
     */
    protected function request($action, array $data, $async = false)
    {
        $method = $async ? 'postAsync' : 'post';
        $payload = json_encode($data);

        $promise = $this->httpClient->$method($action, [
            'json' => $data,
            'headers' => [
                'X-ICObench-Key' => $this->publicKey,
                'X-ICObench-Sig' => $this->sign($payload)
            ]
        ]);
        $promise->then(
            function (ResponseInterface $res) {
                return $this->processResponse($res);
            }
        );

        return $promise;
    }

    /**
     * Sign request
     *
     * @param $payload
     * @return string
     */
    protected function sign($payload)
    {
        return base64_encode(hash_hmac('sha384', $payload, $this->privateKey, true));
    }

    /**
     * Get data from response
     *
     * @param ResponseInterface $response
     * @return array | string
     *
     * @throws IcoBenchException
     */
    protected function processResponse(ResponseInterface $response)
    {
        if (200 !== $response->getStatusCode()) {
            throw new IcoBenchException(
                sprintf('IcoBench replied with non-success status (%s)', $response->getStatusCode())
            );
        }

        $data = json_decode($response->getBody(), true);

        if (isset($data['error'])) {
            throw new IcoBenchException($data['error']);
        }

        if (isset($data['message'])) {
            return $data['message'];
        }

        return $data;
    }
}
