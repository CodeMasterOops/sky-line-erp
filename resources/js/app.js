import { createApp } from "vue";
import App from "./App.vue";
import DatePicker from "vue3-datepicker";
import VueApexCharts from "vue3-apexcharts";

import { createPinia } from "pinia";

import router from "@/router";
import Antd from "ant-design-vue";
import "ant-design-vue/dist/reset.css";
import VueSelect from "vue3-select-component";

//multiselect css
import "@vueform/multiselect/themes/default.css";

//bootstrap
import "bootstrap/dist/css/bootstrap.min.css";
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

import "@/assets/scss/app.scss";

//base components
import VInput from "@/components/base/VInput.vue";
import VTextarea from "@/components/base/VTextarea.vue";
import VSelect from "@/components/base/VSelect.vue";
import VMultiselect from "@/components/base/VMultiselect.vue";
import VCheckbox from "@/components/base/VCheckbox.vue";
import VFileUpload from "@/components/base/VFileUpload.vue";
import VDatepicker from "@/components/base/VDatepicker.vue";
import VButton from "@/components/base/VButton.vue";
import VLoader from "@/components/base/VLoader.vue";
import VDataTable from "@/components/base/VDataTable.vue";
import VModal from "@/components/base/VModal.vue";
import VPrint from "@/components/base/VPrint.vue";
import VExport from "@/components/base/VExport.vue";
import VTimepicker from "@/components/base/VTimepicker.vue";
import VDateTimePicker from "@/components/base/VDateTimePicker.vue";

import VueFeather from "vue-feather";
import ThemeSettings from "@/layouts/theme-settings.vue";
import verticalSidebar from "@/layouts/admin/vertical-sidebar.vue";
import PosLoader from "@/layouts/pos-loader.vue";
import ProductHeader from "@/components/product/product-header.vue";
import PageHeader from "@/components/shared/PageHeader.vue";
import SettingsSidebar from "@/layouts/admin/settings-sidebar.vue";

//permission check helper
import { permissionAccess } from "@/helpers/checkPermission";

//fontawesome
import "@fortawesome/fontawesome-free/css/fontawesome.min.css";
import "@fortawesome/fontawesome-free/css/all.min.css";
import "@/assets/css/tabler-icons.css";
import { IconHome } from "@tabler/icons-vue";

//my plugins
import myPlugins from "./plugins/my-plugins";
import Multiselect from "@vueform/multiselect";

createApp(App)
    .use(router)
    .use(createPinia())
    .use(myPlugins)
    .component("vue-feather", VueFeather)
    .component("theme-settings", ThemeSettings)
    .component("vertical-sidebar", verticalSidebar)
    .component("PosLoader", PosLoader)
    .component("IconHome", IconHome)
    .component("VInput", VInput)
    .component("VTextarea", VTextarea)
    .component("VSelect", VSelect)
    .component("Multiselect", Multiselect)
    .component("VMultiselect", VMultiselect)
    .component("ProductHeader", ProductHeader)
    .component("PageHeader", PageHeader)
    .component("VCheckbox", VCheckbox)
    .component("VDatepicker", VDatepicker)
    .component("VTimepicker", VTimepicker)
    .component("VDatetimepicker", VDateTimePicker)
    .component("VFileUpload", VFileUpload)
    .component("VButton", VButton)
    .component("VLoader", VLoader)
    .component("VDataTable", VDataTable)
    .component("VModal", VModal)
    .component("VPrint", VPrint)
    .component("VExport", VExport)
    .component("DatePicker", DatePicker)
    .directive("can", permissionAccess)
    .component("SettingsSidebar", SettingsSidebar)
    .component("VueSelect", VueSelect)
    .use(VueApexCharts)
    .use(Antd)
    .mount("#app");
