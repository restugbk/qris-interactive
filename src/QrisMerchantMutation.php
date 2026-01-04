<?php

namespace Restugbk;

/**
 * [QRIS] QRIS Interactive Merchant Mutation API (Un-official)
 * Author : restugbk <https://github.com/restugbk>
 * Created at 01-01-2026 14:00
 */
class QrisMerchantMutation
{
    private string $username;
    private string $password;
    private ?string $session = null;
    private ?string $secretToken = null;
    private ?string $csrf = null;

    private const BASE_URL = "https://merchant.qris.interactive.co.id";
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

    private string $loginFilePath;

    /**
     * @param string $username Username login merchant
     * @param string $password Password login merchant
     * @param string|null $storagePath Lokasi file JSON untuk simpan session (Default ke folder yang sama dengan script)
     */
    public function __construct(string $username, string $password, ?string $storagePath = null)
    {
        $this->username = $username;
        $this->password = $password;

        // Jika path tidak ditentukan, buat file di direktori saat ini
        $this->loginFilePath = $storagePath ?: __DIR__ . '/session.json';

        if (!function_exists('str_get_html')) {
            $helperPath = __DIR__ . '/StrDomHtml.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
            } else {
                throw new \RuntimeException("File StrDomHtml.php tidak ditemukan di: " . $helperPath);
            }
        }
    }

    private function saveLoginData(): void
    {
        $data = [
            'session'      => $this->session,
            'secret_token' => $this->secretToken,
            'csrf'         => $this->csrf,
            'username'     => $this->username,
            'password'     => $this->password,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        file_put_contents($this->loginFilePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function loadLoginData(): ?array
    {
        if (!file_exists($this->loginFilePath)) {
            return null;
        }
        $data = json_decode(file_get_contents($this->loginFilePath), true);

        $this->session     = $data['session'] ?? null;
        $this->secretToken = $data['secret_token'] ?? null;
        $this->csrf        = $data['csrf'] ?? null;

        return $data;
    }

    public function ensureLogin(): void
    {
        $this->loadLoginData();

        if (empty($this->session)) {
            $this->performFreshLogin();
            return;
        }

        $cookie = "PHPSESSID={$this->session}";
        $checkCsrf = $this->getCsrfToken($cookie);

        if ($checkCsrf) {
            $this->csrf = $checkCsrf;
            $this->saveLoginData();
        } else {
            $this->performFreshLogin();
        }
    }

    private function performFreshLogin(): void
    {
        $tokenData = $this->getSecretToken();
        if ($tokenData && isset($tokenData['secret_token'])) {
            $this->secretToken = $tokenData['secret_token'];
            $this->session     = $tokenData['session'];
            $result = $this->login($this->secretToken);
            if ($result['status']) {
                $this->saveLoginData();
            }
        }
    }

    /**
     * Get secret_token and PHPSESSID from login page
     */
    public function getSecretToken(): ?array
    {
        $url = self::BASE_URL . "/m/login.php";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
            ],
        ]);

        $htmlContent = curl_exec($ch);
        $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($htmlContent, 0, $headerSize);
        $body        = substr($htmlContent, $headerSize);
        curl_close($ch);

        if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $matches)) {
            $this->session = $matches[1];
        }

        // Pastikan library str_get_html tersedia (dari simple_html_dom)
        if (!function_exists('str_get_html')) {
            throw new \RuntimeException("Fungsi 'str_get_html' tidak ditemukan. Pastikan library simple_html_dom sudah terinstall.");
        }

        $dom = str_get_html($body);
        $secretToken = null;
        if ($dom) {
            $input = $dom->find('input[name=secret_token]', 0);
            if ($input) {
                $secretToken = $input->value;
            }
        }

        return [
            'session'      => $this->session,
            'secret_token' => $secretToken,
        ];
    }

    /**
     * Authenticate to system
     */
    public function login(string $secretToken, bool $saveLogin = true): array
    {
        $url = self::BASE_URL . "/m/login.php?pgv=go";

        $postFields = [
            'secret_token' => $secretToken,
            'username'     => $this->username,
            'password'     => $this->password,
            'savelogin'    => $saveLogin ? 'on' : 'off',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER     => [
                'Origin: ' . self::BASE_URL,
                'Referer: ' . self::BASE_URL . '/m/login.php',
                'User-Agent: ' . $this->userAgent,
                'Cookie: PHPSESSID=' . $this->session,
            ],
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header     = substr($response, 0, $headerSize);
        curl_close($ch);

        $successUrl = "/m/pages/verification.php?step=check-user";
        if (strpos($header, $successUrl) !== false) {
            return ['status' => true, 'session' => $this->session];
        }

        return ['status' => false, 'messages' => 'Login failed!'];
    }

    /**
     * Get merchant location list
     */
    public function getMerchant(): array
    {
        $this->ensureLogin();

        $url = self::BASE_URL . "/v2/m/pages/verification.php?step=pilih-lokasi-usaha";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Cookie: PHPSESSID=' . $this->session,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $dom = str_get_html($response);
        $usahaList = [];

        if ($dom) {
            $ul = $dom->find('ul#keywordListLocation008', 0);
            if ($ul) {
                foreach ($ul->find('li') as $li) {
                    $namaUsaha = $li->getAttribute('data-namausaha');
                    $aTag      = $li->find('a', 0);
                    $urlHref   = $aTag ? $aTag->href : '';

                    // Extract LID and MID from URL
                    parse_str(parse_url($urlHref, PHP_URL_QUERY), $queryParams);

                    $usahaList[] = [
                        'merchant_name' => $namaUsaha,
                        'merchant_id'   => $queryParams['lid'] ?? null,
                        'address'       => $aTag ? trim($aTag->find('span.address-label', 0)->plaintext ?? $aTag->find('small', 0)->plaintext ?? '') : '',
                    ];
                }
            }
        }

        return empty($usahaList) ? ['status' => false, 'data' => []] : ['status' => true, 'data' => $usahaList];
    }

    /**
     * Switch merchant location
     */
    public function switchMerchant(string $lid, string $extraCookies = ''): array
    {
        $url = self::BASE_URL . "/v2/m/kontenr.php?idir=pages/location-list.php&pgv=switch&lid=" . urlencode($lid);
        $cookie = "PHPSESSID=" . $this->session . ($extraCookies ? "; $extraCookies" : "");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Cookie: ' . $cookie,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $isSuccess = (strpos($response, 'step=intro') !== false);
        return ['status' => $isSuccess];
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken(string $extraCookies = ''): ?string
    {
        $url = self::BASE_URL . "/v2/m/kontenr.php?idir=pages/historytrx.php";
        $cookie = "PHPSESSID=" . $this->session . ($extraCookies ? "; $extraCookies" : "");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Cookie: ' . $cookie,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $dom = str_get_html($response);
        if ($dom) {
            $meta = $dom->find('meta[name=csrf-token]', 0);
            if ($meta) {
                $this->csrf = $meta->content;
                return $this->csrf;
            }
        }
        return null;
    }

    /**
     * Main Function: Get Transactions
     */
    public function getTransactionsByRange(string $merchant, string $startDate, string $endDate, int $limit = 300): array
    {
        $this->ensureLogin();
        $this->switchMerchant($merchant);

        $url = self::BASE_URL . "/v2/m/proses.php?required=getTransactions";
        $range = "$startDate - $endDate";

        $postFields = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => $limit,
            'order[0][column]' => 0,
            'order[0][dir]' => 'desc',
            'range' => $range,
            'status' => 'all',
            'limit' => $limit,
            'store' => 0,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Cookie: PHPSESSID=' . $this->session,
                'X-Requested-With: XMLHttpRequest',
                'X-Token-Csrf: ' . $this->csrf,
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Referer: ' . self::BASE_URL . '/v2/m/kontenr.php?idir=pages/historytrx.php',
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['status' => true, 'data' => $this->formatTransactions($json)];
        }

        return ['status' => false, 'error' => 'Invalid JSON response'];
    }

    /**
     * Get Transactions Custom Filtering
     */
    public function getTransactionsByCustom(string $merchant, string $startDate, string $endDate, string $item, string $item_search, int $limit = 300): array
    {
        $this->ensureLogin();
        $this->switchMerchant($merchant);

        $url = self::BASE_URL . "/v2/m/proses.php?required=getTransactions";
        $range = "$startDate - $endDate";

        $postFields = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => $limit,
            'order[0][column]' => 0,
            'order[0][dir]' => 'desc',
            'range' => $range,
            'item' => $item,
            'item_search' => $item_search,
            'status' => 'all',
            'limit' => $limit,
            'store' => 0,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Cookie: PHPSESSID=' . $this->session,
                'X-Requested-With: XMLHttpRequest',
                'X-Token-Csrf: ' . $this->csrf,
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Referer: ' . self::BASE_URL . '/v2/m/kontenr.php?idir=pages/historytrx.php',
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['status' => true, 'data' => $this->formatTransactions($json)];
        }

        return ['status' => false, 'error' => 'Invalid JSON response'];
    }

    private function formatTransactions(array $json): array
    {
        if (!isset($json['data'])) return [];

        $transactions = [];
        foreach ($json['data'] as $row) {
            $transactions[] = [
                'transaction_id'   => (int) $row['idtrans'],
                'invoice_id'       => (int) $row['idinv'],
                'date'             => $row['tgl'],
                'amount'           => (int) $row['nominal1'],
                'amount_display'   => strip_tags($row['nominal']),
                'status'           => strip_tags($row['status']),
                'customer'         => $row['cs'],
                'payment_method'   => $row['paytype'],
                'rrn'              => $row['rrn'] ?? '',
                'note'             => $row['ket'] ?? '',
            ];
        }

        return [
            'total_records' => (int) ($json['recordsTotal'] ?? 0),
            'summary_amount' => (int) ($json['amountSummary'] ?? 0),
            'transactions'  => $transactions,
        ];
    }
}
