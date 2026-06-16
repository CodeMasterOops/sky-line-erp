<template>
    <PageHeader title="Help & Support" subtitle="Tutorials, contact information and assistance" />

    <div v-if="support.loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-else>
        <!-- Tutorial Videos -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">Browse Tutorial Videos</h5>
                    <div class="input-group" style="max-width: 260px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="ti ti-search text-muted"></i>
                        </span>
                        <input
                            v-model="videoSearch"
                            type="text"
                            class="form-control border-start-0 ps-0"
                            placeholder="Search videos"
                        />
                    </div>
                </div>

                <div v-if="!support.data.support_videos.length" class="text-center py-5">
                    <i class="ti ti-video-off fs-32 text-muted d-block mb-2"></i>
                    <span class="text-muted">No tutorial videos available yet.</span>
                </div>

                <template v-else>
                    <p class="text-muted fs-13 mb-3">All Videos ({{ filteredVideos.length }})</p>
                    <div v-if="!filteredVideos.length" class="text-center py-4 text-muted fs-13">
                        No videos match "{{ videoSearch }}"
                    </div>
                    <div v-else class="row g-3">
                        <div
                            v-for="(video, i) in filteredVideos"
                            :key="i"
                            class="col-lg-6"
                        >
                            <a
                                :href="video.url"
                                target="_blank"
                                class="video-card d-flex align-items-center justify-content-between p-3 rounded border text-decoration-none"
                            >
                                <span class="fw-medium text-dark fs-13 me-3">{{ video.title }}</span>
                                <span class="play-btn flex-shrink-0">
                                    <i class="ti ti-player-play-filled"></i>
                                </span>
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Need Assistance -->
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-1">Need Assistance for any issue</h5>
                <p class="text-muted fs-13 mb-4">
                    Reach out to our support team via phone, email, or WhatsApp and we will get back to you soon.
                </p>

                <div class="row g-3">
                    <!-- Phone -->
                    <div v-if="support.data.support_phones.length" class="col-lg-4 col-md-6">
                        <div class="assist-card p-3 rounded border h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="avatar avatar-sm bg-primary-subtle me-2">
                                    <i class="ti ti-phone text-primary fs-16"></i>
                                </span>
                                <span class="fw-semibold">Phone</span>
                            </div>
                            <ul class="list-unstyled mb-0">
                                <li v-for="(item, i) in support.data.support_phones" :key="i" class="mb-2">
                                    <div class="fs-12 text-muted">{{ item.label }}</div>
                                    <a :href="'tel:' + item.number" class="fw-medium text-dark text-decoration-none">
                                        {{ item.number }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Email -->
                    <div v-if="support.data.support_emails.length" class="col-lg-4 col-md-6">
                        <div class="assist-card p-3 rounded border h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="avatar avatar-sm bg-info-subtle me-2">
                                    <i class="ti ti-mail text-info fs-16"></i>
                                </span>
                                <span class="fw-semibold">Email</span>
                            </div>
                            <ul class="list-unstyled mb-0">
                                <li v-for="(item, i) in support.data.support_emails" :key="i" class="mb-2">
                                    <div class="fs-12 text-muted">{{ item.label }}</div>
                                    <a :href="'mailto:' + item.address" class="fw-medium text-dark text-decoration-none">
                                        {{ item.address }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- WhatsApp -->
                    <div v-if="support.data.support_whatsapp.length" class="col-lg-4 col-md-6">
                        <div class="assist-card p-3 rounded border h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="avatar avatar-sm bg-success-subtle me-2">
                                    <i class="ti ti-brand-whatsapp text-success fs-16"></i>
                                </span>
                                <span class="fw-semibold">WhatsApp</span>
                            </div>
                            <ul class="list-unstyled mb-0">
                                <li v-for="(item, i) in support.data.support_whatsapp" :key="i" class="mb-2">
                                    <div class="fs-12 text-muted">{{ item.label }}</div>
                                    <a
                                        :href="'https://wa.me/' + item.number.replace(/\D/g, '')"
                                        target="_blank"
                                        class="fw-medium text-dark text-decoration-none"
                                    >
                                        {{ item.number }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div v-if="support.data.support_social_links.length" class="col-12">
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <a
                                v-for="(item, i) in support.data.support_social_links"
                                :key="i"
                                :href="item.url"
                                target="_blank"
                                class="btn btn-outline-secondary btn-sm rounded-pill"
                            >
                                <i class="ti ti-world me-1"></i>{{ item.platform }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import {computed, onMounted, ref} from 'vue';
import {storeToRefs} from 'pinia';
import {useAdminSupportStore} from '@/stores/admin/support';

const store = useAdminSupportStore();
const {support} = storeToRefs(store);

const videoSearch = ref('');

const filteredVideos = computed(() => {
    const q = videoSearch.value.trim().toLowerCase();
    if (!q) {
        return support.value.data.support_videos;
    }
    return support.value.data.support_videos.filter(v => v.title.toLowerCase().includes(q));
});

onMounted(() => {
    store.getSupportSettings(true);
});
</script>

<style>
.video-card {
    background: #fff;
    transition: background 0.15s, box-shadow 0.15s;
    cursor: pointer;
}

.video-card:hover {
    background: #f8f9fa;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.play-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #198754;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.assist-card {
    background: #fff;
    transition: box-shadow 0.15s;
}

.assist-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
</style>
