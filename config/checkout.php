<?php

return [

    /*
    | Shipping methods. Fee is charged per seller in the order
    | (each seller ships its own package).
    */
    'shipping_methods' => [
        'standard' => [
            'label' => 'Standard Delivery',
            'description' => 'Arrives in 3-7 days.',
            'fee_per_seller' => 50,
        ],
        'express' => [
            'label' => 'Express Delivery',
            'description' => 'Arrives in 1-3 days.',
            'fee_per_seller' => 120,
        ],
        'pickup' => [
            'label' => 'Store Pickup',
            'description' => 'Pick up from the seller at no cost.',
            'fee_per_seller' => 0,
        ],
    ],

    'default_shipping_method' => 'standard',

    /*
    | Orders with a merchandise subtotal (after discount) at or above this
    | amount ship free on non-express methods. Set to null to disable.
    */
    'free_shipping_threshold' => 2000,

    /*
    | Payment methods. "online" methods can use a saved payment method.
    */
    'payment_methods' => [
        'cod' => [
            'label' => 'Cash on Delivery',
            'description' => 'Pay with cash when your order arrives.',
            'online' => false,
        ],
        'gcash' => [
            'label' => 'GCash',
            'description' => 'Pay using your GCash wallet.',
            'online' => true,
        ],
        'maya' => [
            'label' => 'Maya',
            'description' => 'Pay using your Maya wallet.',
            'online' => true,
        ],
        'card' => [
            'label' => 'Credit / Debit Card',
            'description' => 'Pay with Visa, Mastercard or JCB.',
            'online' => true,
        ],
    ],
];

