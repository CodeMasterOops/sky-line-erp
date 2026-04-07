<template>
    <PageHeader title="Profit and Loss" subtitle="Financial report" />
    <div class="d-flex align-items-center justify-content-end">
        <div class="mb-3 me-3">
          <div class="input-icon-start position-relative">
            <input
              type="text"
              class="form-control date-range bookingrange"
              placeholder="dd/mm/yyyy - dd/mm/yyyy"
              ref="dateRangeInput"
            />
            <span class="input-icon-left">
              <i class="ti ti-calendar"></i>
            </span>
          </div>
        </div>
        <div class="mb-3">
          <a href="#" class="btn btn-primary">Generate Report</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table mb-1">
          <thead class="thead-light">
            <tr>
              <th></th>
              <th>Jan 2025</th>
              <th>Feb 2025</th>
              <th>Mar 2025</th>
              <th>Apr 2025</th>
              <th>May 2025</th>
              <th>Jun 2025</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><h6 class="fw-bold">Income</h6></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td class="border-end">Sales</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
            </tr>
            <tr>
              <td class="border-end">Service</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
            </tr>
            <tr>
              <td class="border-end">Purchase Return</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
            </tr>
            <tr>
              <td class="border-end"><h6 class="fw0bold">Gross Profit</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
              <td><h6 class="fw0bold">$8,000</h6></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="table-responsive mb-4">
        <table class="table">
          <tbody>
            <tr>
              <td class="border-end"><h6 class="fw-bold">Expenses</h6></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td class="border-end">Sales</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
              <td>$50,000</td>
            </tr>
            <tr>
              <td class="border-end">Purrchase</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
              <td>$30,000</td>
            </tr>
            <tr>
              <td class="border-end"><h6 class="fw-bold">Sales Return</h6></td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
              <td>$7,000</td>
            </tr>
            <tr>
              <td class="border-end"><h6 class="fw-bold">Total Expense</h6></td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
            </tr>
            <tr>
              <td class="border-end"><h6 class="fw-bold">Net Profit</h6></td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
              <td>$8,000</td>
            </tr>
          </tbody>
        </table>
      </div>
</template>

<script>
import "daterangepicker/daterangepicker.css";
import "daterangepicker/daterangepicker.js";
import { ref } from "vue";
import { onMounted } from "vue";
import moment from "moment";
import DateRangePicker from "daterangepicker";
export default {
  data() {
    return {};
  },
  setup() {
    const dateRangeInput = ref(null);
    function booking_range(start, end) {
      return start.format("M/D/YYYY") + " - " + end.format("M/D/YYYY");
    }
    onMounted(() => {
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
      dateRangeInput,
    };
  },
  method: {
    toggleHeader() {
      document.getElementById("collapse-header").classList.toggle("active");
      document.body.classList.toggle("header-collapse");
    },
  },
};
</script>