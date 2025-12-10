<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
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
                        data-bs-target="#tab-media" type="button" role="tab">
                  Media
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-contents" type="button" role="tab">
                  Contents
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
                    <div class="col-md-4">
                      <VInput
                          id="site_name"
                          v-model="form.site_name"
                          label="Site Name"
                          @validate="validateField('site_name')"
                          :error="errors.site_name"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="alternate_name"
                          v-model="form.alternate_name"
                          label="Alternate Name"
                          @validate="validateField('alternate_name')"
                          :error="errors.alternate_name"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="slogan"
                          v-model="form.slogan"
                          label="Slogan"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="office_time"
                          v-model="form.office_time"
                          label="Office Time"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="primary_email"
                          v-model="form.primary_email"
                          label="Primary Email"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="secondary_email"
                          v-model="form.secondary_email"
                          label="Secondary Email"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="established_year"
                          v-model="form.established_year"
                          label="Established Year"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          id="whatsapp_number"
                          v-model="form.whatsapp_number"
                          label="WhatsApp Number"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          input-type="number"
                          id="total_ratings"
                          v-model="form.total_ratings"
                          label="Total Ratings"
                      />
                    </div>
                    <div class="col-md-4">
                      <VInput
                          input-type="number"
                          id="rating_value"
                          v-model="form.rating_value"
                          label="Rating Value"
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
                          id="favicon"
                          v-model="form.favicon"
                          label="Favicon"
                          :default-photo="setting.data.favicon_url"
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
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-contents" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <VSelect
                          id="blog_page_id"
                          v-model="form.blog_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="Blog Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="about_page_id"
                          v-model="form.about_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="About Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="contact_page_id"
                          v-model="form.contact_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="Contact Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="search_page_id"
                          v-model="form.search_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="Search Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="sitemap_page_id"
                          v-model="form.sitemap_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="Sitemap Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="faq_page_id"
                          v-model="form.faq_page_id"
                          :options="pages.data"
                          name-prop="title"
                          label="FAQ Page"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="featured_collection_id"
                          v-model="form.featured_collection_id"
                          :options="collections.data"
                          label="Featured Collection"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="new_in_collection_id"
                          v-model="form.new_in_collection_id"
                          :options="collections.data"
                          label="New In Collection"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="trending_collection_id"
                          v-model="form.trending_collection_id"
                          :options="collections.data"
                          label="Trending Collection"
                      />
                    </div>
                    <div class="col-md-4">
                      <VSelect
                          id="best_seller_collection_id"
                          v-model="form.best_seller_collection_id"
                          :options="collections.data"
                          label="Best Seller Collection"
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
import {useSettingStore} from "@/stores/admin/setting";
import {usePageStore} from "@/stores/admin/page.js";
import {useCollectionStore} from "@/stores/admin/collection.js";

const settingStore = useSettingStore();
const pageStore = usePageStore();
const collectionStore = useCollectionStore();

onMounted(() => {
  collectionStore.getCollections();
  setSettingData();
  pageStore.getPages({
    filter: {
      limit: 50
    }
  });
})

const setSettingData = async () => {
  await settingStore.getSetting();
  Object.keys(form).forEach(key => {
    form[key] = setting.value.data[key] || '';
  })
}

const {setting} = storeToRefs(settingStore);
const {pages} = storeToRefs(pageStore);
const {collections} = storeToRefs(collectionStore);

const initialState = {
  site_name: '',
  alternate_name: '',
  slogan: '',
  office_time: '',
  primary_email: '',
  secondary_email: '',
  established_year: '',
  logo: '',
  favicon: '',
  og_image: '',
  whatsapp_number: '',
  facebook_link: '',
  twitter_link: '',
  instagram_link: '',
  pinterest_link: '',
  skype_link: '',
  linkedin_link: '',
  youtube_link: '',
  google_map_link: '',
  total_ratings: '',
  rating_value: '',
  meta_title: '',
  meta_keywords: '',
  meta_description: '',
  blog_page_id: '',
  about_page_id: '',
  contact_page_id: '',
  search_page_id: '',
  sitemap_page_id: '',
  faq_page_id: '',
  featured_collection_id: '',
  new_in_collection_id: '',
  trending_collection_id: '',
  best_seller_collection_id: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  site_name: string().required('Site name is required.'),
  alternate_name: string().required('Alt name is required.'),
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

