<template>
  <div>
    <PageHeader
      title="Site Settings"
      subtitle="Manage platform name, logo, and social links"
      @refresh="setSettingData(true)"
    />
  </div>

  <div class="row">
    <div class="col-xl-12">
      <div class="settings-wrapper d-flex">
        <super-admin-settings-sidebar></super-admin-settings-sidebar>
        <div class="card flex-fill mb-0">
          <div class="card-header">
            <h4 class="fs-18 fw-bold">Platform settings</h4>
          </div>
          <VLoader v-if="setting.loading" loader-type="progress" />
          <div v-show="!setting.loading" class="card-body">
            <form @submit.prevent="updateSetting">
              <div class="card-title-head">
                <h6 class="fs-16 fw-bold mb-3">
                  <span class="fs-16 me-2"><i class="ti ti-building"></i></span>
                  Basic information
                </h6>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <VInput
                    id="site_name"
                    v-model="form.site_name"
                    label="Site Name"
                    @validate="validateField('site_name')"
                    :error="errors.site_name"
                  />
                </div>
              </div>

              <div class="card-title-head">
                <h6 class="fs-16 fw-bold mb-3">
                  <span class="fs-16 me-2"><i class="ti ti-photo"></i></span>
                  Media
                </h6>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <div
                    class="profile-pic-upload settings-logo-row d-flex flex-wrap align-items-center"
                  >
                    <div
                      class="profile-pic settings-logo-preview d-flex align-items-center justify-content-center text-center flex-shrink-0"
                      :class="{ 'p-0': logoBoxSrc }"
                      role="button"
                      tabindex="0"
                      @click="openLogoPicker"
                      @keydown.enter.prevent="openLogoPicker"
                    >
                      <img
                        v-if="logoBoxSrc"
                        :src="logoBoxSrc"
                        alt="Site logo"
                        class="d-block w-100 h-100 object-fit-contain rounded-1 p-1"
                      />
                      <span v-else>
                        <i class="ti ti-circle-plus mb-0 fs-14 d-block"></i>
                        <span class="d-block fs-12 mt-1">Add Image</span>
                      </span>
                    </div>
                    <div
                      class="new-employee-field settings-logo-column flex-grow-1 min-w-0 d-flex flex-column justify-content-center"
                    >
                      <VFileUpload
                        id="logo"
                        v-model="form.logo"
                        :default-photo="setting.data.logo_url"
                        :hide-image-preview="true"
                        :button-only="true"
                        image-height="80px"
                        :max-size="2"
                        :mimes="['image/jpeg', 'image/jpg', 'image/png']"
                        browse-button-class="btn btn-primary settings-logo-upload-btn"
                      />
                      <p class="form-text settings-logo-hint mt-2 mb-0">
                        Upload an image below 2 MB. Accepted formats: JPG, PNG
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card-title-head">
                <h6 class="fs-16 fw-bold mb-3">
                  <span class="fs-16 me-2"><i class="ti ti-share"></i></span>
                  Social media
                </h6>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <VInput
                    id="facebook_link"
                    v-model="form.facebook_link"
                    label="Facebook Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="twitter_link"
                    v-model="form.twitter_link"
                    label="Twitter Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="instagram_link"
                    v-model="form.instagram_link"
                    label="Instagram Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="pinterest_link"
                    v-model="form.pinterest_link"
                    label="Pinterest Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="skype_link"
                    v-model="form.skype_link"
                    label="Skype Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="linkedin_link"
                    v-model="form.linkedin_link"
                    label="LinkedIn Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="youtube_link"
                    v-model="form.youtube_link"
                    label="YouTube Link"
                  />
                </div>
                <div class="col-md-6">
                  <VInput
                    id="google_map_link"
                    v-model="form.google_map_link"
                    label="Google Map Link"
                  />
                </div>
              </div>

              <div class="text-end settings-bottom-btn">
                <VButton :loading="isSubmitting" />
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { object, string } from "yup";
import { useYup } from "@/helpers/yup";
import { storeToRefs } from "pinia";
import { useSuperAdminSettingStore } from "@/stores/super-admin/setting.js";

const settingStore = useSuperAdminSettingStore();
const { setting } = storeToRefs(settingStore);

const initialState = {
  site_name: "",
  logo: "",
  facebook_link: "",
  twitter_link: "",
  instagram_link: "",
  pinterest_link: "",
  skype_link: "",
  linkedin_link: "",
  youtube_link: "",
  google_map_link: "",
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);
const logoBlobUrl = ref(null);

const logoBoxSrc = computed(() => {
  if (form.logo && form.logo instanceof File) {
    return logoBlobUrl.value;
  }
  return setting.value.data?.logo_url || null;
});

watch(
  () => form.logo,
  (f) => {
    if (logoBlobUrl.value) {
      URL.revokeObjectURL(logoBlobUrl.value);
      logoBlobUrl.value = null;
    }
    if (f && f instanceof File) {
      logoBlobUrl.value = URL.createObjectURL(f);
    }
  },
);

function openLogoPicker() {
  document.getElementById("logo")?.click();
}

onUnmounted(() => {
  if (logoBlobUrl.value) {
    URL.revokeObjectURL(logoBlobUrl.value);
  }
});

const validations = object({
  site_name: string().required("Site name is required."),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const setSettingData = async (refetch = false) => {
  await settingStore.getSetting(refetch);
  const d = setting.value.data;
  Object.keys(form).forEach((key) => {
    form[key] = d[key] ?? "";
  });
};

onMounted(() => {
  setSettingData();
});

const updateSetting = async () => {
  const validated = await validateForm();
  if (validated) {
    isSubmitting.value = true;
    try {
      const res = await settingStore.updateSetting(form);
      toast(res.status, res.data.message);
      await setSettingData(true);
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
};
</script>
