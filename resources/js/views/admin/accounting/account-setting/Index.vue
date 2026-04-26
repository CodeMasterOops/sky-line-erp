<template>
    <PageHeader title="Account Settings" subtitle="Manage your account settings" />

    <section class="section">
        <div class="card">
            <VLoader v-if="accountSetting.loading" loader-type="progress" />
            <div class="card-body">
                <form @submit.prevent="saveAccountSetting">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <VMultiselect
                                id="cash_sales_account_id"
                                v-model="form.cash_sales_account_id"
                                :options="accounts.data"
                                label="Cash Sales Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="bank_sales_account_id"
                                v-model="form.bank_sales_account_id"
                                :options="accounts.data"
                                label="Bank Sales Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="cash_purchase_account_id"
                                v-model="form.cash_purchase_account_id"
                                :options="accounts.data"
                                label="Cash Purchase Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="bank_purchase_account_id"
                                v-model="form.bank_purchase_account_id"
                                :options="accounts.data"
                                label="Bank Purchase Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="vat_account_id"
                                v-model="form.vat_account_id"
                                :options="accounts.data"
                                label="VAT Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="advance_tax_account_id"
                                v-model="form.advance_tax_account_id"
                                :options="accounts.data"
                                label="Advance Tax Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="sales_discount_account_id"
                                v-model="form.sales_discount_account_id"
                                :options="accounts.data"
                                label="Sales Discount Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="purchase_discount_account_id"
                                v-model="form.purchase_discount_account_id"
                                :options="accounts.data"
                                label="Purchase Discount Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="customer_account_id"
                                v-model="form.customer_account_id"
                                :options="accounts.data"
                                label="Customer Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="supplier_account_id"
                                v-model="form.supplier_account_id"
                                :options="accounts.data"
                                label="Supplier Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="employee_account_id"
                                v-model="form.employee_account_id"
                                :options="accounts.data"
                                label="Employee Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="other_contact_account_id"
                                v-model="form.other_contact_account_id"
                                :options="accounts.data"
                                label="Other Contact Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="purchase_account_id"
                                v-model="form.purchase_account_id"
                                :options="accounts.data"
                                label="Purchase Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="sales_account_id"
                                v-model="form.sales_account_id"
                                :options="accounts.data"
                                label="Sales Account"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="inventory_account_id"
                                v-model="form.inventory_account_id"
                                :options="accounts.data"
                                label="Inventory Account (perpetual GL)"
                            />
                        </div>
                        <div class="col-md-4">
                            <VMultiselect
                                id="cogs_account_id"
                                v-model="form.cogs_account_id"
                                :options="accounts.data"
                                label="COGS Account (perpetual GL)"
                            />
                        </div>
                        <div class="col-12 text-end">
                            <VButton :loading="isSubmitting" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { storeToRefs } from "pinia";
import { useAccountStore } from "@/stores/admin/accounting/account";
import { useAccountSettingStore } from "@/stores/admin/accounting/account-setting";

const accountStore = useAccountStore();
const accountSettingStore = useAccountSettingStore();

const { accounts } = storeToRefs(accountStore);
const { accountSetting } = storeToRefs(accountSettingStore);

const initialState = {
    cash_sales_account_id: "",
    bank_sales_account_id: "",
    cash_purchase_account_id: "",
    bank_purchase_account_id: "",
    vat_account_id: "",
    advance_tax_account_id: "",
    sales_discount_account_id: "",
    purchase_discount_account_id: "",
    customer_account_id: "",
    supplier_account_id: "",
    employee_account_id: "",
    other_contact_account_id: "",
    purchase_account_id: "",
    sales_account_id: "",
    inventory_account_id: "",
    cogs_account_id: "",
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

onMounted(async () => {
    await accountStore.getAccounts();
    await setAccountSettingData();
});

const setAccountSettingData = async (refetch = false) => {
    await accountSettingStore.getAccountSetting(refetch);
    Object.keys(form).forEach((key) => {
        form[key] = accountSetting.value.data[key] || "";
    });
};

const saveAccountSetting = async () => {
    isSubmitting.value = true;
    try {
        const payload = {};
        Object.keys(form).forEach((key) => {
            payload[key] = form[key] === "" ? null : form[key];
        });
        const res = await accountSettingStore.updateAccountSetting(payload);
        toast(res.status, res.data.message);
        await setAccountSettingData(true);
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
