<template>
  <PageHeader title="Unit List" subtitle="Manage your units" @refresh="fetchUnits(true)">
    <template #actions>
      <button
          v-can="'create_unit'"
          type="button"
          @click.prevent="createModalOpened=true"
          class="btn btn-primary d-flex align-items-center">
        <i class="ti ti-circle-plus me-2"></i> Add New
      </button>
    </template>
  </PageHeader>

  <section class="section">
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <a-table
              class="table datanew table-hover table-center mb-0"
              :columns="columns"
              :data-source="units.data"
              :loading="units.loading"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.key === 'sn'">
                {{ index + 1 }}
              </template>
              <template v-if="column.key === 'action'">
                <div class="action-icon d-inline-flex">
                  <a class="me-2" href="javascript:void(0);"
                     @click="edit_unit_id=record.id">
                    <i class="ti ti-edit"></i>
                  </a>
                  <a href="javascript:void(0);"
                     @click="deleteUnit(record.id)">
                    <i class="ti ti-trash"></i>
                  </a>
                </div>
              </template>
            </template>
          </a-table>
        </div>
      </div>
    </div>
  </section>
  <CreateUnit v-model:create-modal-opened="createModalOpened"/>
  <EditUnit v-model:unit_id="edit_unit_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateUnit from './Create.vue';
import EditUnit from './Edit.vue';
import { useUnitStore } from '@/stores/admin/inventory/unit.js';

const unitStore = useUnitStore();

onMounted(() => {
  fetchUnits();
})

const edit_unit_id = ref('');
const createModalOpened = ref(false);

const {units} = storeToRefs(unitStore);

const columns = [
  {
    title: 'SN',
    key: 'sn',
    width: 60,
  },
  {
    title: 'Name',
    dataIndex: 'name',
    sorter: {
      compare: (a, b) => {
        a = a.name.toLowerCase();
        b = b.name.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: 'Code',
    dataIndex: 'code',
    sorter: {
      compare: (a, b) => {
        a = a.code.toLowerCase();
        b = b.code.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: 'Action',
    key: 'action',
    align: 'center',
  },
];

const fetchUnits = (refetch = false) => {
  unitStore.getUnits(refetch);
};

const deleteUnit = async (id) => {
  Swal.fire({
    title: 'Are You Sure to Delete ? ',
    text: "If you delete this, it will be gone forever.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: 'red',
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await unitStore.deleteUnit(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
