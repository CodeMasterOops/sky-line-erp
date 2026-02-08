<template>
    <PageHeader title="Setting" subtitle="Manage your settings" @refresh="setSettingData()" />

    <section class="section">
        <div class="card">
      <VLoader v-if="setting.loading" loader-type="progress"/>
      <div class="card-body">
        <div class="row">
          <div class="col-2 border rounded-1">
            <div class="d-flex align-items-start py-2">
              <div class="nav flex-column nav-pills w-100" role="tablist"
                   aria-orientation="vertical">
                <button class="nav-link active mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-basic" type="button" role="tab">
                  Basic Info
                </button>
                  <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-media" type="button" role="tab">
                  Media
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-social" type="button" role="tab">
                  Social Media
                </button>
              </div>
            </div>
          </div>
          <div class="col-10">
            <form @submit.prevent="updateSetting" class="border rounded-1 p-3">
              <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <VInput
                          id="site_name"
                          v-model="form.site_name"
                          label="Site Name"
                          @validate="validateField('site_name')"
                          :error="errors.site_name"
                      />
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-social" role="tabpanel">
                  <div class="row g-3">
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
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-media" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <VFileUpload
                          id="logo"
                          v-model="form.logo"
                          label="Logo"
                          :default-photo="setting.data.logo_url"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12 text-end mt-3">
                <VButton :loading="isSubmitting"/>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
<script setup>
import {onMounted, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import { useSuperAdminSettingStore } from '@/stores/super-admin/setting.js';

const settingStore = useSuperAdminSettingStore();

onMounted(() => {
  setSettingData();
})

const setSettingData = async () => {
  await settingStore.getSetting();
  Object.keys(form).forEach(key => {
    form[key] = setting.value.data[key] || '';
  })
}

const {setting} = storeToRefs(settingStore);

const initialState = {
  site_name: '',
  logo: '',
  facebook_link: '',
  twitter_link: '',
  instagram_link: '',
  pinterest_link: '',
  skype_link: '',
  linkedin_link: '',
  youtube_link: '',
  google_map_link: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  site_name: string().required('Site name is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateSetting = async () => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await settingStore.updateSetting(form);
      toast(res.status, res.data.message);
      resetForm();
      await setSettingData();
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const resetForm = () => {
  Object.assign(form, {...initialState});
  errors.value = {};
}
</script>

