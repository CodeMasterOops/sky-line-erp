<template>
  <div class="product-category-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Add Product Category</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Add New Category</h5>
        <router-link :to="{name:'admin.product-category-list'}" class="btn btn-sm btn-outline-primary">
          <i class="fa fa-list"> Category List</i>
        </router-link>
      </div>
      <div class="card-body">
        <form @submit.prevent="storeProductCategory" class="row g-3">
          <div class="col-md-6">
            <VMultiselect
                id="parent_id"
                v-model="form.parent_id"
                :options="productCategories.data"
                label="Parent"
            />
          </div>
          <div class="col-md-6">
            <VInput
                id="name"
                v-model="form.name"
                label="Name"
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
          <div class="col-md-6">
            <VInput
                input-type="number"
                id="sort_order"
                v-model="form.sort_order"
                label="Sort Order"
                @validate="validateField('sort_order')"
                :error="errors.sort_order"
            />
          </div>
          <div class="col-md-6">
            <VFileUpload
                id="thumbnail_image"
                v-model="form.thumbnail_image"
                label="Thumbnail Image"
                @validate="validateField('thumbnail_image')"
                :error="errors.thumbnail_image"
            />
          </div>
          <div class="col-md-6">
            <VFileUpload
                id="banner_image"
                v-model="form.banner_image"
                label="Banner Image"
                @validate="validateField('banner_image')"
                :error="errors.banner_image"
            />
          </div>
          <div class="col-md-12">
            <VCkeditor
                id="description"
                v-model="form.description"
                :height="300"
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
            <VButton :loading="isSubmitting"/>
          </div>
        </form>
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
import {useRouter} from "vue-router";
import {useProductCategoryStore} from "@/stores/admin/product-category.js";
import {storeToRefs} from "pinia";

const categoryStore = useProductCategoryStore();
const router = useRouter();

onMounted(() => {
  categoryStore.getProductCategories();
})

const {productCategories} = storeToRefs(categoryStore);

const initialState = {
  parent_id: '',
  name: '',
  slug: '',
  sort_order: '',
  thumbnail_image: '',
  banner_image: '',
  description: '',
  meta_title: '',
  meta_keywords: '',
  meta_description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  name: string().required('Category name is required.'),
  slug: string().required('Slug is required.'),
  sort_order: string().required('Sort order is required.'),
  thumbnail_image: string().nullable(),
  banner_image: string().nullable(),
  description: string().nullable(),
  meta_title: string().nullable(),
  meta_keywords: string().nullable(),
  meta_description: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeProductCategory = async () => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await categoryStore.storeProductCategory(form);
      toast(res.status, res.data.message);
      resetForm();
      await router.push({name: 'admin.product-category-list'})
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

