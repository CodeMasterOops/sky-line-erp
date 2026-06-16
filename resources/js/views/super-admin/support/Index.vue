<template>
    <PageHeader title="Support" subtitle="Contact information & tutorial videos">
        <template #actions>
            <template v-if="editMode">
                <button v-if="!isFirstLoad && hasAnyData()" class="btn btn-outline-secondary me-2" @click="cancelEdit">
                    <i class="ti ti-x me-1"></i> Cancel
                </button>
                <button class="btn btn-primary" :disabled="saving" @click="saveSettings">
                    <i class="ti ti-device-floppy me-1"></i>
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </template>
            <button v-else class="btn btn-primary" @click="startEdit">
                <i class="ti ti-edit me-1"></i> Edit
            </button>
        </template>
    </PageHeader>

    <div v-if="support.loading" class="row">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>

    <template v-else>
        <!-- Contact Information -->
        <div class="row g-3 mb-4">
            <!-- Phone Numbers -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <span class="avatar avatar-sm bg-primary me-2">
                            <i class="ti ti-phone fs-14"></i>
                        </span>
                        <h6 class="mb-0">Phone Numbers</h6>
                    </div>
                    <div class="card-body">
                        <template v-if="!editMode">
                            <div v-if="!form.support_phones.length" class="text-center py-3">
                                <i class="ti ti-phone-off fs-24 text-muted mb-2 d-block"></i>
                                <span class="text-muted fs-13">No phone numbers added.</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 fs-13" @click="startEdit">Add now</button>
                            </div>
                            <ul v-else class="list-unstyled mb-0">
                                <li v-for="(item, i) in form.support_phones" :key="i" class="d-flex align-items-center mb-2">
                                    <i class="ti ti-phone-call text-primary me-2"></i>
                                    <div>
                                        <div class="fw-medium">{{ item.label }}</div>
                                        <a :href="'tel:' + item.number" class="text-muted fs-13">{{ item.number }}</a>
                                    </div>
                                </li>
                            </ul>
                        </template>
                        <template v-else>
                            <div v-for="(item, i) in form.support_phones" :key="i" class="d-flex gap-2 mb-2">
                                <input v-model="item.label" type="text" class="form-control form-control-sm" placeholder="Label (e.g. Main Office)" />
                                <input v-model="item.number" type="text" class="form-control form-control-sm" placeholder="Phone number" />
                                <button class="btn btn-sm btn-outline-danger flex-shrink-0" @click="removeItem('support_phones', i)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-1" @click="addItem('support_phones', { label: '', number: '' })">
                                <i class="ti ti-plus me-1"></i> Add Phone
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Emails -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <span class="avatar avatar-sm bg-info me-2">
                            <i class="ti ti-mail fs-14"></i>
                        </span>
                        <h6 class="mb-0">Email Addresses</h6>
                    </div>
                    <div class="card-body">
                        <template v-if="!editMode">
                            <div v-if="!form.support_emails.length" class="text-center py-3">
                                <i class="ti ti-mail-off fs-24 text-muted mb-2 d-block"></i>
                                <span class="text-muted fs-13">No email addresses added.</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 fs-13" @click="startEdit">Add now</button>
                            </div>
                            <ul v-else class="list-unstyled mb-0">
                                <li v-for="(item, i) in form.support_emails" :key="i" class="d-flex align-items-center mb-2">
                                    <i class="ti ti-mail text-info me-2"></i>
                                    <div>
                                        <div class="fw-medium">{{ item.label }}</div>
                                        <a :href="'mailto:' + item.address" class="text-muted fs-13">{{ item.address }}</a>
                                    </div>
                                </li>
                            </ul>
                        </template>
                        <template v-else>
                            <div v-for="(item, i) in form.support_emails" :key="i" class="d-flex gap-2 mb-2">
                                <input v-model="item.label" type="text" class="form-control form-control-sm" placeholder="Label (e.g. Support)" />
                                <input v-model="item.address" type="email" class="form-control form-control-sm" placeholder="Email address" />
                                <button class="btn btn-sm btn-outline-danger flex-shrink-0" @click="removeItem('support_emails', i)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-info mt-1" @click="addItem('support_emails', { label: '', address: '' })">
                                <i class="ti ti-plus me-1"></i> Add Email
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- WhatsApp -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <span class="avatar avatar-sm bg-success me-2">
                            <i class="ti ti-brand-whatsapp fs-14"></i>
                        </span>
                        <h6 class="mb-0">WhatsApp</h6>
                    </div>
                    <div class="card-body">
                        <template v-if="!editMode">
                            <div v-if="!form.support_whatsapp.length" class="text-center py-3">
                                <i class="ti ti-brand-whatsapp fs-24 text-muted mb-2 d-block"></i>
                                <span class="text-muted fs-13">No WhatsApp numbers added.</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 fs-13" @click="startEdit">Add now</button>
                            </div>
                            <ul v-else class="list-unstyled mb-0">
                                <li v-for="(item, i) in form.support_whatsapp" :key="i" class="d-flex align-items-center mb-2">
                                    <i class="ti ti-brand-whatsapp text-success me-2"></i>
                                    <div>
                                        <div class="fw-medium">{{ item.label }}</div>
                                        <a :href="'https://wa.me/' + item.number.replace(/\D/g, '')" target="_blank" class="text-muted fs-13">{{ item.number }}</a>
                                    </div>
                                </li>
                            </ul>
                        </template>
                        <template v-else>
                            <div v-for="(item, i) in form.support_whatsapp" :key="i" class="d-flex gap-2 mb-2">
                                <input v-model="item.label" type="text" class="form-control form-control-sm" placeholder="Label (e.g. Sales)" />
                                <input v-model="item.number" type="text" class="form-control form-control-sm" placeholder="WhatsApp number" />
                                <button class="btn btn-sm btn-outline-danger flex-shrink-0" @click="removeItem('support_whatsapp', i)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-success mt-1" @click="addItem('support_whatsapp', { label: '', number: '' })">
                                <i class="ti ti-plus me-1"></i> Add WhatsApp
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Social Links -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <span class="avatar avatar-sm bg-warning me-2">
                            <i class="ti ti-share fs-14"></i>
                        </span>
                        <h6 class="mb-0">Social Links</h6>
                    </div>
                    <div class="card-body">
                        <template v-if="!editMode">
                            <div v-if="!form.support_social_links.length" class="text-center py-3">
                                <i class="ti ti-share-off fs-24 text-muted mb-2 d-block"></i>
                                <span class="text-muted fs-13">No social links added.</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 fs-13" @click="startEdit">Add now</button>
                            </div>
                            <ul v-else class="list-unstyled mb-0">
                                <li v-for="(item, i) in form.support_social_links" :key="i" class="d-flex align-items-center mb-2">
                                    <i class="ti ti-world text-warning me-2"></i>
                                    <div>
                                        <div class="fw-medium">{{ item.platform }}</div>
                                        <a :href="item.url" target="_blank" class="text-muted fs-13 text-truncate d-inline-block" style="max-width: 260px;">{{ item.url }}</a>
                                    </div>
                                </li>
                            </ul>
                        </template>
                        <template v-else>
                            <div v-for="(item, i) in form.support_social_links" :key="i" class="d-flex gap-2 mb-2">
                                <input v-model="item.platform" type="text" class="form-control form-control-sm" placeholder="Platform (e.g. Facebook)" />
                                <input v-model="item.url" type="url" class="form-control form-control-sm" placeholder="https://..." />
                                <button class="btn btn-sm btn-outline-danger flex-shrink-0" @click="removeItem('support_social_links', i)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-warning mt-1" @click="addItem('support_social_links', { platform: '', url: '' })">
                                <i class="ti ti-plus me-1"></i> Add Social Link
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tutorial Videos -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="avatar avatar-sm bg-danger me-2">
                    <i class="ti ti-brand-youtube fs-14"></i>
                </span>
                <h6 class="mb-0">Tutorial Videos</h6>
            </div>
            <div class="card-body">
                <template v-if="!editMode">
                    <div v-if="!form.support_videos.length" class="text-center py-4">
                        <i class="ti ti-video-off fs-32 text-muted mb-2 d-block"></i>
                        <span class="text-muted fs-13">No tutorial videos added.</span>
                        <button class="btn btn-sm btn-link p-0 ms-1 fs-13" @click="startEdit">Add now</button>
                    </div>
                    <div v-else class="row g-3">
                        <div v-for="(video, i) in form.support_videos" :key="i" class="col-lg-4 col-md-6">
                            <div class="card border shadow-sm h-100">
                                <div class="video-thumb-wrap position-relative">
                                    <img
                                        :src="getYoutubeThumbnail(video.url)"
                                        :alt="video.title"
                                        class="card-img-top"
                                        style="height: 180px; object-fit: cover;"
                                        @error="onThumbError($event)"
                                    />
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <span class="avatar avatar-lg bg-danger bg-opacity-90 shadow">
                                            <i class="ti ti-player-play fs-20"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <p class="fw-semibold mb-3 flex-grow-1">{{ video.title }}</p>
                                    <a :href="video.url" target="_blank" class="btn btn-sm btn-outline-danger w-100">
                                        <i class="ti ti-brand-youtube me-1"></i> Watch Video
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div v-for="(item, i) in form.support_videos" :key="i" class="d-flex gap-2 mb-2">
                        <input v-model="item.title" type="text" class="form-control form-control-sm" placeholder="Video title" />
                        <input v-model="item.url" type="url" class="form-control form-control-sm" placeholder="YouTube URL (https://youtu.be/...)" />
                        <button class="btn btn-sm btn-outline-danger flex-shrink-0" @click="removeItem('support_videos', i)">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                    <button class="btn btn-sm btn-outline-danger mt-1" @click="addItem('support_videos', { title: '', url: '' })">
                        <i class="ti ti-plus me-1"></i> Add Video
                    </button>
                </template>
            </div>
        </div>
    </template>
