export const CHART_COLORS = {
    primary: '#0339a7',
    success: '#3EB780',
    warning: '#FFCA18',
    danger:  '#FF0000',
    info:    '#40abe6',
    purple:  '#6938EF',
    teal:    '#0E9384',
    cyan:    '#0ad4eb',
    orange:  '#E04F16',
}

export function baseChartOptions() {
    return {
        chart:       { toolbar: { show: false }, fontFamily: 'Nunito, sans-serif' },
        grid:        { borderColor: '#f1f5f6', strokeDashArray: 4 },
        tooltip:     { theme: 'light' },
        dataLabels:  { enabled: false },
        legend:      { position: 'top', horizontalAlign: 'left', fontSize: '13px' },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
    }
}
