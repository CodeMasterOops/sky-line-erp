<template>
  <main>
    <div class="container">
      <section class="section error-404 min-vh-100 d-flex flex-column align-items-center justify-content-center">
        <span class="error-badge">Coming Soon</span>
        <h2>We're Working on It</h2>
        <p>This page is under construction. Check back soon.</p>
        <router-link :to="{ name: 'admin.dashboard' }" class="btn btn-back">Back to Home</router-link>
        <div class="error-img">
          <img :src="pageImg" class="img-fluid" alt="Coming Soon" />
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import pageImg from '@/assets/404.svg'
</script>
