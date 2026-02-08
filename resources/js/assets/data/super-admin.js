const companyChart = {
      series: [{
          name: 'Company',
          data: [40, 60, 20, 80, 60, 60, 60]
      }],
      company: {
          chart: {
              height: 240,
              type: 'bar',
              toolbar: {
                  show: false,
              }
          },
          colors: ['#212529'],
          responsive: [{
              breakpoint: 480,
              options: {
                  legend: {
                      position: 'bottom',
                      offsetX: -10,
                      offsetY: 0
                  }
              }
          }],
          plotOptions: {
              bar: {
                  borderRadius: 10,
                  borderRadiusWhenStacked: 'all',
                  horizontal: false,
                  endingShape: 'rounded',
                  colors: {
                      backgroundBarColors: ['#f3f4f5'], // Background color for bars
                      backgroundBarOpacity: 0.5,
                      hover: {
                          enabled: true,
                          borderColor: '#F26522', // Color when hovering over the bar
                      }
                  }
              },
          },
  
          xaxis: {
              categories: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
              labels: {
                  style: {
                      colors: '#6B7280',
                      fontSize: '13px',
                  }
              }
          },
          yaxis: {
              labels: {
                  offsetX: -15,
                  show: false
              }
          },
          grid: {
              borderColor: '#E5E7EB',
              strokeDashArray: 5,
              padding: {
                  left: -8,
              },
          },
          legend: {
              show: false
          },
          dataLabels: {
              enabled: false // Disable data labels
          },
          fill: {
              opacity: 1
          },
      }
  }
  const revenueCharts = {
      series: [{
          name: 'Income',
          data: [40, 30, 45, 80, 85, 90, 80, 80, 80, 85, 20, 80]
      }, {
          name: 'Expenses',
          data: [60, 70, 55, 20, 15, 10, 20, 20, 20, 15, 80, 20]
      }],
      income: {
          chart: {
              height: 230,
              type: 'bar',
              stacked: true,
              toolbar: {
                  show: false,
              }
          },
          colors: ['#FF6F28', '#F8F9FA'],
          responsive: [{
              breakpoint: 480,
              options: {
                  legend: {
                      position: 'bottom',
                      offsetX: -10,
                      offsetY: 0
                  }
              }
          }],
          plotOptions: {
              bar: {
                  borderRadius: 5,
                  borderRadiusWhenStacked: 'all',
                  horizontal: false,
                  endingShape: 'rounded'
              },
          },
          xaxis: {
              categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
              labels: {
                  style: {
                      colors: '#6B7280',
                      fontSize: '13px',
                  }
              }
          },
          yaxis: {
              min: 0,    // Set the minimum value of the Y-axis to 0
              max: 100,
              labels: {
                  offsetX: -15,
                  style: {
                      colors: '#6B7280',
                      fontSize: '13px',
                  },
                  formatter: function (value) {
                      return value + "K"; // Divide by 1000 and append 'K'
                  }
              }
          },
          grid: {
              borderColor: 'transparent',
              strokeDashArray: 5,
              padding: {
                  left: -8,
              },
          },
          legend: {
              show: false
          },
          dataLabels: {
              enabled: false
          },
          tooltip: {
              y: {
                  formatter: function (val) {
                      return val / 10 + " k"
                  }
              }
          },
          fill: {
              opacity: 1
          },
      }
  };
  
  const donutChart = {
      series: [20, 60, 20],
      donut : {
          chart: {
              height: 240,
              type: 'donut',
              toolbar: {
                  show: false,
              }
          },
          colors: ['#FFC107', '#1B84FF', '#F26522'],
          labels: ['Enterprise', 'Premium', 'Basic'],
          plotOptions: {
              pie: {
                  donut: {
                      size: '50%',
                      labels: {
                          show: false
                      },
                      borderRadius: 30
                  }
              }
          },
          stroke: {
              lineCap: 'round',
              show: true,
              width: 0,    // Space between donut sections
              colors: '#fff'
          },
          dataLabels: {
              enabled: false
          },
          legend: { show: false },
          responsive: [{
              breakpoint: 480,
              options: {
                  chart: {
                      height: 180,
                  },
                  legend: {
                      position: 'bottom'
                  }
              }
          }]
      }
  }
  
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
              type: "bar",
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
                      formatter: function (e) {
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
          colors: ["#4B3088"],
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
                      formatter: function (e) {
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
          colors: ["#177DBC"],
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
                      formatter: function (e) {
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
          colors: ["#2DCB73"],
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
                      formatter: function (e) {
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
  
  export { companyChart, revenueCharts,donutChart,totalChart, activeChart, inactiveChart,locationChart }