<?php

namespace App\Enums;

enum TdsCategoryEnum: string
{
    case SERVICE_VAT_BILL = 'service_vat_bill';
    case SERVICE_PAN_BILL = 'service_pan_bill';
    case SERVICE_VAT_EXEMPT_INSTITUTION = 'service_vat_exempt_institution';
    case CONTRACT_VAT_REGISTERED = 'contract_vat_registered';
    case RENT_PROPERTY = 'rent_property';
    case RENT_VEHICLE_VAT = 'rent_vehicle_vat';
    case RENT_VEHICLE_NO_VAT = 'rent_vehicle_no_vat';
    case INTEREST_BANK_NATURAL_PERSON = 'interest_bank_natural_person';
    case INTEREST_COMPANY = 'interest_company';
    case DIVIDEND = 'dividend';
    case ROYALTY = 'royalty';
    case COMMISSION = 'commission';
    case WINDFALL = 'windfall';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_VAT_BILL => 'Service Fee (VAT Bill) – 1.5%',
            self::SERVICE_PAN_BILL => 'Service Fee (PAN Bill) – 15%',
            self::SERVICE_VAT_EXEMPT_INSTITUTION => 'Service Fee (VAT-Exempt Institution) – 1%',
            self::CONTRACT_VAT_REGISTERED => 'Contract Payment (VAT Registered) – 1.5%',
            self::RENT_PROPERTY => 'Rent (House/Land/Property) – 10%',
            self::RENT_VEHICLE_VAT => 'Vehicle Hire (VAT Bill) – 1.5%',
            self::RENT_VEHICLE_NO_VAT => 'Vehicle Hire (No VAT Bill) – 10%',
            self::INTEREST_BANK_NATURAL_PERSON => 'Interest by Bank to Natural Person – 6%',
            self::INTEREST_COMPANY => 'Interest by Company/Debenture – 15%',
            self::DIVIDEND => 'Dividend – 5%',
            self::ROYALTY => 'Royalty – 15%',
            self::COMMISSION => 'Commission/Sales Bonus – 15%',
            self::WINDFALL => 'Windfall Gains – 25%',
        };
    }

    public function rate(): float
    {
        return match ($this) {
            self::SERVICE_VAT_BILL => 1.5,
            self::SERVICE_PAN_BILL => 15.0,
            self::SERVICE_VAT_EXEMPT_INSTITUTION => 1.0,
            self::CONTRACT_VAT_REGISTERED => 1.5,
            self::RENT_PROPERTY => 10.0,
            self::RENT_VEHICLE_VAT => 1.5,
            self::RENT_VEHICLE_NO_VAT => 10.0,
            self::INTEREST_BANK_NATURAL_PERSON => 6.0,
            self::INTEREST_COMPANY => 15.0,
            self::DIVIDEND => 5.0,
            self::ROYALTY => 15.0,
            self::COMMISSION => 15.0,
            self::WINDFALL => 25.0,
        };
    }

    public function revenueCode(): string
    {
        return match ($this) {
            self::SERVICE_VAT_BILL, self::SERVICE_PAN_BILL, self::SERVICE_VAT_EXEMPT_INSTITUTION => '11112',
            self::CONTRACT_VAT_REGISTERED => '11112',
            self::RENT_PROPERTY, self::RENT_VEHICLE_VAT, self::RENT_VEHICLE_NO_VAT => '11112',
            self::INTEREST_BANK_NATURAL_PERSON, self::INTEREST_COMPANY => '11212',
            self::DIVIDEND => '11213',
            self::ROYALTY => '11112',
            self::COMMISSION => '11112',
            self::WINDFALL => '11112',
        };
    }
}
