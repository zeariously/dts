<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();

const user = computed(() => {
    return page.props.auth?.user || {};
});

const userDisplayName = computed(() => {
    return user.value.name
        || user.value.loginname
        || user.value.username
        || 'User';
});

const userEmail = computed(() => {
    return user.value.email || '';
});

const userInitial = computed(() => {
    return String(userDisplayName.value || 'U').slice(0, 1).toUpperCase();
});

const form = useForm({
    name: user.value.name || user.value.loginname || user.value.username || '',
    email: user.value.email || '',
});
</script>

<template>
    <section class="overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-sm">
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
                            Update your account name and email address.
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

                <div class="mt-4 flex flex-col gap-2 rounded-2xl bg-white px-5 py-4 ring-1 ring-blue-100 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                            Current Name
                        </p>

                        <p class="mt-1 text-sm font-black text-slate-900">
                            {{ userDisplayName }}
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="space-y-6"
                @submit.prevent="form.patch(route('profile.update'))"
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

                
                <div
                    v-if="mustVerifyEmail && user.email_verified_at === null"
                    class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4"
                >
                    <p class="text-sm font-semibold leading-6 text-amber-900">
                        Your email address is unverified.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="font-black text-amber-800 underline hover:text-amber-950"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div
                        v-show="status === 'verification-link-sent'"
                        class="mt-3 rounded-xl bg-green-100 px-4 py-3 text-sm font-black text-green-700"
                    >
                        A new verification link has been sent to your email address.
                    </div>
                </div>

                <div class="flex flex-col gap-4 border-t border-blue-100 pt-6 sm:flex-row sm:items-center">
                    <PrimaryButton
                        :disabled="form.processing"
                        class="rounded-2xl bg-blue-600 px-7 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </PrimaryButton>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in-out"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="rounded-full bg-green-50 px-4 py-2 text-sm font-black text-green-700 ring-1 ring-green-200"
                        >
                            Saved successfully.
                        </p>
                    </Transition>
                </div>
            </form>
        </div>
    </section>
</template>