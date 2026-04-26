<?php

return [
    [
        'name' => 'Assets',
        'code' => 'ASS',
        'description' => 'All Assets',
        'children' => [
            [
                'name' => 'Current Assets',
                'code' => 'CA',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Inventory',
                        'code' => 'INV',
                        'description' => null,
                        'category' => null,
                    ],
                ],
                'children' => [
                    [
                        'name' => 'Cash & Cash Equivalents',
                        'code' => 'CCE',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Cash in Hand',
                                'code' => 'CIH',
                                'description' => null,
                                'category' => 'Cash',
                            ],
                            [
                                'name' => 'Petty Cash',
                                'code' => 'PC',
                                'description' => null,
                                'category' => 'Cash',
                            ],
                            [
                                'name' => 'ABC Bank',
                                'code' => 'AB',
                                'description' => null,
                                'category' => 'Bank',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Advance & Deposits',
                        'code' => 'AD',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Advance Tax',
                                'code' => 'AT',
                                'description' => null,
                                'category' => 'Advance Tax',
                            ],
                            [
                                'name' => 'Advance Salary',
                                'code' => 'AS',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Account Receivables',
                        'code' => 'AR',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Sundry Debtors',
                                'code' => 'SD',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Prepayments',
                        'code' => 'PRE',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Prepaid Expense',
                                'code' => 'PE',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Other Current Assets',
                        'code' => 'OCA',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Other Receivables',
                                'code' => 'OR',
                                'description' => 'Miscellaneous Receivables',
                                'category' => null,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Fixed Assets',
                'code' => 'FA',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Office Equipments',
                        'code' => 'OE',
                        'description' => null,
                        'category' => 'Fixed Assets',
                    ],
                    [
                        'name' => 'Furnitures',
                        'code' => 'FUR',
                        'description' => null,
                        'category' => 'Fixed Assets',
                    ],
                    [
                        'name' => 'Computers & Electonics',
                        'code' => 'CE',
                        'description' => null,
                        'category' => 'Fixed Assets',
                    ],
                    [
                        'name' => 'Vehicles',
                        'code' => 'VEH',
                        'description' => null,
                        'category' => 'Fixed Assets',
                    ],
                ],
                'children' => [
                    [
                        'name' => 'Land & Building',
                        'code' => 'LB',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Land',
                                'code' => 'LAN',
                                'description' => null,
                                'category' => 'Fixed Assets',
                            ],
                            [
                                'name' => 'Building',
                                'code' => 'BUI',
                                'description' => null,
                                'category' => 'Fixed Assets',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        'name' => 'Liabilities',
        'code' => 'LIA',
        'description' => 'All Liabilities',
        'children' => [
            [
                'name' => 'Current Liabilities',
                'code' => 'CL',
                'description' => null,
                'children' => [
                    [
                        'name' => 'Expenses Payables',
                        'code' => 'EP',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Audit Fee Payable',
                                'code' => 'AFP',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Retirement Fund Payables',
                        'code' => 'RFP',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'SSF Payable',
                                'code' => 'SP',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'TDS Payables',
                        'code' => 'TP',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'TDS - Pvt. Ltd.',
                                'code' => 'TPL',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                            [
                                'name' => 'TDS - Remuneration',
                                'code' => 'TR',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                            [
                                'name' => 'TDS - Social Security Fund',
                                'code' => 'TSSF',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                            [
                                'name' => 'TDS - Individual & Proprietorship Firm',
                                'code' => 'TIPF',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                            [
                                'name' => 'TDS - Rent',
                                'code' => 'TR2',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                            [
                                'name' => 'TDS - Others',
                                'code' => 'TO',
                                'description' => null,
                                'category' => 'TDS',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Employee Payables',
                        'code' => 'EP2',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Staff Payables',
                                'code' => 'SP2',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Government Payables',
                        'code' => 'GP',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'VAT Account',
                                'code' => 'VA',
                                'description' => null,
                                'category' => 'VAT',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Account Payables',
                        'code' => 'AP',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Sundry Creditors',
                                'code' => 'SC',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Non-Current Liabilities',
                'code' => 'NCL',
                'description' => null,
                'children' => [
                    [
                        'name' => 'Medium & Long term Loan',
                        'code' => 'MLTL',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'ABC Term Loan 001212121',
                                'code' => 'ATL0',
                                'description' => null,
                                'category' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        'name' => 'Equity',
        'code' => 'EQU',
        'description' => 'All Equity',
        'children' => [
            [
                'name' => 'Share Capital',
                'code' => 'SC',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Equity Share Capital',
                        'code' => 'ESC',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
            [
                'name' => 'Reserve & Surplus',
                'code' => 'RS',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Retained Earnings',
                        'code' => 'RE',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
        ],
    ],
    [
        'name' => 'Income',
        'code' => 'INC',
        'description' => 'All Income',
        'children' => [
            [
                'name' => 'Direct Income',
                'code' => 'DI',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Sales of Goods',
                        'code' => 'SOG',
                        'description' => null,
                        'category' => 'Sales',
                    ],
                    [
                        'name' => 'Sales of Services',
                        'code' => 'SOS',
                        'description' => null,
                        'category' => 'Sales',
                    ],
                    [
                        'name' => 'Less: Sales Discount',
                        'code' => 'LSD',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
            [
                'name' => 'Indirect Income',
                'code' => 'II',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Interest Income',
                        'code' => 'II',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Scrap Sales',
                        'code' => 'SS',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
        ],
    ],
    [
        'name' => 'Expenses',
        'code' => 'EXP',
        'description' => 'All Expenses',
        'children' => [
            [
                'name' => 'Direct Expenses',
                'code' => 'DE',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Purchase Account',
                        'code' => 'PA',
                        'description' => null,
                        'category' => 'Purchases',
                    ],
                    [
                        'name' => 'Less: Purchase Discount',
                        'code' => 'LPD',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Cost of Goods Sold',
                        'code' => 'COGS',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
            [
                'name' => 'Employee Expenses',
                'code' => 'EE',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Basic Salary',
                        'code' => 'BS',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Dearness Allowance',
                        'code' => 'DA',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Festival Allowance',
                        'code' => 'FA',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
            [
                'name' => 'Depreciation Expenses',
                'code' => 'DE2',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Depn. - Office Equipments',
                        'code' => 'DOE',
                        'description' => null,
                        'category' => '2',
                    ],
                    [
                        'name' => 'Depn. - Building',
                        'code' => 'DB',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Depn. - Furniture',
                        'code' => 'DF',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Depn. - Computers & Electronics',
                        'code' => 'DCE',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Depn. - Vehicles',
                        'code' => 'DV',
                        'description' => null,
                        'category' => null,
                    ],
                ],
            ],
            [
                'name' => 'Administrative Expenses',
                'code' => 'AE',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Audit Fee',
                        'code' => 'AF',
                        'description' => null,
                        'category' => null,
                    ],
                    [
                        'name' => 'Business Promotion',
                        'code' => 'BP',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Communication Expense',
                        'code' => 'CE2',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Cleaning Expense',
                        'code' => 'CE3',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Consultant Fees',
                        'code' => 'CF',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Electricity Expense',
                        'code' => 'EE',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Office Consumables',
                        'code' => 'OC',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Insurance Expense',
                        'code' => 'IE',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Fines & Penalties',
                        'code' => 'FP',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Gardening Expense',
                        'code' => 'GE',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Internet Expense',
                        'code' => 'IE2',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Medical Expense',
                        'code' => 'ME',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Meeting Allowance',
                        'code' => 'MA',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Meeting Expense',
                        'code' => 'ME2',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Miscellaneous Expense',
                        'code' => 'ME3',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Newspapers & Periodicals',
                        'code' => 'NP',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Printing & Stationery',
                        'code' => 'PS',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Refreshment Expense',
                        'code' => 'RE2',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Registration & Renewals',
                        'code' => 'RR',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Office Rent Expense',
                        'code' => 'ORE',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Local Fares & Transportation',
                        'code' => 'LFT',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Donation Expense',
                        'code' => 'DE',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                ],
                'children' => [
                    [
                        'name' => 'Repairs & Maintenance',
                        'code' => 'RM',
                        'description' => null,
                        'accounts' => [
                            [
                                'name' => 'Repair - Building',
                                'code' => 'RB',
                                'description' => null,
                                'category' => 'Expense',
                            ],
                            [
                                'name' => 'Repair - Office Equipments',
                                'code' => 'ROE',
                                'description' => null,
                                'category' => 'Expense',
                            ],
                            [
                                'name' => 'Repair - Furniture',
                                'code' => 'RF',
                                'description' => null,
                                'category' => 'Expense',
                            ],
                            [
                                'name' => 'Repair - Computers & Electronics',
                                'code' => 'RCE',
                                'description' => null,
                                'category' => 'Expense',
                            ],
                            [
                                'name' => 'Repair - Vehicles',
                                'code' => 'RV',
                                'description' => null,
                                'category' => 'Expense',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Income Tax Expenses',
                'code' => 'ITE',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Current Year Tax',
                        'code' => 'CYT',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Tax Interest & Penalties',
                        'code' => 'TIP',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                    [
                        'name' => 'Prior Period Taxes',
                        'code' => 'PPT',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                ],
            ],
            [
                'name' => 'Finance Expenses',
                'code' => 'FE',
                'description' => null,
                'accounts' => [
                    [
                        'name' => 'Bank Charges',
                        'code' => 'BC',
                        'description' => null,
                        'category' => 'Bank Charges',
                    ],
                    [
                        'name' => 'Interest Expense',
                        'code' => 'IE3',
                        'description' => null,
                        'category' => 'Expense',
                    ],
                ],
            ],
        ],
    ],
];
