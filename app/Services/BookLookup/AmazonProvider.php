<?php

namespace App\Services\BookLookup;

use Illuminate\Support\Facades\Http;

/**
 * Amazon Product Advertising API 5.0 (GetItems). يتطلب حساب Associates نشطًا ومفاتيح PA-API.
 * ISBN-10 يعمل كـ ASIN للكتب، لذا نحوّل ISBN-13 (978...) إلى ISBN-10.
 */
class AmazonProvider implements IsbnProvider
{
    private const SERVICE = 'ProductAdvertisingAPI';

    private const TARGET = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems';

    public function key(): string
    {
        return 'amazon';
    }

    public function label(): string
    {
        return 'Amazon';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.amazon.access_key'))
            && filled(config('services.amazon.secret_key'))
            && filled(config('services.amazon.partner_tag'));
    }

    public function find(string $isbn): ?array
    {
        $asin = Isbn::to10($isbn);
        if (! $asin) {
            return null;
        }

        $host = (string) config('services.amazon.host', 'webservices.amazon.com');
        $region = (string) config('services.amazon.region', 'us-east-1');

        $payload = json_encode([
            'ItemIds' => [$asin],
            'PartnerTag' => config('services.amazon.partner_tag'),
            'PartnerType' => 'Associates',
            'Marketplace' => config('services.amazon.marketplace', 'www.amazon.com'),
            'Resources' => [
                'ItemInfo.Title',
                'ItemInfo.ByLineInfo',
                'ItemInfo.ContentInfo',
                'ItemInfo.Classifications',
                'Images.Primary.Large',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $headers = $this->signedHeaders($host, $region, '/paapi5/getitems', $payload);

        $response = Http::timeout((int) config('services.isbn_lookup.timeout', 8))
            ->withHeaders($headers)
            ->withBody($payload, 'application/json; charset=utf-8')
            ->post('https://'.$host.'/paapi5/getitems');

        if (! $response->successful()) {
            return null;
        }

        $item = $response->json('ItemsResult.Items.0');
        $title = data_get($item, 'ItemInfo.Title.DisplayValue');
        if (! $title) {
            return null;
        }

        return Normalizer::clean([
            'title' => $title,
            'author' => collect(data_get($item, 'ItemInfo.ByLineInfo.Contributors', []))->pluck('Name')->filter()->implode('، '),
            'publisher' => data_get($item, 'ItemInfo.ByLineInfo.Manufacturer.DisplayValue'),
            'pages' => (int) data_get($item, 'ItemInfo.ContentInfo.PagesCount.DisplayValue', 0),
            'language' => Normalizer::language(data_get($item, 'ItemInfo.ContentInfo.Languages.DisplayValues.0.DisplayValue')),
            'cover' => Normalizer::cover(data_get($item, 'Images.Primary.Large.URL')),
        ]);
    }

    /**
     * توقيع AWS Signature V4 لطلب PA-API.
     *
     * @return array<string, string>
     */
    public function signedHeaders(string $host, string $region, string $path, string $payload, ?\DateTimeInterface $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $canonicalHeaders = "content-encoding:amz-1.0\n"
            ."content-type:application/json; charset=utf-8\n"
            ."host:{$host}\n"
            ."x-amz-date:{$amzDate}\n"
            .'x-amz-target:'.self::TARGET."\n";
        $signedHeaders = 'content-encoding;content-type;host;x-amz-date;x-amz-target';

        $canonicalRequest = implode("\n", ['POST', $path, '', $canonicalHeaders, $signedHeaders, hash('sha256', $payload)]);

        $scope = "{$dateStamp}/{$region}/".self::SERVICE.'/aws4_request';
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.config('services.amazon.secret_key'), true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return [
            'Content-Encoding' => 'amz-1.0',
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Target' => self::TARGET,
            'Authorization' => 'AWS4-HMAC-SHA256 Credential='.config('services.amazon.access_key')."/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}",
        ];
    }
}
