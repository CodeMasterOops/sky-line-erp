<template>
    <VModal
        :show-modal="!!currencyId"
        @close-click="closeEditModal"
        modal-class="medium-modal"
        title="Edit Currency"
    >
        <template #modal-body>
            <VLoader v-if="currency.loading" loader-type="progress"/>
            <form v-else class="row g-3" @submit.prevent="updateCurrency">
                <div class="col-md-4">
                    <label class="form-label">Currency Code</label>
                    <input
                        :value="form.code"
                        class="form-control"
                        disabled
                        type="text"
                    />
                </div>
                <div class="col-md-8">
                    <VInput
                        id="edit_currency_name"
                        v-model="form.name"
                        label="Currency Name"
                        required
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="edit_currency_symbol"
                        v-model="form.symbol"
                        label="Symbol"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="edit_currency_rate"
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
                        id="edit_currency_rate_date"
                        v-model="form.rate_date"
                        input-type="date"
                        label="Rate Date"
                    />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input id="edit_currency_is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="edit_currency_is_active">Active</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="closeEditModal">Cancel</button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {number, object, string} from 'yup';
import {storeToRefs} from 'pinia';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useYup} from '@/helpers/yup';
import {useCurrencyStore} from '@/stores/super-admin/currency';

const currencyStore = useCurrencyStore();
const {currency} = storeToRefs(currencyStore);
const currencyId = defineModel('currencyId');

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
    name: string().required('Currency name is required.'),
    exchange_rate: number().typeError('Exchange rate is required.').min(0.000001, 'Exchange rate must be greater than zero.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

watch(currencyId, async (id) => {
    if (!id) {
        return;
    }
    await currencyStore.getCurrency(id);
    const data = currency.value.data;
    form.code = data.code ?? '';
    form.name = data.name ?? '';
    form.symbol = data.symbol ?? '';
    form.exchange_rate = data.exchange_rate ?? 1;
    form.rate_date = data.rate_date ? data.rate_date.slice(0, 10) : null;
    form.is_active = data.is_active ?? true;
});

const updateCurrency = async () => {
    const validated = await validateForm(validations, form);
    if (!validated || !currencyId.value) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await currencyStore.updateCurrency(currencyId.value, {
            name: form.name,
            symbol: form.symbol,
            exchange_rate: form.exchange_rate,
            rate_date: form.rate_date,
            is_active: form.is_active,
        });
        toast(res.status, res.data.message);
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
    currencyId.value = '';
};
</script>
