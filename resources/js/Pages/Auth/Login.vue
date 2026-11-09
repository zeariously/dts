<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({ canResetPassword: { type: Boolean, default: false }, status: { type: String, default: '' } })
const showPassword = ref(false), showCreatePassword = ref(false), showCreateConfirmPassword = ref(false)
const showForgotPasswordModal = ref(false), showCreateAccountModal = ref(false), showSuccessModal = ref(false), showUserGuideModal = ref(false)
const successMessage = ref(''), selectedUserGuide = ref('')
const form = useForm({ loginname: '', password: '', remember: false })
const registerForm = useForm({ name: '', loginname: '', password: '', password_confirmation: '' })
const userGuideOptions = [
    { key: 'project-manager', label: 'Project Manager', description: 'Guide for project managers who will track, receive, and monitor documents.', file: '/user-guides/project-manager-user-guide.pdf' },
    { key: 'admin-staff', label: 'Admin Staff', description: 'Guide for admin staff who will encode, route, receive, and manage DTS documents.', file: '/user-guides/admin-staff-user-guide.pdf' },
]
const selectedUserGuideDetails = computed(() => userGuideOptions.find(guide => guide.key === selectedUserGuide.value) || null)
const snowflakes = Array.from({ length: 74 }, (_, i) => ({
    id: i, left: `${(i * 73.79 + 9) % 100}%`, delay: `${-((i * 3.17) % 23)}s`, duration: `${11 + (i * 7 % 18)}s`,
    size: `${2 + (i * 11 % 6)}px`, opacity: 0.28 + (i * 3 % 7) / 10, drift: `${(i % 2 ? 1 : -1) * (16 + (i * 9 % 65))}px`,
}))
const stars = Array.from({ length: 35 }, (_, i) => ({ id: i, left: `${(i * 57.3 + 12) % 100}%`, top: `${(i * 37.11 + 4) % 62}%`, delay: `${(i % 9) * -.5}s` }))
const countdownClock = ref(Date.now())
let countdownTimer = null
const christmasCountdown = computed(() => {
    const manilaYear = Number(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', year: 'numeric' }).format(new Date(countdownClock.value)))
    let target = Date.parse(`${manilaYear}-12-25T00:00:00+08:00`)
    if (countdownClock.value >= target) target = Date.parse(`${manilaYear + 1}-12-25T00:00:00+08:00`)
    const seconds = Math.max(0, Math.floor((target - countdownClock.value) / 1000))
    return [
        { label: 'DAYS', value: String(Math.floor(seconds / 86400)).padStart(2, '0') },
        { label: 'HOURS', value: String(Math.floor(seconds / 3600) % 24).padStart(2, '0') },
        { label: 'MINS', value: String(Math.floor(seconds / 60) % 60).padStart(2, '0') },
        { label: 'SECS', value: String(seconds % 60).padStart(2, '0') },
    ]
})
onMounted(() => { countdownClock.value = Date.now(); countdownTimer = window.setInterval(() => { countdownClock.value = Date.now() }, 1000) })
onBeforeUnmount(() => { if (countdownTimer !== null) window.clearInterval(countdownTimer) })
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') })
const openCreateAccountModal = () => { showCreateAccountModal.value = true }
const closeCreateAccountModal = () => { showCreateAccountModal.value = false; showCreatePassword.value = false; showCreateConfirmPassword.value = false; registerForm.reset(); registerForm.clearErrors() }
const createAccount = () => {
    const loginName = registerForm.loginname
    registerForm.post(route('register'), {
        preserveScroll: true,
        onSuccess: () => { closeCreateAccountModal(); form.loginname = loginName; form.password = ''; successMessage.value = 'Your registration has been submitted. Please wait for the system administrator to confirm your account before signing in.'; showSuccessModal.value = true },
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    })
}
const downloadSelectedUserGuide = () => {
    const guide = selectedUserGuideDetails.value
    if (!guide) return
    const anchor = document.createElement('a'); anchor.href = guide.file; anchor.download = guide.file.split('/').pop() || 'user-guide.pdf'
    document.body.appendChild(anchor); anchor.click(); anchor.remove()
}
</script>

<template>
    <Head title="DTS | Winter Wonderland" />
    <div class="winter-page relative isolate min-h-screen overflow-hidden text-white">
        <div class="aurora aurora-one"></div><div class="aurora aurora-two"></div>
        <div class="star-field pointer-events-none absolute inset-0" aria-hidden="true">
            <span v-for="star in stars" :key="star.id" class="star" :style="{ left: star.left, top: star.top, animationDelay: star.delay }"></span>
        </div>
        <div class="snow-field pointer-events-none fixed inset-0 z-40 overflow-hidden" aria-hidden="true">
            <span v-for="flake in snowflakes" :key="flake.id" class="snowflake" :style="{ left: flake.left, width: flake.size, height: flake.size, opacity: flake.opacity, animationDuration: flake.duration, animationDelay: flake.delay, '--snow-drift': flake.drift }"></span>
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-[27%] bg-gradient-to-t from-[#ecf8fb]/15 to-transparent"></div>
        <header class="relative z-20 mx-auto flex w-full max-w-[1440px] items-center justify-between gap-4 px-5 py-5 sm:px-10 lg:px-16">
            <div class="flex items-center gap-3">
                <img src="/images/logo_dts-nobg.png" alt="DTS Logo" class="h-11 w-11 rounded-xl bg-white/95 object-contain p-1 shadow-lg sm:h-12 sm:w-12" />
                <div><div class="text-sm font-black tracking-[.2em] text-[#f9e9bb]">DTS <span class="font-normal text-white/80">· DOST</span></div><div class="text-[10px] font-semibold tracking-[.13em] text-white/60 sm:text-xs">DOCUMENT TRACKING SYSTEM</div></div>
            </div>
            <div class="hidden items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-semibold tracking-widest text-white/85 backdrop-blur-xl sm:flex"><span class="text-base">❄</span> A LITTLE CHRISTMAS MAGIC <span class="text-base">✦</span></div>
        </header>
        <main class="relative z-10 mx-auto grid min-h-[calc(100vh-104px)] w-full max-w-[1440px] items-center gap-10 px-5 pb-12 pt-2 sm:px-10 lg:grid-cols-[minmax(0,1.25fr)_minmax(380px,.75fr)] lg:gap-8 lg:px-16 lg:pb-20">
            <section class="relative flex min-h-[460px] flex-col justify-start lg:min-h-[710px] lg:justify-start" aria-label="Christmas welcome">
                <div class="relative z-10 pt-1 lg:-mt-5 lg:pt-0">
                    <div class="christmas-countdown mb-8 w-full max-w-[560px] rounded-[26px] border border-[#f7e6bc]/35 bg-[#12294d]/70 px-5 py-5 shadow-[0_14px_35px_#061b2840] backdrop-blur-xl sm:px-7 sm:py-6" aria-label="Live countdown until Christmas in Philippine time">
                        <div class="mb-5 flex items-center justify-between gap-2"><span class="text-xs font-black uppercase tracking-[.19em] text-[#ffe0a1] sm:text-sm">🎄 Christmas Countdown</span><span class="text-xs font-semibold text-white/65">DEC 25 · PHT</span></div>
                        <div class="grid grid-cols-4 gap-3"><div v-for="unit in christmasCountdown" :key="unit.label" class="rounded-2xl border border-white/15 bg-white/10 px-1 py-4 text-center sm:py-5"><div class="text-2xl font-black tabular-nums text-white sm:text-4xl">{{ unit.value }}</div><div class="mt-2 text-[10px] font-bold tracking-[.12em] text-[#d9e7ff] sm:text-xs">{{ unit.label }}</div></div></div>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-[#f4deb6]/35 bg-[#eddaac]/10 px-4 py-2 text-[10px] font-black uppercase tracking-[.24em] text-[#f9e7bd] backdrop-blur-lg sm:text-xs">✦ Season's greetings · 2026</span>
                    <h1 class="winter-heading mt-6 max-w-[690px] text-[clamp(1.75rem,3.4vw,3.65rem)] leading-[1.1] tracking-[-.035em]"><span class="block whitespace-nowrap">Manage your documents</span><span class="block text-[#ffe0a1]">the best way</span></h1>
                    <p class="mt-4 max-w-[490px] text-sm leading-6 text-[#e8f0fb]/85 sm:text-base">Track, receive, route, and monitor official documents in one secure workspace.</p>
                </div>
                <!-- Santa and reindeer replace the Christmas house completely. -->
                <div class="santa-flight-stage pointer-events-none relative mt-10 h-[240px] w-full sm:mt-12 sm:h-[300px] lg:absolute lg:bottom-0 lg:left-0 lg:mt-0 lg:h-[355px]" aria-hidden="true">
                    <div class="santa-flight-glow"></div>
                    <div class="santa-flight-track absolute inset-0 overflow-hidden">
                        <div class="santa-house-flight absolute left-0 top-[42%] w-[88%] max-w-[780px] sm:top-[36%] lg:top-[32%]">
                            <img src="/images/Santa_Reindeer_Galloping.gif" alt="" draggable="false" class="block h-auto w-full select-none" />
                        </div>
                    </div>
                </div>
            </section>
            <section class="relative z-20 mx-auto w-full max-w-[485px] lg:ml-auto" aria-label="Login form">
                <div class="login-glass relative overflow-hidden rounded-[34px] border border-white/40 px-6 py-8 shadow-[0_35px_100px_rgba(0,0,0,.3)] sm:px-10 sm:py-10">
                    <div class="pointer-events-none absolute -right-12 -top-20 h-52 w-52 rounded-full bg-[#f8e5bb]/25 blur-3xl"></div>
                    <div class="relative text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-[#7fa9e0] bg-gradient-to-br from-[#285da7] to-[#153461] shadow-[0_12px_25px_rgba(20,52,97,.32)]" aria-hidden="true">
                            <svg class="h-9 w-9 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M4.93 4.93l14.14 14.14M2 12h20M4.93 19.07 19.07 4.93"/>
                                <path d="m9 5 3 3 3-3M9 19l3-3 3 3M5 9l3 3-3 3M19 9l-3 3 3 3M5.9 8.1 8.8 8.8 8.1 5.9M15.2 15.2l2.9.7-.7-2.9M8.8 15.2l-2.9.7.7-2.9M15.2 8.8l2.9-.7-.7 2.9"/>
                            </svg>
                        </div>
                        <div class="mt-5 text-[11px] font-black uppercase tracking-[.26em] text-[#ae8544]">Welcome back</div>
                        <h2 class="winter-heading mt-2 text-4xl font-bold text-[#203759] sm:text-[43px]">Login</h2>
                        <p class="mt-2 text-sm leading-6 text-[#667a93]">Welcome back. Please sign in to continue.</p>
                    </div>
                    <div v-if="props.status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-800">{{ props.status }}</div>
                    <form class="relative mt-8 space-y-5" @submit.prevent="submit">
                        <div><label for="loginname" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#3b536d]">Username</label><input id="loginname" v-model="form.loginname" type="text" required autofocus autocomplete="username" placeholder="Enter your username" class="winter-input"/><p v-if="form.errors.loginname" class="mt-2 text-sm text-red-700">{{ form.errors.loginname }}</p></div>
                        <div><label for="password" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#3b536d]">Password</label><div class="relative"><input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="Enter your password" class="winter-input pr-16"/><button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-[#355e93]" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button></div><p v-if="form.errors.password" class="mt-2 text-sm text-red-700">{{ form.errors.password }}</p></div>
                        <div class="flex flex-wrap items-center justify-between gap-3 text-xs sm:text-sm"><label class="flex cursor-pointer items-center gap-2 font-medium text-[#657a91]"><input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[#aabbd0] text-[#3669a4] focus:ring-[#3669a4]"/>Remember me</label><Link v-if="props.canResetPassword" :href="route('password.request')" class="font-bold text-[#285d9a] hover:underline">Forgot password?</Link><button v-else type="button" class="font-bold text-[#285d9a] hover:underline" @click="showForgotPasswordModal = true">Forgot password?</button></div>
                        <button type="submit" :disabled="form.processing" class="winter-button w-full rounded-2xl px-5 py-4 text-sm font-black tracking-wide text-white disabled:opacity-60">{{ form.processing ? 'Logging in...' : 'Login' }}</button>
                    </form>
                    <p class="relative mt-6 text-center text-sm text-[#60758c]">Don&#39;t have an account? <button type="button" class="font-extrabold text-[#ae4b55] hover:underline" @click="openCreateAccountModal">Create Account</button></p>
                    <div class="relative mt-7 flex items-center gap-3"><span class="h-px flex-1 bg-[#bccddd]/65"></span><span class="text-[10px] font-bold uppercase tracking-[.10em] text-[#8495a8]">Document Tracking System</span><span class="h-px flex-1 bg-[#bccddd]/65"></span></div>
                    <button type="button" class="relative mt-5 flex w-full items-center justify-between rounded-2xl border border-[#c4d5e7] bg-white/60 p-4 text-left transition hover:bg-white" @click="showUserGuideModal = true"><span class="flex items-center gap-3"><span class="text-2xl">📖</span><span><span class="block text-sm font-black text-[#294969]">Need help using DTS?</span><span class="text-xs text-[#74889f]">Select your account type and download the correct user guide.</span></span></span><span class="text-[#3868a1]">  ↗</span></button>
                </div>
                <p class="mt-6 text-center text-[11px] font-medium tracking-wide text-white/65">© 2026 DOST. All Rights Reserved.</p>
            </section>
        </main>
        <div class="pointer-events-none absolute bottom-0 left-0 right-0 z-10 h-4 bg-gradient-to-t from-white/70 to-transparent"></div>
    </div>

    <!-- User Guide Modal -->
    <div v-if="showUserGuideModal" class="modal-overlay" @click.self="showUserGuideModal = false"><div class="winter-modal max-w-lg"><div class="modal-head"><span class="text-3xl">📖</span><div><h3 class="text-xl font-black">DTS User Guides</h3><p class="text-xs text-white/80">Choose your account type to download the correct guide.</p></div></div><div class="space-y-4 p-6"><div><label for="guide-role" class="modal-label">Select User Account Type</label><select id="guide-role" v-model="selectedUserGuide" class="winter-input"><option value="">Select user guide</option><option v-for="guide in userGuideOptions" :key="guide.key" :value="guide.key">{{ guide.label }}</option></select></div><div class="rounded-xl bg-[#eef5fc] p-4 text-sm text-[#4a6685]"><template v-if="selectedUserGuideDetails"><p class="font-black">{{ selectedUserGuideDetails.label }}</p><p class="mt-2">{{ selectedUserGuideDetails.description }}</p><p class="mt-3 break-words text-xs font-bold text-[#5c7392]">File: {{ selectedUserGuideDetails.file }}</p></template><template v-else>Please choose a user account type first.</template></div><div class="flex justify-end gap-3"><button class="modal-cancel" type="button" @click="showUserGuideModal = false">Cancel</button><button class="modal-action" type="button" :disabled="!selectedUserGuide" @click="downloadSelectedUserGuide">Download Guide</button></div></div></div></div>

    <!-- Create Account Modal -->
    <div v-if="showCreateAccountModal" class="modal-overlay" @click.self="closeCreateAccountModal"><div class="winter-modal max-w-md"><div class="modal-head"><span class="text-3xl">🎄</span><div><h3 class="text-xl font-black">Create Account</h3><p class="text-xs text-white/80">Register your DTS account</p></div></div><form class="max-h-[75vh] space-y-4 overflow-y-auto p-6" @submit.prevent="createAccount"><div><label for="reg-name" class="modal-label">Name</label><input id="reg-name" v-model="registerForm.name" type="text" required autocomplete="name" placeholder="Enter your full name" class="winter-input"/><p v-if="registerForm.errors.name" class="modal-error">{{ registerForm.errors.name }}</p></div><div><label for="reg-loginname" class="modal-label">User Name</label><input id="reg-loginname" v-model="registerForm.loginname" type="text" required autocomplete="username" placeholder="Create your username" class="winter-input"/><p v-if="registerForm.errors.loginname" class="modal-error">{{ registerForm.errors.loginname }}</p></div><div><label for="reg-password" class="modal-label">Password</label><div class="relative"><input id="reg-password" v-model="registerForm.password" :type="showCreatePassword ? 'text' : 'password'" required autocomplete="new-password" placeholder="Create password" class="winter-input pr-16"/><button type="button" class="show-toggle" @click="showCreatePassword = !showCreatePassword">{{ showCreatePassword ? 'Hide' : 'Show' }}</button></div><p v-if="registerForm.errors.password" class="modal-error">{{ registerForm.errors.password }}</p></div><div><label for="reg-confirm" class="modal-label">Confirm password</label><div class="relative"><input id="reg-confirm" v-model="registerForm.password_confirmation" :type="showCreateConfirmPassword ? 'text' : 'password'" required autocomplete="new-password" placeholder="Confirm password" class="winter-input pr-16"/><button type="button" class="show-toggle" @click="showCreateConfirmPassword = !showCreateConfirmPassword">{{ showCreateConfirmPassword ? 'Hide' : 'Show' }}</button></div><p v-if="registerForm.errors.password_confirmation" class="modal-error">{{ registerForm.errors.password_confirmation }}</p></div><p class="rounded-xl bg-[#f1f6fd] p-3 text-xs leading-5 text-[#4c6685]">Your account may require administrator approval before you can log in.</p><div class="flex justify-end gap-3 pt-1"><button type="button" class="modal-cancel" @click="closeCreateAccountModal">Cancel</button><button type="submit" :disabled="registerForm.processing" class="modal-action">{{ registerForm.processing ? 'Creating...' : 'Create Account' }}</button></div></form></div></div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click.self="showSuccessModal = false"><div class="winter-modal max-w-md"><div class="modal-head"><span class="text-3xl">✓</span><div><h3 class="text-xl font-black">Account Created</h3><p class="text-xs text-white/80">Registration successful</p></div></div><div class="space-y-5 p-6"><p class="rounded-xl bg-[#f1f6fd] p-4 text-sm leading-6 text-[#3e5a79]">{{ successMessage }}</p><p class="text-xs font-medium leading-5 text-[#4c6685]">Please use your Login Name and Password to access the system after account approval.</p><div class="flex justify-end"><button class="modal-action" type="button" @click="showSuccessModal = false; successMessage = ''">Okay, got it</button></div></div></div></div>

    <!-- Forgot Password Modal -->
    <div v-if="showForgotPasswordModal" class="modal-overlay" @click.self="showForgotPasswordModal = false"><div class="winter-modal max-w-md"><div class="modal-head"><span class="text-3xl">🔒</span><div><h3 class="text-xl font-black">Forgot Password</h3><p class="text-xs text-white/80">Password reset assistance</p></div></div><div class="space-y-5 p-6"><p class="rounded-xl bg-[#f1f6fd] p-4 text-sm leading-6 text-[#3e5a79]">Password reset is not yet enabled. Please contact the system administrator to reset your password.</p><div class="flex justify-end"><button type="button" class="modal-action" @click="showForgotPasswordModal = false">Okay, got it</button></div></div></div></div>
</template>

<style scoped>
.winter-page{background:radial-gradient(ellipse at 67% 24%,#385a8d 0%,transparent 38%),radial-gradient(ellipse at 15% 50%,#1c3c67 0%,transparent 56%),linear-gradient(145deg,#071528 0%,#112f57 48%,#071426 100%);font-family:Inter,ui-sans-serif,system-ui,sans-serif}
.winter-heading{font-family:Georgia,'Times New Roman',serif;font-weight:700;text-shadow:0 5px 30px rgba(0,0,0,.16)}
.aurora{pointer-events:none;position:absolute;z-index:0;width:900px;height:350px;filter:blur(85px);border-radius:50%;opacity:.22;transform:rotate(-22deg);animation:auroraFlow 18s ease-in-out infinite alternate}.aurora-one{top:8%;left:5%;background:#6f9cc9}.aurora-two{top:30%;right:-30%;background:#a3c3ef;animation-delay:-8s}
.star{position:absolute;width:2px;height:2px;border-radius:100%;background:#fff1c6;box-shadow:0 0 13px 3px #f8e9c55c;animation:twinkle 3.5s ease-in-out infinite alternate}
.snowflake{position:absolute;top:-15px;display:block;z-index:40;pointer-events:none;border-radius:50%;background:radial-gradient(circle,#fff 12%,#fff9 62%,#fff3 75%);box-shadow:0 0 7px #fff7;animation:snowFall linear infinite;will-change:transform}

.login-glass{background:linear-gradient(140deg,rgba(255,255,255,.96),rgba(234,243,255,.91) 52%,rgba(250,253,255,.96));box-shadow:inset 0 1px 2px #fff,0 40px 100px #001c2570;backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px)}.login-glass::before{content:'✧';position:absolute;top:22px;left:24px;color:#c8ab70;font-size:25px;opacity:.65}.login-glass::after{content:'✧';position:absolute;top:44px;right:28px;color:#b49a68;font-size:18px;opacity:.7}
.winter-input{display:block;width:100%;border:1px solid #b5c9df;border-radius:14px;background:#ffffffc9;padding:14px 16px;color:#25425f;outline:0;font-size:14px;transition:border-color .2s,box-shadow .2s}.winter-input::placeholder{color:#91a2b5}.winter-input:focus{border-color:#497db3;box-shadow:0 0 0 4px #497db322}
.winter-button{background:linear-gradient(110deg,#234e88,#3c78af 48%,#173f77);box-shadow:0 15px 24px #17457b55,inset 0 1px 0 #fff4;transition:transform .2s,filter .2s}.winter-button:hover{transform:translateY(-2px);filter:brightness(1.07)}
.modal-overlay{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;overflow-y:auto;background:#071a23b8;padding:18px;backdrop-filter:blur(9px)}.winter-modal{width:100%;overflow:hidden;border:1px solid #ffffffab;border-radius:24px;background:#fff;box-shadow:0 30px 90px #0008}.modal-head{display:flex;align-items:center;gap:14px;background:linear-gradient(120deg,#233f76,#366ba4);padding:22px 24px;color:#fff}.modal-label{display:block;margin-bottom:7px;font-size:12px;font-weight:800;letter-spacing:.03em;color:#365373}.modal-error{margin-top:5px;color:#b91c1c;font-size:12px}.modal-cancel,.modal-action{border-radius:12px;padding:11px 17px;font-size:13px;font-weight:800;transition:filter .2s}.modal-cancel{border:1px solid #d3deea;background:#fff;color:#536b86}.modal-action{background:#276a5d;color:#fff}.modal-action:hover{filter:brightness(1.1)}.modal-action:disabled{opacity:.5;cursor:not-allowed}.show-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:800;color:#35699f}
@keyframes snowFall{0%{transform:translate3d(0,-20px,0)}100%{transform:translate3d(var(--snow-drift),110vh,0)}}@keyframes twinkle{0%{opacity:.2;transform:scale(.8)}100%{opacity:1;transform:scale(1.8)}}@keyframes auroraFlow{0%{transform:translate(0,0) rotate(-22deg)}100%{transform:translate(80px,45px) rotate(-14deg)}}
@media(min-width:1024px) and (max-height:850px){.winter-heading{font-size:clamp(3rem,5vw,5.15rem)}.christmas-countdown{padding-top:17px;padding-bottom:17px}.santa-flight-stage{height:260px}.santa-house-flight{top:32%}}
@media(max-width:1023px){.winter-heading{text-shadow:0 5px 20px #0005}.santa-flight-stage{margin-bottom:0}.login-glass{position:relative}}@media(prefers-reduced-motion:reduce){.snowflake,.star,.aurora{animation:none!important}.snowflake{opacity:.2!important}}

/* Large animated sleigh replaces house; always travels right to left. */
.santa-flight-stage{isolation:isolate}
.santa-flight-glow{position:absolute;inset:16% 6% 10%;border-radius:50%;background:radial-gradient(ellipse at 50% 60%,#b1d6ff22,transparent 70%);filter:blur(25px)}
.santa-flight-track{mask-image:linear-gradient(90deg,transparent 0%,#000 8%,#000 92%,transparent 100%)}
.santa-house-flight{filter:drop-shadow(0 15px 15px rgba(3,17,39,.38));animation:santaAcrossStage 12s linear infinite;will-change:transform}
@keyframes santaAcrossStage{0%{transform:translate3d(118%,5px,0)}25%{transform:translate3d(58%,-8px,0)}50%{transform:translate3d(0%,3px,0)}75%{transform:translate3d(-57%,-9px,0)}100%{transform:translate3d(-120%,5px,0)}}
@media(max-width:1023px){.santa-house-flight{width:90%;animation-duration:11s}}
@media(max-width:640px){.santa-house-flight{top:40%;width:100%;animation-duration:10s}}
@media(prefers-reduced-motion:reduce){.santa-house-flight{animation:none!important;transform:translate3d(6%,0,0)!important}}
</style>
