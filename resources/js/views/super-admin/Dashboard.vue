<template>
    <div class="d-lg-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Welcome, Super Admin</h2>
            <p>You have <span class="text-primary fw-bold">200+</span> Orders, Today</p>
        </div>
        <ul class="table-top-head">
            <li>
                <div class="input-icon-start position-relative">
                    <span class="input-icon-addon fs-16 text-gray-9">
                        <i class="ti ti-calendar"></i>
                    </span>
                    <input type="text" class="form-control date-range bookingrange" ref="dateRangeInput"
                        placeholder="Search Product">
                </div>
            </li>
            <li>
                <a data-bs-toggle="tooltip" data-bs-placement="top" id="collapse-header" @click="toggleHeader"
                    aria-label="Collapse" data-bs-original-title="Collapse"><i class="ti ti-chevron-up"></i></a>
            </li>
        </ul>
    </div>
    <!-- Welcome Wrap -->
    <div class="welcome-wrap mb-4">
        <div class=" d-flex align-items-center justify-content-between flex-wrap">
            <div class="mb-3">
                <h2 class="mb-1 text-white">Welcome Back, Super Admin</h2>
                <p class="text-light">14 New Companies Subscribed Today !!!</p>
            </div>
            <div class="d-flex align-items-center flex-wrap mb-1">
                <router-link :to="{ name: 'super-admin.company-list' }"
                    class="btn btn-dark btn-md me-2 mb-2">Companies</router-link>
                <router-link to="#" class="btn btn-light btn-md mb-2">All Packages</router-link>

            </div>
        </div>
        <div class="welcome-bg">
            <img src="@/assets/images/bg/welcome-bg-02.svg" alt="img" class="welcome-bg-01">
            <img src="@/assets/images/bg/welcome-bg-03.svg" alt="img" class="welcome-bg-02">
            <img src="@/assets/images/bg/welcome-bg-01.svg" alt="img" class="welcome-bg-03">
        </div>
    </div>
    <!-- /Welcome Wrap -->

    <div class="row">

        <!-- Total Companies -->
        <div class="col-xl-3 col-sm-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="avatar avatar-md bg-dark mb-3">
                            <i class="ti ti-building fs-16"></i>
                        </span>
                        <span class="badge bg-success fw-normal mb-3">
                            +19.01%
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="mb-1">5468</h2>
                            <p class="fs-13">Total Companies</p>
                        </div>
                        <div class="company-bar1">
                            <apexchart type="bar" width="50" :options="totalChart.total" :series="totalChart.series">
                            </apexchart>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Total Companies -->

        <!-- Active Companies -->
        <div class="col-xl-3 col-sm-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="avatar avatar-md bg-dark mb-3">
                            <i class="ti ti-carousel-vertical fs-16"></i>
                        </span>
                        <span class="badge bg-danger fw-normal mb-3">
                            -12%
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="mb-1">4598</h2>
                            <p class="fs-13">Active Companies</p>
                        </div>
                        <div class="company-bar2">
                            <apexchart type="bar" width="50" :options="activeChart.active" :series="activeChart.series">
                            </apexchart>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Active Companies -->

        <!-- Total Subscribers -->
        <div class="col-xl-3 col-sm-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="avatar avatar-md bg-dark mb-3">
                            <i class="ti ti-chalkboard-off fs-16"></i>
                        </span>
                        <span class="badge bg-success fw-normal mb-3">
                            +6%
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="mb-1">3698</h2>
                            <p class="fs-13">Total Subscribers</p>
                        </div>
                        <div class="company-bar3">
                            <apexchart type="bar" width="50" :options="inactiveChart.inactive"
                                :series="inactiveChart.series">
                            </apexchart>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Total Subscribers -->

        <!-- Total Earnings -->
        <div class="col-xl-3 col-sm-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="avatar avatar-md bg-dark mb-3">
                            <i class="ti ti-businessplan fs-16"></i>
                        </span>
                        <span class="badge bg-danger fw-normal mb-3">
                            -16%
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="mb-1">$89,878,58</h2>
                            <p class="fs-13">Total Earnings</p>
                        </div>
                        <div class="company-bar4">
                            <apexchart type="bar" width="50" :options="locationChart.location"
                                :series="locationChart.series">
                            </apexchart>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Total Earnings -->

    </div>

    <div class="row">

        <!-- Companies -->
        <div class="col-xxl-3 col-lg-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Companies</h5>
                    <div class="dropdown mb-2">
                        <a href="javascript:void(0);"
                            class="btn btn-white border btn-sm d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
                            <i class="ti ti-calendar me-1"></i>This Week
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div id="company-chart">
                        <apexchart type="bar" height="240" :options="companyChart.company"
                            :series="companyChart.series">
                        </apexchart>
                    </div>
                    <p class="f-13 d-inline-flex align-items-center"><span class="badge badge-success me-1">+6%</span> 5
                        Companies from last month</p>
                </div>
            </div>
        </div>
        <!-- /Companies -->

        <!-- Revenue -->
        <div class="col-lg-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Revenue</h5>
                    <div class="dropdown mb-2">
                        <a href="javascript:void(0);"
                            class="btn btn-white border btn-sm d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
                            <i class="ti ti-calendar me-1"></i>2025
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">2024</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">2025</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">2023</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <div class="mb-1">
                            <h5 class="mb-1">$45787</h5>
                            <p><span class="text-success fw-bold">+40%</span> increased from last year</p>
                        </div>
                        <p class="fs-13 text-gray-9 d-flex align-items-center mb-1"><i
                                class="ti ti-circle-filled me-1 fs-6 text-primary"></i>Revenue</p>
                    </div>
                    <div id="revenue-income">
                        <apexchart type="bar" height="230" :options="revenueCharts.income"
                            :series="revenueCharts.series">
                        </apexchart>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Revenue -->

        <!-- Top Plans -->
        <div class="col-xxl-3 col-xl-12 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Top Plans</h5>
                    <div class="dropdown mb-2">
                        <a href="javascript:void(0);"
                            class="btn btn-white border btn-sm d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
                            <i class="ti ti-calendar me-1"></i>This Month
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div id="plan-overview">
                        <apexchart type="donut" height="240" :options="donutChart.donut" :series="donutChart.series">
                        </apexchart>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <p class="f-13 mb-0"><i class="ti ti-circle-filled text-primary me-1"></i>Basic </p>
                        <p class="f-13 fw-medium text-gray-9">60%</p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <p class="f-13 mb-0"><i class="ti ti-circle-filled text-warning me-1"></i>Premium</p>
                        <p class="f-13 fw-medium text-gray-9">20%</p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-0">
                        <p class="f-13 mb-0"><i class="ti ti-circle-filled text-info me-1"></i>Enterprise</p>
                        <p class="f-13 fw-medium text-gray-9">20%</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Top Plans -->

    </div>

    <div class="row">

        <!-- Recent Transactions -->
        <div class="col-xxl-4 col-xl-12 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Recent Transactions</h5>
                    <a href="javascript:void(0);" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body pb-2">
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Stellar
                                        Dynamics</a></h6>
                                <p class="fs-13 d-inline-flex align-items-center"><span
                                        class="text-info">#12457</span><i
                                        class="ti ti-circle-filled fs-6 text-primary mx-1"></i>14 Jan 2025</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <h6 class="mb-1">+$245</h6>
                            <p class="fs-13">Basic</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Quantum Nexus</a>
                                </h6>
                                <p class="fs-13 d-inline-flex align-items-center"><span
                                        class="text-info">#65974</span><i
                                        class="ti ti-circle-filled fs-6 text-primary mx-1"></i>14 Jan 2025</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <h6 class="mb-1">+$395</h6>
                            <p class="fs-13">Enterprise</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Aurora
                                        Technologies</a></h6>
                                <p class="fs-13 d-inline-flex align-items-center"><span
                                        class="text-info">#22457</span><i
                                        class="ti ti-circle-filled fs-6 text-primary mx-1"></i>14 Jan 2025</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <h6 class="mb-1">+$145</h6>
                            <p class="fs-13">Advanced</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">TerraFusion
                                        Energy</a></h6>
                                <p class="fs-13 d-inline-flex align-items-center"><span
                                        class="text-info">#43412</span><i
                                        class="ti ti-circle-filled fs-6 text-primary mx-1"></i>14 Jan 2025</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <h6 class="mb-1">+$145</h6>
                            <p class="fs-13">Enterprise</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-1">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Epicurean
                                        Delights</a></h6>
                                <p class="fs-13 d-inline-flex align-items-center"><span
                                        class="text-info">#43567</span><i
                                        class="ti ti-circle-filled fs-6 text-primary mx-1"></i>14 Jan 2025</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <h6 class="mb-1">+$977</h6>
                            <p class="fs-13">Premium</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Recent Transactions -->

        <!-- Recently Registered -->
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Recently Registered</h5>
                    <a href="javascript:void(0);" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body pb-2">
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Pitch</a></h6>
                                <p class="fs-13">Basic (Monthly)</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <p class="fs-13 mb-1">150 Users</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Initech</a></h6>
                                <p class="fs-13">Enterprise (Yearly)</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <p class="fs-13 mb-1">200 Users</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Umbrella Corp</a>
                                </h6>
                                <p class="fs-13">Advanced (Monthly)</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <p class="fs-13 mb-1">129 Users</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Capital
                                        Partners</a></h6>
                                <p class="fs-13">Enterprise (Monthly)</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <p class="fs-13 mb-1">103 Users</p>
                        </div>
                    </div>
                    <div class="d-sm-flex justify-content-between flex-wrap mb-1">
                        <div class="d-flex align-items-center mb-2">
                            <a href="javscript:void(0);"
                                class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                <img src="@/assets/images/profiles/avatar-01.jpg" class="img-fluid w-auto h-auto"
                                    alt="img">
                            </a>
                            <div class="ms-2 flex-fill">
                                <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Massive
                                        Dynamic</a></h6>
                                <p class="fs-13">Premium (Yearly)</p>
                            </div>
                        </div>
                        <div class="text-sm-end mb-2">
                            <p class="fs-13 mb-1">108 Users</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Recent Registered -->

        <!-- Recent Plan Expired -->
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Recent Plan Expired</h5>
                    <div class="dropdown mb-2">
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white border btn-sm d-inline-flex align-items-center fs-13"
                            data-bs-toggle="dropdown">
                            Expired
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-3">
                            <ul class="nav d-block">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item d-block rounded-1"
                                        data-bs-toggle="tab" data-bs-target="#expired">Expired</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item d-block rounded-1"
                                        data-bs-toggle="tab" data-bs-target="#request">Request</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="expired">
                            <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Silicon
                                                Corp</a></h6>
                                        <p class="fs-13">Expired : 10 Apr 2025</p>
                                    </div>
                                </div>
                                <div class="text-sm-end mb-2">
                                    <a href="javascript:void(0);"
                                        class="link-info text-decoration-underline d-block mb-1">Send Reminder</a>
                                    <p class="fs-13">Basic (Monthly)</p>
                                </div>
                            </div>
                            <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill">
                                        <h6 class="fs-medium text-truncate mb-1"><a
                                                href="javscript:void(0);">Hubspot</a></h6>
                                        <p class="fs-13">Expired : 12 Jun 2025</p>
                                    </div>
                                </div>
                                <div class="text-sm-end mb-2">
                                    <a href="javascript:void(0);"
                                        class="link-info text-decoration-underline d-block mb-1">Send Reminder</a>
                                </div>
                            </div>
                            <div class="d-sm-flex justify-content-between flex-wrap mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Licon
                                                Industries</a></h6>
                                        <p class="fs-13">Expired : 16 Jun 2025</p>
                                    </div>
                                </div>
                                <div class="text-sm-end mb-2">
                                    <a href="javascript:void(0);"
                                        class="link-info text-decoration-underline d-block mb-1">Send Reminder</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between flex-wrap mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill">
                                        <h6 class="fs-medium text-truncate mb-1"><a
                                                href="javscript:void(0);">TerraFusion
                                                Energy</a></h6>
                                        <p class="fs-13">Expired : 12 May 2025</p>
                                    </div>
                                </div>
                                <div class="text-sm-end mb-2">
                                    <a href="javascript:void(0);"
                                        class="link-info text-decoration-underline d-block mb-1">Send Reminder</a>
                                </div>
                            </div>
                            <div class="d-sm-flex justify-content-between flex-wrap mb-1">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Epicurean
                                                Delights</a></h6>
                                        <p class="fs-13">Expired : 15 May 2025</p>
                                    </div>
                                </div>
                                <div class="text-sm-end mb-2">
                                    <a href="javascript:void(0);"
                                        class="link-info text-decoration-underline d-block mb-1">Send Reminder</a>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="request">
                            <div class="d-flex justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill overflow-hidden">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Silicon
                                                Corp</a></h6>
                                        <p class="fs-13 text-info text-truncate">silicon.example.com</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);"
                                        class="link-success text-decoration-underline fs-13 fw-medium me-3">Approve</a>
                                    <a href="javascript:void(0);"
                                        class="link-danger text-decoration-underline fs-13 fw-medium">Reject</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill overflow-hidden">
                                        <h6 class="fs-medium text-truncate mb-1"><a
                                                href="javscript:void(0);">Hubspot</a></h6>
                                        <p class="fs-13 text-info text-truncate">hubspot.example.com</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);"
                                        class="link-success text-decoration-underline fs-13 fw-medium me-3">Approve</a>
                                    <a href="javascript:void(0);"
                                        class="link-danger text-decoration-underline fs-13 fw-medium">Reject</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill overflow-hidden">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Licon
                                                Industries</a></h6>
                                        <p class="fs-13 text-info text-truncate">licon.example.com</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);"
                                        class="link-success text-decoration-underline fs-13 fw-medium me-3">Approve</a>
                                    <a href="javascript:void(0);"
                                        class="link-danger text-decoration-underline fs-13 fw-medium">Reject</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-4">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill overflow-hidden">
                                        <h6 class="fs-medium text-truncate mb-1"><a
                                                href="javscript:void(0);">TerraFusion
                                                Energy</a></h6>
                                        <p class="fs-13 text-info text-truncate">fusion.example.com</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);"
                                        class="link-success text-decoration-underline fs-13 fw-medium me-3">Approve</a>
                                    <a href="javascript:void(0);"
                                        class="link-danger text-decoration-underline fs-13 fw-medium">Reject</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-0">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <a href="javscript:void(0);"
                                        class="avatar avatar-md bg-gray-100 rounded-circle flex-shrink-0">
                                        <img src="@/assets/images/profiles/avatar-01.jpg"
                                            class="img-fluid w-auto h-auto" alt="img">
                                    </a>
                                    <div class="ms-2 flex-fill overflow-hidden">
                                        <h6 class="fs-medium text-truncate mb-1"><a href="javscript:void(0);">Epicurean
                                                Delights</a></h6>
                                        <p class="fs-13 text-info text-truncate">epicuran.example.com</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);"
                                        class="link-success text-decoration-underline fs-13 fw-medium me-3">Approve</a>
                                    <a href="javascript:void(0);"
                                        class="link-danger text-decoration-underline fs-13 fw-medium">Reject</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Recent Plan Expired -->
    </div>
    <div class="footer d-sm-flex align-items-center justify-content-between border-top bg-white p-3">
        <p class="mb-0 text-gray-9">{{ new Date().getFullYear() }} © DreamsPOS. All Right Reserved</p>
        <p>Designed &amp; Developed By <a href="javascript:void(0);" class="text-primary">Redsky</a></p>
    </div>
