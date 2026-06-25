<?php

/**
 * Default manufacturing preferences provisioned for every new company.
 * Stored as Setting keys under "company.{id}.manufacturing.*".
 */
return [

    'default_cost_basis' => 'standard_cost',
    'auto_consume_on_production' => true,
    'allow_negative_stock_in_bom' => false,
    'default_scrap_percentage' => 0,

];
