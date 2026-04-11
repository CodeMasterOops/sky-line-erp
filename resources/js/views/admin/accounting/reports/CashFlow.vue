<template>
    <PageHeader title="Cash Flow" subtitle="Financial report" />
    <div class="card">
                <div class="card-body">
                    <div class="row row-gap-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Choose Your Date</label>
                            <div class="input-groupicon calender-input balance-sheet-date one">
                                <div class="input-groupicon calender-input balance-sheet-date one">
                                    <vue-feather type="calendar" class="info-img"></vue-feather><input type="text" class="datetimepicker w-100" ref="dateRangeInput" placeholder="01-Jan-2025 - 12-Dec-2025">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Store</label>
                            <div class="dropdown">
                                <button
                                    class="btn btn-white dropdown-toggle w-100 d-flex align-items-center justify-content-between"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    All Store
                                </button>
                                <ul class="dropdown-menu p-2">
                                    <li><a class="dropdown-item" href="#">Zephyr Indira</a></li>
                                    <li><a class="dropdown-item" href="#">Quillon Elysia</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary">Submit</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive pb-0">
                        <table class="table border mb-2">
                            <thead class="thead-light">
                                <tr>
                                    <th>Category</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>

                                <tr>
                                    <td class="fw-bold text-gray-9 w-75">Cash Flow from Operating Activities</td>
                                    <td class="w-25"></td>
                                </tr>
                                <tr>
                                    <td>Cash receipts from sales</td>
                                    <td>$50,000</td>
                                </tr>
                                <tr>
                                    <td>Cash payments to suppliers</td>
                                    <td>$30,000</td>
                                </tr>
                                <tr>
                                    <td>Cash payments to employees</td>
                                    <td>$7,000</td>
                                </tr>
                                <tr>
                                    <td>Cash payments for operating expenses (e.g., rent, utilities)</td>
                                    <td>($5,00)</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-gray-9">Net Cash from Operating Activities</td>
                                    <td class="fw-bold text-gray-9">$8,000</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive pb-0">
                        <table class="table border mb-2">
                            <tbody>

                                <tr>
                                    <td class="fw-bold text-gray-9 w-75">Cash Flow from Investing Activities</td>
                                    <td class="w-25"></td>
                                </tr>
                                <tr>
                                    <td>Purchase of POS equipment</td>
                                    <td>$6,000</td>
                                </tr>
                                <tr>
                                    <td>Sale of old equipment</td>
                                    <td>$1,500</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-gray-9">Net Cash from Investing Activities</td>
                                    <td class="fw-bold text-gray-9">$4,500</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive pb-0">
                        <table class="table border mb-2">
                            <tbody>

                                <tr>
                                    <td class="fw-bold text-gray-9 w-75">Cash Flow from Financing Activities</td>
                                    <td class="w-25"></td>
                                </tr>
                                <tr>
                                    <td>Loan received (short-term)</td>
                                    <td>$4,000</td>
                                </tr>
                                <tr>
                                    <td>Repayment of long-term loan</td>
                                    <td>$$3,000</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-gray-9">Net Cash from Financing Activities</td>
                                    <td class="fw-bold text-gray-9">$3,000</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive pb-0">
                        <table class="table border mb-2">
                            <tbody>
                                <tr>
                                    <td class="fw-bold text-gray-9 w-75">Net Increase in Cash</td>
                                    <td class="w-25"></td>
                                </tr>
                                <tr>
                                    <td>Cash at beginning of the period</td>
                                    <td>$5,000</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-gray-9">Net Cash from Financing Activities</td>
                                    <td class="fw-bold text-gray-9">$8,000</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table border">
                            <tbody>
                                <tr>
                                    <td class="bg-light fw-bold text-gray-9 p-3 w-75 fs-16">Cash at End of the Period
                                    </td>
                                    <td class="bg-light fw-bold text-gray-9 p-3 w-25 fs-16">$332642.53</td>
                                </tr>
                            </tbody>
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