<template>
  <VModal
      :show-modal="!!edit_stock_id"
      @close-click="closeEditModal"
      title="Update Stock">
    <template #modal-body>
      <form @submit.prevent="updateStock(edit_stock_id)" class="row g-3">
        <div class="col-md-12">
          <p class="text-muted my-1">
            <strong>Product : </strong> {{ product.name }} {{ variant.variant_options?.join('-') }}
          </p>
        </div>
        <div class="col-md-6">
          <VSelect
              id="type"
              v-model="form.type"
              :options="changeTypes"
              label="Adjust Type"
              @validate="validateField('type')"
              :error="errors.type"
          />
        </div>
        <div class="col-md-6">
          <VInput
              input-type="number"
              id="quantity"
              v-model="form.quantity"
              label="Quantity"
              @validate="validateField('quantity')"
              :error="errors.quantity"
          />
        </div>
        <div class="col-md-6">
          <label for="available_stock" class="form-label">Available Stock</label>
          <input type="number" id="available_stock" :value="variant.available_quantity" class="form-control" disabled>
        </div>
        <div class="col-md-6">
          <label for="new_stock" class="form-label">New Stock</label>
          <input type="number" id="new_stock" :value="new_stock" class="form-control" disabled>
        </div>
        <div class="col-12 text-end">
          <button @click="closeEditModal" class="btn btn-danger" type="button">
            Close
          </button>
          <VButton :loading="isSubmitting"/>
        </div>
      </form>
    </template>
  </VModal>
</template>

<script setup>
import {computed, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useVendorStockStore} from "@/stores/vendor/vendor-stock.js";

const props = defineProps(['product', 'variant']);

const emit = defineEmits(['afterSubmit']);

const stockStore = useVendorStockStore();

const edit_stock_id = defineModel('variant_id');

const initialState = {
  type: '',
  quantity: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const changeTypes = ref([
  {id: 'purchase', name: 'Purchase'},
  {id: 'sale', name: 'Sale'},
  {id: 'damage', name: 'Damage'},
  {id: 'lost', name: 'Lost'},
])

const new_stock = computed(() => {
  if (form.type) {
    return form.type === 'purchase' ? props.variant.available_quantity + parseInt(form.quantity) : props.variant.available_quantity - parseInt(form.quantity);
  }

  return 0;
})

const validations = object({
  type: string().required('Change Type is required.'),
  quantity: string().required('Quantity is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateStock = async (id) => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await stockStore.updateStock(id, form);
      emit('afterSubmit');
      toast(res.status, res.data.message);
      closeEditModal();
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const closeEditModal = () => {
  resetForm();
  edit_stock_id.value = '';
}

function resetForm() {
  Object.assign(form, {...initialState});
  errors.value = {};
}

</script>
