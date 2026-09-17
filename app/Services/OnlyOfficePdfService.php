<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use RuntimeException;

class OnlyOfficePdfService
{
    protected Client $client;
    protected string $url;
    protected string $secret;
    public function __construct()
    {
        $this->url = rtrim(env('ONLYOFFICE_URL'), '/');
        $this->secret = env('ONLYOFFICE_JWT_SECRET');
        $this->client = new Client([
            'timeout' => (int) env('ONLYOFFICE_TIMEOUT', 180),
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }
    public function convertir(string $sourceUrl, string $nombreOriginal, string $requestId): array
    {
        /* 1. Key única de ONLYOFFICE*/
        $key = substr(hash('sha256', $requestId . '|' . $nombreOriginal), 0, 40);
        /* 2. Parámetros de conversión*/
        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'key' => $key,
            'outputtype' => 'pdf',
            'title' => basename($nombreOriginal),
            'url' => $sourceUrl,
        ];
        /* 3. JWT|OFFICE espera:
        |
        | {
        |   "payload": {
        |       ...
        |   }
        | }
        |
        */
        $token = JWT::encode(['payload' => $payload], $this->secret, 'HS256');
        /*4. Conversión    */
        $response = $this->client->post(
            $this->url . '/converter',
                [
                'query' => [
                    'shardkey' => $key
                ],

                'headers' => [

                    'Accept' =>
                        'application/json',

                    'Content-Type' =>
                        'application/json',

                    'Authorization' =>
                        'Bearer ' . $token,
                ],
                'json' => $payload,
            ]
        );
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                'ONLYOFFICE respondió HTTP ' . $response->getStatusCode()
            );
        }
        /* 5. Respuesta JSON*/
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new RuntimeException(
                'Respuesta inválida de ONLYOFFICE.'
            );
        }
        if (isset($data['error'])) {
            throw new RuntimeException(
                'ONLYOFFICE error: ' . $data['error']
            );
        }
        if (empty($data['endConvert']) || empty($data['fileUrl'])) {
            throw new RuntimeException(
                'ONLYOFFICE no completó la conversión.'
            );
        }
        /* 6. Descargar PDF*/

        $pdfResponse = $this->client->get($data['fileUrl'], ['http_errors' => false, 'timeout' => 60,]);
        if ($pdfResponse->getStatusCode() !== 200) {
            throw new RuntimeException(
                'No se pudo recuperar el PDF generado.'
            );
        }
        $pdf = (string) $pdfResponse->getBody();
        /* 7. Validaciones*/
        if (substr($pdf, 0, 5) !== '%PDF-') {
            throw new RuntimeException(
                'La salida no corresponde a un PDF válido.'
            );
        }
        $tamano = strlen($pdf);
        if ($tamano <= 0) {
            throw new RuntimeException(
                'ONLYOFFICE devolvió un PDF vacío.'
            );
        }
        if ($tamano > 50 * 1024 * 1024) {
            throw new RuntimeException(
                'El PDF supera el tamaño permitido.'
            );
        }
        return ['contenido' => $pdf, 'sha256' => hash('sha256', $pdf), 'tamano' => $tamano, 'request_id' => $requestId, 'onlyoffice_key' => $key, 'mime_type' => 'application/pdf',];
    }
}
