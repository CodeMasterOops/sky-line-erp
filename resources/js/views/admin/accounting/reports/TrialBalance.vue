<template>
    <PageHeader title="Trial Balance" subtitle="Financial report" />
    <div class="card">
        <div class="card-body">
            <div class="row row-gap-2 align-items-end">
                <div class="col-sm-4">
                    <div class="dropdown me-2">
                        <label class="form-label">Choose Your Date</label>
                        <div class="input-groupicon calender-input balance-sheet-date one">
                            <vue-feather type="calendar" class="info-img"></vue-feather><input type="text"
                                class="datetimepicker w-100" ref="dateRangeInput"
                                placeholder="01-Jan-2025 - 12-Dec-2025">
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 mb-3">
                    <div class="dropdown">
                        <label class="form-label">Store</label>
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center w-100"
                            data-bs-toggle="dropdown">
                            Select
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Zephyr Indira</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Quillon Elysia</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-3 col-md-2 mb-3">
                    <button class="btn btn-primary shadow-none w-100">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table border">
                    <thead class="thead-light">
                        <tr>
                            <th>Account Name</th>
                            <th>Debit</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold text-gray-9">Assets</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Cash in register</td>
                            <td>$5,000</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Bank Accounts</td>
                            <td>$12,000</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Accounts Receivable</td>
                            <td>$3,000</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Inventory (POS stock)</td>
                            <td>$10,000</td>
                            <td></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-bold text-gray-9">Total Assets</td>
                            <td class="fw-bold text-gray-9">$37,000</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-gray-9">Liabilities</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Accounts Payable</td>
                            <td></td>
                            <td>$2,000</td>
                        </tr>
                        <tr>
                            <td>Short-term Loans</td>
                            <td></td>
                            <td>$4,000</td>
                        </tr>
                        <tr>
                            <td>Sales Tax Payable</td>
                            <td></td>
                            <td>$500</td>
                        </tr>
                        <tr>
                            <td>Wages Payable</td>
                            <td></td>
                            <td>$1,200</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-gray-9">Total Assets</td>
                            <td></td>
                            <td class="fw-bold text-gray-9">$20,700</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tf class="bg-light fw-bold p-3 fs-16">Total</tf>
                        <tf class="bg-light fw-bold p-3 fs-16">$37,000</tf>
                        <tf class="bg-light fw-bold p-3 fs-16">$37,000</tf>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
<script>
import "daterangepicker/daterangepicker.css";
import "daterangepicker/daterangepicker.js";
import { ref } from "vue";
const value1 = ref();
import { onMounted } from "vue";
import moment from "moment";
import DateRangePicker from "daterangepicker";

export default {
  data() {
    return {
      value1,
    };
  },
  methods: {
    toggleHeader() {
      document.getElementById("collapse-header").classList.toggle("active");
      document.body.classList.toggle("header-collapse");
    },
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
};
</script>