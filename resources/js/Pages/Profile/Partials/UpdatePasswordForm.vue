<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-6 text-white">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-3xl shadow-sm">
                    🔐
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                        Security Settings
                    </p>

                    <h2 class="mt-1 text-2xl font-black text-white">
                        Update Password
                    </h2>

                    <p class="mt-1 text-sm font-semibold text-blue-100">
                        Keep your account secure by using a strong password.
                    </p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                <p class="text-sm font-black text-blue-800">
                    Password Security Reminder
                </p>

                <p class="mt-1 text-sm font-semibold leading-6 text-slate-600">
                    Use a password that is hard to guess. A strong password usually includes uppercase letters, lowercase letters, numbers, and symbols.
                </p>
            </div>

            <form
                class="space-y-6"
                @submit.prevent="updatePassword"
            >
                <div>
                    <InputLabel
                        for="current_password"
                        value="Current Password"
                        class="text-sm font-black text-slate-700"
                    />

                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="mt-2 block w-full rounded-2xl border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        autocomplete="current-password"
                        placeholder="Enter your current password"
                    />

                    <InputError
                        :message="form.errors.current_password"
                        class="mt-2"
                    />
                </div>

                <div>
                    <InputLabel
                        for="password"
                        value="New Password"
                        class="text-sm font-black text-slate-700"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-full rounded-2xl border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        autocomplete="new-password"
                        placeholder="Enter your new password"
                    />

                    <InputError
                        :message="form.errors.password"
                        class="mt-2"
                    />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Confirm Password"
                        class="text-sm font-black text-slate-700"
                    />

                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-2 block w-full rounded-2xl border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        autocomplete="new-password"
                        placeholder="Confirm your new password"
                    />

                    <InputError
                        :message="form.errors.password_confirmation"
                        class="mt-2"
                    />
                </div>

                <div class="flex flex-col gap-4 border-t border-blue-100 pt-6 sm:flex-row sm:items-center">
                    <PrimaryButton
                        :disabled="form.processing"
                        class="rounded-2xl bg-blue-600 px-7 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Password' }}
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
                            Password updated successfully.
                        </p>
                    </Transition>
                </div>
            </form>
        </div>
    </section>
</template>