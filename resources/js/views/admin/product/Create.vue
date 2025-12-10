<template>
  <div class="product-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Add Product</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Add New Product</h5>
        <router-link :to="{name:'admin.product-list'}" class="btn btn-sm btn-outline-primary">
          <i class="fa fa-list"> Product List</i>
        </router-link>
      </div>
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
                        data-bs-target="#tab-detail" type="button" role="tab">
                  Detail
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-media" type="button" role="tab">
                  Media
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-price-stock" type="button" role="tab">
                  Price & Stock
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-attributes" type="button" role="tab">
                  Attributes
                </button>
                <button class="nav-link mb-2 border" data-bs-toggle="pill"
                        data-bs-target="#tab-meta" type="button" role="tab">
                  Meta Info
                </button>
              </div>
            </div>
          </div>
          <div class="col-10">
            <form @submit.prevent="storeProduct" class="border rounded-1 p-3">
              <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                  <div class="row g-3">
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
                      <VMultiselect
                          id="vendor_id"
                          v-model="form.vendor_id"
                          :options="vendors.data"
                          name-prop="vendor_name"
                          label="Vendor"
                          @validate="validateField('vendor_id')"
                          :error="errors.vendor_id"
                      />
                    </div>
                    <div class="col-md-6">
                      <VMultiselect
                          id="brand_id"
                          v-model="form.brand_id"
                          :options="brands.data"
                          name-prop="name"
                          label="Brand"
                          @validate="validateField('brand_id')"
                          :error="errors.brand_id"
                      />
                    </div>
                    <div class="col-md-6">
                      <VMultiselect
                          id="categories"
                          v-model="form.categories"
                          :options="productCategories.data"
                          mode="multiple"
                          label="Categories"
                          @validate="validateField('categories')"
                          :error="errors.categories"
                      />
                    </div>
                    <div class="col-md-6">
                      <VMultiselect
                          id="tags"
                          v-model="form.tags"
                          :options="tags.data"
                          name-prop="title"
                          mode="multiple"
                          label="Tags"
                      />
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-detail" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <VCkeditor
                          id="specification"
                          v-model="form.specification"
                          label="Specification"
                          :height="200"
                          @validate="validateField('specification')"
                          :error="errors.specification"
                      />
                    </div>
                    <div class="col-md-12">
                      <VCkeditor
                          id="ingredients"
                          v-model="form.ingredients"
                          label="Ingredients"
                          :height="200"
                          @validate="validateField('ingredients')"
                          :error="errors.ingredients"
                      />
                    </div>
                    <div class="col-md-12">
                      <VCkeditor
                          id="description"
                          v-model="form.description"
                          label="Description"
                          @validate="validateField('description')"
                          :error="errors.description"
                      />
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-media" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-12">
                      <VFileUpload
                          id="thumbnail_image"
                          v-model="form.thumbnail_image"
                          label="Thumbnail Image"
                          @validate="validateField('thumbnail_image')"
                          :error="errors.thumbnail_image"
                      />
                    </div>
                    <div class="d-flex justify-content-between">
                      <h5 class="text-primary mb-0">
                        Product Images
                      </h5>
                      <button type="button" class="btn btn-sm btn-outline-primary"
                              @click="addImage">
                        <i class="fa fa-plus-circle"> ADD MORE</i>
                      </button>
                    </div>
                    <template v-if="form.images.length">
                      <div class="accordion accordion-flush" id="accordionImage">
                        <div v-for="(image,index) in form.images" :key="index">
                          <div class="accordion-item bg-light my-2">
                            <h2 class="accordion-header d-flex justify-content-between">
                              <button
                                  :class="['accordion-button',{'collapsed':(form.images.length-1)!==index}]"
                                  type="button"
                                  data-bs-toggle="collapse"
                                  :data-bs-target="`#image-collapse-${index}`"
                                  :aria-expanded="(form.images.length-1)===index"
                                  :aria-controls="`collapse-${index}`">
                                Photo {{ index + 1 }}
                              </button>
                              <button type="button"
                                      @click="removeImage(index)"
                                      class="btn btn-smm btn-outline-danger m-2">
                                <i class="fa fa-close"> </i>
                              </button>
                            </h2>
                            <div :id="`image-collapse-${index}`"
                                 :class="['accordion-collapse collapse',{'show':(form.images.length-1)===index}]">
                              <div class="accordion-body">
                                <div class="row g-3">
                                  <div class="col-md-6">
                                    <VFileUpload
                                        :id="'image_image-'+index"
                                        v-model="form.images[index].image"
                                        label="Image"
                                        @validate="validateField(`images[${index}].image`)"
                                        :error="errors[`images[${index}].image`]"
                                    />
                                  </div>
                                  <div class="col-md-6">
                                    <VInput
                                        :id="'image_image_alt-'+index"
                                        v-model="form.images[index].image_alt"
                                        label="Image Alt"
                                        @validate="validateField(`images[${index}].image_alt`)"
                                        :error="errors[`images[${index}].image_alt`]"
                                    />
                                  </div>
                                  <div class="col-md-6">
                                    <VInput
                                        :id="'image_title-'+index"
                                        v-model="form.images[index].title"
                                        label="Title"
                                        @validate="validateField(`images[${index}].title`)"
                                        :error="errors[`images[${index}].title`]"
                                    />
                                  </div>
                                  <div class="col-md-6">
                                    <VInput
                                        input-type="number"
                                        :id="'image_sort_order-'+index"
                                        v-model="form.images[index].sort_order"
                                        label="Sort Order"
                                        @validate="validateField(`images[${index}].sort_order`)"
                                        :error="errors[`images[${index}].sort_order`]"
                                    />
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </template>
                    <div v-else class="alert alert-warning my-3">
                      No Images Added
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-price-stock" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <VSelect
                          id="has_variants"
                          v-model="form.has_variants"
                          :options="[{id:1,name:'Yes'},{id:0,name:'No'}]"
                          label="Has Variants?"
                      />
                    </div>
                    <div class="col-md-6">
                      <VSelect
                          id="track_stock"
                          v-model="form.track_stock"
                          :options="[{id:1,name:'Yes'},{id:0,name:'No'}]"
                          label="Track Stock"
                      />
                    </div>
                    <template v-if="form.has_variants==='1'">
                      <div class="border p-3">
                        <div v-for="(variant,index) in selectedVariants" :key="index" class="row my-1">
                          <div class="col-md-4">
                            <VSelect
                                v-model="selectedVariants[index].attribute_id"
                                :options="selectableAttributes(variant.attribute_id)"
                                placeholder="Attribute"
                            />
                          </div>
                          <div class="col-md-6">
                            <VMultiselect
                                v-model="selectedVariants[index].values"
                                mode="multiple"
                                :options="attributeValues(variant.attribute_id)"
                                name-prop="value"
                                placeholder="Value"
                            />
                          </div>
                          <div class="col-md-2">
                            <button type="button" @click="removeVariantOption(index)"
                                    class="btn btn-sm btn-outline-danger">
                              <i class="fa fa-trash"></i>
                            </button>
                          </div>
                        </div>
                        <button v-if="selectedVariants.length!==attributes.data.length" type="button"
                                @click="addVariantOption" class="btn btn-sm btn-outline-secondary mt-2">
                          Add Option
                        </button>
                      </div>
                      <div v-if="form.variants.length" class="table-responsive">
                        <table class="table table-bordered">
                          <thead>
                          <tr>
                            <th style="width:40px;">SN</th>
                            <th v-for="attr in selectedAttributes" :key="attr.id">
                              {{ attr.attr_name }}
                            </th>
                            <th style="width:130px;">SKU</th>
                            <th style="width:130px;">Price</th>
                            <th style="width:130px;">Sales Price</th>
                            <th style="width:130px;">Weight</th>
                          </tr>
                          </thead>
                          <tbody>
                          <tr v-for="(variant,index) in form.variants" :key="index">
                            <td>{{ index + 1 }}</td>
                            <td v-for="value in variant.value_labels" :key="value">
                              {{ value }}
                            </td>
                            <td>
                              <VInput
                                  v-model="form.variants[index].sku"
                                  placeholder="SKU"
                                  input-class="form-control form-control-sm"
                                  @validate="validateField(`variants[${index}].sku`)"
                                  :error="errors[`variants[${index}].sku`]"
                              />
                            </td>
                            <td>
                              <VInput
                                  input-type="number"
                                  v-model="form.variants[index].price"
                                  placeholder="Price"
                                  input-class="form-control form-control-sm"
                                  @validate="validateField(`variants[${index}].price`)"
                                  :error="errors[`variants[${index}].price`]"
                              />
                            </td>
                            <td>
                              <VInput
                                  input-type="number"
                                  v-model="form.variants[index].sales_price"
                                  placeholder="Sales Price"
                                  input-class="form-control form-control-sm"
                                  @validate="validateField(`variants[${index}].sales_price`)"
                                  :error="errors[`variants[${index}].sales_price`]"
                              />
                            </td>
                            <td>
                              <VInput
                                  input-type="number"
                                  v-model="form.variants[index].weight"
                                  placeholder="Weight"
                                  input-class="form-control form-control-sm"
                              />
                            </td>
                          </tr>
                          </tbody>
                        </table>
                      </div>
                    </template>
                    <template v-else-if="form.variants.length">
                      <div class="col-md-4">
                        <VInput
                            id="sku"
                            v-model="form.variants[0].sku"
                            label="SKU"
                            @validate="validateField(`variants[0].sku`)"
                            :error="errors[`variants[0].sku`]"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="price"
                            v-model="form.variants[0].price"
                            label="Price"
                            @validate="validateField(`variants[0].price`)"
                            :error="errors[`variants[0].price`]"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="sales_price"
                            v-model="form.variants[0].sales_price"
                            label="Sales Price"
                            @validate="validateField(`variants[0].sales_price`)"
                            :error="errors[`variants[0].sales_price`]"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="weight"
                            v-model="form.variants[0].weight"
                            label="Weight (g)"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="length"
                            v-model="form.variants[0].length"
                            label="Length (cm)"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="width"
                            v-model="form.variants[0].width"
                            label="Width (cm)"
                        />
                      </div>
                      <div class="col-md-4">
                        <VInput
                            input-type="number"
                            id="height"
                            v-model="form.variants[0].height"
                            label="Height (cm)"
                        />
                      </div>
                    </template>
                  </div>
                </div>
                <div class="tab-pane fade" id="tab-attributes" role="tabpanel">
                  <div class="row g-3">
                    <div class="border p-3">
                      <button v-if="selectedAttributeOptions.length!==attributes.data.length" type="button"
                              @click="addAttributeOption" class="btn btn-sm btn-outline-secondary my-2">
                        Add Option
                      </button>
                      <div v-for="(variant,index) in selectedAttributeOptions" :key="index" class="row my-1">
                        <div class="col-md-4">
                          <VSelect
                              v-model="selectedAttributeOptions[index].attribute_id"
                              :options="selectableAttributeList(variant.attribute_id)"
                              placeholder="Attribute"
                          />
                        </div>
                        <div class="col-md-7">
                          <VMultiselect
                              v-model="selectedAttributeOptions[index].values"
                              mode="multiple"
                              :options="attributeValues(variant.attribute_id)"
                              name-prop="value"
                              placeholder="Value"
                          />
                        </div>
                        <div class="col-md-1">
                          <button type="button" @click="removeAttributeOption(index)"
                                  class="btn btn-sm btn-outline-danger">
                            <i class="fa fa-trash"></i>
                          </button>
                        </div>
                      </div>
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
                          :rows="2"
                          @validate="validateField('meta_keywords')"
                          :error="errors.meta_keywords"
                      />
                    </div>
                    <div class="col-md-12">
                      <VTextarea
                          id="meta_description"
                          v-model="form.meta_description"
                          label="Meta Description"
                          :rows="2"
                          @validate="validateField('meta_description')"
                          :error="errors.meta_description"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12 text-end mt-3">
                <router-link :to="{name:'admin.product-list'}" class="btn btn-danger">
                  Close
                </router-link>
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
import {onMounted, reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useProductStore} from "@/stores/admin/product";
import {useRouter} from "vue-router";
import {useProductCategoryStore} from "@/stores/admin/product-category.js";
import {useTagStore} from "@/stores/admin/tag.js";
import {storeToRefs} from "pinia";
import {useVendorStore} from "@/stores/admin/vendor.js";
import {useAttributeStore} from "@/stores/admin/attribute.js";
import {useBrandStore} from "@/stores/admin/brand.js";

