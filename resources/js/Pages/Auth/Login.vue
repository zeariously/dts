<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
})

const showPassword = ref(false)
const showForgotPasswordModal = ref(false)
const showCreateAccountModal = ref(false)
const showCreatePassword = ref(false)
const showCreateConfirmPassword = ref(false)
const showSuccessModal = ref(false)
const successMessage = ref('')

const form = useForm({
    loginname: '',
    password: '',
    remember: false,
})

const registerForm = useForm({
    name: '',
    loginname: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}

const openSuccessModal = (message) => {
    successMessage.value = message
    showSuccessModal.value = true
}

const closeSuccessModal = () => {
    showSuccessModal.value = false
    successMessage.value = ''
}

const createAccount = () => {
    const createdLoginName = registerForm.loginname

    registerForm.post(route('register'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateAccountModal()

            form.loginname = createdLoginName
            form.password = ''

            openSuccessModal('Account created successfully. You may now log in using your Login Name and Password.')
        },
        onFinish: () => {
            registerForm.reset('password', 'password_confirmation')
        },
    })
}

const openForgotPasswordModal = () => {
    showForgotPasswordModal.value = true
}

const closeForgotPasswordModal = () => {
    showForgotPasswordModal.value = false
}

const openCreateAccountModal = () => {
    showCreateAccountModal.value = true
}

