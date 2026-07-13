<script setup>
import { Head } from '@inertiajs/vue3'
import DTSLayout from '@/Layouts/DTSLayout.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'

defineOptions({
    layout: DTSLayout,
})

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
})
</script>

<template>
    <Head title="Profile" />

    <div class="min-h-screen bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <UpdateProfileInformationForm
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />

            <div class="mt-6">
                <UpdatePasswordForm />
            </div>
        </div>
    </div>
</template>