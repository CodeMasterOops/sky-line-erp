export const jobSheetStatus = {
    PENDING: 'pending',
    DESIGN: 'design',
    PLATE: 'plate',
    PRINTING: 'printing',
    BINDING: 'binding',
    BILLING: 'billing',
    DELIVERY: 'delivery',
    SUCCESS: 'success',
}

export const jobSheetStatusColor = (status) => {
    let color;

    switch (status) {
        case jobSheetStatus.PENDING:
            color = 'warning';
            break;
        case jobSheetStatus.PLATE:
            color = 'info';
            break;
        case jobSheetStatus.PRINTING:
            color = 'primary';
            break;
        case jobSheetStatus.BINDING:
            color = 'primary';
            break;
        case jobSheetStatus.BILLING:
            color = 'success';
            break;
        case jobSheetStatus.SUCCESS:
            color = 'success';
            break;
        default:
            color = 'secondary';
    }

    return color;
}

export const notificationType = {
    REGISTRATION: 'registration',
}

export const shippingRegions = {
    UK: 'uk',
    EUROPEAN_UNION: 'eu',
    INTERNATIONAL: 'international',
}