</template>

<script setup>
import {onMounted, ref, reactive} from 'vue';
import {storeToRefs} from 'pinia';
import {useSupportStore} from '@/stores/super-admin/support';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const store = useSupportStore();
const {support} = storeToRefs(store);

const editMode = ref(false);
const saving = ref(false);
const isFirstLoad = ref(true);

const form = reactive({
    support_phones: [],
    support_emails: [],
    support_whatsapp: [],
    support_social_links: [],
    support_videos: [],
});

const loadFormFromStore = () => {
    const d = support.value.data;
    form.support_phones = JSON.parse(JSON.stringify(d.support_phones || []));
    form.support_emails = JSON.parse(JSON.stringify(d.support_emails || []));
    form.support_whatsapp = JSON.parse(JSON.stringify(d.support_whatsapp || []));
    form.support_social_links = JSON.parse(JSON.stringify(d.support_social_links || []));
    form.support_videos = JSON.parse(JSON.stringify(d.support_videos || []));
};

const hasAnyData = () => {
    return form.support_phones.length
        || form.support_emails.length
        || form.support_whatsapp.length
        || form.support_social_links.length
        || form.support_videos.length;
};

onMounted(async () => {
    await store.getSupportSettings(true);
    loadFormFromStore();
    if (!hasAnyData()) {
        editMode.value = true;
    }
    isFirstLoad.value = false;
});

const startEdit = () => {
    loadFormFromStore();
    editMode.value = true;
};

const cancelEdit = () => {
    loadFormFromStore();
    if (hasAnyData()) {
        editMode.value = false;
    }
};

const addItem = (key, template) => {
    form[key].push({...template});
};

const removeItem = (key, index) => {
    form[key].splice(index, 1);
};

const saveSettings = async () => {
    saving.value = true;
    try {
        const res = await store.updateSupportSettings({...form});
        toast('success', res.data.message);
        loadFormFromStore();
        editMode.value = false;
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const getYoutubeThumbnail = (url) => {
    if (!url) return '';
    const patterns = [
        /youtu\.be\/([^?&]+)/,
        /youtube\.com\/watch\?v=([^&]+)/,
        /youtube\.com\/embed\/([^?&]+)/,
        /youtube\.com\/shorts\/([^?&]+)/,
    ];
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match) {
            return `https://img.youtube.com/vi/${match[1]}/hqdefault.jpg`;
        }
    }
    return '';
};

const onThumbError = (event) => {
    event.target.src = 'https://img.youtube.com/vi/default/hqdefault.jpg';
};
</script>
