<template>
  <main>
    <div class="container">
      <section
          class="section error-404 min-vh-100 d-flex flex-column align-items-center justify-content-center"
      >
        <h2>Coming Soon</h2>
        <router-link :to="{ name: 'user.dashboard' }" class="btn">Back to home</router-link>
        <img :src="pageImg" class="img-fluid py-5" alt="Page Not Found"/>
      </section>
    </div>
  </main>
</template>

<script setup>
import pageImg from '@/assets/404.svg'
</script>
