<template>
  <VModal
      :show-modal="!!edit_brand_id"
      @close-click="closeEditModal"
      modal-class="large-modal"
      title="Update Brand">
    <template #modal-body>
      <VLoader v-if="brand.loading" loader-type="progress"/>
      <form @submit.prevent="updateBrand(brand.data.id)" class="row g-3">
        <div class="col-md-6">
          <VInput
              id="name"
              v-model="form.name"
              label="Brand Name"
              @validate="validateField('name')"
              :error="errors.name"
          />
        </div>
        <div class="col-md-6">
          <VInput
              id="slug"
              v-model="form.slug"
              label="Slug"
              @validate="validateField('slug')"
              :error="errors.slug"
          />
        </div>
        <div class="col-md-12">
          <VFileUpload
              id="thumbnail_image"
              v-model="form.thumbnail_image"
              label="Thumbnail Image"
              :default-photo="brand.data.thumbnail_image_url"
              @validate="validateField('thumbnail_image')"
              :error="errors.thumbnail_image"
          />
        </div>
        <div class="col-md-12">
          <VTextarea
              id="description"
              v-model="form.description"
              label="Description"
              @validate="validateField('description')"
              :error="errors.description"
          />
        </div>
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
        <div class="col-12 text-end">
          <button @click="closeEditModal" class="btn btn-danger" type="button">
            Close
          </button>
          <VButton :loading="isSubmitting"/>
        </div>
      </form>
    </template>
  </VModal>
</template>

<script setup>
import {reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import {useBrandStore} from "@/stores/admin/brand";

const brandStore = useBrandStore();

const edit_brand_id = defineModel('brand_id');

const {brand} = storeToRefs(brandStore);

const initialState = {
  name: '',
  slug: '',
  thumbnail_image: '',
  description: '',
  meta_title: '',
  meta_keywords: '',
  meta_description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_brand_id.value, async (id) => {
  if (id) {
    await brandStore.getBrand(id);
    Object.keys(form).forEach(key => {
      form[key] = brand.value.data[key] || '';
    })
  }
})

const validations = object({
  name: string().required('Category name is required.'),
  slug: string().required('Slug is required.'),
  thumbnail_image: string().nullable(),
  description: string().nullable(),
  meta_title: string().nullable(),
  meta_keywords: string().nullable(),
  meta_description: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateBrand = async (id) => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await brandStore.updateBrand(id, form);
      toast(res.status, res.data.message);
      closeEditModal();
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const closeEditModal = () => {
  resetForm();
  edit_brand_id.value = '';
}

function resetForm() {
  Object.assign(form, {...initialState});
  errors.value = {};
}

</script>
