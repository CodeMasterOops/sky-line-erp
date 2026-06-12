import {computed} from 'vue';
import {storeToRefs} from 'pinia';
import {useSettingStore} from '@/stores/admin/settings/setting.js';

export const useCompanyBranding = () => {
    const settingStore = useSettingStore();
    const {setting} = storeToRefs(settingStore);

    const company = computed(() => setting.value.data || {});

    const logoUrl = computed(() => company.value.logo_url || '');
    const companyName = computed(() => company.value.company_name || '');
    const legalName = computed(() => company.value.legal_name || '');
    const address = computed(() => company.value.address || '');
    const locationLabel = computed(() => company.value.location_label || '');
    const postalCode = computed(() => company.value.postal_code || '');
    const pan = computed(() => company.value.pan || '');
    const phone = computed(() => company.value.phone || '');
    const landline = computed(() => company.value.landline || '');
    const email = computed(() => company.value.email || '');
    const website = computed(() => company.value.website || '');
    const invoiceNote = computed(() => company.value.invoice_note || '');

    const formattedAddress = computed(() => {
        const parts = [
            address.value,
            locationLabel.value,
            postalCode.value,
        ].filter(Boolean);

        return parts.join(', ');
    });

    const displayName = computed(() => legalName.value || companyName.value || 'Company');

    const ensureBranding = async (refetch = false) => {
        await settingStore.getSetting(refetch);
    };

    return {
        setting,
        company,
        logoUrl,
        companyName,
        legalName,
        address,
        locationLabel,
        postalCode,
        pan,
        phone,
        landline,
        email,
        website,
        invoiceNote,
        formattedAddress,
        displayName,
        ensureBranding,
    };
};
