<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'vendor.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Site Setting</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Site Setting</h5>
      </div>
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
                        data-bs-target="#tab-social" type="button" role="tab">
                  Social Media
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-policies" type="button" role="tab">
                  Website Policies
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-media" type="button" role="tab">
                  Media
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-meta" type="button" role="tab">
                  Meta Info
                </button>
              </div>
            </div>
          </div>
          <div class="col-10">
            <form @submit.prevent="updateSetting" class="border rounded-1 p-3">
              <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <VInput
                          id="vendor_name"
                          v-model="form.vendor_name"
                          label="Vendor Name"
                          @validate="validateField('vendor_name')"
                          :error="errors.vendor_name"
                      />
                    </div>
                    <div class="col-md-6">
                      <VInput
                          id="phone"
                          v-model="form.phone"
                          label="Phone"
                      />
                    </div>
                    <div class="col-md-6">
                      <VInput
                          id="email"
                          v-model="form.email"
                          label="Email"
                      />
                    </div>
                    <div class="col-md-6">
                      <VInput
                          id="address"
                          v-model="form.address"
                          label="Address"
                      />
                    </div>
                    <div class="col-md-6">
                      <VInput
                          id="google_map_link"
                          v-model="form.google_map_link"
                          label="Google Map Link (Src Only)"
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
                <div class="tab-pane fade" id="tab-policies" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <VCkeditor
                          id="shipping_policy"
                          v-model="form.shipping_policy"
                          label="Shipping Policy"
                          :height="300"
                      />
                    </div>
                    <div class="col-md-12">
                      <VCkeditor
                          id="refund_policy"
                          v-model="form.refund_policy"
                          label="Refund Policy"
                          :height="300"
                      />
                    </div>
                    <div class="col-md-12">
                      <VCkeditor
                          id="cancellation_policy"
                          v-model="form.cancellation_policy"
                          label="Cancellation Policy"
                          :height="300"
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
                    <div class="col-md-6">
                      <VFileUpload
                          id="og_image"
                          v-model="form.og_image"
                          label="OG Image"
                          :default-photo="setting.data.og_image_url"
                      />
                    </div>
                    <div class="col-md-6">
                      <VFileUpload
                          id="banner_image"
                          v-model="form.banner_image"
                          label="Banner Image"
                          :default-photo="setting.data.banner_image_url"
                      />
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-meta" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <VInput
                          id="meta_title"
                          v-model="form.meta_title"
                          label="Meta Title"
                          @validate="validateField('meta_title')"
                          :error="errors.meta_title"
                      />
                    </div>
                    <div class="col-md-12">
                      <VTextarea
                          id="meta_keywords"
                          v-model="form.meta_keywords"
                          label="Meta Keywords"
                          @validate="validateField('meta_keywords')"
                          :error="errors.meta_keywords"
                      />
                    </div>
                    <div class="col-md-12">
                      <VTextarea
                          id="meta_description"
                          v-model="form.meta_description"
                          label="Meta Description"
                          @validate="validateField('meta_description')"
                          :error="errors.meta_description"
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
import {useVendorSettingStore} from "@/stores/vendor/setting.js";

const settingStore = useVendorSettingStore();

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
  vendor_name: '',
  phone: '',
  email: '',
  logo: '',
  banner_image: '',
  og_image: '',
  address: '',
  description: '',
  facebook_link: '',
  twitter_link: '',
  instagram_link: '',
  pinterest_link: '',
  skype_link: '',
  linkedin_link: '',
  youtube_link: '',
  google_map_link: '',
  meta_title: '',
  meta_keywords: '',
  meta_description: '',
  shipping_policy: '',
  refund_policy: '',
  cancellation_policy: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  vendor_name: string().required('Vendor name is required.'),
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