const closeCreateAccountModal = () => {
    showCreateAccountModal.value = false
    showCreatePassword.value = false
    showCreateConfirmPassword.value = false
    registerForm.reset()
    registerForm.clearErrors()
}
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-blue-600">
        <!-- Background decorations -->
        <div class="absolute -left-24 top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -right-32 bottom-10 h-96 w-96 rounded-full bg-blue-300/20 blur-3xl"></div>
        <div class="absolute left-10 top-10 h-10 w-10 rounded-full bg-white/20"></div>
        <div class="absolute right-14 bottom-20 h-9 w-9 rounded-full bg-white/20"></div>

        <div class="pointer-events-none absolute right-0 top-0 h-[420px] w-[720px] opacity-25">
            <div
                v-for="line in 18"
                :key="line"
                class="absolute right-[-120px] top-[-120px] h-[520px] w-[760px] rounded-[50%] border border-white/80"
                :style="{
                    transform: `translate(${line * 10}px, ${line * 8}px) rotate(${line * 2}deg)`
                }"
            ></div>
        </div>

        <div class="pointer-events-none absolute bottom-0 left-0 h-[360px] w-[720px] opacity-25">
            <div
                v-for="line in 16"
                :key="`bottom-${line}`"
                class="absolute bottom-[-170px] left-[-170px] h-[420px] w-[780px] rounded-[50%] border border-white/80"
                :style="{
                    transform: `translate(${line * -6}px, ${line * -5}px) rotate(${line * 2}deg)`
                }"
            ></div>
        </div>

        <main class="relative z-10 flex min-h-screen items-center justify-center px-5 py-10">
            <div class="w-full max-w-6xl rounded-[2rem] border border-white/60 bg-white/15 p-5 shadow-2xl backdrop-blur-sm">
                <div class="grid min-h-[680px] overflow-hidden rounded-[1.7rem] bg-white shadow-2xl lg:grid-cols-[0.95fr_1.15fr]">
                    <!-- Left panel -->
                    <section class="relative overflow-hidden bg-blue-500 px-8 py-10 text-white sm:px-12 lg:px-14">
                        <div class="absolute -left-24 bottom-[-130px] h-80 w-80 rounded-full bg-blue-300/30"></div>
                        <div class="absolute -right-20 top-16 h-60 w-60 rounded-full bg-white/10"></div>

                        <div class="relative z-10 flex h-full flex-col items-center text-center">
                            <!-- Centered logo -->
                            <div class="flex flex-col items-center justify-center">
                                <div class="flex h-52 w-52 items-center justify-center overflow-hidden rounded-[2rem] bg-white p-1 shadow-2xl ring-8 ring-white/20">
                                <img
                                    src="/images/logo_dts-nobg.png"
                                    alt="DTS Logo"
                                    class="h-full w-full scale-[1.3] object-contain"
                                />
                            </div>
                            </div>

                            <div class="mx-auto mt-14 max-w-md text-center">
                                <h1 class="text-2xl font-black leading-tight tracking-wide sm:text-3xl">
                                    Manage your documents the best way
                                </h1>
                                
                                <p>Track, receive, route, and monitor official documents in one secure workspace.</p>

                             
                            </div>

                            <div class="relative mt-auto hidden h-72 w-full lg:block">
                                <div class="absolute bottom-5 left-12 h-32 w-56 rotate-[-12deg] rounded-3xl bg-blue-700 shadow-2xl"></div>

                                <div class="dts-doc-float-one absolute bottom-24 left-16 h-24 w-32 rotate-[-10deg] rounded-2xl bg-white shadow-xl">
                                    <div class="mx-auto mt-5 h-9 w-20 rounded-lg bg-cyan-300"></div>
                                    <div class="mx-auto mt-3 h-2 w-20 rounded bg-blue-200"></div>
                                    <div class="mx-auto mt-2 h-2 w-14 rounded bg-blue-200"></div>
                                </div>

                                <div class="dts-doc-float-two absolute bottom-32 left-44 h-24 w-32 rotate-[22deg] rounded-2xl bg-white shadow-xl">
                                    <div class="mx-auto mt-5 h-9 w-12 rounded-lg bg-orange-300"></div>
                                    <div class="mx-auto mt-3 h-2 w-20 rounded bg-blue-200"></div>
                                    <div class="mx-auto mt-2 h-2 w-16 rounded bg-blue-200"></div>
                                </div>

                                <!-- Main blue folder -->
                                <div class="dts-folder absolute bottom-2 left-6 h-32 w-72 rounded-[2rem] bg-gradient-to-br from-cyan-400 to-blue-700 shadow-2xl">
                                    <div class="absolute -top-5 left-8 h-12 w-28 rounded-t-2xl bg-cyan-300"></div>

                                    <!-- Animated route line -->
                                    <div class="absolute left-10 top-16 h-2 w-48 overflow-hidden rounded-full bg-white/20">
                                        <div class="dts-route-line h-full w-20 rounded-full bg-white"></div>
                                    </div>

                                    <!-- Small moving dots -->
                                    <div class="dts-dot dts-dot-one absolute left-14 top-11 h-3 w-3 rounded-full bg-white"></div>
                                    <div class="dts-dot dts-dot-two absolute left-32 top-11 h-3 w-3 rounded-full bg-white"></div>
                                    <div class="dts-dot dts-dot-three absolute left-52 top-11 h-3 w-3 rounded-full bg-white"></div>
                                </div>

                                <!-- Magnifying glass / tracking icon -->
                                <div class="dts-magnify absolute bottom-1 left-40 h-28 w-28 rounded-full border-[18px] border-blue-300 shadow-xl"></div>
                                <div class="dts-magnify-handle absolute bottom-[-20px] left-[250px] h-20 w-8 rotate-[-38deg] rounded-full bg-yellow-300 shadow-lg"></div>

                                <!-- Success check badge -->
                                <div class="dts-check absolute bottom-48 left-[285px] flex h-12 w-12 items-center justify-center rounded-full bg-white text-2xl font-black text-blue-600 shadow-xl">
                                    ✓
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Login panel -->
                    <section class="flex items-center justify-center bg-white px-7 py-12 sm:px-12">
                        <div class="w-full max-w-md">
                            <div class="text-center">
                                <h2 class="text-4xl font-black tracking-tight text-slate-800">
                                    Login
                                </h2>

                                <p class="mt-3 text-sm font-medium text-slate-500">
                                    Welcome back. Please sign in to continue.
                                </p>
                            </div>

                            <div
                                v-if="props.status"
                                class="mt-8 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-bold text-green-700"
                            >
                                {{ props.status }}
                            </div>

                            <form class="mt-9 space-y-6" @submit.prevent="submit">
                                <div>
                                    <label
                                        for="loginname"
                                        class="mb-2 block text-sm font-bold text-slate-700"
                                    >
                                        Username
                                    </label>

                                    <input
                                        id="loginname"
                                        v-model="form.loginname"
                                        type="text"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="Enter your  username"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                                    />

                                    <p
                                        v-if="form.errors.loginname"
                                        class="mt-2 text-sm font-bold text-red-600"
                                    >
                                        {{ form.errors.loginname }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="password"
                                        class="mb-2 block text-sm font-bold text-slate-700"
                                    >
                                        Password
                                    </label>

                                    <div class="relative">
                                        <input
                                            id="password"
                                            v-model="form.password"
                                            :type="showPassword ? 'text' : 'password'"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Enter your password"
                                            class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 pr-14 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                                        />

                                        <button
                                            type="button"
                                            class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-black text-blue-600"
                                            @click="showPassword = !showPassword"
                                        >
                                            {{ showPassword ? 'Hide' : 'Show' }}
                                        </button>
                                    </div>

                                    <p
                                        v-if="form.errors.password"
                                        class="mt-2 text-sm font-bold text-red-600"
                                    >
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="flex items-center gap-3 text-sm font-semibold text-slate-600">
                                        <input
                                            v-model="form.remember"
                                            type="checkbox"
                                            class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />

                                        <span>Remember me</span>
                                    </label>

                                    <Link
                                        v-if="props.canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm font-bold text-blue-600 hover:text-blue-700"
                                    >
                                        Forgot password?
                                    </Link>

                                    <button
                                        v-else
                                        type="button"
                                        class="text-sm font-bold text-blue-600 hover:text-blue-700"
                                        @click="openForgotPasswordModal"
                                    >
                                        Forgot password?
                                    </button>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-blue-600 px-6 py-4 text-sm font-black text-white shadow-xl shadow-blue-200 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="form.processing"
                                >
                                    {{ form.processing ? 'Logging in...' : 'Login' }}
                                </button>
                            </form>

                            <div class="mt-6 text-center">
                                <p class="text-sm font-semibold text-slate-500">
                                    Don't have an account?
                                    <button
                                        type="button"
                                        class="font-black text-blue-600 hover:text-blue-700"
                                        @click="openCreateAccountModal"
                                    >
                                        Create Account
                                    </button>
                                </p>
                            </div>

                            <div class="mt-9 flex items-center gap-4">
                                <div class="h-px flex-1 bg-slate-200"></div>
                                <span class="text-sm font-semibold text-slate-400">
                                    Document Tracking System
                                </span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>  

                            <p class="mt-6 text-center text-xs font-semibold text-slate-400">
                                © 2026 DOST. All Rights Reserved.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Account Modal -->
    <div
        v-if="showCreateAccountModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4"
    >
        <div class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="bg-blue-600 px-7 py-6 text-white">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white p-2 shadow-lg">
                        <img
                            src="images/logo_dts-nobg.png"
                            alt="DOST Logo"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div>
                        <h3 class="text-xl font-black">
                            Create Account
                        </h3>

                        <p class="mt-1 text-sm font-semibold text-blue-100">
                            Register your DTS account
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-7 py-6">
                <form class="space-y-5" @submit.prevent="createAccount">
                    <div>
                        <label
                            for="register-name"
                            class="mb-2 block text-sm font-bold text-slate-700"
                        >
                            Name
                        </label>

                        <input
                            id="register-name"
                            v-model="registerForm.name"
                            type="text"
                            required
                            autocomplete="name"
                            placeholder="Enter your full name"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />

                        <p
                            v-if="registerForm.errors.name"
                            class="mt-2 text-sm font-bold text-red-600"
                        >
                            {{ registerForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="register-loginname"
                            class="mb-2 block text-sm font-bold text-slate-700"
                        >
                            User Name
                        </label>

                        <input
                            id="register-loginname"
                            v-model="registerForm.loginname"
                            type="text"
                            required
                            autocomplete="username"
                            placeholder="Create your username"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />

                        <p
                            v-if="registerForm.errors.loginname"
                            class="mt-2 text-sm font-bold text-red-600"
                        >
                            {{ registerForm.errors.loginname }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="register-password"
                            class="mb-2 block text-sm font-bold text-slate-700"
                        >
                            Password
                        </label>

                        <div class="relative">
                            <input
                                id="register-password"
                                v-model="registerForm.password"
                                :type="showCreatePassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Create password"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 pr-14 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            />

                            <button
                                type="button"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-black text-blue-600"
                                @click="showCreatePassword = !showCreatePassword"
                            >
                                {{ showCreatePassword ? 'Hide' : 'Show' }}
                            </button>
                        </div>

                        <p
                            v-if="registerForm.errors.password"
                            class="mt-2 text-sm font-bold text-red-600"
                        >
                            {{ registerForm.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="register-password-confirmation"
                            class="mb-2 block text-sm font-bold text-slate-700"
                        >
                            Confirm Password
                        </label>

                        <div class="relative">
                            <input
                                id="register-password-confirmation"
                                v-model="registerForm.password_confirmation"
                                :type="showCreateConfirmPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm password"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 pr-14 text-sm font-bold text-slate-800 shadow-lg shadow-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                            />

                            <button
                                type="button"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-black text-blue-600"
                                @click="showCreateConfirmPassword = !showCreateConfirmPassword"
                            >
                                {{ showCreateConfirmPassword ? 'Hide' : 'Show' }}
                            </button>
                        </div>

                        <p
                            v-if="registerForm.errors.password_confirmation"
                            class="mt-2 text-sm font-bold text-red-600"
                        >
                            {{ registerForm.errors.password_confirmation }}
                        </p>
                    </div>

                    <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                            @click="closeCreateAccountModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="rounded-2xl bg-blue-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="registerForm.processing"
                        >
                            {{ registerForm.processing ? 'Creating...' : 'Create Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div
        v-if="showSuccessModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4"
    >
        <div class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="bg-green-600 px-7 py-6 text-white">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-3xl font-black text-green-600 shadow-lg">
                        ✓
                    </div>

                    <div>
                        <h3 class="text-xl font-black">
                            Account Created
                        </h3>

                        <p class="mt-1 text-sm font-semibold text-green-100">
                            Registration successful
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-7 py-6">
                <div class="rounded-2xl border border-green-100 bg-green-50 px-5 py-4">
                    <p class="text-sm font-bold leading-6 text-slate-800">
                        {{ successMessage || 'Account created successfully. You may now log in.' }}
                    </p>

                    <p class="mt-3 text-sm font-medium leading-6 text-slate-600">
                        Please use your Login Name and Password to access the system.
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-2xl bg-green-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-green-100 transition hover:bg-green-700"
                        @click="closeSuccessModal"
                    >
                        Login now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div
        v-if="showForgotPasswordModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4"
    >
        <div class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="bg-blue-600 px-7 py-6 text-white">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white p-2 shadow-lg">
                        <img
                            src="/images/dost-logo.png"
                            alt="DOST Logo"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div>
                        <h3 class="text-xl font-black">
                            Forgot Password
                        </h3>

                        <p class="mt-1 text-sm font-semibold text-blue-100">
                            Password reset assistance
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-7 py-6">
                <div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                    <p class="text-sm font-bold leading-6 text-slate-800">
                        Password reset is not yet enabled.
                    </p>

                    <p class="mt-3 text-sm font-medium leading-6 text-slate-600">
                        Please contact the system administrator to reset your password.
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-2xl bg-blue-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700"
                        @click="closeForgotPasswordModal"
                    >
                        Okay, got it
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dts-doc-float-one {
    animation: dtsDocFloatOne 2.4s ease-in-out infinite;
}

.dts-doc-float-two {
    animation: dtsDocFloatTwo 2s ease-in-out infinite;
}

.dts-folder {
    animation: dtsFolderPulse 2.5s ease-in-out infinite;
}

.dts-route-line {
    animation: dtsRouteMove 1.4s ease-in-out infinite;
}

.dts-dot {
    animation: dtsDotBlink 1.2s ease-in-out infinite;
}

.dts-dot-two {
    animation-delay: 0.2s;
}

.dts-dot-three {
    animation-delay: 0.4s;
}

.dts-magnify {
    animation: dtsMagnifyMove 2.2s ease-in-out infinite;
}

.dts-magnify-handle {
    animation: dtsMagnifyHandleMove 2.2s ease-in-out infinite;
}

.dts-check {
    animation: dtsCheckPop 1.6s ease-in-out infinite;
}

@keyframes dtsDocFloatOne {
    0%,
    100% {
        transform: translateY(0) rotate(-10deg);
    }

    50% {
        transform: translateY(-14px) rotate(-4deg);
    }
}

@keyframes dtsDocFloatTwo {
    0%,
    100% {
        transform: translateY(0) rotate(22deg);
    }

    50% {
        transform: translateY(-18px) rotate(12deg);
    }
}

@keyframes dtsFolderPulse {
    0%,
    100% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.03);
    }
}

@keyframes dtsRouteMove {
    0% {
        transform: translateX(-90px);
        opacity: 0.4;
    }

    50% {
        opacity: 1;
    }

    100% {
        transform: translateX(210px);
        opacity: 0.4;
    }
}

@keyframes dtsDotBlink {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.85);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

@keyframes dtsMagnifyMove {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
    }

    50% {
        transform: translate(8px, -8px) rotate(4deg);
    }
}

@keyframes dtsMagnifyHandleMove {
    0%,
    100% {
        transform: rotate(-38deg) translate(0, 0);
    }

    50% {
        transform: rotate(-34deg) translate(5px, -4px);
    }
}

@keyframes dtsCheckPop {
    0%,
    100% {
        opacity: 0.75;
        transform: scale(0.9);
    }

    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}
</style>