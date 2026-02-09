const totalChart = {
    series: [{
        name: "Messages",
        data: [25, 66, 41, 12, 36, 9, 21]
    }],
    total: {
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0, // Start with 0 opacity (transparent)
                opacityTo: 0    // End with 0 opacity (transparent)
            }
        },
        chart: {
            foreColor: '#fff',
            type: "area",
            width: 50,
            toolbar: {
                show: !1
            },
            zoom: {
                enabled: !1
            },
            dropShadow: {
                enabled: 0,
                top: 3,
                left: 14,
                blur: 4,
                opacity: .12,
                color: "#fff"
            },
            sparkline: {
                enabled: !0
            }
        },
        markers: {
            size: 0,
            colors: ["#F26522"],
            strokeColors: "#fff",
            strokeWidth: 2,
            hover: {
                size: 7
            }
        },
        plotOptions: {
            bar: {
                horizontal: !1,
                columnWidth: "35%",
                endingShape: "rounded"
            }
        },
        dataLabels: {
            enabled: !1
        },
        stroke: {
            show: !0,
            width: 2.5,
            curve: "smooth"
        },
        colors: ["#F26522"],
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep"]
        },
        tooltip: {
            theme: "dark",
            fixed: {
                enabled: !1
            },
            x: {
                show: !1
            },
            y: {
                title: {
                    formatter: function () {
                        return ""
                    }
                }
            },
            marker: {
                show: !1
            }
        }
    }
};

const activeChart = {
    series: [{
        name: "Active Company",
        data: [25, 40, 35, 20, 36, 9, 21]
    }],
    active: {
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0, // Start with 0 opacity (transparent)
                opacityTo: 0    // End with 0 opacity (transparent)
            }
        },
        chart: {
            foreColor: '#fff',
            type: "area",
            width: 50,
            toolbar: {
                show: !1
            },
            zoom: {
                enabled: !1
            },
            dropShadow: {
                enabled: 0,
                top: 3,
                left: 14,
                blur: 4,
                opacity: .12,
                color: "#fff"
            },
            sparkline: {
                enabled: !0
            }
        },
        markers: {
            size: 0,
            colors: ["#F26522"],
            strokeColors: "#fff",
            strokeWidth: 2,
            hover: {
                size: 7
            }
        },
        plotOptions: {
            bar: {
                horizontal: !1,
                columnWidth: "35%",
                endingShape: "rounded"
            }
        },
        dataLabels: {
            enabled: !1
        },
        stroke: {
            show: !0,
            width: 2.5,
            curve: "smooth"
        },
        colors: ["#F26522"],
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep"]
        },
        tooltip: {
            theme: "dark",
            fixed: {
                enabled: !1
            },
            x: {
                show: !1
            },
            y: {
                title: {
                    formatter: function () {
                        return ""
                    }
                }
            },
            marker: {
                show: !1
            }
        }
    }
};

const inactiveChart = {
    series: [{
        name: "Inactive Company",
        data: [25, 10, 35, 5, 25, 28, 21]
    }],
    inactive: {
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0, // Start with 0 opacity (transparent)
                opacityTo: 0    // End with 0 opacity (transparent)
            }
        },
        chart: {
            foreColor: '#fff',
            type: "area",
            width: 50,
            toolbar: {
                show: !1
            },
            zoom: {
                enabled: !1
            },
            dropShadow: {
                enabled: 0,
                top: 3,
                left: 14,
                blur: 4,
                opacity: .12,
                color: "#fff"
            },
            sparkline: {
                enabled: !0
            }
        },
        markers: {
            size: 0,
            colors: ["#F26522"],
            strokeColors: "#fff",
            strokeWidth: 2,
            hover: {
                size: 7
            }
        },
        plotOptions: {
            bar: {
                horizontal: !1,
                columnWidth: "35%",
                endingShape: "rounded"
            }
        },
        dataLabels: {
            enabled: !1
        },
        stroke: {
            show: !0,
            width: 2.5,
            curve: "smooth"
        },
        colors: ["#F26522"],
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep"]
        },
        tooltip: {
            theme: "dark",
            fixed: {
                enabled: !1
            },
            x: {
                show: !1
            },
            y: {
                title: {
                    formatter: function () {
                        return ""
                    }
                }
            },
            marker: {
                show: !1
            }
        }
    }
};

const locationChart = {
    series: [{
        name: "Inactive Company",
        data: [30, 40, 15, 23, 20, 23, 25]
    }],
    location: {
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0, // Start with 0 opacity (transparent)
                opacityTo: 0    // End with 0 opacity (transparent)
            }
        },
        chart: {
            foreColor: '#fff',
            type: "area",
            width: 50,
            toolbar: {
                show: !1
            },
            zoom: {
                enabled: !1
            },
            dropShadow: {
                enabled: 0,
                top: 3,
                left: 14,
                blur: 4,
                opacity: .12,
                color: "#fff"
            },
            sparkline: {
                enabled: !0
            }
        },
        markers: {
            size: 0,
            colors: ["#F26522"],
            strokeColors: "#fff",
            strokeWidth: 2,
            hover: {
                size: 7
            }
        },
        plotOptions: {
            bar: {
                horizontal: !1,
                columnWidth: "35%",
                endingShape: "rounded"
            }
        },
        dataLabels: {
            enabled: !1
        },
        stroke: {
            show: !0,
            width: 2.5,
            curve: "smooth"
        },
        colors: ["#F26522"],
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep"]
        },
        tooltip: {
            theme: "dark",
            fixed: {
                enabled: !1
            },
            x: {
                show: !1
            },
            y: {
                title: {
                    formatter: function () {
                        return ""
                    }
                }
            },
            marker: {
                show: !1
            }
        }
    }
};

export { totalChart, activeChart, inactiveChart,locationChart }