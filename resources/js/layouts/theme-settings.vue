<template>
    <div class="sidebar-contact ">
        <div class="toggle-theme"  data-bs-toggle="offcanvas" data-bs-target="#theme-setting"><i class="fa fa-cog fa-w-16 fa-spin"></i></div>
    </div>
    <div class="sidebar-themesettings offcanvas offcanvas-end" id="theme-setting">
        <div class="offcanvas-header d-flex align-items-center justify-content-between bg-dark">
            <div>
                <h3 class="mb-1 text-white">Theme Customizer</h3>
                <p class="text-light">Choose your themes & layouts etc.</p>
            </div>
            <a href="#" class="custom-btn-close d-flex align-items-center justify-content-center text-white"  data-bs-dismiss="offcanvas"><i class="ti ti-x"></i></a>
        </div>
        <div class="themecard-body offcanvas-body">
            <div class="accordion accordion-customicon1 accordions-items-seperate" id="settingtheme">
                <div class="accordion-item border px-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button text-dark fs-16 px-0 py-3 bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#modesetting" aria-expanded="true">
                        Theme Mode
                        </button>
                    </h2>
                    <div id="modesetting" class="accordion-collapse collapse show">
                        <div class="accordion-body px-0 py3 border-top">
                        <div class="d-flex align-items-center">
                                <div class="theme-mode flex-fill text-center w-100 me-3" @click="handleUpdateTheme('data-theme', 'light')">
                                    <input type="radio" name="theme" id="lightTheme" value="light" :checked="themeSettings['data-theme'] === 'light'">
                                    <label for="lightTheme" class="rounded fw-medium w-100">                            
                                        <span class="d-inline-flex rounded me-2"><i class="ti ti-sun-filled"></i></span>Light
                                    </label>
                                </div>
                                <div class="theme-mode flex-fill text-center w-100 me-3" @click="handleUpdateTheme('data-theme', 'dark')">
                                    <input type="radio" name="theme" id="darkTheme" value="dark" :checked="themeSettings['data-theme'] === 'dark'">
                                    <label for="darkTheme" class="rounded fw-medium w-100">                         
                                        <span class="d-inline-flex rounded me-2"><i class="ti ti-moon-filled"></i></span>Dark
                                    </label>
                                </div>
                                <div class="theme-mode flex-fill w-100 text-center" @click="handleUpdateTheme('data-theme', 'system')">
                                    <input type="radio" name="theme" id="systemTheme" value="system" :checked="themeSettings['data-theme'] === 'system'">
                                    <label for="systemTheme" class="rounded fw-medium w-100">                         
                                        <span class="d-inline-flex rounded me-2"><i class="ti ti-device-laptop"></i></span>System 
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>    	    
            </div> 
        </div>
        <div class="p-3 pt-0">
            <div class="row gx-3">
                <div class="col-6">
                    <a href="#" id="resetbutton" class="btn btn-light close-theme w-100" @click.prevent="handleResetTheme"><i class="ti ti-restore me-1"></i>Reset</a>
                </div>
                <div class="col-6">
                    <a href="https://themeforest.net/item/dreamspos-pos-inventory-management-admin-dashboard-template/38834413" target="_blank" class="btn btn-primary w-100" data-bs-dismiss="offcanvas"><i class="ti ti-shopping-cart-plus me-1"></i>Buy Product</a>
                </div>
            </div>
        </div>    
    </div>
</template>

<script>
export default {
    data() {
        return {
            themeSettings: {
                "data-theme": "light",
            },
        };
    },

    mounted() {
        // Initialize theme attribute on first load
        Object.keys(this.themeSettings).forEach((key) => {
            document.documentElement.setAttribute(key, this.themeSettings[key]);
        });
    },

    methods: {
        handleUpdateTheme(key, value) {
            this.themeSettings[key] = value;
            document.documentElement.setAttribute(key, value);

            if (key === "data-theme" && value === "system") {
                const prefersDark = window.matchMedia?.("(prefers-color-scheme: dark)")?.matches;
                document.documentElement.setAttribute("data-theme", prefersDark ? "dark" : "light");
            }
        },

        handleResetTheme() {
            // Reset to default light theme
            this.themeSettings = {
                "data-theme": "light",
            };

            document.documentElement.setAttribute("data-theme", "light");
            this.$forceUpdate();
        },
    },
};
</script>
