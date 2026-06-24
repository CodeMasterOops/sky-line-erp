<template>
    <div class="role-form-skeleton">
        <div class="skeleton skeleton-label mb-2"></div>
        <div class="skeleton skeleton-input mb-4"></div>

        <div class="skeleton skeleton-toolbar mb-3"></div>

        <div v-for="n in 4" :key="n" class="skeleton skeleton-panel mb-2"></div>
    </div>
</template>

<style scoped>
.skeleton {
    border-radius: 0.5rem;
    background: linear-gradient(
        90deg,
        var(--bs-tertiary-bg) 25%,
        var(--bs-secondary-bg) 37%,
        var(--bs-tertiary-bg) 63%
    );
    background-size: 400% 100%;
    animation: skeleton-pulse 1.4s ease infinite;
}

.skeleton-label {
    width: 120px;
    height: 14px;
}

.skeleton-input {
    width: 480px;
    max-width: 100%;
    height: 38px;
}

.skeleton-toolbar {
    height: 80px;
}

.skeleton-panel {
    height: 52px;
}

@keyframes skeleton-pulse {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
}
</style>
