<script setup lang="ts">
import TwoFactorSetup from '../../Auth/TwoFactorSetup.vue'

type TwoFactorPayload = { configured?: boolean; qr_svg?: string | null; manual_key?: string | null; recovery_codes?: string[] | null }
const props = withDefaults(defineProps<{ twoFactor?: TwoFactorPayload; enabled?: boolean; qrCode?: string | null; manualKey?: string | null; recoveryCodes?: string[] | null }>(), { twoFactor: () => ({}), enabled: false, qrCode: null, manualKey: null, recoveryCodes: null })
</script>
<template><TwoFactorSetup v-bind="props" /></template>
