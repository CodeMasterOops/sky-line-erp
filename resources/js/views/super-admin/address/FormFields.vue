<template>
    <template v-if="activeTab === 'provinces'">
        <div class="col-md-6">
            <VInput
                id="province_name"
                v-model="form.name"
                label="Name"
                required
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="province_sort_order"
                v-model="form.sort_order"
                label="Sort order"
                input-type="number"
            />
        </div>
    </template>

    <template v-else-if="activeTab === 'districts'">
        <div class="col-md-6">
            <VSelect
                id="district_province"
                v-model="form.province_id"
                label="Province"
                required
                :options="provinces"
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="district_name"
                v-model="form.name"
                label="District name"
                required
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="district_sort_order"
                v-model="form.sort_order"
                label="Sort order"
                input-type="number"
            />
        </div>
    </template>

    <template v-else-if="activeTab === 'palikas'">
        <div class="col-md-6">
            <VSelect
                id="palika_province"
                v-model="palForm.province_id"
                label="Province"
                required
                :options="provinces"
            />
        </div>
        <div class="col-md-6">
            <VSelect
                id="palika_district"
                v-model="form.district_id"
                :disabled="!palForm.province_id"
                label="District"
                required
                :options="palikaDistricts"
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="palika_name"
                v-model="form.name"
                label="Palika name"
                required
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="palika_sort_order"
                v-model="form.sort_order"
                label="Sort order"
                input-type="number"
            />
        </div>
    </template>

    <template v-else-if="activeTab === 'wards'">
        <div class="col-md-6">
            <VSelect
                id="ward_province"
                v-model="wardForm.province_id"
                label="Province"
                required
                :options="provinces"
            />
        </div>
        <div class="col-md-6">
            <VSelect
                id="ward_district"
                v-model="wardForm.district_id"
                :disabled="!wardForm.province_id"
                label="District"
                required
                :options="wardDistricts"
            />
        </div>
        <div class="col-md-6">
            <VSelect
                id="ward_palika"
                v-model="form.palika_id"
                :disabled="!wardForm.district_id"
                label="Palika"
                required
                :options="wardPalikas"
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="ward_name"
                v-model="form.name"
                label="Ward"
                placeholder="e.g. 1 or Ward 5"
                required
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="ward_postal_code"
                v-model="form.postal_code"
                label="Postal code (default for companies in this ward)"
            />
        </div>
        <div class="col-md-6">
            <VInput
                id="ward_sort_order"
                v-model="form.sort_order"
                label="Sort order"
                input-type="number"
            />
        </div>
    </template>
</template>

<script setup>
defineProps({
    activeTab: {
        type: String,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    palForm: {
        type: Object,
        required: true,
    },
    wardForm: {
        type: Object,
        required: true,
    },
    provinces: {
        type: Array,
        required: true,
    },
    palikaDistricts: {
        type: Array,
        required: true,
    },
    wardDistricts: {
        type: Array,
        required: true,
    },
    wardPalikas: {
        type: Array,
        required: true,
    },
});
</script>
