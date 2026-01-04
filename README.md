# QRIS Interactive Merchant Mutation API

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-777bb4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Open Source Love svg1](https://badges.frapsoft.com/os/v1/open-source.svg?v=103)](https://github.com/restugbk/qris-interactive)

**[Un-Official]** A lightweight, Un-Official PHP Library for automating transaction mutation retrieval from the QRIS Interactive Merchant Dashboard. Designed to be framework-agnostic, efficient, and easy to integrate.

---

## 📦 Installation

Install the package via [Composer](https://getcomposer.org/):

```bash
composer require restugbk/qris-interactive
```

## 1. Basic Initialization

By default, the library stores session data in a `session.json` file within the script's directory.
```php
use Restugbk\QrisMerchantMutation;

$username = 'YOUR_USERNAME';
$password = 'YOUR_PASSWORD';

// For Native PHP
$qris = new QrisMerchantMutation($username, $password);

// For Laravel (Recommended: store session in storage folder)
// $qris = new QrisMerchantMutation($username, $password, storage_path('app/qris_session.json'));
```

## 2. Retrieve Merchant List (Outlets)

Before fetching transactions, you must identify the `merchant_id` for your specific outlet.
```php
$response = $qris->getMerchant();

if ($response['status']) {
    foreach ($response['data'] as $merchant) {
        echo "Outlet Name: " . $merchant['merchant_name'] . "\n";
        echo "Merchant ID: " . $merchant['merchant_id'] . "\n"; 
        echo "Address    : " . $merchant['address'] . "\n---\n";
    }
}
```

## 3. Fetch Transaction Mutations

Retrieve transaction data by providing the `merchant_id` and a date range (Format: `DD/MM/YYYY`).
```php
$merchantId = '1234567890'; 
$startDate  = '01/01/2026';
$endDate    = '03/01/2026';

$result = $qris->getTransactionsByRange($merchantId, $startDate, $endDate);

if ($result['status']) {
    $data = $result['data'];
    echo "Total Records: " . $data['total_records'] . "\n";
    echo "Total Amount : Rp " . number_format($data['summary_amount']) . "\n";

    foreach ($data['transactions'] as $trx) {
        echo "[{$trx['date']}] {$trx['customer']} - Rp " . number_format($trx['amount']) . " ({$trx['status']})\n";
    }
} else {
    echo "Error: " . $result['error'];
}
```

## 4. Advanced Search (Custom Filtering)
You can search for specific transactions using `RRN`, `Invoice ID`, or `Customer Name` to filter results more accurately without fetching all data.

```php
$merchantId = '123456789';
$startDate  = '01/01/2026';
$endDate    = '04/01/2026';

/**
 * Search Category ($item):
 * 'rrn'      -> Search by Retrieval Reference Number
 * 'inv'      -> Search by Invoice ID
 * 'nominal'  -> Search by Transaction Amount
 * 'csname'   -> Search by Customer Name
 * 'infoket'  -> Search by Transaction Note/Description
 */
$filterBy = 'nominal';  // Search Category
$keyword  = '1000'; // The actual value you want to find

$result = $qris->getTransactionsByCustom(
    $merchantId, 
    $startDate, 
    $endDate, 
    $filterBy, 
    $keyword
);

if ($result['status']) {
    print_r($result['data']['transactions']);
}
```

## 📋 Data Structure Reference

The `transactions` array returns the following keys:

| Key | Type | Description |
| :--- | :--- | :--- |
| `transaction_id` | **Integer** | Unique transaction ID from the server. |
| `invoice_id` | **Integer** | Associated Invoice number. |
| `date` | **String** | Transaction timestamp. |
| `amount` | **Integer** | Raw transaction nominal. |
| `amount_display` | **String** | Formatted nominal (e.g., Rp 50.000). |
| `status` | **String** | Transaction status (Success/Pending/Expired). |
| `payment_method` | **String** | Customer's payment source (e.g., ShopeePay, OVO). |
| `customer` | **String** | Customer name or identifier. |

🤝 Contributing
------------

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are greatly appreciated.

1. Fork the Project.
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`).
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`).
4. Push to the Branch (`git push origin feature/AmazingFeature`).
5. Open a Pull Request.

📄 License
------------

This open-source software is distributed under the MIT License. See LICENSE for more information.

## 🛠 Support

If you found this project helpful, please give it a ⭐ star!

For issues and questions, please create an issue in the GitHub repository.
