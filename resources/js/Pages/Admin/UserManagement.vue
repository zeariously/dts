<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

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
    documents: {
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
            total_documents: 0,
            total_announcements: 0,
            current_page: 1,
            last_page: 1,
        }),
    },
    activityLogs: {
        type: Array,
        default: () => [],
    },
    announcements: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role_id: '',
            per_page: 10,
            document_search: '',
            document_per_page: 15,
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

const inertiaPage = usePage()

const search = ref(props.filters?.search || '')
const selectedRole = ref(props.filters?.role_id || '')
const perPage = ref(Number(props.filters?.per_page || 10))
const documentSearch = ref(props.filters?.document_search || '')
const documentPerPage = ref(Number(props.filters?.document_per_page || 15))
const activeTab = ref(props.filters?.tab || 'role-management')
const roleDrafts = ref({})
const savingUserId = ref(null)

const showAddUserModal = ref(false)
const showDocumentModal = ref(false)
const documentModalTab = ref('details')
const documentLoading = ref(false)
const documentError = ref('')
const selectedDocument = ref(null)
const selectedDocumentSummary = ref(null)

let userSearchTimer = null
let documentSearchTimer = null

const createUserForm = useForm({
    name: '',
    loginname: '',
    role_id: '',
    password: '',
    password_confirmation: '',
})

const announcementForm = useForm({
    title: '',
    message: '',
    target_type: 'all',
    target_value: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
})

const tabs = [
    { value: 'role-management', label: 'User Roles', icon: '🛡️' },
    { value: 'activity-logs', label: 'Activity Logs', icon: '🧾' },
    { value: 'documents', label: 'All Documents', icon: '📄' },
    { value: 'notifications', label: 'Notifications', icon: '🔔' },
]

const rows = computed(() => props.users?.data || [])
const userLinks = computed(() => props.users?.links || [])
const documentRows = computed(() => props.documents?.data || [])
const documentLinks = computed(() => props.documents?.links || [])
const flashSuccess = computed(() => props.flash?.success || '')
const flashError = computed(() => props.flash?.error || '')
const currentTabLabel = computed(() => {
    return tabs.find((tab) => tab.value === activeTab.value)?.label || 'User Roles'
})

const announcementRows = computed(() => props.announcements || [])

const displayDocument = computed(() => {
    return selectedDocument.value || selectedDocumentSummary.value || {}
})

const actionHistory = computed(() => {
    return selectedDocument.value?.action_history || []
})

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

watch(search, () => {
    clearTimeout(userSearchTimer)

    userSearchTimer = window.setTimeout(() => {
        if (activeTab.value === 'role-management') {
            applyUserFilters()
        }
    }, 400)
})

watch(documentSearch, () => {
    clearTimeout(documentSearchTimer)

    documentSearchTimer = window.setTimeout(() => {
        if (activeTab.value === 'documents') {
            applyDocumentFilters()
        }
    }, 400)
})

onBeforeUnmount(() => {
    clearTimeout(userSearchTimer)
    clearTimeout(documentSearchTimer)
})

const commonQuery = () => ({
    search: search.value || undefined,
    role_id: selectedRole.value || undefined,
    per_page: perPage.value,
    document_search: documentSearch.value || undefined,
    document_per_page: documentPerPage.value,
    tab: activeTab.value,
})

