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
        <div class="card assist-section">
            <!-- Section banner -->
            <div class="assist-section__banner">
                <div class="assist-section__banner-content">
                    <div class="assist-section__banner-icon">
                        <i class="ti ti-headset"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-white">Need Assistance?</h5>
                        <p class="mb-0 assist-section__banner-sub">
                            Reach out to our support team via phone, email, or WhatsApp — we'll get back to you promptly.
                        </p>
                    </div>
                </div>
                <div class="assist-section__banner-deco" aria-hidden="true"></div>
            </div>

            <div class="card-body pt-4">
                <div class="row g-3">
                    <!-- Phone -->
                    <div v-if="support.data.support_phones.length" class="col-lg-4 col-md-6">
                        <div class="assist-channel assist-channel--primary h-100">
                            <div class="assist-channel__header">
                                <span class="assist-channel__icon-wrap assist-channel__icon-wrap--primary">
                                    <i class="ti ti-phone-call"></i>
                                </span>
                                <div>
                                    <div class="assist-channel__label">Call Us</div>
                                    <div class="assist-channel__count">{{ support.data.support_phones.length }} line{{ support.data.support_phones.length > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 assist-channel__list">
                                <li v-for="(item, i) in support.data.support_phones" :key="i">
                                    <a :href="'tel:' + item.number" class="assist-channel__entry text-decoration-none">
                                        <span class="assist-channel__entry-label">{{ item.label }}</span>
                                        <span class="assist-channel__entry-value">{{ item.number }}</span>
                                        <i class="ti ti-arrow-up-right assist-channel__arrow"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Email -->
                    <div v-if="support.data.support_emails.length" class="col-lg-4 col-md-6">
                        <div class="assist-channel assist-channel--info h-100">
                            <div class="assist-channel__header">
                                <span class="assist-channel__icon-wrap assist-channel__icon-wrap--info">
                                    <i class="ti ti-mail-forward"></i>
                                </span>
                                <div>
                                    <div class="assist-channel__label">Email Us</div>
                                    <div class="assist-channel__count">{{ support.data.support_emails.length }} address{{ support.data.support_emails.length > 1 ? 'es' : '' }}</div>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 assist-channel__list">
                                <li v-for="(item, i) in support.data.support_emails" :key="i">
                                    <a :href="'mailto:' + item.address" class="assist-channel__entry text-decoration-none">
                                        <span class="assist-channel__entry-label">{{ item.label }}</span>
                                        <span class="assist-channel__entry-value">{{ item.address }}</span>
                                        <i class="ti ti-arrow-up-right assist-channel__arrow"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- WhatsApp -->
                    <div v-if="support.data.support_whatsapp.length" class="col-lg-4 col-md-6">
                        <div class="assist-channel assist-channel--success h-100">
                            <div class="assist-channel__header">
                                <span class="assist-channel__icon-wrap assist-channel__icon-wrap--success">
                                    <i class="ti ti-brand-whatsapp"></i>
                                </span>
                                <div>
                                    <div class="assist-channel__label">WhatsApp</div>
                                    <div class="assist-channel__count">{{ support.data.support_whatsapp.length }} number{{ support.data.support_whatsapp.length > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 assist-channel__list">
                                <li v-for="(item, i) in support.data.support_whatsapp" :key="i">
                                    <a
                                        :href="'https://wa.me/' + item.number.replace(/\D/g, '')"
                                        target="_blank"
                                        class="assist-channel__entry text-decoration-none"
                                    >
                                        <span class="assist-channel__entry-label">{{ item.label }}</span>
                                        <span class="assist-channel__entry-value">{{ item.number }}</span>
                                        <i class="ti ti-arrow-up-right assist-channel__arrow"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div v-if="support.data.support_social_links.length" class="col-12">
                        <div class="assist-social">
                            <span class="assist-social__label">Find us online</span>
                            <div class="d-flex flex-wrap gap-2">
                                <a
                                    v-for="(item, i) in support.data.support_social_links"
                                    :key="i"
                                    :href="item.url"
                                    target="_blank"
                                    class="assist-social__link"
                                >
                                    <i class="ti ti-world"></i>
                                    {{ item.platform }}
                                </a>
                            </div>
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
/* ── Video Cards ─────────────────────────────────────────── */
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
    flex-shrink: 0;
}

/* ── Assist Section: banner ──────────────────────────────── */
.assist-section {
    overflow: hidden;
}

.assist-section__banner {
    position: relative;
    background: linear-gradient(135deg, #0339a7 0%, #40abe6 100%);
    padding: 1.5rem 1.75rem;
    overflow: hidden;
}

.assist-section__banner-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.assist-section__banner-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #fff;
    flex-shrink: 0;
}

.assist-section__banner-sub {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    max-width: 540px;
}

.assist-section__banner-deco {
    position: absolute;
    top: -40px;
    right: -40px;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
}

.assist-section__banner-deco::after {
    content: '';
    position: absolute;
    top: 30px;
    left: 30px;
    right: -20px;
    bottom: -20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}

/* ── Channel Cards ───────────────────────────────────────── */
.assist-channel {
    border: 1px solid #e6e9ed;
    border-radius: 8px;
    padding: 1.25rem;
    background: #fff;
    transition: box-shadow 0.2s, border-color 0.2s;
    position: relative;
    overflow: hidden;
}

.assist-channel::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    border-radius: 8px 8px 0 0;
}

.assist-channel--primary::before { background: linear-gradient(90deg, #0339a7, #40abe6); }
.assist-channel--info::before    { background: linear-gradient(90deg, #40abe6, #0ad4eb); }
.assist-channel--success::before { background: linear-gradient(90deg, #3EB780, #0ad4eb); }

.assist-channel:hover {
    box-shadow: 0 4px 20px rgba(3, 57, 167, 0.1);
    border-color: #bbd0ff;
}

.assist-channel__header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e6e9ed;
}

.assist-channel__icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.assist-channel__icon-wrap--primary { background: #eef4ff; color: #0339a7; }
.assist-channel__icon-wrap--info    { background: #ecf7fc; color: #40abe6; }
.assist-channel__icon-wrap--success { background: #EEFAF1; color: #3EB780; }

.assist-channel__label {
    font-size: 14px;
    font-weight: 600;
    color: #001440;
    line-height: 1.2;
}

.assist-channel__count {
    font-size: 11px;
    color: #9595b5;
    margin-top: 2px;
}

.assist-channel__list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.assist-channel__entry {
    display: flex;
    flex-direction: column;
    padding: 0.6rem 0.75rem;
    border-radius: 6px;
    background: #FAFBFE;
    border: 1px solid transparent;
    transition: background 0.15s, border-color 0.15s;
    position: relative;
}

.assist-channel__entry:hover {
    background: #eef4ff;
    border-color: #bbd0ff;
}

.assist-channel__entry:hover .assist-channel__arrow {
    opacity: 1;
    transform: translateY(-1px);
}

.assist-channel__entry-label {
    font-size: 11px;
    color: #9595b5;
    line-height: 1.3;
}

.assist-channel__entry-value {
    font-size: 13px;
    font-weight: 600;
    color: #001440;
    margin-top: 1px;
}

.assist-channel__arrow {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    font-size: 14px;
    color: #0339a7;
    opacity: 0;
    transition: opacity 0.15s, transform 0.15s;
}

/* ── Social Row ──────────────────────────────────────────── */
.assist-social {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-top: 0.5rem;
    flex-wrap: wrap;
}

.assist-social__label {
    font-size: 12px;
    font-weight: 600;
    color: #9595b5;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.assist-social__link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.35rem 0.9rem;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #0339a7;
    background: #eef4ff;
    border: 1px solid #bbd0ff;
    text-decoration: none;
    transition: background 0.15s, color 0.15s, box-shadow 0.15s;
}

.assist-social__link:hover {
    background: #0339a7;
    color: #fff;
    border-color: #0339a7;
    box-shadow: 0 2px 8px rgba(3, 57, 167, 0.25);
}
</style>
