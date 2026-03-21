<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add New Attribute">
        <template #modal-body>
            <form @submit.prevent="storeAttribute" class="row g-3">
                <div class="col-md-12">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr class="align-middle">
                                <th>SN</th>
                                <th>Value</th>
                                <th>Order</th>
                                <th style="width: 40px;">
                                    <button type="button" @click="addAttributeValue"
                                            class="btn btn-xs btn-outline-primary">
                                        <i class="fa fa-plus-circle"></i>
                                    </button>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="align-middle">
                            <template v-if="form.values.length">
                                <tr v-for="(attrVal,index) in form.values" :key="index">
                                    <td>{{ index + 1 }}</td>
                                    <td>
                                        <VInput
                                            v-model="form.values[index].value"
                                            placeholder="Value"
                                            input-class="form-control form-control-sm"
                                            @validate="validateField(`values[${index}].value`)"
                                            :error="errors[`values[${index}].value`]"
                                        />
                                    </td>
                                    <td>
                                        <VInput
                                            input-type="number"
                                            v-model="form.values[index].sort_order"
                                            placeholder="Sort Order"
                                            input-class="form-control form-control-sm"
                                            @validate="validateField(`values[${index}].sort_order`)"
                                            :error="errors[`values[${index}].sort_order`]"
                                        />
                                    </td>
                                    <td>
                                        <button type="button" @click="removeAttributeValue(index)"
                                                class="btn btn-xs btn-outline-warning">
                                            <i class="fa fa-minus-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td class="text-center" colspan="4">
                                    No values added.
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <p v-if="errors.values" class="text-danger">
                            {{ errors.values }}
                        </p>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast.js";
import showErrors from "@/helpers/showErrors.js";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup.js";
import {useAttributeStore} from "@/stores/admin/inventory/attribute.js";

const attributeStore = useAttributeStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    values: []
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addAttributeValue = () => {
    form.values.push({
        value: '',
        sort_order: form.values.length + 1
    })
}

const removeAttributeValue = (index) => {
    form.values.splice(index, 1);
}

const validations = object({
    name: string().required('Name is required.'),
    values: array().min(1, 'Values are required.').of(
        object({
            value: string().required('Value is required.'),
            sort_order: string().required('Sort order is required.'),
        })
    )
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeAttribute = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await attributeStore.storeAttribute(form);
            toast(res.status, res.data.message);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
}

function resetForm() {
    Object.assign(form, {...initialState});
    form.values.splice(0);
    errors.value = {};
}

</script>