const applyUserFilters = () => {
    router.get('/admin/users', commonQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const applyDocumentFilters = () => {
    router.get('/admin/users', commonQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const setTab = (tab) => {
    activeTab.value = tab

    router.get('/admin/users', {
        ...commonQuery(),
        tab,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const openAddUserModal = () => {
    createUserForm.clearErrors()
    showAddUserModal.value = true
}

const closeAddUserModal = () => {
    if (createUserForm.processing) return

    showAddUserModal.value = false
    createUserForm.reset()
    createUserForm.clearErrors()
}

const submitNewUser = () => {
    createUserForm.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => {
            showAddUserModal.value = false
            createUserForm.reset()
            createUserForm.clearErrors()
        },
    })
}

const submitAnnouncement = () => {
    announcementForm.post('/admin/announcements', {
        preserveScroll: true,
        onSuccess: () => {
            announcementForm.reset()
            announcementForm.target_type = 'all'
            announcementForm.target_value = ''
            announcementForm.is_active = true
        },
    })
}

const deleteAnnouncement = (announcement) => {
    if (!announcement?.id) return

    const confirmed = window.confirm(
        `Delete the announcement "${announcement.title}"?`
    )

    if (!confirmed) return

    router.delete(`/admin/announcements/${announcement.id}`, {
        preserveScroll: true,
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

const openDocumentModal = async (document) => {
    if (!document?.IDdoc && !document?.document_no) return

    const documentId = document.IDdoc || document.document_no

    selectedDocumentSummary.value = document
    selectedDocument.value = null
    documentModalTab.value = 'details'
    documentError.value = ''
    documentLoading.value = true
    showDocumentModal.value = true

    try {
        const requestHeaders = {
            Accept: 'text/html, application/xhtml+xml',
            'X-Inertia': 'true',
            'X-Requested-With': 'XMLHttpRequest',
        }

        /*
         * Manual requests to an Inertia route must send the currently loaded
         * asset version. Without this header, Laravel/Inertia may return 409
         * when the frontend assets and server version do not match.
         */
        if (inertiaPage.version) {
            requestHeaders['X-Inertia-Version'] = inertiaPage.version
        }

        const response = await fetch(`/dts/${documentId}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: requestHeaders,
        })

        /*
         * A 409 with X-Inertia-Location is Inertia's instruction to perform
         * a full-page refresh so the browser receives the latest assets.
         */
        if (response.status === 409) {
            const refreshLocation = response.headers.get('X-Inertia-Location')

            window.location.assign(refreshLocation || window.location.href)
            return
        }

        if (!response.ok) {
            throw new Error(`Unable to load document information (${response.status}).`)
        }

        const inertiaResponse = await response.json()
        const documentPayload = inertiaResponse?.props?.document

        if (!documentPayload) {
            throw new Error('The document details were not included in the server response.')
        }

        selectedDocument.value = documentPayload
    } catch (error) {
        documentError.value = error?.message || 'Unable to load document details.'
    } finally {
        documentLoading.value = false
    }
}

const closeDocumentModal = () => {
    showDocumentModal.value = false
    selectedDocument.value = null
    selectedDocumentSummary.value = null
    documentError.value = ''
    documentModalTab.value = 'details'
}

const formatDateTime = (value) => {
    if (!value) return '-'

    const normalizedValue = String(value).replace(' ', 'T')
    const date = new Date(normalizedValue)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date)
}

const formatDate = (value) => {
    if (!value) return '-'

    const normalizedValue = String(value).replace(' ', 'T')
    const date = new Date(normalizedValue)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(date)
}

const classificationLabel = (value) => {
    const normalized = String(value ?? '').toLowerCase()

    return ['true', '1', 'y', 'yes', 'outgoing'].includes(normalized)
        ? 'Outgoing'
        : 'Incoming'
}

const announcementStatus = (announcement) => {
    if (!announcement?.is_active) {
        return 'Inactive'
    }

    const now = new Date()
    const startsAt = announcement?.starts_at
        ? new Date(String(announcement.starts_at).replace(' ', 'T'))
        : null
    const endsAt = announcement?.ends_at
        ? new Date(String(announcement.ends_at).replace(' ', 'T'))
        : null

    if (startsAt && startsAt > now) {
        return 'Scheduled'
    }

    if (endsAt && endsAt < now) {
        return 'Expired'
    }

    return 'Active'
}

const announcementStatusClass = (announcement) => {
    const status = announcementStatus(announcement)

    if (status === 'Active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }

    if (status === 'Scheduled') {
        return 'border-sky-200 bg-sky-50 text-sky-700'
    }

    if (status === 'Expired') {
        return 'border-amber-200 bg-amber-50 text-amber-700'
    }

    return 'border-slate-200 bg-slate-100 text-slate-600'
}

const announcementAudienceLabel = (announcement) => {
    if (announcement?.target_type !== 'role') {
        return 'All Users'
    }

    const role = props.roles.find(
        (item) => String(item.id) === String(announcement.target_value)
    )

    return role ? `${role.name} only` : 'Selected Role'
}

const historyBadgeClass = (type) => {
    const normalized = String(type || '').toLowerCase()

    if (normalized.includes('created')) return 'bg-indigo-600'
    if (normalized.includes('received')) return 'bg-emerald-600'
    if (normalized.includes('returned')) return 'bg-rose-600'
    if (normalized.includes('transferred')) return 'bg-sky-600'
    if (normalized.includes('action')) return 'bg-violet-600'
    if (normalized.includes('completed')) return 'bg-teal-600'
    if (normalized.includes('remark')) return 'bg-amber-600'

    return 'bg-slate-600'
}
</script>

<template>
    <Head title="DTS Administration" />

    <div class="min-h-screen bg-slate-100 p-4 sm:p-6">
        <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm">
            <section class="bg-gradient-to-r from-slate-950 via-indigo-950 to-violet-800 px-6 py-8 text-white sm:px-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-xl font-black">
                            {{ String(authUser.name || 'A').slice(0, 2).toUpperCase() }}
                        </div>

                        <div>
                            <div class="inline-flex rounded-full bg-white/10 px-4 py-1 text-xs font-black uppercase tracking-[0.3em] text-white/70">
                                Admin Console
                            </div>

                            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                                DTS Administration
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm font-medium text-white/80">
                                Manage user roles, review activity, inspect every document, and publish system announcements.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Active Tab
                            </p>
                            <p class="mt-2 text-sm font-black">
                                {{ currentTabLabel }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Documents
                            </p>
                            <p class="mt-2 text-sm font-black">
                                {{ stats.total_documents || 0 }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-white/50">
                                Announcements
                            </p>
                            <p class="mt-2 text-sm font-black">
                                {{ stats.total_announcements || 0 }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4 px-5 py-5 sm:px-6">
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

                <div
                    v-if="activeTab === 'role-management'"
                    class="grid grid-cols-1 gap-3 xl:grid-cols-[1fr_300px_180px]"
                >
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            🔍
                        </span>

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search users..."
                            class="h-14 w-full rounded-2xl border border-slate-200 bg-white pl-12 pr-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <select
                        v-model="selectedRole"
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        @change="applyUserFilters"
                    >
                        <option value="">All Roles</option>

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
                        @change="applyUserFilters"
                    >
                        <option :value="10">10 rows</option>
                        <option :value="15">15 rows</option>
                        <option :value="20">20 rows</option>
                        <option :value="50">50 rows</option>
                    </select>
                </div>

                <div
                    v-if="activeTab === 'documents'"
                    class="grid grid-cols-1 gap-3 xl:grid-cols-[1fr_180px]"
                >
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            🔍
                        </span>

                        <input
                            v-model="documentSearch"
                            type="text"
                            placeholder="Search by DTS ID, subject, or regarding..."
                            class="h-14 w-full rounded-2xl border border-slate-200 bg-white pl-12 pr-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        />
                    </div>

                    <select
                        v-model="documentPerPage"
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                        @change="applyDocumentFilters"
                    >
                        <option :value="10">10 rows</option>
                        <option :value="15">15 rows</option>
                        <option :value="20">20 rows</option>
                        <option :value="50">50 rows</option>
                    </select>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-2xl px-5 py-3 text-sm font-black transition"
                        :class="activeTab === tab.value
                            ? 'bg-slate-950 text-white shadow-lg'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="setTab(tab.value)"
                    >
                        <span>{{ tab.icon }}</span>
                        <span>{{ tab.label }}</span>
                    </button>
                </div>
            </section>
        </div>

        <main class="mt-6 rounded-[2rem] bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-950">
                        {{ currentTabLabel }}
                    </h2>

                    <p class="mt-1 text-sm font-medium text-slate-500">
                        {{
                            activeTab === 'role-management'
                                ? 'Create accounts and manage each user’s access role.'
                                : activeTab === 'activity-logs'
                                    ? 'Review recorded user and administrative activity.'
                                    : activeTab === 'documents'
                                        ? 'View every DTS document without leaving the Admin Console.'
                                        : 'Create and manage announcements for DTS users.'
                        }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700 md:w-auto"
                    @click="openAddUserModal"
                >
                    <span class="text-lg leading-none">+</span>
                    <span>Add User</span>
                </button>
            </div>

            <!-- Activity Logs -->
            <section
                v-if="activeTab === 'activity-logs'"
                class="overflow-hidden rounded-3xl border border-slate-200"
            >
                <div class="bg-gradient-to-r from-indigo-700 to-sky-500 px-6 py-5 text-white">
                    <h3 class="text-xl font-black">Activity Logs</h3>
                    <p class="mt-1 text-sm font-medium text-white/80">
                        Monitor user actions, affected modules, IP addresses, and timestamps.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-indigo-600 text-white">
                            <tr>
                                <th class="px-5 py-4 font-black">Action</th>
                                <th class="px-5 py-4 font-black">Module</th>
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
                                <td class="px-5 py-4 font-semibold text-slate-700">{{ log.module }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-700">{{ log.user }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-500">{{ log.ip_address }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-500">{{ formatDateTime(log.date) }}</td>
                            </tr>

                            <tr v-if="activityLogs.length === 0">
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <p class="text-lg font-black text-slate-800">No activity logs found</p>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Recorded activities will appear here.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- All Documents -->
            <section
                v-else-if="activeTab === 'documents'"
                class="overflow-hidden rounded-3xl border border-slate-200"
            >
                <div class="bg-gradient-to-r from-indigo-700 to-sky-500 px-6 py-5 text-white">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-xl font-black">All DTS Documents</h3>
                            <p class="mt-1 text-sm font-medium text-white/80">
                                The View button opens document details and action history in a modal.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm font-black">
                            Showing {{ documents.from || 0 }} - {{ documents.to || 0 }} of {{ documents.total || 0 }}
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1250px] text-left text-sm">
                        <thead class="bg-indigo-600 text-white">
                            <tr>
                                <th class="px-5 py-4 font-black">DTS ID</th>
                                <th class="px-5 py-4 font-black">Entry Date</th>
                                <th class="px-5 py-4 font-black">Subject / Regarding</th>
                                <th class="px-5 py-4 font-black">Classification</th>
                                <th class="px-5 py-4 font-black">Document Type</th>
                                <th class="px-5 py-4 font-black">Assigned To</th>
                                <th class="px-5 py-4 font-black">Status</th>
                                <th class="px-5 py-4 text-center font-black">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="document in documentRows"
                                :key="document.IDdoc"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-5 py-5 font-black text-indigo-700">
                                    DTS - #{{ document.IDdoc }}
                                </td>

                                <td class="px-5 py-5 font-semibold text-slate-600">
                                    {{ formatDate(document.entrydate) }}
                                </td>

                                <td class="max-w-[360px] px-5 py-5">
                                    <p class="break-words font-black text-slate-900">
                                        {{ document.subject || 'No subject' }}
                                    </p>
                                    <p class="mt-1 break-words text-xs font-semibold text-slate-500">
                                        {{ document.regarding || 'No regarding information' }}
                                    </p>
                                </td>

                                <td class="px-5 py-5">
                                    <span class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                                        {{ document.classification_label || classificationLabel(document.classification) }}
                                    </span>
                                </td>

                                <td class="px-5 py-5 font-semibold text-slate-700">
                                    {{ document.doctype || '-' }}
                                </td>

                                <td class="px-5 py-5 font-semibold text-slate-700">
                                    {{ document.staff_concern || document.transferred_to || '-' }}
                                </td>

                                <td class="px-5 py-5">
                                    <span class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                                        {{ document.workflow_status || '-' }}
                                    </span>
                                </td>

                                <td class="px-5 py-5 text-center">
                                    <button
                                        type="button"
                                        class="rounded-2xl bg-slate-950 px-5 py-3 text-xs font-black text-white hover:bg-indigo-700"
                                        @click="openDocumentModal(document)"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="documentRows.length === 0">
                                <td colspan="8" class="px-5 py-14 text-center">
                                    <p class="text-lg font-black text-slate-800">No documents found</p>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Try another DTS ID, subject, or regarding keyword.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="documentLinks.length > 3"
                    class="flex flex-col gap-4 border-t border-slate-200 p-5 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm font-black text-slate-600">
                        Page {{ documents.current_page }} of {{ documents.last_page }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in documentLinks"
                            :key="`document-${link.label}-${link.url}`"
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

            <!-- Notifications / Create Announcement -->
            <section
                v-else-if="activeTab === 'notifications'"
                class="space-y-6"
            >
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                    <form
                        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                        @submit.prevent="submitAnnouncement"
                    >
                        <div class="bg-gradient-to-r from-indigo-700 to-violet-700 px-6 py-5 text-white">
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-indigo-100">
                                System Notification
                            </p>

                            <h3 class="mt-2 text-2xl font-black">
                                Create Announcement
                            </h3>

                            <p class="mt-1 text-sm font-semibold text-white/75">
                                Publish an announcement for all users or a selected role.
                            </p>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <div>
                                <label class="mb-2 block text-sm font-black text-slate-800">
                                    Announcement Title
                                </label>

                                <input
                                    v-model.trim="announcementForm.title"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Example: Scheduled DTS Maintenance"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                    :class="announcementForm.errors.title ? 'border-rose-400' : ''"
                                />

                                <p
                                    v-if="announcementForm.errors.title"
                                    class="mt-2 text-xs font-bold text-rose-600"
                                >
                                    {{ announcementForm.errors.title }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-black text-slate-800">
                                    Message
                                </label>

                                <textarea
                                    v-model.trim="announcementForm.message"
                                    rows="6"
                                    maxlength="2000"
                                    placeholder="Write the announcement that users should see..."
                                    class="w-full resize-y rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold leading-6 text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                    :class="announcementForm.errors.message ? 'border-rose-400' : ''"
                                ></textarea>

                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <p
                                        v-if="announcementForm.errors.message"
                                        class="text-xs font-bold text-rose-600"
                                    >
                                        {{ announcementForm.errors.message }}
                                    </p>

                                    <p class="ml-auto text-xs font-bold text-slate-400">
                                        {{ announcementForm.message.length }}/2000
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-black text-slate-800">
                                        Send To
                                    </label>

                                    <select
                                        v-model="announcementForm.target_type"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-black text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                        @change="announcementForm.target_value = ''"
                                    >
                                        <option value="all">All Users</option>
                                        <option value="role">Specific Role</option>
                                    </select>
                                </div>

                                <div v-if="announcementForm.target_type === 'role'">
                                    <label class="mb-2 block text-sm font-black text-slate-800">
                                        Select Role
                                    </label>

                                    <select
                                        v-model="announcementForm.target_value"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-black text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                        :class="announcementForm.errors.target_value ? 'border-rose-400' : ''"
                                    >
                                        <option value="" disabled>Select a role</option>

                                        <option
                                            v-for="role in roles"
                                            :key="`announcement-role-${role.id}`"
                                            :value="String(role.id)"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </select>

                                    <p
                                        v-if="announcementForm.errors.target_value"
                                        class="mt-2 text-xs font-bold text-rose-600"
                                    >
                                        {{ announcementForm.errors.target_value }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-black text-slate-800">
                                        Start Date and Time
                                    </label>

                                    <input
                                        v-model="announcementForm.starts_at"
                                        type="datetime-local"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                        :class="announcementForm.errors.starts_at ? 'border-rose-400' : ''"
                                    />

                                    <p class="mt-2 text-xs font-semibold text-slate-400">
                                        Leave blank to start immediately.
                                    </p>

                                    <p
                                        v-if="announcementForm.errors.starts_at"
                                        class="mt-2 text-xs font-bold text-rose-600"
                                    >
                                        {{ announcementForm.errors.starts_at }}
                                    </p>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-black text-slate-800">
                                        End Date and Time
                                    </label>

                                    <input
                                        v-model="announcementForm.ends_at"
                                        type="datetime-local"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                        :class="announcementForm.errors.ends_at ? 'border-rose-400' : ''"
                                    />

                                    <p class="mt-2 text-xs font-semibold text-slate-400">
                                        Leave blank if it has no expiration.
                                    </p>

                                    <p
                                        v-if="announcementForm.errors.ends_at"
                                        class="mt-2 text-xs font-bold text-rose-600"
                                    >
                                        {{ announcementForm.errors.ends_at }}
                                    </p>
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                                <input
                                    v-model="announcementForm.is_active"
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500"
                                />

                                <span>
                                    <span class="block text-sm font-black text-indigo-900">
                                        Active Announcement
                                    </span>

                                    <span class="mt-1 block text-xs font-semibold leading-5 text-indigo-700">
                                        Turn this off to save the announcement as inactive.
                                    </span>
                                </span>
                            </label>

                            <button
                                type="submit"
                                class="w-full rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="announcementForm.processing"
                            >
                                {{ announcementForm.processing ? 'Publishing...' : 'Publish Announcement' }}
                            </button>
                        </div>
                    </form>

                    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-xl font-black text-slate-900">
                                    Announcement History
                                </h3>

                                <p class="mt-1 text-sm font-semibold text-slate-500">
                                    Review active, scheduled, expired, and inactive announcements.
                                </p>
                            </div>

                            <span class="w-fit rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-indigo-700">
                                {{ announcementRows.length }} total
                            </span>
                        </div>

                        <div
                            v-if="announcementRows.length"
                            class="max-h-[720px] divide-y divide-slate-100 overflow-y-auto"
                        >
                            <article
                                v-for="announcement in announcementRows"
                                :key="announcement.id"
                                class="px-6 py-5"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded-full border px-3 py-1 text-xs font-black"
                                                :class="announcementStatusClass(announcement)"
                                            >
                                                {{ announcementStatus(announcement) }}
                                            </span>

                                            <span class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                                                {{ announcementAudienceLabel(announcement) }}
                                            </span>
                                        </div>

                                        <h4 class="mt-3 break-words text-lg font-black text-slate-900">
                                            {{ announcement.title }}
                                        </h4>

                                        <p class="mt-2 whitespace-pre-wrap break-words text-sm font-semibold leading-6 text-slate-600">
                                            {{ announcement.message }}
                                        </p>

                                        <div class="mt-4 grid grid-cols-1 gap-2 text-xs font-bold text-slate-500 sm:grid-cols-2">
                                            <p>
                                                Starts:
                                                <span class="text-slate-700">
                                                    {{ formatDateTime(announcement.starts_at) }}
                                                </span>
                                            </p>

                                            <p>
                                                Ends:
                                                <span class="text-slate-700">
                                                    {{ formatDateTime(announcement.ends_at) }}
                                                </span>
                                            </p>

                                            <p>
                                                Created by:
                                                <span class="text-slate-700">
                                                    {{ announcement.created_by_name || 'Admin' }}
                                                </span>
                                            </p>

                                            <p>
                                                Created:
                                                <span class="text-slate-700">
                                                    {{ formatDateTime(announcement.created_at) }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full shrink-0 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700 hover:bg-rose-100 lg:w-auto"
                                        @click="deleteAnnouncement(announcement)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="px-6 py-16 text-center"
                        >
                            <p class="text-4xl">📣</p>
                            <p class="mt-4 text-lg font-black text-slate-800">
                                No announcements yet
                            </p>
                            <p class="mt-2 text-sm font-semibold text-slate-500">
                                Create the first DTS announcement using the form.
                            </p>
                        </div>
                    </section>
                </div>
            </section>

            <!-- User Roles -->
            <section
                v-else
                class="overflow-hidden rounded-3xl border border-slate-200"
            >
                <div class="bg-gradient-to-r from-indigo-700 to-sky-500 px-6 py-5 text-white">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-xl font-black">User Roles</h3>
                            <p class="mt-1 text-sm font-medium text-white/80">
                                Set and update the access role of each DTS account.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm font-black">
                            Showing {{ users.from || 0 }} - {{ users.to || 0 }} of {{ users.total || 0 }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 border-b border-slate-200 bg-slate-50 p-5 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">Total Users</p>
                        <p class="mt-2 text-2xl font-black text-indigo-700">{{ stats.total_users }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">Admin Users</p>
                        <p class="mt-2 text-2xl font-black text-indigo-700">{{ stats.admin_users }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400">Current Page</p>
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
                                <td class="px-5 py-5 font-black text-indigo-700">#{{ user.id }}</td>

                                <td class="px-5 py-5">
                                    <p class="font-black text-slate-900">{{ user.name }}</p>
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
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <p class="text-lg font-black text-slate-800">No users found</p>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Try another search keyword or role filter.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="userLinks.length > 3"
                    class="flex flex-col gap-4 border-t border-slate-200 p-5 md:flex-row md:items-center md:justify-between"
                >
                    <p class="text-sm font-black text-slate-600">
                        Page {{ users.current_page }} of {{ users.last_page }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in userLinks"
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

        <!-- Add User Modal -->
        <div
            v-if="showAddUserModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/65 backdrop-blur-sm sm:items-center sm:px-4 sm:py-8"
            @click.self="closeAddUserModal"
        >
            <div class="flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-[2rem]">
                <div class="shrink-0 bg-gradient-to-r from-indigo-700 to-violet-700 px-5 py-5 text-white sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-indigo-100">
                                Account Creation
                            </p>
                            <h2 class="mt-2 text-2xl font-black">Add New User</h2>
                            <p class="mt-1 text-sm font-semibold text-indigo-100">
                                Create a DTS account and assign its initial role.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-xl bg-white/15 px-4 py-3 text-sm font-black text-white hover:bg-white/25 sm:w-auto sm:py-2"
                            :disabled="createUserForm.processing"
                            @click="closeAddUserModal"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <form
                    class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6"
                    @submit.prevent="submitNewUser"
                >
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-black text-slate-800">Full Name</label>
                            <input
                                v-model.trim="createUserForm.name"
                                type="text"
                                autocomplete="name"
                                placeholder="Enter the user's full name"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                :class="createUserForm.errors.name ? 'border-rose-400' : ''"
                            />
                            <p v-if="createUserForm.errors.name" class="mt-2 text-xs font-bold text-rose-600">
                                {{ createUserForm.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">Username</label>
                            <input
                                v-model.trim="createUserForm.loginname"
                                type="text"
                                autocomplete="username"
                                placeholder="Example: juandelacruz"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                :class="createUserForm.errors.loginname ? 'border-rose-400' : ''"
                            />
                            <p v-if="createUserForm.errors.loginname" class="mt-2 text-xs font-bold text-rose-600">
                                {{ createUserForm.errors.loginname }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">Account Role</label>
                            <select
                                v-model="createUserForm.role_id"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-black text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                :class="createUserForm.errors.role_id ? 'border-rose-400' : ''"
                            >
                                <option value="" disabled>Select a role</option>
                                <option
                                    v-for="role in roles"
                                    :key="`new-user-role-${role.id}`"
                                    :value="String(role.id)"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                            <p v-if="createUserForm.errors.role_id" class="mt-2 text-xs font-bold text-rose-600">
                                {{ createUserForm.errors.role_id }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">Temporary Password</label>
                            <input
                                v-model="createUserForm.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="At least 4 characters"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                                :class="createUserForm.errors.password ? 'border-rose-400' : ''"
                            />
                            <p v-if="createUserForm.errors.password" class="mt-2 text-xs font-bold text-rose-600">
                                {{ createUserForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">Confirm Password</label>
                            <input
                                v-model="createUserForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Retype the password"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-700 hover:bg-slate-50 sm:w-auto"
                            :disabled="createUserForm.processing"
                            @click="closeAddUserModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 disabled:opacity-60 sm:w-auto"
                            :disabled="createUserForm.processing"
                        >
                            {{ createUserForm.processing ? 'Creating Account...' : 'Create User' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Document Details Modal -->
        <div
            v-if="showDocumentModal"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/75 backdrop-blur-sm sm:items-center sm:px-4 sm:py-6"
            @click.self="closeDocumentModal"
        >
            <div class="flex max-h-[100dvh] w-full max-w-6xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[94vh] sm:rounded-[2rem]">
                <header class="shrink-0 bg-gradient-to-r from-slate-950 via-indigo-950 to-violet-800 px-5 py-5 text-white sm:px-7">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase tracking-wider text-white/75">
                                    Read-Only Admin View
                                </span>

                                <span class="rounded-full bg-indigo-500/30 px-3 py-1 text-xs font-black text-indigo-100">
                                    {{ displayDocument.status || displayDocument.workflow_status || 'Document' }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-2xl font-black sm:text-3xl">
                                DTS - #{{ displayDocument.IDdoc || displayDocument.document_no }}
                            </h2>

                            <p class="mt-2 max-w-4xl break-words text-sm font-semibold text-white/75">
                                {{ displayDocument.subject || 'No subject' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-xl bg-white/15 px-5 py-3 text-sm font-black text-white hover:bg-white/25 lg:w-auto"
                            @click="closeDocumentModal"
                        >
                            Close
                        </button>
                    </div>
                </header>

                <div class="shrink-0 border-b border-slate-200 bg-white px-5 py-4 sm:px-7">
                    <div class="flex gap-2 overflow-x-auto">
                        <button
                            type="button"
                            class="shrink-0 rounded-2xl px-5 py-3 text-sm font-black"
                            :class="documentModalTab === 'details'
                                ? 'bg-slate-950 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="documentModalTab = 'details'"
                        >
                            Document Details
                        </button>

                        <button
                            type="button"
                            class="shrink-0 rounded-2xl px-5 py-3 text-sm font-black"
                            :class="documentModalTab === 'history'
                                ? 'bg-slate-950 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="documentModalTab = 'history'"
                        >
                            View Action History
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 p-5 sm:p-7">
                    <div
                        v-if="documentLoading"
                        class="flex min-h-[320px] flex-col items-center justify-center rounded-3xl border border-slate-200 bg-white text-center"
                    >
                        <div class="h-12 w-12 animate-spin rounded-full border-4 border-indigo-100 border-t-indigo-600"></div>
                        <p class="mt-4 font-black text-slate-800">Loading document details...</p>
                    </div>

                    <div
                        v-else-if="documentError"
                        class="rounded-3xl border border-rose-200 bg-rose-50 px-6 py-10 text-center"
                    >
                        <p class="text-lg font-black text-rose-800">Unable to load complete details</p>
                        <p class="mt-2 text-sm font-semibold text-rose-700">{{ documentError }}</p>
                    </div>

                    <template v-else-if="documentModalTab === 'details'">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Entry Date</p>
                                <p class="mt-2 font-black text-slate-900">{{ formatDateTime(displayDocument.entrydate) }}</p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Classification</p>
                                <p class="mt-2 font-black text-slate-900">
                                    {{ displayDocument.classification_label || classificationLabel(displayDocument.classification) }}
                                </p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Document Type</p>
                                <p class="mt-2 font-black text-slate-900">{{ displayDocument.doctype || '-' }}</p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Staff Concern</p>
                                <p class="mt-2 font-black text-slate-900">
                                    {{ displayDocument.staff_concern || displayDocument.transferred_to || '-' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
                            <section class="rounded-3xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-black uppercase tracking-wider text-indigo-500">From</p>
                                <p class="mt-2 text-lg font-black text-slate-900">
                                    {{ displayDocument.from_office || '-' }}
                                </p>
                                <p
                                    v-if="displayDocument.from_name || displayDocument.sender_name"
                                    class="mt-1 text-sm font-semibold text-slate-500"
                                >
                                    {{ displayDocument.from_name || displayDocument.sender_name }}
                                </p>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-black uppercase tracking-wider text-indigo-500">To</p>
                                <p class="mt-2 text-lg font-black text-slate-900">
                                    {{ displayDocument.for_office || '-' }}
                                </p>
                                <p
                                    v-if="displayDocument.to_name || displayDocument.recipient_name"
                                    class="mt-1 text-sm font-semibold text-slate-500"
                                >
                                    {{ displayDocument.to_name || displayDocument.recipient_name }}
                                </p>
                            </section>
                        </div>

                        <div class="mt-5 space-y-5">
                            <section class="rounded-3xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Subject</p>
                                <p class="mt-3 break-words text-lg font-black leading-7 text-slate-900">
                                    {{ displayDocument.subject || '-' }}
                                </p>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Regarding</p>
                                <p class="mt-3 whitespace-pre-wrap break-words text-sm font-semibold leading-7 text-slate-700">
                                    {{ displayDocument.regarding || '-' }}
                                </p>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Current Remarks</p>
                                <p class="mt-3 whitespace-pre-wrap break-words text-sm font-semibold leading-7 text-slate-700">
                                    {{ displayDocument.remarks || '-' }}
                                </p>
                            </section>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            v-if="actionHistory.length"
                            class="space-y-4"
                        >
                            <article
                                v-for="history in actionHistory"
                                :key="history.id"
                                class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 pl-8 shadow-sm"
                            >
                                <span
                                    class="absolute bottom-0 left-0 top-0 w-2"
                                    :class="historyBadgeClass(history.type)"
                                ></span>

                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded-full px-3 py-1 text-xs font-black text-white"
                                                :class="historyBadgeClass(history.type)"
                                            >
                                                {{ history.title || history.type || 'Activity' }}
                                            </span>

                                            <span class="text-xs font-bold text-slate-400">
                                                {{ formatDateTime(history.date) }}
                                            </span>
                                        </div>

                                        <p
                                            v-if="history.description"
                                            class="mt-3 break-words text-sm font-semibold leading-6 text-slate-700"
                                        >
                                            {{ history.description }}
                                        </p>

                                        <p
                                            v-if="history.remarks"
                                            class="mt-3 whitespace-pre-wrap break-words rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold leading-6 text-slate-700"
                                        >
                                            {{ history.remarks }}
                                        </p>
                                    </div>

                                    <div class="shrink-0 rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                                        <p class="font-black text-slate-900">{{ history.actor || 'System' }}</p>
                                        <p v-if="history.office" class="mt-1 font-semibold text-slate-500">
                                            {{ history.office }}
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"
                        >
                            <p class="text-4xl">🕘</p>
                            <p class="mt-4 text-lg font-black text-slate-800">No action history found</p>
                            <p class="mt-2 text-sm font-semibold text-slate-500">
                                Document actions will appear here once they are recorded.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
