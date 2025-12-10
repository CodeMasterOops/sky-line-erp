import {createApp} from 'vue'
import App from './App.vue'

import {createPinia} from 'pinia'

import router from "@/router";

//multiselect css
import '@vueform/multiselect/themes/default.css';

//bootstrap
import "bootstrap";
import '@/assets/scss/app.scss';

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

//permission check helper
import {permissionAccess} from "@/helpers/checkPermission";

//fontawesome
import '@/assets/fontawesome/css/font-awesome.min.css'

//my plugins
import myPlugins from "./plugins/my-plugins";
import Multiselect from "@vueform/multiselect";

createApp(App)
    .use(router)
    .use(createPinia())
    .use(myPlugins)
    .component('VInput', VInput)
    .component('VTextarea', VTextarea)
    .component('VSelect', VSelect)
    .component('Multiselect', Multiselect)
    .component('VMultiselect', VMultiselect)
    .component('VCheckbox', VCheckbox)
    .component('VDatepicker', VDatepicker)
    .component('VTimepicker', VTimepicker)
    .component('VDatetimepicker', VDateTimePicker)
    .component('VFileUpload', VFileUpload)
    .component('VButton', VButton)
    .component('VLoader', VLoader)
    .component('VDataTable', VDataTable)
    .component('VModal', VModal)
    .component('VPrint', VPrint)
    .component('VExport', VExport)
    .directive('can', permissionAccess)
    .mount('#app')
