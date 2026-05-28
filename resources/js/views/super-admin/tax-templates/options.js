export const typeOptions = [
    { value: '', label: 'None' },
    { value: 'vat_standard', label: 'VAT Standard (13%)' },
    { value: 'vat_exempt', label: 'VAT Exempt' },
    { value: 'vat_zero_rated', label: 'VAT Zero Rated' },
    { value: 'tds', label: 'TDS' },
];

export const tdsCategoryOptions = [
    { value: '', label: 'None' },
    { value: 'service_vat_bill', label: 'Service (VAT Bill) – 1.5%' },
    { value: 'service_pan_bill', label: 'Service (PAN Bill) – 15%' },
    { value: 'service_exempt', label: 'Service (Exempt Institution) – 1%' },
    { value: 'contract', label: 'Contract (VAT Registered) – 1.5%' },
    { value: 'rent_property', label: 'Rent (Property) – 10%' },
    { value: 'rent_vehicle_vat', label: 'Rent (Vehicle, VAT Bill) – 1.5%' },
    { value: 'dividend', label: 'Dividend – 5%' },
    { value: 'interest', label: 'Interest – 6%' },
    { value: 'royalty', label: 'Royalty / Commission – 15%' },
    { value: 'windfall', label: 'Windfall Gains – 25%' },
];
