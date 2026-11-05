<?php

namespace Tests\Integration\Pkp;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PkpUploadEnvelopeTest extends TestCase
{
    public function test_authenticated_source_upload_crosses_nginx_php_and_application_limits(): void
    {
        $baseUrl = rtrim((string) getenv('PKP_STACK_BASE_URL'), '/');
        $this->assertNotSame('', $baseUrl, 'PKP_STACK_BASE_URL must point to the provisioned Nginx stack.');
        $cookies = new CookieJar;
        $client = new Client([
            'base_uri' => $baseUrl,
            'cookies' => $cookies,
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 90,
        ]);
        $loginPage = $client->get('/login');
        $this->assertSame(200, $loginPage->getStatusCode());
        $token = $this->csrfToken((string) $loginPage->getBody());
        $login = $client->post('/login', [
            'headers' => ['X-CSRF-TOKEN' => $token],
            'form_params' => [
                '_token' => $token,
                'email' => 'pkp-owner@example.test',
                'password' => 'pkp-testing-password',
            ],
        ]);
        $this->assertSame(302, $login->getStatusCode());
        $createPage = $client->get('/pkp/create');
        $this->assertSame(200, $createPage->getStatusCode(), 'Unexpected redirect: '.$createPage->getHeaderLine('Location').' cookies='.json_encode($cookies->toArray()).' '.(string) $createPage->getBody());
        $token = $this->csrfToken((string) $createPage->getBody());

        $period = CarbonImmutable::create(random_int(2027, 9990), random_int(1, 12), 1, 12, 0, 0, 'Asia/Makassar');
        $created = $client->post('/pkp', [
            'headers' => ['Accept' => 'application/json', 'X-CSRF-TOKEN' => $token],
            'form_params' => [
                '_token' => $token,
                'year' => $period->year,
                'month' => $period->month,
                'source' => 'external',
            ],
        ]);
        $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $createdData = json_decode((string) $created->getBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $pkpUuid = $createdData['pkp_uuid'];
        $revisionUuid = $createdData['revision_uuid'];
        $tenMiBPath = $this->createPdfAtSize(10 * 1024 * 1024, 'exactly ten mebibytes');
        $tenMiBAndOnePath = $this->createPdfAtSize((10 * 1024 * 1024) + 1, 'over application limit');
        $overEnvelopePath = $this->createPdfAtSize((16 * 1024 * 1024) + 1, 'over proxy envelope');

        try {
            $accepted = $this->uploadSource($client, $pkpUuid, $revisionUuid, $token, 1, $tenMiBPath);
            $this->assertSame(201, $accepted->getStatusCode(), (string) $accepted->getBody());
            $documentUuid = json_decode((string) $accepted->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['document_uuid'];
            $this->assertDatabaseHas('pkp_documents', [
                'uuid' => $documentUuid,
                'byte_size' => 10 * 1024 * 1024,
            ]);

            $tooLargeForFileRule = $this->uploadSource($client, $pkpUuid, $revisionUuid, $token, 2, $tenMiBAndOnePath);
            $this->assertSame(422, $tooLargeForFileRule->getStatusCode(), (string) $tooLargeForFileRule->getBody());
            $validation = json_decode((string) $tooLargeForFileRule->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('file', $validation['errors'] ?? []);
            $this->assertSame(1, DB::table('pkp_documents')->where('revision_uuid', $revisionUuid)->count());
            $this->assertDatabaseHas('pkp_revisions', [
                'uuid' => $revisionUuid,
                'source_document_uuid' => $documentUuid,
            ]);
            $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 2]);

            $overEnvelope = $this->uploadSource($client, $pkpUuid, $revisionUuid, $token, 2, $overEnvelopePath);
            $this->assertSame(413, $overEnvelope->getStatusCode(), (string) $overEnvelope->getBody());
            $this->assertSame(1, DB::table('pkp_documents')->where('revision_uuid', $revisionUuid)->count());
            $this->assertDatabaseHas('pkp_revisions', [
                'uuid' => $revisionUuid,
                'source_document_uuid' => $documentUuid,
            ]);
            $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 2]);
        } finally {
            @unlink($tenMiBPath);
            @unlink($tenMiBAndOnePath);
            @unlink($overEnvelopePath);
        }
    }

    private function uploadSource(Client $client, string $pkpUuid, string $revisionUuid, string $token, int $version, string $path): \Psr\Http\Message\ResponseInterface
    {
        $file = fopen($path, 'rb');
        $this->assertIsResource($file);
        try {
            return $client->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
                'headers' => ['Accept' => 'application/json', 'X-CSRF-TOKEN' => $token],
                'multipart' => [
                    ['name' => '_token', 'contents' => $token],
                    ['name' => 'expected_version', 'contents' => (string) $version],
                    ['name' => 'kind', 'contents' => 'source'],
                    ['name' => 'declared_signed', 'contents' => '0'],
                    [
                        'name' => 'file',
                        'contents' => $file,
                        'filename' => basename($path).'.pdf',
                        'headers' => ['Content-Type' => 'application/pdf'],
                    ],
                ],
            ]);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }
    }

    private function createPdfAtSize(int $size, string $title): string
    {
        $pdf = Pdf::loadHTML('<html><body><h1>'.e($title).'</h1></body></html>')->output();
        $xref = strrpos($pdf, 'startxref');
        $this->assertNotFalse($xref, 'The fixture PDF must contain a structural cross-reference marker.');
        $padding = $size - strlen($pdf);
        $this->assertGreaterThan(0, $padding, 'The generated fixture PDF must be smaller than its requested test size.');
        $bytes = substr($pdf, 0, $xref).str_repeat(' ', $padding).substr($pdf, $xref);
        $this->assertSame($size, strlen($bytes));
        $path = tempnam(sys_get_temp_dir(), 'pkp-envelope-');
        $this->assertNotFalse($path);
        file_put_contents($path, $bytes);

        return $path;
    }

    private function csrfToken(string $html): string
    {
        $matched = preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches);
        $this->assertSame(1, $matched, 'The real login page must provide its CSRF token.');

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }
}
