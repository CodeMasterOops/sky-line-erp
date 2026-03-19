<template>
    <PageHeader title="Chart Of Accounts" subtitle="Manage your chart of accounts" @refresh="setSettingData()"/>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-2 border rounded-1">
                        <div class="d-flex align-items-start py-2">
                            <div class="nav flex-column nav-pills w-100" role="tablist"
                                 aria-orientation="vertical">
                                <button :class="['nav-link mb-2 border',{'active':activeTab==='tree'}]"
                                        type="button" @click="activeTab='tree'">
                                    Tree View
                                </button>
                                <button :class="['nav-link mb-2 border',{'active':activeTab==='accounts'}]"
                                        type="button" @click="activeTab='accounts'">
                                    Accounts
                                </button>
                                <button :class="['nav-link mb-2 border',{'active':activeTab==='account-group'}]"
                                        type="button" @click="activeTab='account-group'">
                                    Account Group
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-10">
                        <div class="tab-content">
                            <div v-if="activeTab==='account-group'">
                                <AccountGroups/>
                            </div>
                            <div v-else-if="activeTab==='tree'">
                                <CoaTree/>
                            </div>
                            <div v-else>
                                <Accounts/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
<script setup>
import AccountGroups from './account-group/Index.vue';
import Accounts from './accounts/Index.vue';
import CoaTree from './tree/Index.vue';
import {ref} from "vue";

const activeTab = ref('tree');
</script>
