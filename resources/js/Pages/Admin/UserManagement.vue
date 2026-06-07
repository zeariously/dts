<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    users: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            from: 0,
            to: 0,
            total: 0,
            current_page: 1,
            last_page: 1,
        }),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_users: 0,
            admin_users: 0,
            current_page: 1,
            last_page: 1,
        }),
    },
    activityLogs: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role_id: '',
            per_page: 10,
            tab: 'role-management',
        }),
    },
    authUser: {
        type: Object,
        default: () => ({
            id: null,
            name: '',
            role_id: null,
        }),
    },
    flash: {
        type: Object,
        default: () => ({
            success: '',
            error: '',
        }),
    },
})

const search = ref(props.filters?.search || '')
const selectedRole = ref(props.filters?.role_id || '')
const perPage = ref(Number(props.filters?.per_page || 10))
const activeTab = ref(props.filters?.tab || 'role-management')
const roleDrafts = ref({})
const savingUserId = ref(null)

const rows = computed(() => props.users?.data || [])
const links = computed(() => props.users?.links || [])
const flashSuccess = computed(() => props.flash?.success || '')
const flashError = computed(() => props.flash?.error || '')

watch(
    () => rows.value,
    (users) => {
        users.forEach((user) => {
            if (roleDrafts.value[user.id] === undefined) {
                roleDrafts.value[user.id] = user.role_id
            }
        })
    },
    { immediate: true }
)

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value || undefined,
        role_id: selectedRole.value || undefined,
        per_page: perPage.value,
        tab: activeTab.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const resetFilters = () => {
    search.value = ''
    selectedRole.value = ''
    perPage.value = 10

    router.get('/admin/users', {
        tab: activeTab.value,
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const setTab = (tab) => {
    activeTab.value = tab

    router.get('/admin/users', {
        search: search.value || undefined,
        role_id: selectedRole.value || undefined,
        per_page: perPage.value,
        tab,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const saveUserRole = (user) => {
    if (!user?.id) return

    savingUserId.value = user.id

    router.patch(`/admin/users/${user.id}/role`, {
        role_id: roleDrafts.value[user.id],
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingUserId.value = null
        },
    })
}

const goToPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    })
}

const formatDateTime = (value) => {
    if (!value) return '-'

    const normalizedValue = String(value).replace(' ', 'T')
    const date = new Date(normalizedValue)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date)
}
</script>

<template>
    <Head title="Admin User Management" />

    <div class="min-h-screen bg-slate-100 p-4 sm:p-6">
        <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm">
            <section class="bg-gradient-to-r from-slate-950 via-indigo-950 to-violet-800 px-8 py-8 text-white">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-xl font-black">
                            {{ String(authUser.name || 'A').slice(0, 2).toUpperCase() }}
                        </div>

                        <div>
                            <div class="inline-flex rounded-full bg-white/10 px-4 py-1 text-xs font-black uppercase tracking-[0.3em] text-white/70">
                                Admin Console
                            </div>

                            <h1 class="mt-3 text-4xl font-black tracking-tight">
                                User Management
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm font-medium text-white/80">
                                Manage users, role access, project assignments, and activity visibility in one place.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Active Tab
                            </p>
                            <p class="mt-2 text-sm font-black">
                                {{ activeTab === 'activity-logs' ? 'Logs' : activeTab === 'project-assignments' ? 'Projects' : 'Roles' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Access
                            </p>
                            <p class="mt-2 text-sm font-black">
                                Admin
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Users
                            </p>
                            <p class="mt-2 text-sm font-black">
                                {{ stats.total_users }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4 px-6 py-5">
                <div
                    v-if="flashSuccess || flashError"
                    class="space-y-3"
                >
                    <div
                        v-if="flashSuccess"
                        class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-black text-green-800"
                    >
                        {{ flashSuccess }}
                    </div>

                    <div
                        v-if="flashError"
                        class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-black text-red-800"
                    >
                        {{ flashError }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 xl:grid-cols-[1fr_300px_180px_auto_auto]">
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            🔍
                        </span>

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search users..."
                            class="h-14 w-full rounded-2xl border border-slate-200 bg-white pl-12 pr-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <select
                        v-model="selectedRole"
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        @change="applyFilters"
                    >
                        <option value="">
                            All Roles
                        </option>

                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>

                    <select
                        v-model="perPage"
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        @change="applyFilters"
                    >
                        <option :value="10">
                            10 rows
                        </option>
                        <option :value="15">
                            15 rows
                        </option>
                        <option :value="20">
                            20 rows
                        </option>
                        <option :value="50">
                            50 rows
                        </option>
                    </select>

                    <button
                        type="button"
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-6 text-sm font-black text-indigo-700 hover:bg-indigo-50"
                        @click="resetFilters"
                    >
                        Reset
                    </button>

                    <button
                        type="button"
                        class="h-14 rounded-2xl bg-indigo-600 px-6 text-sm font-black text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700"
                        @click="applyFilters"
                    >
                        Search
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-2xl px-5 py-3 text-sm font-black"
                        :class="activeTab === 'activity-logs'
                            ? 'bg-slate-950 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="setTab('activity-logs')"
                    >
                        Users Activity Logs
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl px-5 py-3 text-sm font-black"
                        :class="activeTab === 'role-management'
                            ? 'bg-slate-950 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="setTab('role-management')"
                    >
                        User Roles Management
                    </button>
                </div>
            </section>
        </div>

        <main class="mt-6 rounded-[2rem] bg-white p-6 shadow-sm">
            <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-950">
                        {{
                            activeTab === 'activity-logs'
                                ? 'Users Activity Logs'
                                : activeTab === 'project-assignments'
                                    ? 'Projects Assigned to User'
                                    : 'User Roles Management'
                        }}
                    </h2>

                    <p class="mt-1 text-sm font-medium text-slate-500">
                        Manage and review records from the selected section.
                    </p>
                </div>

                <span class="w-fit rounded-full bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-500">
                    Admin Mode
                </span>
            </div>

            <section
                v-if="activeTab === 'activity-logs'"
                class="overflow-hidden rounded-3xl border border-slate-200"
            >
                <div class="bg-gradient-to-r from-indigo-700 to-sky-500 px-6 py-5 text-white">
                    <h3 class="text-xl font-black">
                        Activity Logs
                    </h3>
                    <p class="mt-1 text-sm font-medium text-white/80">
                        Monitor user actions, affected records, and access details.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-indigo-600 text-white">
                            <tr>
                                <th class="px-5 py-4 font-black">Action</th>
                                <th class="px-5 py-4 font-black">Project / Model</th>
                                <th class="px-5 py-4 font-black">User</th>
                                <th class="px-5 py-4 font-black">IP Address</th>
                                <th class="px-5 py-4 font-black">Date</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(log, index) in activityLogs"
                                :key="index"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-5 py-4">
                                    <span class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-700">
                                    {{ log.module }}
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-700">
                                    {{ log.user }}
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-500">
                                    {{ log.ip_address }}
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-500">
                                    {{ formatDateTime(log.date) }}
                                </td>
                            </tr>

                            <tr v-if="activityLogs.length === 0">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center"
                                >
                                    <p class="text-lg font-black text-slate-800">
                                        No activity logs table/data found yet
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        This tab is ready. It will display data once your activity_logs table exists.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-else-if="activeTab === 'project-assignments'"
                class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center"
            >
                <h3 class="text-2xl font-black text-slate-900">
                    Project Assignments
                </h3>

                <p class="mx-auto mt-3 max-w-2xl text-sm font-medium leading-7 text-slate-500">
                    This tab is already prepared for your next module. Once you send me your project assignment table names,
                    we can connect this to actual project records per user.
                </p>
            </section>

            <section
                v-else
                class="overflow-hidden rounded-3xl border border-slate-200"
            >
                <div class="bg-gradient-to-r from-indigo-700 to-sky-500 px-6 py-5 text-white">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-xl font-black">
                                User Roles
                            </h3>

                            <p class="mt-1 text-sm font-medium text-white/80">
                                Set and update the access role of each user account.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm font-black">
                            Showing {{ users.from || 0 }} - {{ users.to || 0 }} of {{ users.total || 0 }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 border-b border-slate-200 bg-slate-50 p-5 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">
                            Total Records
                        </p>
                        <p class="mt-2 text-2xl font-black text-indigo-700">
                            {{ stats.total_users }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">
                            Admin Users
                        </p>
                        <p class="mt-2 text-2xl font-black text-indigo-700">
                            {{ stats.admin_users }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">
                            Current Page
                        </p>
                        <p class="mt-2 text-2xl font-black text-slate-900">
                            {{ stats.current_page }} / {{ stats.last_page }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-left text-sm">
                        <thead class="bg-indigo-600 text-white">
                            <tr>
                                <th class="w-[8%] px-5 py-4 font-black">ID</th>
                                <th class="w-[24%] px-5 py-4 font-black">User</th>
                                <th class="w-[16%] px-5 py-4 font-black">Current Role</th>
                                <th class="w-[20%] px-5 py-4 font-black">Set Role</th>
                                <th class="w-[10%] px-5 py-4 text-center font-black">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="user in rows"
                                :key="user.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-5 py-5 font-black text-indigo-700">
                                    #{{ user.id }}
                                </td>

                                <td class="px-5 py-5">
                                    <p class="font-black text-slate-900">
                                        {{ user.name }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">
                                        {{ user.username || 'No username' }}
                                    </p>
                                </td>

                                

                                <td class="px-5 py-5">
                                    <span class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                                        {{ user.role_name }}
                                    </span>
                                </td>

                                <td class="px-5 py-5">
                                    <select
                                        v-model="roleDrafts[user.id]"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                        :disabled="authUser.id === user.id"
                                    >
                                        <option
                                            v-for="role in roles"
                                            :key="role.id"
                                            :value="role.id"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </select>

                                    <p
                                        v-if="authUser.id === user.id"
                                        class="mt-2 text-xs font-bold text-red-600"
                                    >
                                        You cannot edit your own role.
                                    </p>
                                </td>

                                <td class="px-5 py-5 text-center">
                                    <button
                                        type="button"
                                        class="rounded-2xl bg-indigo-600 px-5 py-3 text-xs font-black text-white shadow-lg shadow-indigo-100 hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="savingUserId === user.id || authUser.id === user.id"
                                        @click="saveUserRole(user)"
                                    >
                                        {{ savingUserId === user.id ? 'Saving...' : 'Save' }}
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="6"
                                    class="px-5 py-14 text-center"
                                >
                                    <p class="text-lg font-black text-slate-800">
                                        No users found
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Try another search keyword or role filter.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="flex flex-col gap-4 border-t border-slate-200 p-5 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm font-black text-slate-600">
                        Page {{ users.current_page }} of {{ users.last_page }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-xl border px-3 py-2 text-sm font-black"
                            :class="[
                                link.active
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-slate-200 bg-white text-indigo-700 hover:bg-indigo-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
