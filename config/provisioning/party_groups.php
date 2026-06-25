<?php

/**
 * Default party groups provisioned for every new company.
 * 'type' matches PartyTypeEnum values: customer | supplier.
 */
return [

    ['type' => 'customer', 'name' => 'Retail', 'code' => 'CG-RET'],
    ['type' => 'customer', 'name' => 'Wholesale', 'code' => 'CG-WHL'],
    ['type' => 'customer', 'name' => 'VIP', 'code' => 'CG-VIP'],
    ['type' => 'customer', 'name' => 'Government', 'code' => 'CG-GOV'],
    ['type' => 'supplier', 'name' => 'Local Supplier', 'code' => 'SG-LOC'],
    ['type' => 'supplier', 'name' => 'Foreign Supplier', 'code' => 'SG-FOR'],
    ['type' => 'supplier', 'name' => 'Service Provider', 'code' => 'SG-SVC'],

];
