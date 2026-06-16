import { createApp } from "vue";
import App from "./App.vue";
import DatePicker from "vue3-datepicker";
import VueApexCharts from "vue3-apexcharts";

import { createPinia } from "pinia";

import router from "@/router";
import Antd from "ant-design-vue";
import "ant-design-vue/dist/reset.css";

//multiselect css — @vueform/multiselect is the single project-wide
//select component (handles both single and multi-select).
import "@vueform/multiselect/themes/default.css";

//bootstrap — import the ESM build (the same 'bootstrap' module the components
//import from). Importing the separate UMD bundle here registered Bootstrap's
//data-api on `document` a second time, so every dropdown toggle fired twice
//(open then immediately close). One shared module = one data-api registration.
import "bootstrap/dist/css/bootstrap.min.css";
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import "@/assets/scss/app.scss";

//base components
import VInput from "@/components/base/VInput.vue";
import VRequiredMark from "@/components/base/VRequiredMark.vue";
import VTextarea from "@/components/base/VTextarea.vue";
import VSelect from "@/components/base/VSelect.vue";
import VMultiselect from "@/components/base/VMultiselect.vue";
import VCheckbox from "@/components/base/VCheckbox.vue";
import VFileUpload from "@/components/base/VFileUpload.vue";
import VDatepicker from "@/components/base/VDatepicker.vue";
import VButton from "@/components/base/VButton.vue";
import VLoader from "@/components/base/VLoader.vue";
import VDataTable from "@/components/base/VDataTable.vue";
import VPagination from "@/components/base/VPagination.vue";
import VModal from "@/components/base/VModal.vue";
import VPrint from "@/components/base/VPrint.vue";
import VExport from "@/components/base/VExport.vue";
import VTimepicker from "@/components/base/VTimepicker.vue";
import VDateTimePicker from "@/components/base/VDateTimePicker.vue";

import VueFeather from "vue-feather";
import verticalSidebar from "@/layouts/admin/vertical-sidebar.vue";
import PosLoader from "@/layouts/pos-loader.vue";
import PageHeader from "@/components/shared/PageHeader.vue";
import SettingsSidebar from "@/layouts/admin/settings-sidebar.vue";
import SuperAdminSettingsSidebar from "@/layouts/super-admin/settings-sidebar.vue";

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
    .use(createPinia())
    .use(router)
    .use(myPlugins)
    .component("vue-feather", VueFeather)
    .component("vertical-sidebar", verticalSidebar)
    .component("PosLoader", PosLoader)
    .component("IconHome", IconHome)
    .component("VInput", VInput)
    .component("VRequiredMark", VRequiredMark)
    .component("VTextarea", VTextarea)
    .component("VSelect", VSelect)
    .component("Multiselect", Multiselect)
    .component("VMultiselect", VMultiselect)
    .component("PageHeader", PageHeader)
    .component("VCheckbox", VCheckbox)
    .component("VDatepicker", VDatepicker)
    .component("VTimepicker", VTimepicker)
    .component("VDatetimepicker", VDateTimePicker)
    .component("VFileUpload", VFileUpload)
    .component("VButton", VButton)
    .component("VLoader", VLoader)
    .component("VDataTable", VDataTable)
    .component("VPagination", VPagination)
    .component("VModal", VModal)
    .component("VPrint", VPrint)
    .component("VExport", VExport)
    .component("DatePicker", DatePicker)
    .directive("can", permissionAccess)
    .component("SettingsSidebar", SettingsSidebar)
    .component("SuperAdminSettingsSidebar", SuperAdminSettingsSidebar)
    .use(VueApexCharts)
    .use(Antd)
    .mount("#app");
