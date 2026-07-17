import { defineStore } from 'pinia';
import { apiAdmin, downloadAdminFile } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'data-transfers';

export const useDataTransferStore = defineStore('dataTransfer', {
    state: () => ({
        jobs: {
            data: [],
            meta: {},
            loading: false,
        },
        currentJob: {
            data: null,
            loading: false,
        },
        previewRows: {
            data: [],
            loading: false,
        },
    }),

    actions: {
        getJobs({ filter } = {}) {
            const params = new URLSearchParams();
            if (filter?.page) params.set('page', filter.page);
            if (filter?.limit) params.set('limit', filter.limit);
            if (filter?.search) params.set('search', filter.search);
            if (filter?.direction) params.set('direction', filter.direction);
            if (filter?.entity_type) params.set('entity_type', filter.entity_type);
            if (filter?.status) params.set('status', filter.status);

            this.jobs.loading = true;
            return apiAdmin(`${apiUrl}?${params}`)
                .then((res) => {
                    this.jobs.data = res.data.data;
                    this.jobs.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.jobs.loading = false;
                });
        },

        getJob(uuid) {
            this.currentJob.loading = true;
            return apiAdmin(`${apiUrl}/${uuid}`)
                .then((res) => {
                    this.currentJob.data = res.data.data;
                    return res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.currentJob.loading = false;
                });
        },

        uploadImport(file, entityType = 'product', options = {}) {
            const form = new FormData();
            form.append('file', file);
            form.append('entity_type', entityType);
            Object.entries(options).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    form.append(key, value);
                }
            });

            return apiAdmin(`${apiUrl}/imports`, 'post', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            }).then((res) => {
                this.currentJob.data = res.data.data;
                return res.data.data;
            }).catch((err) => {
                showErrors(err);
                throw err;
            });
        },

        saveMapping(uuid, mapping, duplicateMode) {
            return apiAdmin(`${apiUrl}/${uuid}/mapping`, 'put', {
                mapping,
                duplicate_mode: duplicateMode,
            }).then((res) => {
                this.currentJob.data = res.data.data;
                return res.data.data;
            }).catch(showErrors);
        },

        validate(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/validate`, 'post').catch(showErrors);
        },

        getUnresolved(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/unresolved`)
                .then((res) => res.data.data)
                .catch(showErrors);
        },

        getLookupOptions(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/lookup-options`)
                .then((res) => res.data.data)
                .catch(showErrors);
        },

        submitResolutions(uuid, resolutions) {
            return apiAdmin(`${apiUrl}/${uuid}/resolutions`, 'post', { resolutions })
                .then((res) => res.data)
                .catch((err) => {
                    showErrors(err);
                    throw err;
                });
        },

        commit(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/commit`, 'post').catch(showErrors);
        },

        cancel(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/cancel`, 'post').catch(showErrors);
        },

        rollback(uuid) {
            return apiAdmin(`${apiUrl}/${uuid}/rollback`, 'post').catch(showErrors);
        },

        queueExport(entityType, format = 'csv', filters = {}) {
            return apiAdmin(`${apiUrl}/exports`, 'post', {
                entity_type: entityType,
                format,
                filters,
            }).catch(showErrors);
        },

        getPreviewRows(uuid, status = 'invalid', limit = 50) {
            this.previewRows.loading = true;
            const params = new URLSearchParams({ status, limit });
            return apiAdmin(`${apiUrl}/${uuid}/rows?${params}`)
                .then((res) => {
                    this.previewRows.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.previewRows.loading = false;
                });
        },

        downloadResult(uuid, filename) {
            return downloadAdminFile(`${apiUrl}/${uuid}/download`, filename);
        },

        downloadErrors(uuid) {
            return downloadAdminFile(`${apiUrl}/${uuid}/errors`, `import-errors-${uuid}.csv`);
        },

        downloadProductTemplate(format = 'csv') {
            return downloadAdminFile(
                `${apiUrl}/templates/product?format=${format}`,
                `product-import-template.${format}`,
            );
        },

        downloadOpeningStockTemplate(format = 'csv') {
            return downloadAdminFile(
                `${apiUrl}/templates/opening-stock?format=${format}`,
                `opening-stock-import-template.${format}`,
            );
        },

        downloadOpeningStockWorksheet(format = 'csv') {
            return downloadAdminFile(
                `${apiUrl}/templates/opening-stock-worksheet?format=${format}`,
                `opening-stock-worksheet.${format}`,
            );
        },

        downloadWarehouseTemplate(format = 'csv') {
            return downloadAdminFile(
                `${apiUrl}/templates/warehouse?format=${format}`,
                `warehouse-import-template.${format}`,
            );
        },

        downloadContactTemplate(format = 'csv') {
            return downloadAdminFile(
                `${apiUrl}/templates/party?format=${format}`,
                `contact-import-template.${format}`,
            );
        },
    },
});
