<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { Button } from '@/components/ui/button'

const { t } = useI18n()
</script>

<template>
  <main class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 text-center">
    <h1 class="font-heading text-3xl font-semibold tracking-tight">{{ t('home.title') }}</h1>
    <p class="text-muted-foreground max-w-md">{{ t('home.subtitle') }}</p>
    <Button disabled>{{ t('home.comingSoon') }}</Button>
  </main>
</template>
