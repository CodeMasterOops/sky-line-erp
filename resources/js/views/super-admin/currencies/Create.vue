<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
        modal-class="medium-modal"
        title="Add Currency"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="storeCurrency">
                <div class="col-md-4">
                    <VInput
                        id="currency_code"
                        v-model="form.code"
                        label="Currency Code"
                        placeholder="USD"
                        required
                        @validate="validateField('code')"
                        :error="errors.code"
                    />
                </div>
                <div class="col-md-8">
                    <VInput
                        id="currency_name"
                        v-model="form.name"
                        label="Currency Name"
                        placeholder="US Dollar"
                        required
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="currency_symbol"
                        v-model="form.symbol"
                        label="Symbol"
                        placeholder="$"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="currency_rate"
                        v-model="form.exchange_rate"
                        input-type="number"
                        label="Exchange Rate (to NPR)"
                        required
                        @validate="validateField('exchange_rate')"
                        :error="errors.exchange_rate"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="currency_rate_date"
                        v-model="form.rate_date"
                        input-type="date"
                        label="Rate Date"
                    />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input id="currency_is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="currency_is_active">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-danger me-1" type="button" @click="closeCreateModal">Close</button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref} from 'vue';
import {number, object, string} from 'yup';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useYup} from '@/helpers/yup';
import {useCurrencyStore} from '@/stores/super-admin/currency';

const currencyStore = useCurrencyStore();
const createModalOpened = defineModel('createModalOpened');

const initialState = {
    code: '',
    name: '',
    symbol: '',
    exchange_rate: 1,
    rate_date: null,
    is_active: true,
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    code: string().required('Currency code is required.').length(3, 'Code must be 3 characters.'),
    name: string().required('Currency name is required.'),
    exchange_rate: number().typeError('Exchange rate is required.').min(0.000001, 'Exchange rate must be greater than zero.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeCurrency = async () => {
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await currencyStore.storeCurrency({
            ...form,
            code: form.code?.toUpperCase(),
        });
        toast(res.status, res.data.message);
        closeCreateModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeCreateModal = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
    createModalOpened.value = false;
};
</script>
