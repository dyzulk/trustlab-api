<?php

return [
    'ca_root' => [
        'countryName'            => env('CA_ROOT_COUNTRY_NAME', 'ID'),
        'organizationName'       => env('CA_ROOT_ORGANIZATION_NAME', 'DyDev Authority'),
        'organizationalUnitName' => env('CA_ROOT_ORGANIZATIONAL_UNIT_NAME', 'Security Division'),
        'commonName'             => env('CA_ROOT_COMMON_NAME', 'DyDev Its True'),
    ],
    'ca_4096' => [
        'countryName'            => env('CA_4096_COUNTRY_NAME', 'ID'),
        'organizationName'       => env('CA_4096_ORGANIZATION_NAME', 'TrustLab CA'),
        'organizationalUnitName' => env('CA_4096_ORGANIZATIONAL_UNIT_NAME', 'Security Division'),
        'commonName'             => env('CA_4096_COMMON_NAME', 'TrustLab Intermediate CA 4096'),
    ],
    'ca_2048' => [
        'countryName'            => env('CA_2048_COUNTRY_NAME', 'ID'),
        'organizationName'       => env('CA_2048_ORGANIZATION_NAME', 'TrustLab CA'),
        'organizationalUnitName' => env('CA_2048_ORGANIZATIONAL_UNIT_NAME', 'Security Division'),
        'commonName'             => env('CA_2048_COMMON_NAME', 'TrustLab Intermediate CA 2048'),
    ],
    'ca_leaf_default' => [
        'countryName'            => env('CA_LEAF_DEFAULT_COUNTRY_NAME', 'ID'),
        'localityName'           => env('CA_LEAF_DEFAULT_LOCALITY', 'Jakarta'),
        'stateOrProvinceName'    => env('CA_LEAF_DEFAULT_STATE', 'DKI Jakarta'),
        'organizationName'       => env('CA_LEAF_DEFAULT_ORGANIZATION_NAME', 'TrustLab Cooking'),
        'commonName'             => env('CA_LEAF_DEFAULT_COMMON_NAME', 'customer.trustlab.com'),
    ],
];
