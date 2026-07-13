<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
})

const page = usePage()

const showProfileToast = ref(false)
const showErrorToast = ref(false)

const user = computed(() => {
    return page.props.auth?.user || {}
})

const userDisplayName = computed(() => {
    return user.value.name
        || user.value.loginname
        || user.value.username
        || 'User'
})

const usernameDisplay = computed(() => {
    return user.value.loginname
        || user.value.username
        || ''
})

const userInitial = computed(() => {
    return String(userDisplayName.value || 'U').slice(0, 1).toUpperCase()
})

const form = useForm({
    name: user.value.name || user.value.loginname || user.value.username || '',
    loginname: user.value.loginname || user.value.username || '',
})

const saveProfile = () => {
    showProfileToast.value = false
    showErrorToast.value = false

    form.patch(route('profile.update'), {
        preserveScroll: true,

        onSuccess: () => {
            showProfileToast.value = true

            setTimeout(() => {
                showProfileToast.value = false
            }, 4000)
        },

        onError: () => {
            showErrorToast.value = true

            setTimeout(() => {
                showErrorToast.value = false
            }, 4000)
        },
    })
}
</script>

<template>
    <section class="relative overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-sm">
        <!-- SUCCESS TOAST -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-8"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-8"
        >
            <div
                v-if="showProfileToast"
                class="fixed right-6 top-6 z-[99999] w-[370px] max-w-[calc(100vw-3rem)] rounded-2xl border border-green-200 bg-white shadow-2xl"
            >
                <div class="flex gap-4 border-l-8 border-green-500 px-5 py-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-100 text-xl font-black text-green-700">
                        ✓
                    </div>

                    <div>
                        <p class="text-sm font-black text-slate-900">
                            Account updated successfully!
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            Your name and username have been saved.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="ml-auto text-lg font-black text-slate-400 hover:text-slate-700"
                        @click="showProfileToast = false"
                    >
                        ×
                    </button>
                </div>
            </div>
        </Transition>

        <!-- ERROR TOAST -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-8"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-8"
        >
            <div
                v-if="showErrorToast"
                class="fixed right-6 top-6 z-[99999] w-[370px] max-w-[calc(100vw-3rem)] rounded-2xl border border-red-200 bg-white shadow-2xl"
            >
                <div class="flex gap-4 border-l-8 border-red-500 px-5 py-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-xl font-black text-red-700">
                        !
                    </div>

                    <div>
                        <p class="text-sm font-black text-slate-900">
                            Update failed.
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            Please check the fields and try again.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="ml-auto text-lg font-black text-slate-400 hover:text-slate-700"
                        @click="showErrorToast = false"
                    >
                        ×
                    </button>
                </div>
            </div>
        </Transition>

        <div class="border-b border-blue-100 bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-6 text-white">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-2xl font-black text-blue-700 shadow-sm">
                        {{ userInitial }}
                    </div>

                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                            Account Settings
                        </p>

                        <h2 class="mt-1 text-2xl font-black text-white">
                            Profile Information
                        </h2>

                        <p class="mt-1 text-sm font-semibold text-blue-100">
                            Update your account name and username.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm font-bold ring-1 ring-white/20">
                    {{ userDisplayName }}
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                <p class="text-sm font-black text-blue-800">
                    Account Information
                </p>

                <p class="mt-1 text-sm font-semibold leading-6 text-slate-600">
                    Keep your profile information updated so your account details stay accurate in the system.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-2xl bg-white px-5 py-4 ring-1 ring-blue-100">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                            Current Name
                        </p>

                        <p class="mt-1 text-sm font-black text-slate-900">
                            {{ userDisplayName }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white px-5 py-4 ring-1 ring-blue-100">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                            Current Username
                        </p>

                        <p class="mt-1 text-sm font-black text-slate-900">
                            {{ usernameDisplay || 'No username' }}
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="space-y-6"
                @submit.prevent="saveProfile"
            >
                <div>
                    <InputLabel
                        for="name"
                        value="Name"
                        class="text-sm font-black text-slate-700"
                    />

                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-2 block w-full rounded-2xl border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Enter your name"
                    />

                    <InputError
                        class="mt-2"
                        :message="form.errors.name"
                    />
                </div>

                <div>
                    <InputLabel
                        for="loginname"
                        value="Username"
                        class="text-sm font-black text-slate-700"
                    />

                    <TextInput
                        id="loginname"
                        v-model="form.loginname"
                        type="text"
                        class="mt-2 block w-full rounded-2xl border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        required
                        autocomplete="username"
                        placeholder="Enter username"
                    />

                    <InputError
                        class="mt-2"
                        :message="form.errors.loginname"
                    />

                    <p class="mt-2 text-xs font-semibold text-slate-500">
                        This username will be used on your next login.
                    </p>
                </div>

                <div class="flex flex-col gap-4 border-t border-blue-100 pt-6 sm:flex-row sm:items-center">
                    <PrimaryButton
                        type="button"
                        :disabled="form.processing"
                        class="rounded-2xl bg-blue-600 px-7 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                        @click="saveProfile"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </section>
</template>