</template>

<script>
import "daterangepicker/daterangepicker.css";
import "daterangepicker/daterangepicker.js";
import { ref } from "vue";
import { onMounted } from "vue";
import moment from "moment";
import DateRangePicker from "daterangepicker";

import {
    companyChart,
    revenueCharts,
    donutChart,
    totalChart,
    activeChart,
    inactiveChart,
    locationChart,
} from "@/assets/data/super-admin.js";

export default {
    data() {
        return {
            totalChart: totalChart,
            activeChart: activeChart,
            inactiveChart: inactiveChart,
            locationChart: locationChart,
            companyChart: companyChart,
            revenueCharts: revenueCharts,
            donutChart: donutChart,
        }
    },
    setup() {
        const counter1 = ref(0); // Current counter value
        const target1 = 95000.45; // Target value
        const duration = 20; // Animation duration in milliseconds
        const dateRangeInput = ref(null);

        // Move the function declaration outside of the onMounted callback
        function booking_range(start, end) {
            return start.format("M/D/YYYY") + " - " + end.format("M/D/YYYY");
        }

        const animateCounter = (target, counterRef) => {
            let current = 0;
            const step = target / (duration / 50); // Calculate the increment step

            const interval = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target; // Ensure the counter stops at the target value
                    clearInterval(interval); // Stop the interval when the target is reached
                }
                counterRef.value = Math.floor(current); // Update the reactive counter value
            }, 50); // Update every 50ms
        };
        onMounted(() => {
            animateCounter(target1, counter1);
            if (dateRangeInput.value) {
                const start = moment().subtract(6, "days");
                const end = moment();

                new DateRangePicker(
                    dateRangeInput.value,
                    {
                        startDate: start,
                        endDate: end,
                        ranges: {
                            Today: [moment(), moment()],
                            Yesterday: [moment().subtract(1, "days"), moment().subtract(1, "days")],
                            "Last 7 Days": [moment().subtract(6, "days"), moment()],
                            "Last 30 Days": [moment().subtract(29, "days"), moment()],
                            "This Month": [moment().startOf("month"), moment().endOf("month")],
                            "Last Month": [
                                moment().subtract(1, "month").startOf("month"),
                                moment().subtract(1, "month").endOf("month"),
                            ],
                        },
                    },
                    booking_range
                );

                booking_range(start, end);
            }
        });

        return {
            counter1,
            dateRangeInput,
        };
    },
    methods: {
        toggleHeader() {
            document.getElementById("collapse-header").classList.toggle("active");
            document.body.classList.toggle("header-collapse");
        },
    }
}
</script>