const categoryStore = useProductCategoryStore();
const tagStore = useTagStore();
const productStore = useProductStore();
const vendorStore = useVendorStore();
const brandStore = useBrandStore();
const attributeStore = useAttributeStore();
const router = useRouter();

onMounted(() => {
  categoryStore.getProductCategories();
  tagStore.getTags({});
  attributeStore.getAttributes();
  brandStore.getBrands();
  addVariants();
  vendorStore.getVendors({
    filter: {limit: 500}
  });
})

const {tags} = storeToRefs(tagStore);
const {productCategories} = storeToRefs(categoryStore);
const {vendors} = storeToRefs(vendorStore);
const {attributes} = storeToRefs(attributeStore);
const {brands} = storeToRefs(brandStore);

const initialState = {
  name: '',
  slug: '',
  vendor_id: '',
  thumbnail_image: '',
  brand_id: '',
  images: [],
  tags: [],
  categories: [],
  specification: '',
  ingredients: '',
  description: '',
  is_active: 1,
  has_variants: 0,
  variants: [],
  attribute_values: [],
  track_stock: 0,
  meta_title: '',
  meta_keywords: '',
  meta_description: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addImage = () => {
  form.images.push({
    title: '',
    image: '',
    image_alt: '',
    sort_order: form.images.length + 1
  })
}

const removeImage = (index) => {
  form.images.splice(index, 1);
}

const addVariants = () => {
  form.variants.push({
    sku: '',
    price: '',
    sales_price: '',
    weight: '',
    length: '',
    width: '',
    height: '',
    is_default: false,
  })
}

const selectedVariants = ref([]);

const addVariantOption = () => {
  selectedVariants.value.push({
    attribute_id: '',
    values: []
  })
}

const removeVariantOption = (index) => {
  selectedVariants.value.splice(index, 1);
}

const selectedAttributeOptions = ref([]);

const addAttributeOption = () => {
  selectedAttributeOptions.value.push({
    attribute_id: '',
    values: []
  })
}

const removeAttributeOption = (index) => {
  selectedAttributeOptions.value.splice(index, 1);
}

const selectableAttributes = (attrId = null) => {
  let attrIds = selectedVariants.value.filter(v => v.attribute_id).map(v => parseInt(v.attribute_id));
  if (attrId) {
    return attributes.value.data.filter(a =>
        !attrIds.includes(a.id) || a.id === parseInt(attrId)
    )
  }
  if (attrIds.length) {
    return attributes.value.data.filter(a => !attrIds.includes(a.id));
  }
  return attributes.value.data;
}

const selectableAttributeList = (attrId = null) => {
  let attrIds = selectedAttributeOptions.value.filter(v => v.attribute_id).map(v => parseInt(v.attribute_id));
  if (attrId) {
    return attributes.value.data.filter(a =>
        !attrIds.includes(a.id) || a.id === parseInt(attrId)
    )
  }
  if (attrIds.length) {
    return attributes.value.data.filter(a => !attrIds.includes(a.id));
  }
  return attributes.value.data;
}

const attributeValues = (attrId = null, valId = null) => {
  if (attrId) {
    const attrValues = attributes.value.data.find(a => a.id == attrId).values;
    if (valId) {
      return attrValues.find(v => v.id === valId);
    }
    return attributes.value.data.find(a => a.id == attrId).values;
  }
  return [];
}

const selectedAttributes = ref([]);

const cartesianProduct = (arr) => {
  return arr.reduce((acc, curr) => {
    return acc.flatMap(a => curr.map(b => [...a, b]))
  }, [[]])
}

watch(() => selectedVariants.value, () => {
  const variantValueGroups = selectedVariants.value
      .filter(v => v.attribute_id && v.values.length)
      .map(v =>
          v.values.map(val => ({
            attr_id: parseInt(v.attribute_id),
            value_id: parseInt(val)
          }))
      )

  if (!variantValueGroups.length) {
    form.variants = [];
    return
  }

  const combinations = cartesianProduct(variantValueGroups);
  let cList = [];

  let attrList = [];

  combinations.forEach((cmb, index) => {
    cmb.forEach(l => {
      let check = attrList.find(a => a.attr_id === l.attr_id);
      if (!check) {
        const attr = attributes.value.data.find(d => d.id === l.attr_id);
        attrList.push({
          attr_id: attr.id,
          attr_name: attr.name
        })
      }
    })
    cList.push({
      value_labels: cmb.map(attr => attributeValues(attr.attr_id, attr.value_id)?.value || ''),
      sku: '',
      price: '',
      sales_price: '',
      weight: '',
      length: '',
      width: '',
      height: '',
      is_default: index === 0,
      attribute_values: cmb.map(a => a.value_id)
    })
  })

  selectedAttributes.value = attrList;
  form.variants = cList;
}, {deep: true})

watch(() => selectedAttributeOptions.value, (attrOptions) => {
  let values=[];
  attrOptions.forEach(attr => {
    attr.values.forEach(v=>{
      if(!values.includes(v)){
        values.push(v);
      }
    })
  })
  form.attribute_values = values;
}, {deep: true})

watch(() => form.has_variants, (has_variant) => {
  form.variants.splice(0);
  if (!has_variant) {
    addVariants();
  }
})

const validations = object({
  name: string().required('Name is required.'),
  slug: string().required('Slug is required.'),
  vendor_id: string().required('Vendor is required.'),
  thumbnail_image: string().required('Thumbnail image is required.'),
  brand_id: string().nullable(),
  images: array().of(
      object({
        title: string().nullable(),
        image: string().required('Image is required.'),
        image_alt: string().nullable(),
        sort_order: string().required('Sort order is required.')
      })
  ),
  categories: array().min(1, 'Category is required.'),
  specification: string().nullable(),
  ingredients: string().nullable(),
  description: string().nullable(),
  variants: array().of(
      object({
        sku: string().nullable(),
        sales_price: string().required('Price is required.'),
        price: string().nullable(),
      })
  ),
  meta_title: string().nullable(),
  meta_keywords: string().nullable(),
  meta_description: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeProduct = async () => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await productStore.storeProduct(form);
      toast(res.status, res.data.message);
      resetForm();
      await router.push({name: 'admin.product-list'})
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const resetForm = () => {
  Object.assign(form, {...initialState});
  form.categories.splice(0);
  form.tags.splice(0);
  form.images.splice(0);
  form.variants.splice(0);
  form.attribute_values.splice(0);
  errors.value = {};
}

</script>

