<?php
return [
    // Key that clients (Postman, other systems) must send to the Hotel API: header X-API-Key
    'api_key'     => env('HOTEL_API_KEY', 'demo-key-123'),
    // System 2: the Payment API (runs on XAMPP Apache)
    'payment_url' => env('PAYMENT_API_URL', 'http://127.0.0.1:8003/api.php/charges'),
    'payment_key' => env('PAYMENT_API_KEY', 'pay-key-456'),
    'banks' => ['BDO Unibank', 'BPI', 'Metrobank', 'Security Bank', 'UnionBank', 'Landbank', 'PNB', 'RCBC',
                'China Bank', 'EastWest Bank', 'PSBank', 'Maybank Philippines', 'GoTyme Bank', 'Other bank'],
];
