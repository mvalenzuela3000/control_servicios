<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CiudadaniaDigitalAprobadorService
{
    protected $client;
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ciudadania.aprobador_url'), '/');
        $this->token = config('ciudadania.aprobador_token');

        $this->client = new Client([
            'timeout' => 90,
            'verify' => true,
        ]);
    }

    private function post($endpoint, array $payload)
    {
        try {
            $response = $this->client->post($this->baseUrl . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return [
                'ok' => true,
                'status' => $response->getStatusCode(),
                'body' => json_decode((string) $response->getBody(), true),
            ];
        } catch (RequestException $e) {
            return [
                'ok' => false,
                'status' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500,
                'body' => $e->hasResponse()
                    ? json_decode((string) $e->getResponse()->getBody(), true)
                    : null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function aprobarIndividual(array $payload)
    {
        return $this->post('/api/solicitudes', $payload);
    }

    public function aprobarMultiple(array $payload)
    {
        return $this->post('/api/solicitudes/multiples', $payload);
    }

    public function verificarDocumento(string $archivoBase64)
    {
        return $this->post('/api/documentos/verificar', [
            'archivo' => $archivoBase64,
        ]);
    }
}
